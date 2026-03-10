<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\InboxItem;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InboxItemTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->user = User::factory()->create();
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'can-modify' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Visualizzazione
    // -------------------------------------------------------------------------

    #[Test]
    public function user_can_view_inbox_index()
    {
        $this->withoutVite();

        InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => '10 Caffè',
            'amount' => 10.00,
        ]);

        $response = $this->actingAs($this->user)->get(route('inbox.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Inbox/Index'));
    }

    // -------------------------------------------------------------------------
    // Aggiornamento
    // -------------------------------------------------------------------------

    #[Test]
    public function user_can_update_inbox_item()
    {
        $item = InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => '15 Pizza',
        ]);

        $response = $this->actingAs($this->user)->putJson(route('inbox.update', $item->id), [
            'amount' => '15.00',
            'description' => 'Pizza Margherita',
            'transaction_date' => '2026-03-09',
        ]);

        $response->assertRedirect();

        $item->refresh();
        $this->assertEquals('15.00', $item->amount);
        $this->assertEquals('Pizza Margherita', $item->description);
    }

    // -------------------------------------------------------------------------
    // Conferma — amount null impedisce conferma
    // -------------------------------------------------------------------------

    #[Test]
    public function cannot_confirm_inbox_item_when_amount_is_null()
    {
        $item = InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => 'Qualcosa',
            'amount' => null,
        ]);

        $response = $this->actingAs($this->user)->post(route('inbox.confirm', $item->id));

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $item->refresh();
        $this->assertEquals('draft', $item->status);
        $this->assertNull($item->transaction_id);
    }

    #[Test]
    public function confirming_inbox_item_creates_transaction()
    {
        $item = InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => '20 Cena',
            'amount' => 20.00,
            'description' => 'Cena al ristorante',
            'transaction_date' => '2026-03-09',
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('inbox.confirm', $item->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $item->refresh();
        $this->assertEquals('confirmed', $item->status);
        $this->assertNotNull($item->transaction_id);

        $this->assertDatabaseHas('transactions', [
            'id' => $item->transaction_id,
            'user_id' => $this->user->id,
            'description' => 'Cena al ristorante',
        ]);
    }

    // -------------------------------------------------------------------------
    // Voci non confermate escluse dai report
    // -------------------------------------------------------------------------

    #[Test]
    public function pending_inbox_items_are_not_transactions()
    {
        InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => '50 Spesa',
            'amount' => 50.00,
        ]);

        // Le voci in draft non devono comparire nella tabella transactions
        $this->assertEquals(0, \App\Models\Transaction::where('user_id', $this->user->id)->count());
    }

    // -------------------------------------------------------------------------
    // Scarto
    // -------------------------------------------------------------------------

    #[Test]
    public function user_can_reject_inbox_item()
    {
        $item = InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => 'Da scartare',
            'amount' => 5.00,
        ]);

        $response = $this->actingAs($this->user)->post(route('inbox.reject', $item->id));

        $response->assertRedirect();
        $item->refresh();
        $this->assertEquals('rejected', $item->status);
    }

    // -------------------------------------------------------------------------
    // Autorizzazione
    // -------------------------------------------------------------------------

    #[Test]
    public function user_cannot_access_other_users_inbox_item()
    {
        $otherUser = User::factory()->create();
        $item = InboxItem::create([
            'user_id' => $otherUser->id,
            'status' => 'draft',
            'source' => 'manual',
            'amount' => 30.00,
        ]);

        $response = $this->actingAs($this->user)->post(route('inbox.confirm', $item->id));

        $response->assertForbidden();
    }

    #[Test]
    public function unauthenticated_user_cannot_access_inbox()
    {
        $response = $this->get(route('inbox.index'));
        $response->assertRedirect(route('login'));
    }

    // -------------------------------------------------------------------------
    // Scope model
    // -------------------------------------------------------------------------

    #[Test]
    public function pending_scope_returns_only_draft_and_needs_review()
    {
        InboxItem::create(['user_id' => $this->user->id, 'status' => 'draft', 'source' => 'manual']);
        InboxItem::create(['user_id' => $this->user->id, 'status' => 'needs_review', 'source' => 'manual']);
        InboxItem::create(['user_id' => $this->user->id, 'status' => 'confirmed', 'source' => 'manual']);
        InboxItem::create(['user_id' => $this->user->id, 'status' => 'rejected', 'source' => 'manual']);

        $pending = InboxItem::pending()->get();
        $this->assertCount(2, $pending);
        $this->assertTrue($pending->every(fn ($i) => in_array($i->status, ['draft', 'needs_review'])));
    }
}
