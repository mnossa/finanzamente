<?php

namespace App\Services;

use App\Models\Account;
use App\Models\MealVoucherLot;
use App\Models\MealVoucherLotMovement;
use App\Models\MealVoucherUnitValue;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MealVoucherLedgerService
{
    /**
     * Valore ticket vigente alla data (ultimo effective_from <= date).
     */
    public function unitValueOn(Account $account, CarbonInterface $date): ?float
    {
        if (! $account->isMealVoucher()) {
            return null;
        }

        $row = MealVoucherUnitValue::query()
            ->where('account_id', $account->id)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->orderByDesc('effective_from')
            ->first();

        if ($row) {
            return (float) $row->unit_value;
        }

        return $account->ticket_unit_value !== null ? (float) $account->ticket_unit_value : null;
    }

    /**
     * @return list<array{id: int, unit_value: float, quantity_remaining: int, acquired_on: string, euro_value: float}>
     */
    public function lotsPayload(Account $account): array
    {
        return MealVoucherLot::query()
            ->where('account_id', $account->id)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('acquired_on')
            ->orderBy('id')
            ->get()
            ->map(function (MealVoucherLot $lot) {
                $unit = (float) $lot->unit_value;
                $qty = (int) $lot->quantity_remaining;

                return [
                    'id' => $lot->id,
                    'unit_value' => $unit,
                    'quantity_remaining' => $qty,
                    'acquired_on' => $lot->acquired_on->format('Y-m-d'),
                    'euro_value' => round($unit * $qty, 2),
                ];
            })
            ->values()
            ->all();
    }

    public function totalTicketCount(Account $account): int
    {
        return (int) MealVoucherLot::query()
            ->where('account_id', $account->id)
            ->sum('quantity_remaining');
    }

    /**
     * @return list<array{unit_value: float, effective_from: string}>
     */
    public function unitValueHistory(Account $account): array
    {
        return MealVoucherUnitValue::query()
            ->where('account_id', $account->id)
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn (MealVoucherUnitValue $row) => [
                'unit_value' => (float) $row->unit_value,
                'effective_from' => $row->effective_from->format('Y-m-d'),
            ])
            ->values()
            ->all();
    }

    /**
     * Inizializza storico valore + lotto di apertura da saldo iniziale.
     */
    public function initializeAccount(Account $account): void
    {
        if (! $account->isMealVoucher()) {
            return;
        }

        $unit = (float) ($account->ticket_unit_value ?? 0);
        if ($unit <= 0) {
            throw new InvalidArgumentException('Valore ticket mancante per il conto buoni pasto.');
        }

        $from = ($account->created_at ?? now())->toDateString();

        MealVoucherUnitValue::query()->firstOrCreate(
            [
                'account_id' => $account->id,
                'effective_from' => $from,
            ],
            ['unit_value' => $unit],
        );

        $balance = (float) $account->initial_balance;
        if ($balance <= 0) {
            return;
        }

        if (! $this->isMultipleOfUnit($balance, $unit)) {
            throw new InvalidArgumentException('Il saldo iniziale deve essere un multiplo del valore di un ticket.');
        }

        $qty = (int) round($balance / $unit);
        if ($qty <= 0) {
            return;
        }

        $this->createLotWithMovement(
            account: $account,
            unitValue: $unit,
            quantity: $qty,
            acquiredOn: Carbon::parse($from),
            transaction: null,
            note: 'saldo_iniziale',
        );
    }

    /**
     * Nuovo valore ticket da data (non retroattivo: effective_from >= oggi).
     */
    public function scheduleUnitValue(Account $account, float $unitValue, CarbonInterface $effectiveFrom): MealVoucherUnitValue
    {
        if (! $account->isMealVoucher()) {
            throw new InvalidArgumentException('Il conto non è un conto buoni pasto.');
        }

        if ($unitValue < 0.01) {
            throw new InvalidArgumentException('Il valore ticket non è valido.');
        }

        $from = $effectiveFrom->copy()->startOfDay();
        $today = now()->startOfDay();
        if ($from->lt($today)) {
            throw new InvalidArgumentException('Non puoi impostare un valore ticket con data passata.');
        }

        $existsSameDay = MealVoucherUnitValue::query()
            ->where('account_id', $account->id)
            ->whereDate('effective_from', $from->toDateString())
            ->first();

        return DB::transaction(function () use ($account, $unitValue, $from, $existsSameDay) {
            if ($existsSameDay) {
                $existsSameDay->unit_value = $unitValue;
                $existsSameDay->save();
                $row = $existsSameDay;
            } else {
                $row = MealVoucherUnitValue::query()->create([
                    'account_id' => $account->id,
                    'unit_value' => $unitValue,
                    'effective_from' => $from->toDateString(),
                ]);
            }

            $current = $this->unitValueOn($account->fresh(), now());
            if ($current !== null) {
                $account->ticket_unit_value = $current;
                $account->save();
            }

            return $row;
        });
    }

    public function applyIncome(Account $account, Transaction $transaction): void
    {
        if (! $account->isMealVoucher()) {
            return;
        }

        $amount = (float) $transaction->amount;
        if ($amount <= 0) {
            throw new InvalidArgumentException('L\'accredito buoni pasto deve avere importo positivo.');
        }

        $unit = $this->unitValueOn($account, $transaction->date);
        if ($unit === null || $unit <= 0) {
            throw new InvalidArgumentException('Nessun valore ticket vigente alla data della transazione.');
        }

        if (! $this->isMultipleOfUnit($amount, $unit)) {
            throw new InvalidArgumentException('L\'importo deve essere un multiplo del valore ticket vigente ('.$unit.' €).');
        }

        $qty = (int) round($amount / $unit);
        $this->createLotWithMovement(
            account: $account,
            unitValue: $unit,
            quantity: $qty,
            acquiredOn: $transaction->date,
            transaction: $transaction,
            note: null,
        );
    }

    /**
     * @param  list<array{lot_id: int, quantity: int}>  $lines
     */
    public function euroFromLines(Account $account, array $lines): float
    {
        $total = 0.0;
        foreach ($lines as $line) {
            $lot = MealVoucherLot::query()
                ->where('account_id', $account->id)
                ->whereKey($line['lot_id'])
                ->first();

            if (! $lot) {
                throw new InvalidArgumentException('Lotto buoni pasto non valido.');
            }

            $qty = (int) $line['quantity'];
            if ($qty < 1) {
                throw new InvalidArgumentException('La quantità di ticket deve essere almeno 1.');
            }

            $total += $qty * (float) $lot->unit_value;
        }

        return round($total, 2);
    }

    /**
     * @param  list<array{lot_id: int, quantity: int}>  $lines
     */
    public function applySpend(Account $account, Transaction $transaction, array $lines): void
    {
        if (! $account->isMealVoucher()) {
            return;
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Seleziona almeno un ticket da spendere.');
        }

        $expected = $this->euroFromLines($account, $lines);
        $spent = abs((float) $transaction->amount);
        if (abs($expected - $spent) > 0.001) {
            throw new InvalidArgumentException('L\'importo non corrisponde ai ticket selezionati.');
        }

        DB::transaction(function () use ($account, $transaction, $lines) {
            foreach ($lines as $line) {
                /** @var MealVoucherLot $lot */
                $lot = MealVoucherLot::query()
                    ->where('account_id', $account->id)
                    ->whereKey($line['lot_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                $qty = (int) $line['quantity'];
                if ($qty > (int) $lot->quantity_remaining) {
                    throw new InvalidArgumentException('Quantità ticket non disponibile per il lotto selezionato.');
                }

                $lot->quantity_remaining = (int) $lot->quantity_remaining - $qty;
                $lot->save();

                MealVoucherLotMovement::query()->create([
                    'lot_id' => $lot->id,
                    'transaction_id' => $transaction->id,
                    'quantity_delta' => -$qty,
                    'occurred_on' => $transaction->date->toDateString(),
                    'note' => null,
                ]);
            }
        });
    }

    /**
     * Suggerimento FIFO: spezza un importo € in linee lotto (ticket interi).
     *
     * @return list<array{lot_id: int, quantity: int, unit_value: float}>
     */
    public function suggestFifoForEuro(Account $account, float $euroAmount): array
    {
        if ($euroAmount <= 0) {
            return [];
        }

        $remaining = round($euroAmount, 2);
        $lines = [];

        $lots = MealVoucherLot::query()
            ->where('account_id', $account->id)
            ->where('quantity_remaining', '>', 0)
            ->orderBy('acquired_on')
            ->orderBy('id')
            ->get();

        foreach ($lots as $lot) {
            if ($remaining <= 0) {
                break;
            }

            $unit = (float) $lot->unit_value;
            if ($unit <= 0) {
                continue;
            }

            $maxByEuro = (int) floor($remaining / $unit + 1e-9);
            $qty = min((int) $lot->quantity_remaining, $maxByEuro);
            if ($qty < 1) {
                continue;
            }

            $lines[] = [
                'lot_id' => $lot->id,
                'quantity' => $qty,
                'unit_value' => $unit,
            ];
            $remaining = round($remaining - ($qty * $unit), 2);
        }

        return $lines;
    }

    public function reverseTransaction(Transaction $transaction): void
    {
        $movements = MealVoucherLotMovement::query()
            ->where('transaction_id', $transaction->id)
            ->with('lot')
            ->get();

        if ($movements->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($movements) {
            foreach ($movements as $movement) {
                $lot = $movement->lot;
                if (! $lot) {
                    continue;
                }

                $lot->quantity_remaining = (int) $lot->quantity_remaining - (int) $movement->quantity_delta;
                if ($lot->quantity_remaining < 0) {
                    $lot->quantity_remaining = 0;
                }
                $lot->save();
                $movement->delete();
            }
        });
    }

    /**
     * Annulla movimenti esistenti e riapplica sul conto attuale della transazione.
     * Usato dopo update/bulk move account_id (o cambio importo) su/da buoni pasto.
     *
     * @param  list<array{lot_id: int, quantity: int}>|null  $spendLines
     */
    public function resyncTransaction(Transaction $transaction, ?array $spendLines = null): void
    {
        $this->reverseTransaction($transaction);

        $transaction->refresh();
        $account = $transaction->account;
        if (! $account?->isMealVoucher()) {
            return;
        }

        $amount = (float) $transaction->amount;
        if ($amount > 0) {
            $this->applyIncome($account, $transaction);

            return;
        }

        if ($amount >= 0) {
            return;
        }

        $lines = $spendLines;
        if ($lines === null || $lines === []) {
            $suggested = $this->suggestFifoForEuro($account, abs($amount));
            $lines = array_map(fn (array $line): array => [
                'lot_id' => (int) $line['lot_id'],
                'quantity' => (int) $line['quantity'],
            ], $suggested);
        }

        if ($lines === []) {
            throw new InvalidArgumentException('Impossibile allocare i ticket buoni pasto per questo importo.');
        }

        $expected = $this->euroFromLines($account, $lines);
        if (abs($expected - abs($amount)) > 0.001) {
            throw new InvalidArgumentException('L\'importo non corrisponde a un numero intero di ticket disponibili sul conto buoni pasto.');
        }

        $this->applySpend($account, $transaction, $lines);
    }

    /**
     * @return list<array{lot_id: int, unit_value: float, quantity: int}>
     */
    public function movementsForTransaction(Transaction $transaction): array
    {
        return MealVoucherLotMovement::query()
            ->where('transaction_id', $transaction->id)
            ->with('lot:id,unit_value')
            ->get()
            ->map(fn (MealVoucherLotMovement $m) => [
                'lot_id' => $m->lot_id,
                'unit_value' => (float) ($m->lot?->unit_value ?? 0),
                'quantity' => (int) $m->quantity_delta,
            ])
            ->values()
            ->all();
    }

    public function isMultipleOfUnit(float $amount, float $unit): bool
    {
        if ($unit <= 0) {
            return false;
        }

        $ratio = $amount / $unit;
        $nearest = round($ratio);

        return abs($ratio - $nearest) < 0.0001;
    }

    private function createLotWithMovement(
        Account $account,
        float $unitValue,
        int $quantity,
        CarbonInterface $acquiredOn,
        ?Transaction $transaction,
        ?string $note,
    ): MealVoucherLot {
        return DB::transaction(function () use ($account, $unitValue, $quantity, $acquiredOn, $transaction, $note) {
            $lot = MealVoucherLot::query()->create([
                'account_id' => $account->id,
                'unit_value' => $unitValue,
                'quantity_remaining' => $quantity,
                'acquired_on' => $acquiredOn->toDateString(),
            ]);

            MealVoucherLotMovement::query()->create([
                'lot_id' => $lot->id,
                'transaction_id' => $transaction?->id,
                'quantity_delta' => $quantity,
                'occurred_on' => $acquiredOn->toDateString(),
                'note' => $note,
            ]);

            return $lot;
        });
    }
}
