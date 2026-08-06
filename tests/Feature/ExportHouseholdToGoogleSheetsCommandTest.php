<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExportHouseholdToGoogleSheetsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_writes_csv_for_active_household(): void
    {
        $user = User::factory()->create(['profile_completed' => true]);
        $household = Household::create([
            'name' => 'Casa CLI',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        $dir = storage_path('framework/testing/cli-sheets-export-'.uniqid());

        try {
            $this->artisan('finanzamente:export-google-sheets', [
                '--user' => $user->id,
                '--output' => $dir,
                '--skip-exchange-rates' => true,
            ])
                ->assertSuccessful()
                ->expectsOutputToContain('CSV scritti in');

            $this->assertFileExists($dir.'/Dashboard.csv');
            $this->assertFileExists($dir.'/Conti.csv');
            $this->assertFileDoesNotExist($dir.'/Accounts.csv');
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_dry_run_prints_counts(): void
    {
        $user = User::factory()->create(['profile_completed' => true]);
        $household = Household::create([
            'name' => 'Casa Dry',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $this->artisan('finanzamente:export-google-sheets', [
            '--user' => $user->id,
            '--dry-run' => true,
            '--skip-exchange-rates' => true,
        ])
            ->assertSuccessful()
            ->expectsOutputToContain('Dry-run');
    }
}
