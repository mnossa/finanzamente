<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuickSessionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

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

        Currency::firstOrCreate(['code' => 'EUR'], ['symbol' => '€', 'name' => 'Euro']);

        $this->account = Account::create([
            'household_id' => $this->household->id,
            'name' => 'Conto Test',
            'type' => 'bank',
            'initial_balance' => 1000,
            'currency_code' => 'EUR',
            'active' => true,
        ]);

        $this->expenseCategory = Category::create([
            'household_id' => $this->household->id,
            'name' => 'Spesa',
            'type' => 'expense',
        ]);
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    #[Test]
    public function unauthenticated_user_cannot_access_quick_session(): void
    {
        $this->get(route('transactions.quick-session'))
            ->assertRedirect(route('login'));
    }

    // ── GET quick-session ─────────────────────────────────────────────────────

    #[Test]
    public function authenticated_user_can_view_quick_session_page(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('transactions.quick-session'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Transactions/QuickSession')
            ->has('accounts')
            ->has('categories')
            ->has('sessionTransactions')
        );
    }

    #[Test]
    public function fresh_session_has_empty_transaction_list(): void
    {
        $response = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('transactions.quick-session'));

        $response->assertInertia(fn ($page) => $page->where('sessionTransactions', [])
        );
    }

    // ── POST quick-store ──────────────────────────────────────────────────────

    #[Test]
    public function user_can_add_transaction_via_quick_store(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('transactions.quick-store'), [
                'account_id' => $this->account->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 25.50,
                'date' => now()->toDateString(),
                'description' => 'Pizza',
            ]);

        $response->assertRedirect(route('transactions.quick-session'));
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Pizza',
        ]);
    }

    #[Test]
    public function expense_category_makes_amount_negative(): void
    {
        $this->actingAs($this->user)
            ->post(route('transactions.quick-store'), [
                'account_id' => $this->account->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 30,
                'date' => now()->toDateString(),
                'description' => 'Uscita',
            ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'amount' => -30,
        ]);
    }

    #[Test]
    public function quick_store_tracks_transaction_in_session(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('transactions.quick-store'), [
                'account_id' => $this->account->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 15,
                'date' => now()->toDateString(),
                'description' => 'Sessione test',
            ]);

        $response->assertRedirect(route('transactions.quick-session'));

        // Verifica che la transazione sia tracciata nella sessione
        $sessionResponse = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('transactions.quick-session'));

        $sessionResponse->assertInertia(fn ($page) => $page->has('sessionTransactions', 1)
        );
    }

    #[Test]
    public function quick_store_validation_fails_without_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('transactions.quick-store'), []);

        $response->assertSessionHasErrors(['account_id', 'category_id', 'amount', 'date']);
    }

    // ── DELETE quick-session (clear) ──────────────────────────────────────────

    #[Test]
    public function user_can_clear_quick_session(): void
    {
        // Prima aggiungi una transazione
        $this->actingAs($this->user)
            ->post(route('transactions.quick-store'), [
                'account_id' => $this->account->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 10,
                'date' => now()->toDateString(),
            ]);

        // Poi pulisci la sessione
        $clearResponse = $this->actingAs($this->user)
            ->delete(route('transactions.quick-session.clear'));

        $clearResponse->assertRedirect(route('transactions.quick-session'));

        // La lista sessione deve essere vuota
        $sessionResponse = $this->withoutVite()
            ->actingAs($this->user)
            ->get(route('transactions.quick-session'));

        $sessionResponse->assertInertia(fn ($page) => $page->where('sessionTransactions', [])
        );
    }

    #[Test]
    public function clearing_session_does_not_delete_transactions(): void
    {
        $this->actingAs($this->user)
            ->post(route('transactions.quick-store'), [
                'account_id' => $this->account->id,
                'category_id' => $this->expenseCategory->id,
                'amount' => 10,
                'date' => now()->toDateString(),
                'description' => 'Mantenuta',
            ]);

        $this->actingAs($this->user)
            ->delete(route('transactions.quick-session.clear'));

        // La transazione deve ancora esistere nel DB
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user->id,
            'description' => 'Mantenuta',
        ]);
    }
}
