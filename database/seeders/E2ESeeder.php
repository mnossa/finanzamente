<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\Household;
use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use App\Models\Subscription;
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
        $this->call(MagazineCategorySeeder::class);

        $email = env('E2E_USER_EMAIL', 'e2e@finanzamente.test');
        $password = env('E2E_USER_PASSWORD', 'password');

        // Crea o aggiorna l'utente E2E
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Utente E2E',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'profile_completed' => true,
                'plan' => 'pro',
                'plan_expires_at' => now()->addMonths(6),
                'profile_settings' => [
                    'has_vat' => false,
                    'family_status' => 'single',
                    'tracks_investments' => true,
                    'completed_at' => now()->toISOString(),
                ],
            ]
        );

        // Crea o recupera la household di test
        $household = Household::firstOrCreate(
            ['owner_user_id' => $user->id],
            [
                'name' => 'Casa E2E',
                'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
            ]
        );

        // Associa utente alla household se non già collegato
        if (! $user->households()->where('household_id', $household->id)->exists()) {
            $user->households()->attach($household->id, ['role' => 'owner']);
        }

        // Crea una seconda household per i test inter-household
        $secondHousehold = Household::firstOrCreate(
            [
                'owner_user_id' => $user->id,
                'name' => 'Casa E2E Secondaria',
            ],
            [
                'financial_management_type' => Household::FINANCIAL_MANAGEMENT_DEBT_BALANCING,
            ]
        );

        if (! $user->households()->where('household_id', $secondHousehold->id)->exists()) {
            $user->households()->attach($secondHousehold->id, ['role' => 'owner']);
        }

        // Imposta la household come attiva
        $user->update(['active_household_id' => $household->id]);

        // Account di test per trasferimenti inter-household
        Account::firstOrCreate(
            ['household_id' => $household->id, 'name' => 'Conto E2E Principale'],
            [
                'type' => 'bank',
                'initial_balance' => 1500,
                'current_balance' => 1500,
                'currency_code' => 'EUR',
                'active' => true,
                'is_private' => false,
                'owner_user_id' => $user->id,
            ]
        );

        Account::firstOrCreate(
            ['household_id' => $secondHousehold->id, 'name' => 'Conto E2E Secondario'],
            [
                'type' => 'bank',
                'initial_balance' => 500,
                'current_balance' => 500,
                'currency_code' => 'EUR',
                'active' => true,
                'is_private' => false,
                'owner_user_id' => $user->id,
            ]
        );

        // Subscription Pro attiva per test E2E dei flussi abbonamento
        Subscription::updateOrCreate(
            ['user_id' => $user->id, 'status' => 'active'],
            [
                'plan' => 'pro',
                'billing_cycle' => 'monthly',
                'currency' => 'EUR',
                'amount_cents' => 990,
                'next_payment_at' => now()->addMonth(),
                'billing_name' => 'Utente E2E',
                'billing_email' => $email,
                'billing_country' => 'IT',
            ]
        );

        $this->command->info("Utente E2E pronto: {$email}");
        $this->command->info("Household: {$household->name} (ID: {$household->id})");

        // Crea un obiettivo finanziario di test per il widget dashboard
        FinancialGoal::firstOrCreate(
            ['household_id' => $household->id, 'name' => 'Obiettivo E2E Vacanza'],
            [
                'user_id' => $user->id,
                'description' => 'Obiettivo creato dal seeder E2E',
                'target_amount' => 2000.00,
                'current_amount' => 500.00,
                'currency_code' => 'EUR',
                'target_date' => now()->addYear()->format('Y-m-d'),
                'status' => 'in_progress',
                'icon' => '✈️',
                'color' => '#10b981',
            ]
        );

        // Articoli magazine di test
        $this->seedMagazineArticles();
    }

    private function seedMagazineArticles(): void
    {
        $risparmio = MagazineCategory::where('slug', 'risparmio')->first();
        $investimenti = MagazineCategory::where('slug', 'investimenti')->first();
        $budgeting = MagazineCategory::where('slug', 'budgeting')->first();

        if (! $risparmio || ! $investimenti || ! $budgeting) {
            return;
        }

        $articles = [
            [
                'category_id' => $risparmio->id,
                'slug' => 'fondo-emergenza-guida-e2e',
                'title' => 'Come costruire un fondo di emergenza (articolo E2E)',
                'excerpt' => 'Una guida pratica per accantonare 3-6 mesi di spese.',
                'content' => "## Perché hai bisogno di un fondo di emergenza\n\nIl fondo di emergenza è la base di ogni piano finanziario solido.\n\n> Inizia con piccoli passi: anche 50 € al mese fanno la differenza.\n\n---\n\n## Quant'è abbastanza?\n\nL'obiettivo ideale è coprire da **3 a 6 mesi** di spese fisse.",
                'author_name' => 'Team Finanzamente',
                'reading_time_minutes' => 5,
                'published_at' => now()->subDays(10),
                'is_featured' => true,
                'meta_title' => 'Fondo di emergenza: guida E2E',
                'meta_description' => 'Guida pratica per il fondo di emergenza - articolo E2E test.',
            ],
            [
                'category_id' => $investimenti->id,
                'slug' => 'etf-per-principianti-e2e',
                'title' => 'ETF per principianti (articolo E2E)',
                'excerpt' => 'Tutto quello che devi sapere sugli ETF prima di iniziare.',
                'content' => "## Cosa sono gli ETF\n\nGli ETF (Exchange Traded Fund) sono strumenti che replicano un indice di mercato.\n\n---\n\n## Vantaggi principali\n\n- Diversificazione automatica\n- Costi bassi\n- Liquidità elevata",
                'author_name' => 'Team Finanzamente',
                'reading_time_minutes' => 7,
                'published_at' => now()->subDays(5),
                'is_featured' => false,
                'meta_title' => 'ETF per principianti - E2E',
                'meta_description' => 'Guida agli ETF per principianti - articolo E2E test.',
            ],
            [
                'category_id' => $budgeting->id,
                'slug' => 'regola-50-30-20-e2e',
                'title' => 'La regola 50/30/20 spiegata (articolo E2E)',
                'excerpt' => 'Un metodo semplice per dividere il tuo stipendio.',
                'content' => "## La regola 50/30/20\n\nDividi il reddito netto in tre categorie:\n\n- **50%** bisogni (affitto, cibo, bollette)\n- **30%** desideri (svago, abbonamenti)\n- **20%** risparmio e investimenti",
                'author_name' => 'Team Finanzamente',
                'reading_time_minutes' => 4,
                'published_at' => now()->subDays(2),
                'is_featured' => false,
                'meta_title' => 'Regola 50/30/20 - E2E',
                'meta_description' => 'La regola 50/30/20 per gestire il budget - articolo E2E test.',
            ],
        ];

        foreach ($articles as $data) {
            MagazineArticle::firstOrCreate(['slug' => $data['slug']], $data);
        }

        $this->command->info('Articoli magazine E2E creati: '.count($articles));
    }
}
