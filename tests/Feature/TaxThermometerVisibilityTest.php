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
                'tax_rate' => 15,
                'inps_rate' => 26.23,
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

        // Verifica che i dati del TaxThermometer siano presenti nelle props
        $response->assertInertia(fn ($page) => 
            $page->has('taxThermometerData')
                ->where('taxThermometerData.has_vat', true)
                ->where('taxThermometerData.tax_rate', 15)
                ->where('taxThermometerData.inps_rate', 26.23)
        );
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

        // Verifica che i dati del TaxThermometer indichino has_vat = false
        $response->assertInertia(fn ($page) => 
            $page->has('taxThermometerData')
                ->where('taxThermometerData.has_vat', false)
        );
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
                'tax_rate' => 5,
                'inps_rate' => 26.23,
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

        // Verifica che i dati del TaxThermometer siano presenti con le aliquote personalizzate
        $response->assertInertia(fn ($page) => 
            $page->has('taxThermometerData')
                ->where('taxThermometerData.has_vat', true)
                ->where('taxThermometerData.tax_rate', 5)
                ->where('taxThermometerData.inps_rate', 26.23)
        );
    }
}
