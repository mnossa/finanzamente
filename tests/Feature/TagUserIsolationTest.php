<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TagUserIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    private Household $household;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();

        $this->household = Household::factory()->create(['owner_user_id' => $this->userA->id]);
        $this->household->users()->attach($this->userA->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $this->household->users()->attach($this->userB->id, [
            'role' => 'member',
            'permissions' => json_encode(['manage' => true]),
        ]);

        $this->userA->update(['active_household_id' => $this->household->id]);
        $this->userB->update(['active_household_id' => $this->household->id]);
    }

    #[Test]
    public function tag_search_returns_only_current_user_tags(): void
    {
        Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->userA->id,
            'name' => 'PRIVATO_A',
        ]);
        Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->userB->id,
            'name' => 'PRIVATO_B',
        ]);

        $responseA = $this->actingAs($this->userA)->getJson(route('tags.search', ['q' => 'PRIV']));
        $responseA->assertOk();
        $responseA->assertJsonCount(1);
        $responseA->assertJsonFragment(['name' => 'PRIVATO_A']);

        $responseB = $this->actingAs($this->userB)->getJson(route('tags.search', ['q' => 'PRIV']));
        $responseB->assertOk();
        $responseB->assertJsonCount(1);
        $responseB->assertJsonFragment(['name' => 'PRIVATO_B']);
    }

    #[Test]
    public function user_cannot_access_other_users_tag(): void
    {
        $foreignTag = Tag::create([
            'household_id' => $this->household->id,
            'user_id' => $this->userA->id,
            'name' => 'SEGRETO',
        ]);

        $this->actingAs($this->userB)
            ->get(route('tags.edit', $foreignTag))
            ->assertForbidden();

        $this->actingAs($this->userB)
            ->get(route('tags.show', $foreignTag))
            ->assertForbidden();
    }
}
