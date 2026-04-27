<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\InboxItem;
use App\Models\TelegramLinkToken;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();
        // Blocca le chiamate HTTP reali verso Telegram durante i test
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $this->user = User::factory()->create(['telegram_chat_id' => '987654321']);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id]);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'can-modify' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    #[Test]
    public function webhook_accepts_text_message_and_creates_inbox_item()
    {
        $payload = $this->buildTextPayload('987654321', '15.50 Pizza');

        $response = $this->postJson(route('telegram.webhook'), $payload);

        $response->assertOk();

        $this->assertDatabaseHas('inbox_items', [
            'user_id' => $this->user->id,
            'source' => 'telegram_text',
            'raw_text' => '15.50 Pizza',
            'status' => 'draft',
        ]);

        $item = InboxItem::where('user_id', $this->user->id)->first();
        $this->assertEquals(15.50, (float) $item->amount);
        $this->assertEquals('Pizza', $item->description);
    }

    #[Test]
    public function webhook_parses_account_from_at_syntax()
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Corrente',
        ]);

        $payload = $this->buildTextPayload('987654321', '15 Pizza @Corrente');

        $this->postJson(route('telegram.webhook'), $payload);

        $item = InboxItem::where('user_id', $this->user->id)->first();
        $this->assertEquals($account->id, $item->account_id);
        $this->assertEquals('Pizza', $item->description);
        $this->assertEquals(15.0, (float) $item->amount);
    }

    #[Test]
    public function webhook_parses_category_from_hash_syntax()
    {
        $category = Category::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Alimentari',
            'type' => 'expense',
        ]);

        $payload = $this->buildTextPayload('987654321', '20 Spesa #Alimentari');

        $this->postJson(route('telegram.webhook'), $payload);

        $item = InboxItem::where('user_id', $this->user->id)->first();
        $this->assertEquals($category->id, $item->category_id);
        $this->assertEquals('Spesa', $item->description);
    }

    #[Test]
    public function webhook_parses_date_from_dd_mm_syntax()
    {
        $payload = $this->buildTextPayload('987654321', '10 Caffè 15/03');

        $this->postJson(route('telegram.webhook'), $payload);

        $item = InboxItem::where('user_id', $this->user->id)->first();
        $year = now()->year;
        $this->assertEquals("{$year}-03-15", $item->transaction_date->toDateString());
    }

    #[Test]
    public function webhook_parses_combined_extended_syntax()
    {
        $account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'name' => 'Corrente',
        ]);
        $category = Category::factory()->create([
            'household_id' => $this->household->id,
            'name' => 'Cibo',
            'type' => 'expense',
        ]);

        $payload = $this->buildTextPayload('987654321', '15 Pizza @Corrente #Cibo 01/03');

        $this->postJson(route('telegram.webhook'), $payload);

        $item = InboxItem::where('user_id', $this->user->id)->first();
        $this->assertEquals(15.0, (float) $item->amount);
        $this->assertEquals('Pizza', $item->description);
        $this->assertEquals($account->id, $item->account_id);
        $this->assertEquals($category->id, $item->category_id);
        $year = now()->year;
        $this->assertEquals("{$year}-03-01", $item->transaction_date->toDateString());
    }

    #[Test]
    public function webhook_creates_draft_inbox_item_for_unknown_amount()
    {
        $payload = $this->buildTextPayload('987654321', 'Qualcosa da comprare');

        $this->postJson(route('telegram.webhook'), $payload);

        $item = InboxItem::where('user_id', $this->user->id)->first();
        $this->assertNull($item->amount);
        $this->assertEquals('draft', $item->status);
        $this->assertEquals('Qualcosa da comprare', $item->raw_text);
    }

    #[Test]
    public function webhook_sends_feedback_message_on_unknown_user()
    {
        $payload = $this->buildTextPayload('000000000', '20 Test');

        $response = $this->postJson(route('telegram.webhook'), $payload);

        $response->assertOk();
        $this->assertEquals(0, InboxItem::count());
        // Il messaggio di risposta deve essere inviato via HTTP::fake
        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage'));
    }

    #[Test]
    public function start_command_with_valid_token_links_account()
    {
        $newUser = User::factory()->create();
        $household2 = Household::factory()->create(['owner_user_id' => $newUser->id]);
        $household2->users()->attach($newUser->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $newUser->update(['active_household_id' => $household2->id]);

        $token = TelegramLinkToken::create([
            'user_id' => $newUser->id,
            'token' => 'validtoken12345678901234567890ab',
            'expires_at' => now()->addMinutes(30),
        ]);

        $payload = $this->buildTextPayload('111222333', '/start validtoken12345678901234567890ab');

        $response = $this->postJson(route('telegram.webhook'), $payload);

        $response->assertOk();

        $newUser->refresh();
        $this->assertEquals('111222333', $newUser->telegram_chat_id);

        $token->refresh();
        $this->assertNotNull($token->used_at);
    }

    #[Test]
    public function start_command_with_expired_token_does_not_link()
    {
        $newUser = User::factory()->create();
        TelegramLinkToken::create([
            'user_id' => $newUser->id,
            'token' => 'expiredtoken1234567890123456789',
            'expires_at' => now()->subMinute(),
        ]);

        $payload = $this->buildTextPayload('555666777', '/start expiredtoken1234567890123456789');

        $this->postJson(route('telegram.webhook'), $payload);

        $newUser->refresh();
        $this->assertNull($newUser->telegram_chat_id);
    }

    #[Test]
    public function webhook_returns_200_for_non_message_updates()
    {
        $response = $this->postJson(route('telegram.webhook'), ['callback_query' => ['id' => 'abc']]);
        $response->assertOk();
    }

    #[Test]
    public function webhook_rejects_request_when_secret_header_is_invalid()
    {
        config(['services.telegram.webhook_secret' => 'telegram-secret']);

        $payload = $this->buildTextPayload('987654321', '15.50 Pizza', 99001);

        $response = $this->postJson(route('telegram.webhook'), $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => 'invalid-secret',
        ]);

        $response->assertStatus(401);
        $this->assertDatabaseCount('inbox_items', 0);
    }

    #[Test]
    public function webhook_rejects_request_when_secret_is_configured_but_header_is_absent(): void
    {
        config(['services.telegram.webhook_secret' => 'telegram-secret']);

        $payload = $this->buildTextPayload('987654321', '15.50 Pizza', 99004);

        $response = $this->postJson(route('telegram.webhook'), $payload);

        $response->assertStatus(401);
        $this->assertDatabaseCount('inbox_items', 0);
    }

    #[Test]
    public function webhook_accepts_request_when_secret_header_is_valid()
    {
        config(['services.telegram.webhook_secret' => 'telegram-secret']);

        $payload = $this->buildTextPayload('987654321', '15.50 Pizza', 99002);

        $response = $this->postJson(route('telegram.webhook'), $payload, [
            'X-Telegram-Bot-Api-Secret-Token' => 'telegram-secret',
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('inbox_items', 1);
    }

    #[Test]
    public function webhook_is_idempotent_for_duplicate_update_id()
    {
        $payload = $this->buildTextPayload('987654321', '20 Spesa', 88001);

        $first = $this->postJson(route('telegram.webhook'), $payload);
        $second = $this->postJson(route('telegram.webhook'), $payload);

        $first->assertOk();
        $second->assertOk();
        $this->assertDatabaseCount('inbox_items', 1);
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function buildTextPayload(string $chatId, string $text, ?int $updateId = null): array
    {
        return [
            'update_id' => $updateId ?? random_int(1000000, 9999999),
            'message' => [
                'message_id' => 1,
                'chat' => ['id' => $chatId],
                'from' => ['id' => $chatId, 'first_name' => 'Test'],
                'text' => $text,
                'date' => time(),
            ],
        ];
    }
}
