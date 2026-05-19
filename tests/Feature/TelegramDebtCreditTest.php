<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TelegramDebtCreditTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Cache::flush();
        config(['services.telegram.bot_token' => 'test-token']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        $this->user = User::factory()->create(['telegram_chat_id' => '111222333']);
        $this->household = Household::factory()->create(['owner_user_id' => $this->user->id, 'name' => 'Casa Test']);
        $this->household->users()->attach($this->user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->user->update(['active_household_id' => $this->household->id]);
    }

    #[Test]
    public function debito_command_creates_debt_credit(): void
    {
        $payload = [
            'message' => [
                'chat' => ['id' => 111222333],
                'text' => '/debito 250 Mario Rossi',
            ],
        ];

        $this->postJson(route('telegram.webhook'), $payload)->assertOk();

        $this->assertDatabaseHas('debts_credits', [
            'household_id' => $this->household->id,
            'counterparty' => 'Mario Rossi',
            'amount' => 250,
            'type' => 'debt',
        ]);
    }

    #[Test]
    public function household_callback_switches_active_household(): void
    {
        $other = Household::factory()->create(['name' => 'Altro Nucleo']);
        $other->users()->attach($this->user->id, [
            'role' => 'member',
            'permissions' => json_encode(['manage' => true]),
        ]);

        $payload = [
            'callback_query' => [
                'id' => 'cb1',
                'data' => 'household:'.$other->id,
                'message' => ['chat' => ['id' => 111222333]],
            ],
        ];

        $this->postJson(route('telegram.webhook'), $payload)->assertOk();

        $this->user->refresh();
        $this->assertSame($other->id, $this->user->active_household_id);
    }
}
