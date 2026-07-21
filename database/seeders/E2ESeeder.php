<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\DashboardLayout;
use App\Models\FinancialGoal;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\MagazineArticle;
use App\Models\MagazineCategory;
use App\Models\RecurringTransaction;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\InvestmentTransactionSyncService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
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

        // Conto in valuta estera (GBP) per testare flussi multi-currency end-to-end:
        // dashboard cross-conto, transazioni in valuta nativa, conferme Inbox da Telegram
        // con `original_amount` in £.
        Account::firstOrCreate(
            ['household_id' => $household->id, 'name' => 'Revolut GBP E2E'],
            [
                'type' => 'bank',
                'initial_balance' => 800,
                'current_balance' => 800,
                'currency_code' => 'GBP',
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

        // Asset investimento per form /investimenti/crea (test Playwright happy path)
        $e2eAsset = InvestmentAsset::firstOrCreate(
            ['symbol' => 'E2ESEED'],
            [
                'type' => 'stock',
                'name' => 'Asset E2E Seeder',
                'currency_code' => 'EUR',
            ]
        );

        $this->seedE2ETransactionSources($user, $household, $e2eAsset);

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

        // Metrica a formula per i test dei widget a formula (anteprima + controlli avanzati)
        $balanceVariable = FinancialVariable::firstOrCreate(
            ['user_id' => $user->id, 'code' => 'e2e_bilancio_periodo'],
            [
                'name' => 'Bilancio Periodo E2E',
                'type' => FinancialVariable::TYPE_FORMULA,
                'formula_string' => '[period_net]',
                'is_public' => false,
            ]
        );

        // Due widget KPI con selettore conto, fissati in dashboard: servono al test
        // E2E che verifica il ricalcolo del SOLO widget filtrato (no refetch globale).
        $accountParameter = [
            ['key' => 'account_id', 'type' => 'account', 'label' => 'Conto', 'default' => 'all'],
        ];

        $widgetA = FormulaWidget::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Widget Conto A E2E'],
            [
                'financial_variable_id' => $balanceVariable->id,
                'display_type' => 'kpi',
                'period_preset' => 'current_month',
                'chart_config' => ['format' => 'currency', 'parameters' => $accountParameter],
            ]
        );

        $widgetB = FormulaWidget::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Widget Conto B E2E'],
            [
                'financial_variable_id' => $balanceVariable->id,
                'display_type' => 'kpi',
                'period_preset' => 'current_month',
                'chart_config' => ['format' => 'currency', 'parameters' => $accountParameter],
            ]
        );

        DashboardLayout::updateOrCreate(
            ['user_id' => $user->id],
            [
                'config' => [
                    'widgets' => [
                        ['id' => "formula_widget_{$widgetA->id}", 'visible' => true, 'position' => 0, 'size' => 'md'],
                        ['id' => "formula_widget_{$widgetB->id}", 'visible' => true, 'position' => 1, 'size' => 'md'],
                    ],
                ],
            ]
        );

        // Articoli magazine di test
        $this->seedMagazineArticles();
    }

    private function seedE2ETransactionSources(User $user, Household $household, InvestmentAsset $asset): void
    {
        $account = Account::where('household_id', $household->id)
            ->where('name', 'Conto E2E Principale')
            ->first();

        if ($account === null) {
            return;
        }

        if (! Category::query()->where('household_id', $household->id)->exists()) {
            app(CategoryService::class)->createDefaultCategoriesForHousehold($household);
        }

        $expenseCategory = Category::query()
            ->where('household_id', $household->id)
            ->where('type', 'expense')
            ->first();

        if ($expenseCategory === null) {
            return;
        }

        $pac = InvestmentPac::firstOrCreate(
            [
                'household_id' => $household->id,
                'account_id' => $account->id,
                'investment_asset_id' => $asset->id,
            ],
            [
                'user_id' => $user->id,
                'amount' => 50,
                'fees' => 0,
                'adjust_for_inflation' => false,
                'currency_code' => 'EUR',
                'frequency' => 'monthly',
                'start_date' => Carbon::today()->subMonth()->toDateString(),
                'status' => 'active',
            ]
        );

        $pacInvestment = Investment::firstOrCreate(
            [
                'household_id' => $household->id,
                'investment_pac_id' => $pac->id,
                'buy_date' => Carbon::today()->subDays(5)->toDateString(),
            ],
            [
                'user_id' => $user->id,
                'account_id' => $account->id,
                'asset_id' => $asset->id,
                'quantity' => 1,
                'buy_price' => 50,
                'notes' => 'PAC automatico',
                'is_private' => false,
            ]
        );

        app(InvestmentTransactionSyncService::class)->syncPurchase($pacInvestment);

        $recurring = RecurringTransaction::firstOrCreate(
            [
                'user_id' => $user->id,
                'account_id' => $account->id,
                'description' => 'Abbonamento E2E ricorrente',
            ],
            [
                'category_id' => $expenseCategory->id,
                'amount' => 9.99,
                'currency_code' => 'EUR',
                'frequency' => 'monthly',
                'start_date' => Carbon::today()->subMonths(2)->toDateString(),
                'last_generated_date' => Carbon::today()->subDays(2)->toDateString(),
            ]
        );

        Transaction::updateOrCreate(
            [
                'account_id' => $account->id,
                'recurring_transaction_id' => $recurring->id,
                'date' => Carbon::today()->subDays(2)->toDateString(),
            ],
            [
                'user_id' => $user->id,
                'category_id' => $expenseCategory->id,
                'amount' => -9.99,
                'currency_code' => 'EUR',
                'description' => 'Abbonamento E2E ricorrente',
                'recurring' => true,
            ]
        );

        // TX futura per sezione "Prossimi movimenti" (collapse E2E WFI-107)
        Transaction::updateOrCreate(
            [
                'account_id' => $account->id,
                'description' => 'Movimento futuro E2E',
                'date' => Carbon::today()->addDays(20)->toDateString(),
            ],
            [
                'user_id' => $user->id,
                'category_id' => $expenseCategory->id,
                'amount' => -15.00,
                'currency_code' => 'EUR',
                'recurring' => false,
            ]
        );
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
