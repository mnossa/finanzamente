<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\TelegramLinkToken;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramLinkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Household $household;

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
    }

    #[Test]
    public function user_can_view_telegram_link_page()
    {
        $this->withoutVite();

        $response = $this->actingAs($this->user)->get(route('telegram.link.show'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Telegram/Link'));
    }

    #[Test]
    public function user_can_generate_link_token()
    {
        $response = $this->actingAs($this->user)->post(route('telegram.link.generate'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('telegram_link_tokens', [
            'user_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function generating_new_token_invalidates_old_token()
    {
        // Crea un token precedente
        TelegramLinkToken::create([
            'user_id' => $this->user->id,
            'token' => 'old-token-abcdefgh1234567890',
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->actingAs($this->user)->post(route('telegram.link.generate'));

        // Il vecchio token deve essere stato eliminato
        $this->assertDatabaseMissing('telegram_link_tokens', [
            'token' => 'old-token-abcdefgh1234567890',
        ]);

        // Il nuovo token deve essere presente
        $this->assertEquals(1, TelegramLinkToken::where('user_id', $this->user->id)->count());
    }

    #[Test]
    public function token_expires_after_30_minutes()
    {
        $token = TelegramLinkToken::create([
            'user_id' => $this->user->id,
            'token' => 'expiring-token-abc12345678901234',
            'expires_at' => now()->addMinutes(30),
        ]);

        $this->assertTrue($token->isValid());

        // Simula scadenza
        $token->update(['expires_at' => now()->subMinute()]);
        $token->refresh();

        $this->assertFalse($token->isValid());
    }

    #[Test]
    public function used_token_is_not_valid()
    {
        $token = TelegramLinkToken::create([
            'user_id' => $this->user->id,
            'token' => 'used-token-abcdefgh123456789012',
            'expires_at' => now()->addMinutes(30),
            'used_at' => now(),
        ]);

        $this->assertFalse($token->isValid());
    }

    #[Test]
    public function user_can_unlink_telegram_account()
    {
        $this->user->update(['telegram_chat_id' => '123456789']);

        $response = $this->actingAs($this->user)->delete(route('telegram.link.unlink'));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertNull($this->user->telegram_chat_id);
    }

    #[Test]
    public function unauthenticated_user_cannot_access_telegram_link()
    {
        $response = $this->get(route('telegram.link.show'));
        $response->assertRedirect(route('login'));
    }
}
