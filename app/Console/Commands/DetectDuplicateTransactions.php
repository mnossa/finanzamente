<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\DuplicateTransactionCandidate;
use App\Models\Transaction;
use Illuminate\Console\Command;

class DetectDuplicateTransactions extends Command
{
    protected $signature = 'transactions:detect-duplicates {--days=3}';

    protected $description = 'Rileva possibili transazioni duplicate e notifica l’utente';

    public function handle(): int
    {
        $windowDays = max(1, (int) $this->option('days'));

        $transactions = Transaction::query()
            ->select(['id', 'user_id', 'description', 'amount', 'date', 'recurring_transaction_id'])
            ->whereNotNull('description')
            ->orderBy('user_id')
            ->orderBy('date')
            ->get()
            ->groupBy('user_id');

        $created = 0;
        foreach ($transactions as $userId => $items) {
            $userCreated = 0;
            $list = $items->values();
            for ($i = 0; $i < $list->count(); $i++) {
                for ($j = $i + 1; $j < $list->count(); $j++) {
                    $a = $list[$i];
                    $b = $list[$j];
                    if (mb_strtolower(trim((string) $a->description)) !== mb_strtolower(trim((string) $b->description))) {
                        continue;
                    }
                    if (round((float) $a->amount, 2) !== round((float) $b->amount, 2)) {
                        continue;
                    }
                    $distance = abs((int) $a->date->diffInDays($b->date));
                    if ($distance > $windowDays) {
                        continue;
                    }

                    $primaryRecurringId = $a->recurring_transaction_id;
                    $candidateRecurringId = $b->recurring_transaction_id;
                    if (
                        $primaryRecurringId !== null
                        && $candidateRecurringId !== null
                        && (int) $primaryRecurringId === (int) $candidateRecurringId
                    ) {
                        continue;
                    }

                    DuplicateTransactionCandidate::firstOrCreate(
                        [
                            'user_id' => $userId,
                            'primary_transaction_id' => min($a->id, $b->id),
                            'candidate_transaction_id' => max($a->id, $b->id),
                        ],
                        [
                            'status' => 'pending',
                            'distance_days' => $distance,
                        ]
                    );
                    $created++;
                    $userCreated++;
                }
            }

            if ($userCreated > 0) {
                AppNotification::firstOrCreate([
                    'user_id' => $userId,
                    'notification_key' => 'duplicates_detect_'.now()->format('Y-m-d'),
                ], [
                    'title' => '🔁 Possibili duplicati trovati',
                    'message' => 'Sono state trovate transazioni potenzialmente duplicate. Apri il pannello per confermare o ignorare.',
                    'read' => false,
                ]);
            }
        }

        $this->info("Candidati duplicati elaborati: {$created}");

        return self::SUCCESS;
    }
}
