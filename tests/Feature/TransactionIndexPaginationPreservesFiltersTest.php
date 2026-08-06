<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionIndexPaginationPreservesFiltersTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pagination_links_include_query_filters(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        Transaction::factory()->count(30)->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('transactions.index', [
            'account_id' => $account->id,
        ]));

        $response->assertOk();
        $response->assertViewHas('page');

        $page = json_decode(json_encode($response->viewData('page')), true);
        $this->assertSame('Transactions/Index', $page['component']);
        $links = $page['props']['transactions']['links'];
        $page2Url = collect($links)
            ->pluck('url')
            ->first(fn (?string $url) => $url !== null && str_contains($url, 'page=2'));

        $this->assertNotNull($page2Url, 'Nessun link di paginazione verso la pagina 2.');
        $this->assertStringContainsString('account_id='.$account->id, $page2Url);
    }
}
