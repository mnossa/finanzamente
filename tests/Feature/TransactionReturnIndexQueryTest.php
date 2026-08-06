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
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionReturnIndexQueryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function update_redirects_to_index_with_sanitized_return_index_query(): void
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
        $category = Category::factory()->expense()->create([
            'household_id' => $household->id,
        ]);

        $tx = Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -40.00,
            'currency_code' => 'EUR',
            'date' => '2026-05-01',
            'description' => 'Voce test',
        ]);

        $returnPayload = json_encode([
            'account_id' => $account->id,
            'page' => 3,
            'type' => 'expense',
        ]);

        $response = $this->actingAs($user)->patchJson("/transazioni/{$tx->id}", [
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => 42.00,
            'date' => '2026-05-10',
            'description' => 'Aggiornata',
            'return_index_query' => $returnPayload,
        ]);

        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString(route('transactions.index'), $location);
        $query = (string) parse_url($location, PHP_URL_QUERY);
        parse_str($query, $params);
        $this->assertSame((string) $account->id, $params['account_id']);
        $this->assertSame('expense', $params['type']);
        $this->assertArrayNotHasKey('page', $params, 'Dopo modifica si riparte dalla pagina 1');
    }
}
