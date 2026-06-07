<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DatabaseAnonymizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseAnonymizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function anonymize_command_scrubs_user_and_transaction_pii(): void
    {
        $user = User::factory()->create([
            'name' => 'Mario Rossi',
            'email' => 'mario.rossi@example.com',
            'fiscal_code' => 'RSSMRA80A01H501U',
        ]);

        $household = Household::factory()->create([
            'owner_user_id' => $user->id,
            'name' => 'Famiglia Rossi',
        ]);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'name' => 'Conto Intesa Mario',
            'currency_code' => 'EUR',
        ]);

        $transaction = Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'description' => 'Spesa Esselunga via Roma',
            'amount' => -42.5,
            'currency_code' => 'EUR',
            'date' => now(),
        ]);

        $this->artisan('db:anonymize --force')->assertSuccessful();

        $user->refresh();
        $household->refresh();
        $account->refresh();
        $transaction->refresh();

        $this->assertSame('user'.$user->id.'@anon.finanzamente.local', $user->email);
        $this->assertSame('Utente '.$user->id, $user->name);
        $this->assertNull($user->fiscal_code);
        $this->assertSame('Household '.$household->id, $household->name);
        $this->assertStringContainsString((string) $account->id, $account->name);
        $this->assertSame('Movimento #'.$transaction->id, $transaction->description);
    }

    #[Test]
    public function anonymize_service_is_blocked_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(\RuntimeException::class);

        app(DatabaseAnonymizationService::class)->run();
    }

    #[Test]
    public function anonymize_service_is_blocked_in_staging(): void
    {
        $this->app->detectEnvironment(fn () => 'staging');

        $this->expectException(\RuntimeException::class);

        app(DatabaseAnonymizationService::class)->assertSafeEnvironment();
    }
}
