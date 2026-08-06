<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DatabaseAnonymizationService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
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
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_recovery_codes' => ['recovery-code-one', 'recovery-code-two'],
            'two_factor_confirmed_at' => now(),
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

        $this->assertSame(DatabaseAnonymizationService::OWNER_DEV_EMAIL, $user->email);
        $this->assertSame('Utente '.$user->id, $user->name);
        $this->assertTrue(Hash::check(DatabaseAnonymizationService::DEFAULT_PASSWORD, $user->password));
        $this->assertNull($user->fiscal_code);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertNull($user->two_factor_confirmed_at);
        $this->assertSame('Household '.$household->id, $household->name);
        $this->assertStringContainsString((string) $account->id, $account->name);
        $this->assertSame('Movimento #'.$transaction->id, $transaction->description);
    }

    #[Test]
    public function anonymize_owner_email_maps_to_dev_login_and_can_authenticate(): void
    {
        config(['app.admin_email' => 'owner@example.com']);

        $owner = User::factory()->create(['email' => 'owner@example.com']);
        User::factory()->create(['email' => 'member@example.com']);

        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->artisan('db:anonymize --force')->assertSuccessful();

        $owner->refresh();
        $this->assertSame(DatabaseAnonymizationService::OWNER_DEV_EMAIL, $owner->email);

        $response = $this->post('/accedi', [
            'email' => DatabaseAnonymizationService::OWNER_DEV_EMAIL,
            'password' => DatabaseAnonymizationService::DEFAULT_PASSWORD,
        ]);

        $this->assertAuthenticatedAs($owner);
        $response->assertRedirect(route('dashboard', absolute: false));
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
