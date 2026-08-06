<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionDescriptionFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionDescriptionFilterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function token_mode_escapes_like_wildcards(): void
    {
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

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'description' => '100% sconto',
            'amount' => -10,
        ]);

        Transaction::factory()->create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'description' => 'altro',
            'amount' => -5,
        ]);

        $query = Transaction::query()->where('account_id', $account->id);
        TransactionDescriptionFilter::apply($query, '100%', false);

        $this->assertSame(1, $query->count());
        $this->assertSame('100% sconto', $query->first()->description);
    }
}
