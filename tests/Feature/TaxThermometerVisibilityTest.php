<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Household;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxThermometerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test che un utente con Partita IVA vede il widget Termometro Tasse.
     */
    public function test_user_with_vat_sees_tax_thermometer(): void
    {
        // Crea un utente con Partita IVA
        $user = User::factory()->create([
            'user_type' => 'partita_iva',
            'profile_settings' => [
                'has_vat' => true,
                'family_status' => 'single',
                'tracks_investments' => false,
            ],
        ]);

        // Crea e assegna un household
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->active_household_id = $household->id;
        $user->save();

        // Accedi come utente
        $response = $this->actingAs($user)->get(route('dashboard'));

        // Verifica che la dashboard sia accessibile
        $response->assertStatus(200);

        // Verifica che il componente TaxThermometer sia presente nell'HTML generato
        $response->assertSee('Termometro Tasse');
    }

    /**
     * Test che un utente senza Partita IVA non vede il widget.
     */
    public function test_user_without_vat_does_not_see_tax_thermometer(): void
    {
        // Crea un utente persona fisica (senza Partita IVA)
        $user = User::factory()->create([
            'user_type' => 'persona',
            'profile_settings' => [
                'has_vat' => false,
                'family_status' => 'single',
                'tracks_investments' => false,
            ],
        ]);

        // Crea e assegna un household
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->active_household_id = $household->id;
        $user->save();

        // Accedi come utente
        $response = $this->actingAs($user)->get(route('dashboard'));

        // Verifica che la dashboard sia accessibile
        $response->assertStatus(200);

        // Verifica che il componente TaxThermometer NON sia presente
        $response->assertDontSee('Termometro Tasse');
    }

    /**
     * Test che un utente con has_vat=true nelle impostazioni vede il widget.
     */
    public function test_user_with_has_vat_setting_sees_tax_thermometer(): void
    {
        // Crea un utente con has_vat impostato a true
        $user = User::factory()->create([
            'user_type' => 'persona',
            'profile_settings' => [
                'has_vat' => true,
                'family_status' => 'single',
                'tracks_investments' => false,
            ],
        ]);

        // Crea e assegna un household
        $household = Household::factory()->create(['owner_user_id' => $user->id]);
        $user->active_household_id = $household->id;
        $user->save();

        // Accedi come utente
        $response = $this->actingAs($user)->get(route('dashboard'));

        // Verifica che la dashboard sia accessibile
        $response->assertStatus(200);

        // Verifica che il componente TaxThermometer sia presente
        $response->assertSee('Termometro Tasse');
    }
}
