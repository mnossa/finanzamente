<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Services\DuplicateTransactionClusterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DuplicateTransactionClusterServiceTest extends TestCase
{
    use RefreshDatabase;

    private DuplicateTransactionClusterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DuplicateTransactionClusterService::class);
    }

    #[Test]
    public function three_transactions_within_window_form_one_cluster(): void
    {
        $transactions = collect([
            $this->makeTx(1, '2026-04-17', 'ETA - Visto Scozia', -23.88),
            $this->makeTx(2, '2026-04-19', 'ETA - Visto Scozia', -23.88),
            $this->makeTx(3, '2026-04-20', 'ETA - Visto Scozia', -23.88),
        ]);

        $clusters = $this->service->findClusters($transactions, 3);

        $this->assertCount(1, $clusters);
        $this->assertCount(3, $clusters->first());
        $this->assertSame([1, 2, 3], $this->service->clusterTransactionIds($clusters->first()));
    }

    #[Test]
    public function distant_transaction_splits_clusters(): void
    {
        $transactions = collect([
            $this->makeTx(1, '2026-04-17', 'Spesa', -10.00),
            $this->makeTx(2, '2026-04-19', 'Spesa', -10.00),
            $this->makeTx(3, '2026-05-01', 'Spesa', -10.00),
        ]);

        $clusters = $this->service->findClusters($transactions, 3);

        $this->assertCount(1, $clusters);
        $this->assertCount(2, $clusters->first());
        $this->assertSame([1, 2], $this->service->clusterTransactionIds($clusters->first()));
    }

    private function makeTx(int $id, string $date, string $description, float $amount): Transaction
    {
        $tx = new Transaction([
            'account_id' => 1,
            'description' => $description,
            'amount' => $amount,
            'date' => $date,
            'recurring_transaction_id' => null,
        ]);
        $tx->id = $id;
        $tx->date = Carbon::parse($date);

        return $tx;
    }
}
