<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AppNotification;
use App\Models\Category;
use App\Models\Household;
use App\Models\InboxItem;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    #[Test]
    public function confirming_inbox_item_accepts_account_and_category_from_request()
    {
        $category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $item = InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => '15 Pizza',
            'amount' => 15.00,
            'description' => 'Pizza',
            'transaction_date' => '2026-03-09',
        ]);

        $response = $this->actingAs($this->user)->post(route('inbox.confirm', $item->id), [
            'account_id' => $this->account->id,
            'category_id' => $category->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $item->refresh();
        $this->assertEquals('confirmed', $item->status);
        $this->assertDatabaseHas('transactions', [
            'id' => $item->transaction_id,
            'account_id' => $this->account->id,
            'category_id' => $category->id,
        ]);
    }

    #[Test]
    public function confirming_inbox_item_marks_related_notification_as_read()
    {
        $item = InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => '10 Test',
            'amount' => 10.00,
            'account_id' => $this->account->id,
        ]);

        $notification = AppNotification::create([
            'user_id' => $this->user->id,
            'title' => '💸 Nuova uscita in Inbox',
            'message' => 'Test',
            'read' => false,
            'notification_key' => 'inbox_telegram_'.$item->id,
        ]);

        $this->actingAs($this->user)->post(route('inbox.confirm', $item->id));

        $notification->refresh();
        $this->assertTrue($notification->read);
    }

    #[Test]
    public function rejecting_inbox_item_marks_related_notification_as_read()
    {
        $item = InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => '10 Test',
            'amount' => 10.00,
        ]);

        $notification = AppNotification::create([
            'user_id' => $this->user->id,
            'title' => '💸 Nuova uscita in Inbox',
            'message' => 'Test',
            'read' => false,
            'notification_key' => 'inbox_telegram_'.$item->id,
        ]);

        $this->actingAs($this->user)->post(route('inbox.reject', $item->id));

        $notification->refresh();
        $this->assertTrue($notification->read);
    }

    #[Test]
    public function user_can_confirm_all_pending_inbox_items()
    {
        InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => '10 Test1',
            'amount' => 10.00,
            'account_id' => $this->account->id,
        ]);
        InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'needs_review',
            'source' => 'telegram_text',
            'raw_text' => '20 Test2',
            'amount' => 20.00,
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('inbox.confirm-all'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(
            0,
            InboxItem::where('user_id', $this->user->id)->whereIn('status', ['draft', 'needs_review'])->count()
        );
        $this->assertEquals(
            2,
            InboxItem::where('user_id', $this->user->id)->where('status', 'confirmed')->count()
        );
    }

    #[Test]
    public function user_can_reject_all_pending_inbox_items()
    {
        InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => '10 Test1',
            'amount' => 10.00,
        ]);
        InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'needs_review',
            'source' => 'telegram_text',
            'raw_text' => '20 Test2',
            'amount' => 20.00,
        ]);

        $response = $this->actingAs($this->user)->post(route('inbox.reject-all'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(
            0,
            InboxItem::where('user_id', $this->user->id)->whereIn('status', ['draft', 'needs_review'])->count()
        );
        $this->assertEquals(
            2,
            InboxItem::where('user_id', $this->user->id)->where('status', 'rejected')->count()
        );
    }

    #[Test]
    public function confirm_all_skips_items_without_amount()
    {
        InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => 'Test senza importo',
            'amount' => null,
        ]);
        InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'raw_text' => '15 Test',
            'amount' => 15.00,
            'account_id' => $this->account->id,
        ]);

        $this->actingAs($this->user)->post(route('inbox.confirm-all'));

        // Solo quella con importo deve essere confermata
        $this->assertEquals(
            1,
            InboxItem::where('user_id', $this->user->id)->where('status', 'confirmed')->count()
        );
        // Quella senza importo rimane in draft (skipped)
        $this->assertEquals(
            1,
            InboxItem::where('user_id', $this->user->id)->where('status', 'draft')->count()
        );
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
        $this->assertEquals(0, Transaction::where('user_id', $this->user->id)->count());
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

    // -------------------------------------------------------------------------
    // Multi-currency
    // -------------------------------------------------------------------------

    #[Test]
    public function confirming_foreign_currency_item_converts_to_account_currency_with_manual_rate(): void
    {
        Http::fake();

        // Item GBP con override rate manuale 1 GBP = 1.18 EUR.
        // L'account è in EUR → la transazione finale deve essere in EUR (35.40)
        // con tracciatura dell'originale 30 GBP.
        $item = InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'type' => 'expense',
            'raw_text' => '30 GBP cena pub ~1.18',
            'amount' => 30.00,
            'currency_code' => 'GBP',
            'exchange_rate_to_base' => 1.18,
            'amount_base' => 35.40,
            'description' => 'cena pub',
            'transaction_date' => now()->toDateString(),
            'account_id' => $this->account->id,
        ]);

        $this->actingAs($this->user)->post(route('inbox.confirm', $item->id))->assertRedirect();

        $tx = Transaction::where('user_id', $this->user->id)->first();
        $this->assertNotNull($tx);
        $this->assertEqualsWithDelta(-35.40, (float) $tx->amount, 0.05);
        $this->assertEquals('EUR', $tx->currency_code);
        $this->assertEqualsWithDelta(1.0, (float) $tx->exchange_rate_to_base, 0.001);
        $this->assertEqualsWithDelta(-35.40, (float) $tx->amount_base, 0.05);
        $this->assertEquals(30.0, (float) $tx->original_amount);
        $this->assertEquals('GBP', $tx->original_currency_code);

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'frankfurter'));
    }

    #[Test]
    public function confirming_eur_item_keeps_amount_unchanged(): void
    {
        Http::fake();

        $item = InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'type' => 'expense',
            'raw_text' => '15 Pizza',
            'amount' => 15.00,
            'currency_code' => 'EUR',
            'exchange_rate_to_base' => 1.0,
            'amount_base' => 15.00,
            'description' => 'Pizza',
            'transaction_date' => now()->toDateString(),
            'account_id' => $this->account->id,
        ]);

        $this->actingAs($this->user)->post(route('inbox.confirm', $item->id))->assertRedirect();

        $tx = Transaction::where('user_id', $this->user->id)->first();
        $this->assertEquals(-15.00, (float) $tx->amount);
        $this->assertEquals('EUR', $tx->currency_code);
        $this->assertNull($tx->original_amount);
        $this->assertNull($tx->original_currency_code);
    }

    #[Test]
    public function confirming_inbox_item_can_attach_tags(): void
    {
        $existingTag = Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'name' => 'CENA',
            'color' => '#6366f1',
        ]);

        $item = InboxItem::create([
            'user_id' => $this->user->id,
            'household_id' => $this->household->id,
            'status' => 'draft',
            'source' => 'telegram_text',
            'type' => 'expense',
            'raw_text' => '20 Cena',
            'amount' => 20.00,
            'description' => 'Cena',
            'transaction_date' => '2026-07-16',
            'account_id' => $this->account->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('inbox.confirm', $item->id), [
            'tag_ids' => [$existingTag->id],
            'new_tag_names' => ['AMICI'],
        ]);

        $response->assertRedirect();
        $item->refresh();
        $transaction = Transaction::find($item->transaction_id);
        $this->assertNotNull($transaction);
        $tagNames = $transaction->tags()->pluck('name')->sort()->values()->all();
        $this->assertEquals(['AMICI', 'CENA'], $tagNames);
    }

    #[Test]
    public function index_exposes_similar_groups_for_matching_pending_items(): void
    {
        $this->withoutVite();

        $category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        foreach ([10, 12, 8] as $amount) {
            InboxItem::create([
                'user_id' => $this->user->id,
                'household_id' => $this->household->id,
                'status' => 'draft',
                'source' => 'telegram_text',
                'type' => 'expense',
                'raw_text' => "{$amount} Pizza",
                'amount' => $amount,
                'description' => 'Pizza',
                'transaction_date' => '2026-07-16',
                'account_id' => $this->account->id,
                'category_id' => $category->id,
            ]);
        }

        $response = $this->actingAs($this->user)->get(route('inbox.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Inbox/Index')
            ->has('similarGroups', 1)
            ->where('similarGroups.0.item_count', 3)
            ->where('similarGroups.0.total_amount', 30));
    }

    #[Test]
    public function merging_similar_inbox_items_creates_one_transaction_with_summed_amount_and_tags(): void
    {
        $category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $ids = [];
        foreach ([10, 15, 5] as $amount) {
            $item = InboxItem::create([
                'user_id' => $this->user->id,
                'household_id' => $this->household->id,
                'status' => 'draft',
                'source' => 'telegram_text',
                'type' => 'expense',
                'raw_text' => "{$amount} Pizza",
                'amount' => $amount,
                'currency_code' => 'EUR',
                'description' => 'Pizza',
                'transaction_date' => '2026-07-16',
                'account_id' => $this->account->id,
                'category_id' => $category->id,
            ]);
            $ids[] = $item->id;
        }

        $response = $this->actingAs($this->user)->post(route('inbox.merge'), [
            'inbox_item_ids' => $ids,
            'new_tag_names' => ['SERATA'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertEquals(1, Transaction::where('user_id', $this->user->id)->count());
        $tx = Transaction::where('user_id', $this->user->id)->first();
        $this->assertEquals(-30.0, (float) $tx->amount);
        $this->assertEquals('Pizza', $tx->description);
        $this->assertTrue($tx->tags()->where('name', 'SERATA')->exists());

        foreach ($ids as $id) {
            $item = InboxItem::find($id);
            $this->assertEquals('confirmed', $item->status);
            $this->assertEquals($tx->id, $item->transaction_id);
        }
    }

    #[Test]
    public function confirming_similar_items_separately_creates_one_transaction_each_with_shared_tags(): void
    {
        $category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);

        $ids = [];
        foreach ([10, 12] as $amount) {
            $item = InboxItem::create([
                'user_id' => $this->user->id,
                'household_id' => $this->household->id,
                'status' => 'draft',
                'source' => 'telegram_text',
                'type' => 'expense',
                'raw_text' => "{$amount} Pizza",
                'amount' => $amount,
                'currency_code' => 'EUR',
                'description' => 'Pizza',
                'transaction_date' => '2026-07-16',
                'account_id' => $this->account->id,
                'category_id' => $category->id,
            ]);
            $ids[] = $item->id;
        }

        $response = $this->actingAs($this->user)->post(route('inbox.confirm-separate'), [
            'inbox_item_ids' => $ids,
            'new_tag_names' => ['DOPPIO'],
        ]);

        $response->assertRedirect();
        $this->assertEquals(2, Transaction::where('user_id', $this->user->id)->count());

        foreach (Transaction::where('user_id', $this->user->id)->get() as $tx) {
            $this->assertTrue($tx->tags()->where('name', 'DOPPIO')->exists());
        }

        foreach ($ids as $id) {
            $this->assertEquals('confirmed', InboxItem::find($id)->status);
        }
    }
}
