<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\TransactionImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarkStaleTransactionImportsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function command_marks_old_processing_and_pending_imports_as_failed(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);

        $this->travel(-3)->hours();
        $staleProcessing = TransactionImport::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'status' => 'processing',
            'rows_total' => 10,
            'started_at' => now(),
        ]);
        $this->travelBack();

        $this->travel(-5)->hours();
        $stalePending = TransactionImport::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'status' => 'pending',
            'rows_total' => 5,
            'started_at' => null,
        ]);
        $this->travelBack();

        $this->travel(-30)->minutes();
        $freshPending = TransactionImport::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'status' => 'pending',
            'rows_total' => 2,
            'started_at' => null,
        ]);
        $this->travelBack();

        $this->artisan('transaction-imports:mark-stale', [
            '--processing-minutes' => '60',
            '--pending-hours' => '2',
        ])->assertSuccessful();

        $staleProcessing->refresh();
        $stalePending->refresh();
        $freshPending->refresh();

        $this->assertSame('failed', $staleProcessing->status);
        $this->assertStringContainsString('pulizia automatica', (string) $staleProcessing->error_message);

        $this->assertSame('failed', $stalePending->status);

        $this->assertSame('pending', $freshPending->status);
    }

    #[Test]
    public function command_is_blocked_in_production_without_scheduled_flag(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('transaction-imports:mark-stale')->assertFailed();
    }

    #[Test]
    public function command_runs_in_production_when_scheduled_flag_is_set(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);

        $this->travel(-5)->hours();
        $stalePending = TransactionImport::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'status' => 'pending',
            'rows_total' => 1,
            'started_at' => null,
        ]);
        $this->travelBack();

        $this->artisan('transaction-imports:mark-stale', [
            '--scheduled' => true,
            '--pending-hours' => '2',
        ])->assertSuccessful();

        $this->assertSame('failed', $stalePending->fresh()->status);
    }
}
