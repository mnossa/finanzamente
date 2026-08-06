<?php

namespace App\Console\Commands;

use App\Mail\RecurringDetectionMail;
use App\Models\AppNotification;
use App\Models\Household;
use App\Models\RecurringTransactionSuggestion;
use App\Services\RecurrenceDetectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Comando per rilevare pattern ricorrenti nelle transazioni esistenti
 * e creare i relativi suggerimenti in attesa di conferma dall'utente.
 */
class DetectRecurringTransactions extends Command
{
    protected $signature = 'recurring:detect
                            {--household= : ID dell\'household da analizzare (default: tutti)}
                            {--refresh-pending : Rigenera i suggerimenti pending (cancella pending prima del rilevamento)}';

    protected $description = 'Rileva pattern ricorrenti nelle transazioni e genera suggerimenti di ricorrenza';

    public function __construct(private readonly RecurrenceDetectionService $detectionService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔍 Avvio rilevamento ricorrenze...');

        $householdId = $this->option('household');
        $refreshPending = (bool) $this->option('refresh-pending');

        if ($householdId) {
            $households = Household::where('id', $householdId)->get();
            if ($households->isEmpty()) {
                $this->error("Household #{$householdId} non trovato.");

                return Command::FAILURE;
            }
        } else {
            $households = Household::all();
        }

        $totalCreated = 0;

        foreach ($households as $household) {
            try {
                if ($refreshPending) {
                    $deleted = $this->deletePendingSuggestionsForHousehold($household->id);
                    if ($deleted > 0) {
                        $this->line("  ♻️ Household #{$household->id} ({$household->name}): rimossi {$deleted} suggerimenti pending");
                    }
                }

                $created = $this->detectionService->detectForHousehold($household->id);
                $totalCreated += $created;

                if ($created > 0) {
                    $this->notifyHouseholdMembers($household, $created);
                    $this->info("  ✅ Household #{$household->id} ({$household->name}): {$created} nuovi suggerimenti");

                    continue;
                }

                $pendingCount = $this->getPendingSuggestionsCount($household->id);
                if ($pendingCount > 0) {
                    $this->notifyHouseholdMembers($household, $pendingCount);
                }
            } catch (\Exception $e) {
                $this->error("  ❌ Errore household #{$household->id}: {$e->getMessage()}");
            }
        }

        $this->info("✅ Rilevamento completato. Totale suggerimenti creati: {$totalCreated}");

        return Command::SUCCESS;
    }

    /**
     * Invia una notifica in-app ai membri della household quando vengono creati
     * nuovi suggerimenti di ricorrenza. Deduplica una notifica per household/giorno.
     */
    private function notifyHouseholdMembers(Household $household, int $created): void
    {
        $periodKey = Carbon::now()->format('Y-m-d');
        $notificationKey = "recurring_detect_{$household->id}_{$periodKey}";

        $users = $household->users()->get();
        foreach ($users as $user) {
            $alreadyNotified = AppNotification::where('user_id', $user->id)
                ->where('notification_key', $notificationKey)
                ->exists();

            if ($alreadyNotified) {
                continue;
            }

            AppNotification::create([
                'user_id' => $user->id,
                'title' => '🔁 Nuove ricorrenze suggerite',
                'message' => "Ho trovato {$created} possibili transazioni ricorrenti in \"{$household->name}\". Vai su Rilevamento Ricorrenze per confermare o ignorare.",
                'read' => false,
                'notification_key' => $notificationKey,
            ]);

            if ($user->email) {
                Mail::to($user->email)->send(new RecurringDetectionMail($user, $household, $created));
            }
        }
    }

    private function getPendingSuggestionsCount(int $householdId): int
    {
        return RecurringTransactionSuggestion::query()
            ->where('status', 'pending')
            ->whereHas('account', fn ($query) => $query->where('household_id', $householdId))
            ->count();
    }

    private function deletePendingSuggestionsForHousehold(int $householdId): int
    {
        return RecurringTransactionSuggestion::query()
            ->where('status', 'pending')
            ->whereHas('account', fn ($query) => $query->where('household_id', $householdId))
            ->delete();
    }
}
