<?php

namespace Tests\Feature;

use App\Models\Household;
use App\Models\InboxItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InboxIndexTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function index_lists_only_pending_items_and_exposes_archive_summary(): void
    {
        $user = User::factory()->create();
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true]),
        ]);
        $user->update(['active_household_id' => $household->id]);

        InboxItem::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'status' => 'draft',
            'source' => 'manual',
            'amount' => 10,
        ]);

        InboxItem::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'status' => 'confirmed',
            'source' => 'manual',
            'amount' => 20,
        ]);

        InboxItem::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'status' => 'rejected',
            'source' => 'manual',
            'amount' => 5,
        ]);

        $response = $this->withoutVite()
            ->actingAs($user)
            ->get(route('inbox.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Inbox/Index')
            ->has('items.data', 1)
            ->where('items.data.0.status', 'draft')
            ->where('archiveCount', 2)
            ->has('recentArchive', 2));
    }
}
