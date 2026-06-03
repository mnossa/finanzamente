<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\DuplicateTransactionCandidate;
use App\Models\Transaction;
use App\Services\DuplicateTransactionCandidateService;
use App\Services\DuplicateTransactionDetectionService;
use Illuminate\Console\Command;

class DetectDuplicateTransactions extends Command
{
    protected $signature = 'transactions:detect-duplicates {--days=3}';

    protected $description = 'Rileva possibili transazioni duplicate e notifica l’utente';

    public function __construct(
        private readonly DuplicateTransactionDetectionService $detectionService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $windowDays = max(1, (int) $this->option('days'));

        $transactionUserIds = Transaction::query()
            ->whereNotNull('description')
            ->distinct()
            ->pluck('user_id');
        $candidateUserIds = DuplicateTransactionCandidate::query()
            ->where('status', DuplicateTransactionCandidateService::STATUS_PENDING)
            ->distinct()
            ->pluck('user_id');
        $userIds = $transactionUserIds
            ->merge($candidateUserIds)
            ->unique()
            ->values();

        $created = 0;
        $pruned = 0;
        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            $result = $this->detectionService->detectForUser($userId, $windowDays);
            $userCreated = $result['created'];
            $created += $userCreated;
            $pruned += $result['pruned'];

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
        if ($pruned > 0) {
            $this->info("Segnalazioni rimosse (stale o non più valide): {$pruned}");
        }

        return self::SUCCESS;
    }
}
