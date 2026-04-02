<?php

namespace Database\Seeders;

use App\Models\Household;
use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder dedicato ai test E2E con Playwright.
 *
 * Crea (o aggiorna) un utente verificato con household attiva,
 * pronto per i test automatici senza passare per il flusso di registrazione/verifica.
 *
 * Credenziali di default:
 *   - Email:    e2e@finanzamente.test  (override: E2E_USER_EMAIL)
 *   - Password: password               (override: E2E_USER_PASSWORD)
 */
class E2ESeeder extends Seeder
{
    public function run(): void
    {
        // Dipendenze richieste dall'app
        $this->call(CurrencySeeder::class);
        $this->call(CategorySeeder::class);

        $email    = env('E2E_USER_EMAIL', 'e2e@finanzamente.test');
        $password = env('E2E_USER_PASSWORD', 'password');

        // Crea o aggiorna l'utente E2E
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'               => 'Utente E2E',
                'password'           => Hash::make($password),
                'email_verified_at'  => now(),
                'profile_completed'  => true,
                'profile_settings'   => [
                    'has_vat'           => false,
                    'family_status'     => 'single',
                    'tracks_investments' => false,
                    'completed_at'      => now()->toISOString(),
                ],
            ]
        );

        // Crea o recupera la household di test
        $household = Household::firstOrCreate(
            ['owner_user_id' => $user->id],
            [
                'name'                      => 'Casa E2E',
                'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
            ]
        );

        // Associa utente alla household se non già collegato
        if (! $user->households()->where('household_id', $household->id)->exists()) {
            $user->households()->attach($household->id, ['role' => 'owner']);
        }

        // Imposta la household come attiva
        $user->update(['active_household_id' => $household->id]);

        $this->command->info("Utente E2E pronto: {$email}");
        $this->command->info("Household: {$household->name} (ID: {$household->id})");

        // Crea un obiettivo finanziario di test per il widget dashboard
        FinancialGoal::firstOrCreate(
            ['household_id' => $household->id, 'name' => 'Obiettivo E2E Vacanza'],
            [
                'user_id'        => $user->id,
                'description'    => 'Obiettivo creato dal seeder E2E',
                'target_amount'  => 2000.00,
                'current_amount' => 500.00,
                'currency_code'  => 'EUR',
                'target_date'    => now()->addYear()->format('Y-m-d'),
                'status'         => 'in_progress',
                'icon'           => '✈️',
                'color'          => '#10b981',
            ]
        );
    }
}
