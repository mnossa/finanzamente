<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Household;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransactionTagTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Household $household;

    private Account $account;

    private Category $category;

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

        $this->account = Account::factory()->create([
            'household_id' => $this->household->id,
            'owner_user_id' => $this->user->id,
            'current_balance' => 1000.00,
        ]);

        $this->category = Category::factory()->create([
            'household_id' => $this->household->id,
            'type' => 'expense',
        ]);
    }

    // ---------------------------------------------------------------
    // Modello Tag: normalizzazione uppercase
    // ---------------------------------------------------------------

    #[Test]
    public function tag_name_is_stored_in_uppercase(): void
    {
        $tag = Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'name' => 'spesa alimentare',
            'color' => '#ff0000',
        ]);

        $this->assertSame('SPESA ALIMENTARE', $tag->name);
    }

    #[Test]
    public function tag_name_mixed_case_is_normalized_to_uppercase(): void
    {
        $tag = Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'name' => 'Viaggio Di Lavoro',
            'color' => null,
        ]);

        $this->assertSame('VIAGGIO DI LAVORO', $tag->name);
    }

    // ---------------------------------------------------------------
    // Unicità case-insensitive per household
    // ---------------------------------------------------------------

    #[Test]
    public function find_by_name_for_household_returns_existing_tag_case_insensitively(): void
    {
        Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'name' => 'VIAGGI',
            'color' => '#00ff00',
        ]);

        $found = Tag::findByNameForHousehold('viaggi', $this->household->id, $this->user->id);
        $this->assertNotNull($found);
        $this->assertSame('VIAGGI', $found->name);

        $found2 = Tag::findByNameForHousehold('Viaggi', $this->household->id, $this->user->id);
        $this->assertNotNull($found2);
        $this->assertSame('VIAGGI', $found2->name);
    }

    #[Test]
    public function find_by_name_for_household_returns_null_if_not_found(): void
    {
        $result = Tag::findByNameForHousehold('NONEXISTENT', $this->household->id);
        $this->assertNull($result);
    }

    #[Test]
    public function tags_are_unique_per_household_by_name(): void
    {
        Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'name' => 'SPORT',
        ]);

        $this->expectException(QueryException::class);

        Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'name' => 'SPORT',
        ]);
    }

    // ---------------------------------------------------------------
    // Associazione tag alle transazioni
    // ---------------------------------------------------------------

    #[Test]
    public function can_create_transaction_with_multiple_tags(): void
    {
        $tag1 = Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'LAVORO', 'color' => '#111111']);
        $tag2 = Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'RIMBORSO', 'color' => '#222222']);

        $response = $this->actingAs($this->user)->post(route('transactions.store'), [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => '100.00',
            'date' => '2024-01-15',
            'description' => 'Test con tag',
            'tag_ids' => [$tag1->id, $tag2->id],
        ]);

        $response->assertRedirect();

        $transaction = Transaction::where('description', 'Test con tag')->first();
        $this->assertNotNull($transaction);
        $this->assertCount(2, $transaction->tags);
        $this->assertTrue($transaction->tags->contains($tag1->id));
        $this->assertTrue($transaction->tags->contains($tag2->id));
    }

    #[Test]
    public function can_create_transaction_with_new_tag_names(): void
    {
        $response = $this->actingAs($this->user)->post(route('transactions.store'), [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => '50.00',
            'date' => '2024-01-20',
            'description' => 'Nuovi tag',
            'new_tag_names' => ['vacanze', 'estate'],
        ]);

        $response->assertRedirect();

        $transaction = Transaction::where('description', 'Nuovi tag')->first();
        $this->assertNotNull($transaction);
        $this->assertCount(2, $transaction->tags);

        // Verifica che i tag siano stati salvati in uppercase
        $tagNames = $transaction->tags->pluck('name')->toArray();
        $this->assertContains('VACANZE', $tagNames);
        $this->assertContains('ESTATE', $tagNames);
    }

    #[Test]
    public function creating_transaction_with_existing_tag_name_reuses_existing_tag(): void
    {
        $existingTag = Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->user->id,
            'name' => 'SPORT',
            'color' => '#ff0000',
        ]);

        $response = $this->actingAs($this->user)->post(route('transactions.store'), [
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => '30.00',
            'date' => '2024-01-25',
            'description' => 'Riuso tag',
            'new_tag_names' => ['sport'], // lowercase, dovrebbe riusare quello esistente
        ]);

        $response->assertRedirect();

        $transaction = Transaction::where('description', 'Riuso tag')->first();
        $this->assertNotNull($transaction);
        $this->assertCount(1, $transaction->tags);
        $this->assertEquals($existingTag->id, $transaction->tags->first()->id);

        // Verifica che non sia stato creato un tag duplicato
        $this->assertEquals(1, Tag::where('household_id', $this->household->id)->where('name', 'SPORT')->count());
    }

    // ---------------------------------------------------------------
    // Autocomplete (route tags.search)
    // ---------------------------------------------------------------

    #[Test]
    public function tag_search_returns_matching_tags_for_household(): void
    {
        Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'ALIMENTARI', 'color' => null]);
        Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'ALTRO ALIMENTARE', 'color' => null]);
        Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'BENZINA', 'color' => null]);

        $response = $this->actingAs($this->user)->get(route('tags.search', ['q' => 'al']));

        $response->assertOk();
        $data = $response->json();
        $names = array_column($data, 'name');

        $this->assertContains('ALIMENTARI', $names);
        $this->assertContains('ALTRO ALIMENTARE', $names);
        $this->assertNotContains('BENZINA', $names);
    }

    #[Test]
    public function tag_search_without_query_returns_all_household_tags(): void
    {
        Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'TAG1', 'color' => null]);
        Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'TAG2', 'color' => null]);

        $response = $this->actingAs($this->user)->get(route('tags.search'));

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(2, $data);
    }

    #[Test]
    public function tag_search_is_case_insensitive(): void
    {
        Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'VIAGGI', 'color' => null]);

        $response = $this->actingAs($this->user)->get(route('tags.search', ['q' => 'via']));

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertSame('VIAGGI', $data[0]['name']);
    }

    #[Test]
    public function tag_search_does_not_return_other_household_tags(): void
    {
        $otherHousehold = Household::factory()->create(['owner_user_id' => $this->user->id]);
        Tag::create(['household_id' => $otherHousehold->id, 'user_id' => $this->user->id, 'name' => 'PRIVATO', 'color' => null]);
        Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'PUBBLICO', 'color' => null]);

        $response = $this->actingAs($this->user)->get(route('tags.search'));

        $response->assertOk();
        $data = $response->json();
        $names = array_column($data, 'name');

        $this->assertContains('PUBBLICO', $names);
        $this->assertNotContains('PRIVATO', $names);
    }

    #[Test]
    public function unauthenticated_user_cannot_search_tags(): void
    {
        $response = $this->get(route('tags.search'));
        $response->assertRedirect('/accedi');
    }

    // ---------------------------------------------------------------
    // Filtro transazioni per tag
    // ---------------------------------------------------------------

    #[Test]
    public function can_filter_transactions_by_tag(): void
    {
        $tag = Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'SPORT', 'color' => null]);

        $tagged = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => -50.00,
            'currency_code' => 'EUR',
            'date' => now()->format('Y-m-d'),
            'description' => 'Con tag sport',
        ]);
        $tagged->tags()->attach($tag->id);

        $notTagged = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => -30.00,
            'currency_code' => 'EUR',
            'date' => now()->format('Y-m-d'),
            'description' => 'Senza tag',
        ]);

        $response = $this->actingAs($this->user)
            ->withoutVite()
            ->get(route('transactions.index', ['tag_id' => $tag->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Transactions/Index')
            ->where('transactions.data.0.description', 'Con tag sport')
            ->where('transactions.total', 1)
        );
    }

    #[Test]
    public function tag_filter_does_not_return_transactions_without_that_tag(): void
    {
        $tag1 = Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'LAVORO', 'color' => null]);
        $tag2 = Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'PERSONALE', 'color' => null]);

        $t1 = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => -100.00,
            'currency_code' => 'EUR',
            'date' => now()->format('Y-m-d'),
            'description' => 'Transazione lavoro',
        ]);
        $t1->tags()->attach($tag1->id);

        $t2 = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'amount' => -80.00,
            'currency_code' => 'EUR',
            'date' => now()->format('Y-m-d'),
            'description' => 'Transazione personale',
        ]);
        $t2->tags()->attach($tag2->id);

        $response = $this->actingAs($this->user)
            ->withoutVite()
            ->get(route('transactions.index', ['tag_id' => $tag1->id]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Transactions/Index')
            ->where('transactions.total', 1)
            ->where('transactions.data.0.description', 'Transazione lavoro')
        );
    }

    // ---------------------------------------------------------------
    // Creazione tag: store con duplicato case-insensitive
    // ---------------------------------------------------------------

    #[Test]
    public function creating_duplicate_tag_redirects_with_warning(): void
    {
        Tag::create(['household_id' => $this->household->id, 'user_id' => $this->user->id, 'name' => 'SPORT', 'color' => '#ff0000']);

        $response = $this->actingAs($this->user)->post(route('tags.store'), [
            'name' => 'sport',
            'color' => '#00ff00',
        ]);

        $response->assertRedirect(route('tags.index'));
        $response->assertSessionHas('warning');

        // Verifica che non sia stato creato un secondo tag
        $this->assertEquals(1, Tag::where('household_id', $this->household->id)->where('name', 'SPORT')->count());
    }
}
