<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use App\Services\AccountBalanceService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Account
 *
 * Rappresenta un conto finanziario appartenente a una household (es. conto
 * bancario, carta, portafoglio crypto). Ha un saldo iniziale e un saldo
 * corrente che può essere aggiornato aggregando le transazioni.
 *
 * Relazioni principali:
 * - household(): belongsTo(Household)
 * - owner(): belongsTo(User)
 * - currency(): belongsTo(Currency)
 * - transactions(): hasMany(Transaction)
 */
class Account extends Model
{
    use DispatchesModelEvents, HasFactory, SoftDeletes;

    public const TYPES = [
        'bank' => 'Conto Bancario',
        'cash' => 'Contanti',
        'card' => 'Carta',
        'broker' => 'Broker',
        'crypto' => 'Crypto',
        'meal_voucher' => 'Buoni pasto',
        'other' => 'Altro',
    ];

    public const SAVINGS_DEPOSIT_TYPE = 'savings_deposit';

    public const MEAL_VOUCHER_TYPE = 'meal_voucher';

    protected $fillable = [
        'household_id',
        'name',
        'type',
        'initial_balance',
        'current_balance',
        'currency_code',
        'active',
        'is_private',
        'owner_user_id',
        'interest_rate',
        'ticket_unit_value',
    ];

    protected $casts = [
        'initial_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'active' => 'boolean',
        'is_private' => 'boolean',
        'interest_rate' => 'decimal:2',
        'ticket_unit_value' => 'decimal:2',
    ];

    /**
     * Tipi mostrati in UI (include alias "conto deposito" salvato come bank).
     *
     * @return array<string, string>
     */
    public static function uiTypes(): array
    {
        return [
            ...self::TYPES,
            self::SAVINGS_DEPOSIT_TYPE => 'Conto Deposito',
        ];
    }

    public function isSavingsDeposit(): bool
    {
        return $this->type === 'bank' && $this->interest_rate !== null;
    }

    public function isMealVoucher(): bool
    {
        return $this->type === self::MEAL_VOUCHER_TYPE;
    }

    /**
     * Ticket interi disponibili da un saldo: floor(balance / unit), mai sotto 0.
     * Resto non multiplo non conta come ticket intero.
     */
    public function ticketCountFromBalance(float $balance): ?int
    {
        if (! $this->isMealVoucher()) {
            return null;
        }

        $unit = (float) ($this->ticket_unit_value ?? 0);
        if ($unit <= 0) {
            return null;
        }

        if ($balance <= 0) {
            return 0;
        }

        return (int) floor($balance / $unit);
    }

    /**
     * Equivalenza ticket di un importo (segno = direzione TX). Null se non meal voucher.
     */
    public function ticketsDeltaForAmount(float $amount): ?float
    {
        if (! $this->isMealVoucher()) {
            return null;
        }

        $unit = (float) ($this->ticket_unit_value ?? 0);
        if ($unit <= 0) {
            return null;
        }

        return round($amount / $unit, 2);
    }

    /**
     * Conti su cui è consentito registrare uscite (esclude i conti deposito).
     * I buoni pasto sono eleggibili alle uscite.
     */
    public function scopeEligibleForExpenseTransactions($query)
    {
        return $query->where(function ($q) {
            $q->where('type', '!=', 'bank')
                ->orWhereNull('interest_rate');
        });
    }

    /**
     * @return array{id: int, name: string, currency_code: string, is_savings_deposit: bool}
     */
    public function toTransactionFormOption(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'currency_code' => $this->currency_code,
            'is_savings_deposit' => $this->isSavingsDeposit(),
        ];
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * Ricalcola e aggiorna il saldo corrente basato sul saldo iniziale + transazioni.
     */
    public function recalculateBalance(): void
    {
        $this->current_balance = app(AccountBalanceService::class)->computeBalance($this);
        $this->save();
    }
}
