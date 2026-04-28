<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Category;
use App\Models\Currency;
use App\Models\DebtCredit;
use App\Models\Household;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    private Currency $eur;

    private array $expenseCategories = [];

    private array $incomeCategories = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $this->command->info('🚀 Inizio generazione dati demo...');

            // Assicurati che le valute esistano
            if (Currency::count() === 0) {
                $this->command->info('💱 Creazione valute...');
                $this->call(CurrencySeeder::class);
            }

            // Carica valuta EUR
            $this->eur = Currency::where('code', 'EUR')->firstOrFail();

            // Crea utente con partita IVA
            $this->command->info('👤 Creazione utente con Partita IVA...');
            $userVat = $this->createUser(
                'Mario Rossi',
                'mario.rossi@example.com',
                'RSSMRA85M01H501Z',
                '12345678901'
            );

            // Crea utente residenziale
            $this->command->info('👤 Creazione utente residenziale...');
            $userResidential = $this->createUser(
                'Laura Bianchi',
                'laura.bianchi@example.com',
                'BNCLRA90A41H501W',
                null
            );

            // Crea 2 household per utente con P.IVA
            $this->command->info('🏠 Creazione household per utente con P.IVA...');
            $household1Vat = $this->createHousehold($userVat, 'Famiglia Rossi', 'shared_wallet');
            $household2Vat = $this->createHousehold($userVat, 'Attività Professionale', 'debt_balancing');

            // Crea 2 household per utente residenziale
            $this->command->info('🏠 Creazione household per utente residenziale...');
            $household1Res = $this->createHousehold($userResidential, 'Casa Bianchi', 'shared_wallet');
            $household2Res = $this->createHousehold($userResidential, 'Risparmi Personali', 'shared_wallet');

            // Carica categorie (ora che gli household sono stati creati)
            $this->loadCategories();

            // Genera dati per ogni household
            $households = [
                ['household' => $household1Vat, 'user' => $userVat, 'name' => 'Famiglia Rossi'],
                ['household' => $household2Vat, 'user' => $userVat, 'name' => 'Attività Professionale'],
                ['household' => $household1Res, 'user' => $userResidential, 'name' => 'Casa Bianchi'],
                ['household' => $household2Res, 'user' => $userResidential, 'name' => 'Risparmi Personali'],
            ];

            foreach ($households as $data) {
                $this->command->info("📊 Generazione dati per: {$data['name']}");
                $this->generateHouseholdData($data['household'], $data['user']);
            }

            DB::commit();

            $this->command->info('');
            $this->command->info('✅ Dati demo generati con successo!');
            $this->command->info('');
            $this->command->info('📧 Credenziali utenti:');
            $this->command->info('   • mario.rossi@example.com (password: password)');
            $this->command->info('   • laura.bianchi@example.com (password: password)');
            $this->command->info('');
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Errore durante la generazione: '.$e->getMessage());
            throw $e;
        }
    }

    private function loadCategories(): void
    {
        $this->expenseCategories = Category::where('type', 'expense')->pluck('id')->toArray();
        $this->incomeCategories = Category::where('type', 'income')->pluck('id')->toArray();

        if (empty($this->expenseCategories) || empty($this->incomeCategories)) {
            throw new \Exception('Categorie non trovate. Esegui prima il CategorySeeder.');
        }
    }

    private function createUser(string $name, string $email, ?string $fiscalCode, ?string $vatNumber): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'fiscal_code' => $fiscalCode,
            'vat_number' => $vatNumber,
            'user_type' => ! is_null($vatNumber) ? 'partita_iva' : 'persona',
            'profile_completed' => true,
        ]);
    }

    private function createHousehold(User $user, string $name, string $type): Household
    {
        $household = Household::create([
            'name' => $name,
            'owner_user_id' => $user->id,
            'financial_management_type' => $type,
        ]);

        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['view', 'create', 'modify', 'delete']),
        ]);

        $household->active_household_id = $household->id;
        $user->active_household_id = $household->id;
        $user->save();

        return $household;
    }

    private function generateHouseholdData(Household $household, User $user): void
    {
        // Crea conti
        $this->command->info('   💳 Creazione conti...');
        $accounts = $this->createAccounts($household, $user);

        // Crea debiti/crediti
        $this->command->info('   💰 Creazione debiti e crediti...');
        $debts = $this->createDebtsAndCredits($household, $user, $accounts);

        // Crea transazioni ricorrenti
        $this->command->info('   🔄 Creazione transazioni ricorrenti...');
        $this->createRecurringTransactions($household, $user, $accounts, $debts);

        // Crea 4000 transazioni dal 2022 ad oggi
        $this->command->info('   📝 Creazione 4000 transazioni (può richiedere alcuni minuti)...');
        $this->createTransactions($household, $user, $accounts, 4000);
    }

    private function createAccounts(Household $household, User $user): array
    {
        $accountTypes = ['bank', 'cash', 'card', 'broker'];
        $accounts = [];

        foreach ($accountTypes as $type) {
            $balance = rand(100000, 5000000) / 100; // 1000-50000 EUR
            $accounts[] = Account::create([
                'household_id' => $household->id,
                'owner_user_id' => $user->id,
                'currency_code' => $this->eur->code,
                'name' => $this->getAccountName($type),
                'type' => $type,
                'initial_balance' => $balance,
                'current_balance' => $balance,
                'is_private' => false,
            ]);
        }

        return $accounts;
    }

    private function getAccountName(string $type): string
    {
        return match ($type) {
            'bank' => 'Conto Corrente Principale',
            'cash' => 'Contanti',
            'card' => 'Carta di Credito',
            'broker' => 'Conto Investimenti',
            default => 'Conto Generico',
        };
    }

    private function createDebtsAndCredits(Household $household, User $user, array $accounts): array
    {
        $debts = [];

        // Crea 3 debiti
        for ($i = 1; $i <= 3; $i++) {
            $isClosed = $i === 3; // Ultimo debito già chiuso
            $amount = rand(50000, 500000) / 100; // 500-5000 EUR
            $paidAmount = $isClosed ? $amount : rand(0, (int) ($amount * 0.7 * 100)) / 100;

            $debts[] = DebtCredit::create([
                'household_id' => $household->id,
                'user_id' => $user->id,
                'type' => rand(0, 1) === 0 ? 'debt' : 'credit',
                'counterparty' => $this->getRandomCounterparty(),
                'amount' => $amount,
                'initial_amount' => $amount,
                'paid_amount' => $paidAmount,
                'currency_code' => $this->eur->code,
                'status' => $isClosed ? 'closed' : 'open',
                'due_date' => Carbon::now()->addMonths(rand(1, 12)),
                'description' => "Debito/Credito #$i",
                'interest_rate' => rand(0, 500) / 100, // 0-5%
                'interest_type' => rand(0, 1) === 0 ? 'simple' : 'compound',
            ]);
        }

        return $debts;
    }

    private function createRecurringTransactions(Household $household, User $user, array $accounts, array $debts): void
    {
        $patterns = [
            ['frequency' => 'monthly', 'description' => 'Affitto', 'amount' => 800],
            ['frequency' => 'monthly', 'description' => 'Stipendio', 'amount' => 2500],
            ['frequency' => 'weekly', 'description' => 'Spesa Supermercato', 'amount' => 80],
            ['frequency' => 'monthly', 'description' => 'Bolletta Luce', 'amount' => 120],
            ['frequency' => 'monthly', 'description' => 'Abbonamento Palestra', 'amount' => 50],
        ];

        foreach ($patterns as $pattern) {
            $isIncome = $pattern['amount'] > 2000; // Stipendio è income
            $categoryId = $isIncome
                ? $this->incomeCategories[array_rand($this->incomeCategories)]
                : $this->expenseCategories[array_rand($this->expenseCategories)];

            // Aggiungi pagamento debito se disponibile e non è income
            $debtCreditId = null;
            if (! $isIncome && ! empty($debts) && rand(0, 3) === 0) {
                $debt = $debts[array_rand($debts)];
                if ($debt->status !== 'closed') {
                    $debtCreditId = $debt->id;
                }
            }

            RecurringTransaction::create([
                'user_id' => $user->id,
                'account_id' => $accounts[array_rand($accounts)]->id,
                'category_id' => $categoryId,
                'debt_credit_id' => $debtCreditId,
                'amount' => $pattern['amount'],
                'currency_code' => $this->eur->code,
                'description' => $pattern['description'],
                'frequency' => $pattern['frequency'],
                'start_date' => Carbon::now()->subYear(),
                'end_date' => Carbon::now()->addYear(),
            ]);
        }
    }

    private function createTransactions(Household $household, User $user, array $accounts, int $count): void
    {
        $startDate = Carbon::create(2022, 1, 1);
        $endDate = Carbon::now();
        $totalDays = $startDate->diffInDays($endDate);

        $batchSize = 500;
        $iterations = ceil($count / $batchSize);

        $progressBar = $this->command->getOutput()->createProgressBar($iterations);
        $progressBar->start();

        for ($i = 0; $i < $iterations; $i++) {
            $transactions = [];
            $currentBatchSize = min($batchSize, $count - ($i * $batchSize));

            for ($j = 0; $j < $currentBatchSize; $j++) {
                $isIncome = rand(0, 3) === 0; // 25% entrate, 75% uscite
                $categoryId = $isIncome
                    ? $this->incomeCategories[array_rand($this->incomeCategories)]
                    : $this->expenseCategories[array_rand($this->expenseCategories)];

                $date = $startDate->copy()->addDays(rand(0, $totalDays));

                $transactions[] = [
                    'user_id' => $user->id,
                    'account_id' => $accounts[array_rand($accounts)]->id,
                    'category_id' => $categoryId,
                    'amount' => rand(500, 50000) / 100, // 5-500 EUR
                    'currency_code' => $this->eur->code,
                    'date' => $date,
                    'description' => $this->getRandomDescription($isIncome),
                    'is_private' => rand(0, 9) === 0, // 10% private
                    'created_at' => $date,
                    'updated_at' => $date,
                ];
            }

            Transaction::insert($transactions);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->newLine();
    }

    private function getRandomCounterparty(): string
    {
        $counterparties = [
            'Banca Principale',
            'Secondo Istituto',
            'Amico Giovanni',
            'Fornitore ABC Srl',
            'Cliente XYZ',
            'Prestito Famiglia',
            'Società Beta SpA',
        ];

        return $counterparties[array_rand($counterparties)];
    }

    private function getRandomDescription(bool $isIncome): string
    {
        if ($isIncome) {
            $descriptions = [
                'Stipendio',
                'Freelance',
                'Rimborso',
                'Vendita',
                'Bonus',
                'Regalo',
                'Entrata varia',
            ];
        } else {
            $descriptions = [
                'Spesa supermercato',
                'Ristorante',
                'Carburante',
                'Bolletta',
                'Shopping',
                'Farmacia',
                'Trasporti',
                'Abbonamento',
                'Manutenzione',
                'Regalo',
                'Spesa varia',
            ];
        }

        return $descriptions[array_rand($descriptions)];
    }
}
