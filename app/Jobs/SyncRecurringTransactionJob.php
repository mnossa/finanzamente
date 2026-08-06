<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\RecurringTransaction;
use App\Models\User;
use App\Services\RecurringTransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncRecurringTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(
        private readonly int $recurringTransactionId,
        private readonly int $userId,
        private readonly bool $reconcileSchedule = true,
    ) {}

    public function handle(RecurringTransactionService $recurringService): void
    {
        @set_time_limit(0);

        $recurring = RecurringTransaction::find($this->recurringTransactionId);
        $user = User::find($this->userId);

        if (! $recurring || ! $user) {
            return;
        }

        $notificationKey = "recurring_sync_{$recurring->id}_".now()->format('YmdHis');

        try {
            $result = $recurringService->syncAndReconcile($recurring, $this->reconcileSchedule);
            $reconcile = $result['reconcile'];
            $synced = $result['synced'];

            $message = 'La ricorrenza è stata aggiornata.';
            if ($synced > 0) {
                $message .= " {$synced} transazioni allineate al template.";
            }
            if ($reconcile->totalChanges() > 0) {
                $message .= " Aggiunte {$reconcile->created}, rimosse {$reconcile->removed} occorrenze.";
            }

            AppNotification::create([
                'user_id' => $user->id,
                'title' => '✅ Ricorrenza aggiornata',
                'message' => $message,
                'notification_key' => $notificationKey,
            ]);
        } catch (Throwable $e) {
            Log::error('SyncRecurringTransactionJob fallito', [
                'recurring_transaction_id' => $this->recurringTransactionId,
                'error' => $e->getMessage(),
            ]);

            AppNotification::create([
                'user_id' => $user->id,
                'title' => '⚠️ Errore aggiornamento ricorrenza',
                'message' => 'Non è stato possibile completare l\'aggiornamento in background. Riprova dalla pagina della ricorrenza.',
                'notification_key' => $notificationKey,
            ]);

            throw $e;
        }
    }
}
