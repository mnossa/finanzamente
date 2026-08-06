<?php

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

class TransactionMutateResetsPaginationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function destroy_redirects_to_index_without_page_after_mutation(): void
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

        $category = Category::factory()->create([
            'household_id' => $household->id,
            'type' => 'expense',
        ]);

        $toDelete = Transaction::factory()->count(26)->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'category_id' => $category->id,
            'amount' => -10,
        ])->first();

        $returnPayload = json_encode([
            'category_id' => $category->id,
            'page' => 2,
        ]);

        $response = $this->actingAs($user)->delete(route('transactions.destroy', $toDelete->id), [
            'return_index_query' => $returnPayload,
        ]);

        $response->assertRedirect();
        $target = $response->headers->get('Location');
        $this->assertNotNull($target);
        $this->assertStringContainsString('category_id='.$category->id, $target);
        $this->assertStringNotContainsString('page=2', $target);
    }
}
