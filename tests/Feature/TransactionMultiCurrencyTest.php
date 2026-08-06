<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Test del flusso multi-currency su `TransactionController::store`/`update`.
 * Si concentra su:
 *  - cambio manuale fornito dal form (nessuna chiamata API)
 *  - fallback rate API quando il form non specifica un manual rate
 *  - persistenza delle colonne `original_amount` / `original_currency_code`
 *  - retro-compatibilità: chiamata legacy senza campi multi-currency
 */
class TransactionMultiCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $accountEur;

    private Category $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->accountEur = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'currency_code' => 'EUR',
        ]);

        $this->expenseCategory = Category::factory()->expense()->create([
            'household_id' => $this->household->id,
        ]);
    }

    #[Test]
    public function store_persists_original_currency_with_manual_rate_and_no_api_call(): void
    {
        Http::fake();

        $this->actingAs($this->user)->postJson('/transazioni', [
            'account_id' => $this->accountEur->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 35.40,
            'date' => '2026-05-08',
            'description' => 'Cena pub Londra',
            'original_amount' => 30.00,
            'original_currency_code' => 'GBP',
            'manual_rate' => 1.18,
        ])->assertStatus(302);

        $tx = Transaction::where('user_id', $this->user->id)->first();

        $this->assertNotNull($tx);
        $this->assertEquals('EUR', $tx->currency_code);
        $this->assertEquals(-35.40, (float) $tx->amount);
        $this->assertEquals(30.00, (float) $tx->original_amount);
        $this->assertEquals('GBP', $tx->original_currency_code);
        $this->assertEqualsWithDelta(1.0, (float) $tx->exchange_rate_to_base, 0.0001);
        $this->assertEqualsWithDelta(-35.40, (float) $tx->amount_base, 0.01);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'frankfurter'));
    }

    #[Test]
    public function store_without_currency_fields_is_backward_compatible(): void
    {
        Http::fake();

        $this->actingAs($this->user)->postJson('/transazioni', [
            'account_id' => $this->accountEur->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 50.00,
            'date' => '2026-05-08',
            'description' => 'Spesa',
        ])->assertStatus(302);

        $tx = Transaction::where('user_id', $this->user->id)->first();
        $this->assertEquals(-50.00, (float) $tx->amount);
        $this->assertEquals('EUR', $tx->currency_code);
        $this->assertNull($tx->original_amount);
        $this->assertNull($tx->original_currency_code);
        $this->assertEqualsWithDelta(1.0, (float) $tx->exchange_rate_to_base, 0.0001);
        $this->assertEqualsWithDelta(-50.00, (float) $tx->amount_base, 0.01);
    }

    #[Test]
    public function update_persists_original_currency_fields(): void
    {
        Http::fake();

        $tx = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->accountEur->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => -50.00,
            'currency_code' => 'EUR',
            'date' => '2026-05-01',
            'description' => 'Originale',
        ]);

        $this->actingAs($this->user)->patchJson("/transazioni/{$tx->id}", [
            'account_id' => $this->accountEur->id,
            'category_id' => $this->expenseCategory->id,
            'amount' => 35.40,
            'date' => '2026-05-08',
            'description' => 'Cena pub Londra',
            'original_amount' => 30.00,
            'original_currency_code' => 'GBP',
            'manual_rate' => 1.18,
        ])->assertStatus(302);

        $tx->refresh();
        $this->assertEquals('EUR', $tx->currency_code);
        $this->assertEquals(-35.40, (float) $tx->amount);
        $this->assertEquals(30.00, (float) $tx->original_amount);
        $this->assertEquals('GBP', $tx->original_currency_code);
        $this->assertEqualsWithDelta(-35.40, (float) $tx->amount_base, 0.01);
    }

    #[Test]
    public function profile_update_accepts_default_currency_code(): void
    {
        $this->actingAs($this->user)->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $this->user->email,
            'default_currency_code' => 'GBP',
        ])->assertRedirect();

        $this->user->refresh();
        $this->assertEquals('GBP', $this->user->default_currency_code);
    }

    #[Test]
    public function fx_preview_returns_identity_for_same_currency(): void
    {
        Http::fake();

        $this->actingAs($this->user)
            ->getJson(route('transactions.fx-preview', ['from' => 'EUR', 'to' => 'EUR', 'date' => '2026-05-08']))
            ->assertOk()
            ->assertJson([
                'from' => 'EUR',
                'to' => 'EUR',
                'rate' => 1.0,
                'source' => 'identity',
            ]);

        Http::assertNothingSent();
    }

    #[Test]
    public function fx_preview_returns_rate_from_frankfurter_on_cache_miss(): void
    {
        // CurrencyConverter chiama Frankfurter con base=EUR&symbols=GBP per
        // ottenere "1 EUR = X GBP", e poi inverte per avere "1 GBP = Y EUR".
        // Quindi mockiamo `rates.GBP = 1/1.18 ≈ 0.847` per ottenere rate=1.18.
        Http::fake([
            'api.frankfurter.dev/*' => Http::response([
                'amount' => 1.0,
                'base' => 'EUR',
                'date' => '2026-05-08',
                'rates' => ['GBP' => 0.847457627],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('transactions.fx-preview', ['from' => 'GBP', 'to' => 'EUR', 'date' => '2026-05-08']))
            ->assertOk();

        $payload = $response->json();
        $this->assertEquals('GBP', $payload['from']);
        $this->assertEquals('EUR', $payload['to']);
        $this->assertEqualsWithDelta(1.18, $payload['rate'], 0.01);
        $this->assertEquals('exchange_rates', $payload['source']);
    }

    #[Test]
    public function fx_preview_validates_currency_codes(): void
    {
        Http::fake();

        $this->actingAs($this->user)
            ->getJson(route('transactions.fx-preview', ['from' => 'XX', 'to' => 'EUR']))
            ->assertStatus(422);

        $this->actingAs($this->user)
            ->getJson(route('transactions.fx-preview', ['from' => 'GBP', 'to' => 'ZZZ']))
            ->assertStatus(422);
    }

    #[Test]
    public function fx_preview_requires_authentication(): void
    {
        $this->getJson(route('transactions.fx-preview', ['from' => 'GBP', 'to' => 'EUR']))
            ->assertStatus(401);
    }
}
