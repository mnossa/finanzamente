<?php

namespace Tests\Unit;

use App\Models\InboxItem;
use App\Services\InboxSimilarClusterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InboxSimilarClusterServiceTest extends TestCase
{
    use RefreshDatabase;

    private InboxSimilarClusterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InboxSimilarClusterService;
    }

    #[Test]
    public function clusters_items_with_same_description_account_category_and_nearby_dates(): void
    {
        $a = new InboxItem([
            'type' => 'expense',
            'description' => 'Pizza',
            'account_id' => 1,
            'category_id' => 2,
            'amount' => 10,
            'transaction_date' => '2026-07-16',
        ]);
        $b = new InboxItem([
            'type' => 'expense',
            'description' => 'pizza',
            'account_id' => 1,
            'category_id' => 2,
            'amount' => 12,
            'transaction_date' => '2026-07-16',
        ]);
        $c = new InboxItem([
            'type' => 'expense',
            'description' => 'Pizza',
            'account_id' => 1,
            'category_id' => 2,
            'amount' => 8,
            'transaction_date' => '2026-07-17',
        ]);

        $clusters = $this->service->findClusters(collect([$a, $b, $c]), windowDays: 1);

        $this->assertCount(1, $clusters);
        $this->assertCount(3, $clusters->first());
    }

    #[Test]
    public function does_not_cluster_when_account_differs(): void
    {
        $a = new InboxItem([
            'type' => 'expense',
            'description' => 'Pizza',
            'account_id' => 1,
            'category_id' => 2,
            'amount' => 10,
            'transaction_date' => '2026-07-16',
        ]);
        $b = new InboxItem([
            'type' => 'expense',
            'description' => 'Pizza',
            'account_id' => 99,
            'category_id' => 2,
            'amount' => 10,
            'transaction_date' => '2026-07-16',
        ]);

        $this->assertTrue($this->service->areSimilarPair($a, $a));
        $this->assertFalse($this->service->areSimilarPair($a, $b));
        $this->assertCount(0, $this->service->findClusters(collect([$a, $b])));
    }

    #[Test]
    public function does_not_cluster_when_dates_are_too_far(): void
    {
        $a = new InboxItem([
            'type' => 'expense',
            'description' => 'Pizza',
            'account_id' => 1,
            'category_id' => 2,
            'amount' => 10,
            'transaction_date' => '2026-07-16',
        ]);
        $b = new InboxItem([
            'type' => 'expense',
            'description' => 'Pizza',
            'account_id' => 1,
            'category_id' => 2,
            'amount' => 10,
            'transaction_date' => '2026-07-20',
        ]);

        $this->assertFalse($this->service->areSimilarPair($a, $b, 1));
        $this->assertCount(0, $this->service->findClusters(collect([$a, $b]), 1));
    }
}
