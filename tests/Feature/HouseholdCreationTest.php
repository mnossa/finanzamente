<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Household;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HouseholdCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    #[Test]
    public function authenticated_user_can_create_household_with_shared_wallet_mode()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('households.store'), [
            'name' => 'Test Household',
            'financial_management_type' => 'shared_wallet',
        ]);

        $response->assertRedirect(route('dashboard'));
        
        $household = Household::where('name', 'Test Household')->first();
        $this->assertNotNull($household);
        $this->assertEquals('shared_wallet', $household->financial_management_type);
        $this->assertEquals($user->id, $household->owner_user_id);
    }

    #[Test]
    public function authenticated_user_can_create_household_with_debt_balancing_mode()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('households.store'), [
            'name' => 'Test Debt Household',
            'financial_management_type' => 'debt_balancing',
        ]);

        $response->assertRedirect(route('dashboard'));
        
        $household = Household::where('name', 'Test Debt Household')->first();
        $this->assertNotNull($household);
        $this->assertEquals('debt_balancing', $household->financial_management_type);
    }

    #[Test]
    public function creating_household_requires_valid_financial_management_type()
    {
        $user = User::factory()->create();

        // Test con tipo invalido
        $response = $this->actingAs($user)->post(route('households.store'), [
            'name' => 'Test Household',
            'financial_management_type' => 'invalid_type',
        ]);

        $response->assertSessionHasErrors(['financial_management_type']);
        $this->assertEquals(0, Household::count());
    }

    #[Test]
    public function creating_household_requires_financial_management_type()
    {
        $user = User::factory()->create();

        // Test senza specificare il tipo
        $response = $this->actingAs($user)->post(route('households.store'), [
            'name' => 'Test Household',
            // financial_management_type mancante
        ]);

        $response->assertSessionHasErrors(['financial_management_type']);
        $this->assertEquals(0, Household::count());
    }

    #[Test]
    public function owner_can_update_household_financial_management_type()
    {
        $user = User::factory()->create();
        $household = Household::factory()->create([
            'owner_user_id' => $user->id,
            'financial_management_type' => 'shared_wallet',
        ]);

        // Aggiungi l'utente alla household come owner
        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'supervise' => true]),
        ]);

        $user->update(['active_household_id' => $household->id]);

        $response = $this->actingAs($user)->patch(route('households.update', $household->id), [
            'name' => $household->name,
            'financial_management_type' => 'debt_balancing',
        ]);

        $response->assertRedirect();
        
        $household->refresh();
        $this->assertEquals('debt_balancing', $household->financial_management_type);
    }

    #[Test]
    public function non_owner_cannot_update_household_financial_management_type()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        
        $household = Household::factory()->create([
            'owner_user_id' => $owner->id,
            'financial_management_type' => 'shared_wallet',
        ]);

        // Aggiungi il member come membro, non owner
        $household->users()->attach($member->id, [
            'role' => 'member',
            'permissions' => json_encode([]),
        ]);

        $member->update(['active_household_id' => $household->id]);

        $response = $this->actingAs($member)->patch(route('households.update', $household->id), [
            'name' => $household->name,
            'financial_management_type' => 'debt_balancing',
        ]);

        $response->assertStatus(403); // Forbidden
        
        $household->refresh();
        $this->assertEquals('shared_wallet', $household->financial_management_type); // Non cambiato
    }

    #[Test]
    public function owner_can_update_balance_percentages()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        
        $household = Household::factory()->create([
            'owner_user_id' => $owner->id,
            'financial_management_type' => 'debt_balancing',
        ]);

        // Aggiungi utenti alla household
        $household->users()->attach([
            $owner->id => ['role' => 'owner', 'permissions' => json_encode(['manage' => true])],
            $member->id => ['role' => 'member', 'permissions' => json_encode([])],
        ]);

        $owner->update(['active_household_id' => $household->id]);

        $response = $this->actingAs($owner)->patch(route('households.update', $household->id), [
            'name' => $household->name,
            'financial_management_type' => 'debt_balancing',
            'balance_percentages' => [
                $owner->id => 70,
                $member->id => 30,
            ],
        ]);

        $response->assertRedirect();
        
        $household->refresh();
        $this->assertEquals([
            (string)$owner->id => 70,
            (string)$member->id => 30,
        ], $household->balance_percentages);
    }

    #[Test]
    public function updating_balance_percentages_requires_100_percent_total()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        
        $household = Household::factory()->create([
            'owner_user_id' => $owner->id,
            'financial_management_type' => 'debt_balancing',
        ]);

        $household->users()->attach([
            $owner->id => ['role' => 'owner', 'permissions' => json_encode(['manage' => true])],
            $member->id => ['role' => 'member', 'permissions' => json_encode([])],
        ]);

        $owner->update(['active_household_id' => $household->id]);

        // Percentuali che non sommano a 100
        $response = $this->actingAs($owner)->patch(route('households.update', $household->id), [
            'name' => $household->name,
            'financial_management_type' => 'debt_balancing',
            'balance_percentages' => [
                $owner->id => 60,
                $member->id => 30, // Totale = 90%
            ],
        ]);

        $response->assertSessionHasErrors(['balance_percentages']);
        
        $household->refresh();
        $this->assertNull($household->balance_percentages);
    }

    #[Test]
    public function can_create_household_with_debt_balancing_and_balance_type()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('households.store'), [
            'name' => 'Test Debt Household',
            'financial_management_type' => 'debt_balancing',
            'balance_type' => 'custom',
        ]);

        $response->assertRedirect(route('dashboard'));
        
        $household = Household::where('name', 'Test Debt Household')->first();
        $this->assertNotNull($household);
        $this->assertEquals('debt_balancing', $household->financial_management_type);
        
        // Le percentuali personalizzate saranno configurate dopo nella pagina di modifica
    }
}