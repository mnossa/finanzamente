<?php

namespace App\Jobs;

use App\Mail\TransactionImportFinishedMail;
use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\TransactionImport;
use App\Models\User;
use App\Services\CurrencyConverter;
use App\Services\RecurrenceDetectionService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ImportTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Soglia minima di transazioni effettivamente importate per avviare
     * il rilevamento automatico delle ricorrenze.
     */
    private const AUTO_RECURRING_DETECTION_MIN_IMPORTED_ROWS = 200;

    /** Timeout massimo in secondi (10 minuti) */
    public int $timeout = 600;

    /** Numero massimo di tentativi */
    public int $tries = 1;

    public function __construct(
        private readonly int $userId,
        private readonly int $householdId,
        private readonly array $validated,
        private readonly int $importId = 0,
    ) {}

    public function handle(
        CurrencyConverter $converter,
        RecurrenceDetectionService $recurrenceDetectionService
    ): void {
        // Con QUEUE_CONNECTION=sync l'import gira nella richiesta HTTP (FPM max_execution_time ~30s).
        // Import grandi: senza questo si va in fatal prima della fine del loop insert.
        @set_time_limit(0);

        $user = User::findOrFail($this->userId);
        $defaultCurrency = ! empty($this->validated['default_currency'])
            ? strtoupper($this->validated['default_currency'])
            : null;

        $importRecord = $this->importId ? TransactionImport::find($this->importId) : null;

        // Una sola transazione DB: niente commit parziali se il job muore a metà; su fatal MySQL fa rollback.
        // Lo stato «processing» + righe + saldi + «completed» diventano visibili solo al commit.
        [
            'imported' => $imported,
            'skipped' => $skipped,
            'skippedDuplicateIgnore' => $skippedDuplicateIgnore,
            'skippedMissingAccount' => $skippedMissingAccount,
            'skipReasonText' => $skipReasonText,
        ] = DB::transaction(function () use ($converter, $importRecord, $defaultCurrency): array {
            if ($importRecord) {
                $importRecord->update([
                    'status' => 'processing',
                    'started_at' => now(),
                ]);
            }

            // Cache conti e variazioni di saldo
            $accountsCache = [];
            $balanceChanges = [];

            $loadAccount = function (int $id) use (&$accountsCache, &$balanceChanges): ?Account {
                if (! isset($accountsCache[$id])) {
                    $acc = Account::where('id', $id)
                        ->where('household_id', $this->householdId)
                        ->first();
                    if ($acc) {
                        $accountsCache[$id] = $acc;
                        $balanceChanges[$id] = 0.0;
                    }
                }

                return $accountsCache[$id] ?? null;
            };

            $globalAccountId = ! empty($this->validated['account_id']) ? (int) $this->validated['account_id'] : null;

            $imported = 0;
            $skipped = 0;
            $skippedDuplicateIgnore = 0;
            $skippedMissingAccount = 0;

            // Risolvi i mapping delle categorie (nome dal file → category_id + type)
            $categoryIdMap = [];
            $categoryTypeMap = [];
            if (! empty($this->validated['category_mappings'])) {
                foreach ($this->validated['category_mappings'] as $catMapping) {
                    $catName = $catMapping['name'];
                    $catAction = $catMapping['action'];
                    if ($catAction === 'existing') {
                        $catId = isset($catMapping['category_id']) ? (int) $catMapping['category_id'] : null;
                        $categoryIdMap[$catName] = $catId;
                        if ($catId) {
                            $cat = Category::find($catId);
                            $categoryTypeMap[$catName] = $cat?->type;
                        }
                    } elseif ($catAction === 'create') {
                        $cat = Category::firstOrCreate([
                            'household_id' => $this->householdId,
                            'name' => $catName,
                            'type' => $catMapping['type'] ?? 'expense',
                        ]);
                        $categoryIdMap[$catName] = $cat->id;
                        $categoryTypeMap[$catName] = $cat->type;
                    } else {
                        $categoryIdMap[$catName] = null;
                        $categoryTypeMap[$catName] = null;
                    }
                }
            }

            $resolveCategoryId = fn (?string $name): ?int => ($name !== null && $name !== '' && isset($categoryIdMap[$name]))
                    ? $categoryIdMap[$name]
                    : null;

            $resolveCategoryType = fn (?string $name): ?string => ($name !== null && $name !== '' && isset($categoryTypeMap[$name]))
                    ? $categoryTypeMap[$name]
                    : null;

            // Risolvi i mapping dei conti dal file (nome → account_id)
            $accountNameIdMap = [];
            if (! empty($this->validated['account_mappings'])) {
                foreach ($this->validated['account_mappings'] as $accMapping) {
                    $accName = $accMapping['name'];
                    $accAction = $accMapping['action'];
                    if ($accAction === 'existing') {
                        $accountNameIdMap[$accName] = isset($accMapping['account_id']) ? (int) $accMapping['account_id'] : null;
                    } elseif ($accAction === 'create') {
                        $newAcc = Account::create([
                            'household_id' => $this->householdId,
                            'name' => $accName,
                            'type' => $accMapping['type'] ?? 'bank',
                            'currency_code' => $accMapping['currency_code'] ?? 'EUR',
                            'initial_balance' => 0,
                            'current_balance' => 0,
                            'active' => true,
                            'is_private' => false,
                        ]);
                        $accountsCache[$newAcc->id] = $newAcc;
                        $balanceChanges[$newAcc->id] = 0.0;
                        $accountNameIdMap[$accName] = $newAcc->id;
                    }
                }
            }

            $resolveAccount = function (array $row) use ($globalAccountId, $loadAccount, $accountNameIdMap): ?Account {
                $accountName = $row['account_name'] ?? null;
                if ($accountName !== null && isset($accountNameIdMap[$accountName])) {
                    return $loadAccount($accountNameIdMap[$accountName]);
                }
                $id = ! empty($row['account_id']) ? (int) $row['account_id'] : $globalAccountId;

                return $id !== null ? $loadAccount($id) : null;
            };

            foreach ($this->validated['rows'] as $row) {
                $action = $row['duplicate_action'] ?? 'import';
                $account = $resolveAccount($row);

                if ($action === 'ignore') {
                    $skipped++;
                    $skippedDuplicateIgnore++;

                    continue;
                }

                if ($account === null) {
                    $skipped++;
                    $skippedMissingAccount++;

                    continue;
                }

                $amount = abs((float) $row['amount']);
                $catName = $row['category_name'] ?? null;
                $catType = $resolveCategoryType($catName);
                if ($catType === 'expense') {
                    $amount = -$amount;
                }
                $description = $row['description'];
                if (! empty($row['notes'])) {
                    $description .= ' - '.$row['notes'];
                }
                $description = mb_substr($description, 0, 1000);

                $rowCurrency = ! empty($row['currency_code']) ? strtoupper($row['currency_code']) : $defaultCurrency;
                $accountCurrency = $account->currency_code ?? 'EUR';
                $txDate = Carbon::parse($row['date']);

                $fxData = $converter->convertToAccountCurrency(
                    $amount,
                    $rowCurrency ?? $accountCurrency,
                    $accountCurrency,
                    $txDate,
                );

                if (in_array($action, ['replace', 'update'], true) && ! empty($row['duplicate_transaction_id'])) {
                    $existing = Transaction::where('id', (int) $row['duplicate_transaction_id'])
                        ->where('account_id', $account->id)
                        ->first();

                    if ($existing) {
                        $oldAmount = (float) $existing->amount;
                        if ($action === 'replace') {
                            $balanceChanges[$account->id] -= $oldAmount;
                            $existing->delete();
                            Transaction::create([
                                'user_id' => $this->userId,
                                'account_id' => $account->id,
                                'category_id' => $resolveCategoryId($row['category_name'] ?? null),
                                'amount' => $fxData['amount'],
                                'currency_code' => $fxData['currency_code'],
                                'exchange_rate_to_base' => $fxData['exchange_rate_to_base'],
                                'amount_base' => $fxData['amount_base'],
                                'original_amount' => $fxData['original_amount'],
                                'original_currency_code' => $fxData['original_currency_code'],
                                'date' => $row['date'],
                                'description' => $description,
                                'is_private' => false,
                            ]);
                        } else {
                            $existing->update([
                                'amount' => $fxData['amount'],
                                'date' => $row['date'],
                                'description' => $description,
                            ]);
                            $balanceChanges[$account->id] -= $oldAmount;
                        }
                        $balanceChanges[$account->id] += $fxData['amount'];
                        $imported++;

                        continue;
                    }
                }

                Transaction::create([
                    'user_id' => $this->userId,
                    'account_id' => $account->id,
                    'category_id' => $resolveCategoryId($row['category_name'] ?? null),
                    'amount' => $fxData['amount'],
                    'currency_code' => $fxData['currency_code'],
                    'exchange_rate_to_base' => $fxData['exchange_rate_to_base'],
                    'amount_base' => $fxData['amount_base'],
                    'original_amount' => $fxData['original_amount'],
                    'original_currency_code' => $fxData['original_currency_code'],
                    'date' => $row['date'],
                    'description' => $description,
                    'is_private' => false,
                ]);
                $balanceChanges[$account->id] += $fxData['amount'];
                $imported++;
            }

            // Salva le variazioni di saldo per tutti i conti coinvolti
            foreach ($balanceChanges as $accountId => $delta) {
                $accountsCache[$accountId]->current_balance += $delta;
                $accountsCache[$accountId]->save();
            }

            $skipReasons = [];
            if ($skippedMissingAccount > 0) {
                $skipReasons[] = "{$skippedMissingAccount} per conto non assegnato";
            }
            if ($skippedDuplicateIgnore > 0) {
                $skipReasons[] = "{$skippedDuplicateIgnore} ignorate manualmente";
            }
            $skipReasonText = empty($skipReasons) ? null : implode(', ', $skipReasons);

            if ($importRecord) {
                $importRecord->update([
                    'status' => 'completed',
                    'rows_imported' => $imported,
                    'rows_skipped' => $skipped,
                    'error_message' => $imported === 0 && $skipReasonText !== null
                        ? "Nessuna transazione importata: {$skipReasonText}."
                        : null,
                    'completed_at' => now(),
                ]);
            }

            return [
                'imported' => $imported,
                'skipped' => $skipped,
                'skippedDuplicateIgnore' => $skippedDuplicateIgnore,
                'skippedMissingAccount' => $skippedMissingAccount,
                'skipReasonText' => $skipReasonText,
            ];
        });

        $autoDetectionCreated = null;
        $autoDetectionError = false;
        if ($imported >= self::AUTO_RECURRING_DETECTION_MIN_IMPORTED_ROWS) {
            try {
                $autoDetectionCreated = $recurrenceDetectionService->detectForHousehold($this->householdId);
            } catch (Throwable $e) {
                $autoDetectionError = true;
                Log::warning('Rilevamento ricorrenze post-import fallito', [
                    'user_id' => $this->userId,
                    'household_id' => $this->householdId,
                    'import_id' => $this->importId,
                    'imported_rows' => $imported,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Notifica in-app al completamento
        $msg = "{$imported} ".($imported === 1 ? 'transazione importata' : 'transazioni importate').' con successo.';
        if ($skipped > 0) {
            $msg .= " {$skipped} ".($skipped === 1 ? 'riga ignorata' : 'righe ignorate').'.';
            if ($skipReasonText !== null) {
                $msg .= " Motivi: {$skipReasonText}.";
            }
        }
        if ($autoDetectionCreated !== null) {
            if ($autoDetectionCreated > 0) {
                $msg .= " Ho anche analizzato automaticamente le ricorrenze e trovato {$autoDetectionCreated} ".($autoDetectionCreated === 1 ? 'nuovo suggerimento' : 'nuovi suggerimenti').'.';
            } else {
                $msg .= ' Ho anche analizzato automaticamente le ricorrenze: nessun nuovo suggerimento trovato.';
            }
        } elseif ($autoDetectionError) {
            $msg .= " L'analisi automatica delle ricorrenze non e' riuscita: puoi rilanciarla dalla sezione dedicata.";
        }

        AppNotification::create([
            'user_id' => $this->userId,
            'title' => '✅ Importazione completata',
            'message' => $msg,
            'read' => false,
            'notification_key' => 'import_done_'.time().'_'.$this->userId,
        ]);

        $this->sendImportFinishedEmail($user, true, '✅ Importazione completata', $msg, null);
    }

    /**
     * Invia email di riepilogo import (successo o fallimento). Errori SMTP non devono far fallire il job.
     */
    private function sendImportFinishedEmail(
        User $user,
        bool $successful,
        string $notificationTitle,
        string $notificationMessage,
        ?string $errorDetail,
    ): void {
        $email = $user->email;
        if ($email === null || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($email)->send(new TransactionImportFinishedMail(
                $user,
                $successful,
                $notificationTitle,
                $notificationMessage,
                $errorDetail,
            ));
        } catch (Throwable $e) {
            Log::warning('Invio email esito import transazioni fallito', [
                'user_id' => $user->id,
                'successful' => $successful,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * In caso di errore del job, notifica l'utente.
     */
    public function failed(Throwable $exception): void
    {
        $importRecord = $this->importId ? TransactionImport::find($this->importId) : null;
        if ($importRecord) {
            $importRecord->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 500),
                'completed_at' => now(),
            ]);
        }

        AppNotification::create([
            'user_id' => $this->userId,
            'title' => '❌ Importazione fallita',
            'message' => 'Si è verificato un errore durante l\'importazione delle transazioni. Riprova o contatta il supporto.',
            'read' => false,
            'notification_key' => 'import_failed_'.time().'_'.$this->userId,
        ]);

        $failMsg = 'Si è verificato un errore durante l\'importazione delle transazioni. Riprova o contatta il supporto.';
        $user = User::find($this->userId);
        if ($user) {
            $this->sendImportFinishedEmail(
                $user,
                false,
                '❌ Importazione fallita',
                $failMsg,
                mb_substr($exception->getMessage(), 0, 2000),
            );
        }
    }
}
