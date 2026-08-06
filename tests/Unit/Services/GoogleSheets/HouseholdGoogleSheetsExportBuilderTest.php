<?php

namespace Tests\Unit\Services\GoogleSheets;

use App\Models\Account;
use App\Models\Category;
use App\Models\DebtCredit;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GoogleSheets\GoogleSheetsPushService;
use App\Services\GoogleSheets\HouseholdFinanceWorkbookBuilder;
use App\Services\GoogleSheets\HouseholdGoogleSheetsExportBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class HouseholdGoogleSheetsExportBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_workbook_uses_names_for_accounts_and_categories(): void
    {
        [$user, $household] = $this->seedHousehold();

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'name' => 'Conto Test',
            'currency_code' => 'EUR',
        ]);
        $category = Category::factory()->create([
            'household_id' => $household->id,
            'type' => 'expense',
            'name' => 'Spesa casa',
            'expense_distribution' => Category::DISTRIBUTION_NEEDS,
        ]);
        $tag = Tag::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'name' => 'spesa',
            'color' => '#000000',
        ]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => -12.50,
            'currency_code' => 'EUR',
            'description' => 'Test export',
        ]);
        $transaction->tags()->attach($tag->id);

        $sheets = app(HouseholdFinanceWorkbookBuilder::class)->build($household, $user);

        $this->assertArrayHasKey('Dashboard', $sheets);
        $this->assertArrayNotHasKey('KPI', $sheets);
        $this->assertArrayHasKey('KPI Cards', $sheets);
        $this->assertSame(
            ['_id', 'Gruppo', 'Metrica', 'Valore', 'Ordine', 'Visibile'],
            $sheets['KPI Cards']['headers']
        );
        $this->assertCount(13, $sheets['KPI Cards']['rows']);
        $this->assertSame('Saldi', $sheets['KPI Cards']['rows'][0][1]);
        $this->assertTrue($sheets['KPI Cards']['rows'][0][5]);
        $this->assertStringContainsString('€ ', $sheets['KPI Cards']['formulas'][2][3]);
        $this->assertStringContainsString('FISSO(', $sheets['KPI Cards']['formulas'][2][3]);
        $this->assertStringContainsString('ARROTONDA(', $sheets['KPI Cards']['formulas'][2][3]);
        $this->assertStringContainsString('Conti!H:H', $sheets['KPI Cards']['formulas'][2][3]);
        $this->assertStringContainsString('0,0%', $sheets['KPI Cards']['formulas'][14][3]);
        $this->assertSame([5], $sheets['KPI Cards']['checkbox_columns']);
        $this->assertArrayHasKey('Conti', $sheets);
        $this->assertContains('Vincolato', $sheets['Conti']['headers']);
        $this->assertArrayHasKey('Transazioni', $sheets);
        $this->assertArrayHasKey('_Grafici', $sheets);
        $this->assertArrayNotHasKey('Ricorrenti', $sheets);
        $this->assertArrayNotHasKey('Exchange_Rates', $sheets);

        $this->assertNotContains('colore', $sheets['Categorie']['headers']);
        $this->assertNotContains('color', $sheets['Categorie']['headers']);

        $tx = $sheets['Transazioni']['rows'][0];
        $this->assertSame('Uscita', $tx[4]);
        $this->assertSame($account->id, $tx[5]); // _conto_id
        $this->assertNull($tx[6]); // Conto → label via CERCA.VERT
        $this->assertSame($category->id, $tx[7]); // _categoria_id
        $this->assertNull($tx[8]); // Categoria → label via CERCA.VERT
        $this->assertSame('', $tx[9]); // _debito_id
        $this->assertNull($tx[10]); // Controparte → label via CERCA.VERT
        $this->assertSame('', $tx[11]); // _asset_id
        $this->assertNull($tx[12]); // Asset → label via CERCA.VERT
        $this->assertStringContainsString('SPESA', (string) $tx[13]);
        $this->assertSame(
            [
                'Data', 'Descrizione', 'Importo', 'Valuta', 'Tipo',
                '_conto_id', 'Conto', '_categoria_id', 'Categoria',
                '_debito_id', 'Controparte', '_asset_id', 'Asset', 'Tag',
            ],
            $sheets['Transazioni']['headers']
        );
        $this->assertNotContains('id', $sheets['Transazioni']['headers']);
        $this->assertNotContains('privato', $sheets['Transazioni']['headers']);
        $this->assertNotContains('spesa_lifestyle', $sheets['Transazioni']['headers']);
        $this->assertStringContainsString(
            'CERCA.VERT(F2:F;Conti!A:B',
            $sheets['Transazioni']['formulas'][2][6]
        );

        $this->assertStringContainsString('Transazioni!F:F', $sheets['Conti']['formulas'][2][5]);

        $this->assertSame('_id', $sheets['Conti']['headers'][0]);
        $this->assertSame('_id', $sheets['Categorie']['headers'][0]);
        $this->assertSame(['_id', 'Nome'], $sheets['Tag']['headers']);
        $this->assertNotContains('income', array_column($sheets['Categorie']['rows'], 2));
        $this->assertNotContains('expense', array_column($sheets['Categorie']['rows'], 2));

        $categoryRow = collect($sheets['Categorie']['rows'])->first(fn (array $row) => $row[1] === 'Spesa casa');
        $this->assertNotNull($categoryRow);
        $this->assertSame($category->id, $categoryRow[0]);
        $this->assertSame('Uscita', $categoryRow[2]);
        $this->assertSame('Necessità', $categoryRow[5]);

        $dashboardJoined = collect($sheets['Dashboard']['rows'])->flatten()->implode(' ');
        $this->assertStringContainsString('Lifestyle Inflation Score', $dashboardJoined);
        $this->assertStringContainsString('_Grafici!L', $dashboardJoined);
        $this->assertStringContainsString('Portfolio!I:I', $dashboardJoined);
        $this->assertStringContainsString('Conti!G:G', $dashboardJoined);
        $this->assertStringContainsString('Liquidità disponibile', $dashboardJoined);
        $this->assertStringContainsString('Liquidità vincolata', $dashboardJoined);
        $this->assertTrue($sheets['Conti']['as_table'] ?? false);
        $this->assertTrue($sheets['Transazioni']['as_table'] ?? false);
        $this->assertSame('Categoria', $sheets['_Grafici']['rows'][0][5]);
        $this->assertSame('spesa_ls_helper', $sheets['_Grafici']['rows'][0][11]);
        $this->assertArrayHasKey(10, $sheets['_Grafici']['rows'][0]);
        $this->assertSame(range(0, 11), array_keys($sheets['_Grafici']['rows'][0]));
        $this->assertNotEmpty($sheets['_Grafici']['formulas'][2][6] ?? null);
        $this->assertMatchesRegularExpression('#^\d{1,2}/\d{1,2}/\d{4}$#', (string) $tx[0]);
    }

    public function test_portfolio_is_formula_driven_from_investimenti(): void
    {
        [$user, $household] = $this->seedHousehold();

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'name' => 'Broker',
            'currency_code' => 'EUR',
        ]);

        $asset = InvestmentAsset::create([
            'name' => 'VWCE',
            'symbol' => 'VWCE',
            'type' => 'etf',
            'currency_code' => 'EUR',
        ]);

        Investment::create([
            'user_id' => $user->id,
            'household_id' => $household->id,
            'account_id' => $account->id,
            'asset_id' => $asset->id,
            'quantity' => 10,
            'buy_price' => 100,
            'buy_date' => now()->subMonth()->toDateString(),
            'sell_date' => null,
            'fees' => 0,
        ]);

        $sheets = app(HouseholdFinanceWorkbookBuilder::class)->build($household, $user);

        $this->assertArrayHasKey('Investimenti', $sheets);
        $this->assertArrayHasKey('Portfolio', $sheets);
        $this->assertTrue($sheets['Portfolio']['as_table'] ?? false);
        $this->assertSame('_id', $sheets['Portfolio']['headers'][0]);
        $this->assertSame('Asset', $sheets['Portfolio']['headers'][1]);
        $this->assertContains('Symbol', $sheets['Portfolio']['headers']);
        $this->assertSame($asset->id, $sheets['Portfolio']['rows'][0][0]);
        $this->assertStringContainsString('Investimenti!$D$2:$D', $sheets['Portfolio']['formulas'][2][7]);
        $this->assertStringContainsString('aperto', $sheets['Portfolio']['formulas'][2][7]);
        $this->assertSame($asset->id, $sheets['Investimenti']['rows'][0][3]); // _asset_id
        $this->assertNull($sheets['Investimenti']['rows'][0][4]); // Asset → label via CERCA.VERT
        $this->assertSame(
            [
                'Data Acquisto', 'Data Vendita', 'Stato', '_asset_id', 'Asset',
                'Quantita', 'Prezzo Acquisto', 'Costo', 'Prezzo Vendita', 'Fee',
                '_conto_id', 'Conto', 'Movimenti Cassa', 'Note',
            ],
            $sheets['Investimenti']['headers']
        );
        $this->assertNotEmpty($sheets['Investimenti']['formulas'][2][2] ?? null); // Stato
        $this->assertStringContainsString('Portfolio!A:B', $sheets['Investimenti']['formulas'][2][4]); // Asset label
        $this->assertNotEmpty($sheets['Investimenti']['formulas'][2][7] ?? null); // Costo
        $this->assertStringContainsString('Conti!A:B', $sheets['Investimenti']['formulas'][2][11]); // Conto label
        $this->assertStringContainsString('Transazioni!$L$2:$L', $sheets['Investimenti']['formulas'][2][12]);
        $this->assertMatchesRegularExpression(
            '#^\d{1,2}/\d{1,2}/\d{4}$#',
            (string) $sheets['Investimenti']['rows'][0][0]
        );
    }

    public function test_debiti_pagato_sums_transactions_by_controparte(): void
    {
        [$user, $household] = $this->seedHousehold();

        $account = Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
            'name' => 'Conto',
            'currency_code' => 'EUR',
        ]);

        $debt = DebtCredit::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'counterparty' => 'Banca Test',
            'type' => 'debt',
            'amount' => 1000,
            'initial_amount' => 1000,
            'paid_amount' => 0,
            'currency_code' => 'EUR',
            'status' => 'open',
            'due_date' => now()->addMonth()->toDateString(),
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'debt_credit_id' => $debt->id,
            'amount' => -100,
            'currency_code' => 'EUR',
            'description' => 'Rata',
        ]);

        $sheets = app(HouseholdFinanceWorkbookBuilder::class)->build($household, $user);

        $this->assertArrayHasKey('Debiti', $sheets);
        $this->assertArrayHasKey('Transazioni', $sheets);
        $this->assertSame($debt->id, $sheets['Transazioni']['rows'][0][9]); // _debito_id
        $this->assertStringContainsString('Transazioni!$J$2:$J', $sheets['Debiti']['formulas'][2][4]);
        $this->assertStringContainsString('ASS', $sheets['Debiti']['formulas'][2][4]);
        $this->assertNull($sheets['Debiti']['rows'][0][4]);
        $this->assertStringContainsString('D2-E2', $sheets['Debiti']['formulas'][2][5]);
        $this->assertStringContainsString('chiuso', $sheets['Debiti']['formulas'][2][7]);
    }

    public function test_debiti_residuo_and_stato_are_formula_driven(): void
    {
        [$user, $household] = $this->seedHousehold();

        DebtCredit::create([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'counterparty' => 'Banca Test',
            'type' => 'debt',
            'amount' => 1000,
            'initial_amount' => 1000,
            'paid_amount' => 250,
            'currency_code' => 'EUR',
            'status' => 'open',
            'due_date' => now()->addMonth()->toDateString(),
        ]);

        $sheets = app(HouseholdFinanceWorkbookBuilder::class)->build($household, $user);

        $this->assertArrayHasKey('Debiti', $sheets);
        $this->assertNotEmpty($sheets['Debiti']['formulas'][2][4] ?? null);
        $this->assertNotEmpty($sheets['Debiti']['formulas'][2][5] ?? null);
        $this->assertNotEmpty($sheets['Debiti']['formulas'][2][7] ?? null);
        $this->assertStringContainsString('D2-E2', $sheets['Debiti']['formulas'][2][5]);
        $this->assertStringContainsString('chiuso', $sheets['Debiti']['formulas'][2][7]);
        $this->assertNull($sheets['Debiti']['rows'][0][4]);
        $this->assertNull($sheets['Debiti']['rows'][0][5]);
        $this->assertNull($sheets['Debiti']['rows'][0][7]);
    }

    public function test_workbook_skips_empty_optional_sheets(): void
    {
        [$user, $household] = $this->seedHousehold();

        Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        $sheets = app(HouseholdFinanceWorkbookBuilder::class)->build($household, $user);

        $this->assertArrayHasKey('Conti', $sheets);
        $this->assertArrayNotHasKey('Transazioni', $sheets);
        $this->assertArrayNotHasKey('Investimenti', $sheets);
        $this->assertArrayNotHasKey('Debiti', $sheets);
        $this->assertArrayNotHasKey('Budget', $sheets);
        $this->assertArrayHasKey('_Grafici', $sheets);
    }

    public function test_raw_builder_still_exports_db_style_tabs(): void
    {
        [$user, $household] = $this->seedHousehold();
        Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        $sheets = app(HouseholdGoogleSheetsExportBuilder::class)->build($household, $user, false, false);

        $this->assertArrayHasKey('Accounts', $sheets);
        $this->assertArrayHasKey('Transactions', $sheets);
        $this->assertArrayNotHasKey('Exchange_Rates', $sheets);
    }

    public function test_push_service_default_writes_workbook_csv(): void
    {
        [$user, $household] = $this->seedHousehold();

        Account::factory()->create([
            'household_id' => $household->id,
            'owner_user_id' => $user->id,
        ]);

        $dir = storage_path('framework/testing/google-sheets-export-'.uniqid());
        File::ensureDirectoryExists(dirname($dir));

        try {
            $result = app(GoogleSheetsPushService::class)->export(
                household: $household,
                user: $user,
                csvOutputDir: $dir,
            );

            $this->assertSame('workbook', $result['mode']);
            $this->assertSame($dir, $result['csvPath']);
            $this->assertFileExists($dir.'/Dashboard.csv');
            $this->assertFileExists($dir.'/Conti.csv');
            $this->assertFileDoesNotExist($dir.'/Transazioni.csv');
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_dry_run_does_not_write_files(): void
    {
        [$user, $household] = $this->seedHousehold();

        $result = app(GoogleSheetsPushService::class)->export(
            household: $household,
            user: $user,
            dryRun: true,
        );

        $this->assertSame('workbook', $result['mode']);
        $this->assertArrayHasKey('counts', $result);
        $this->assertArrayNotHasKey('csvPath', $result);
        $this->assertArrayNotHasKey('spreadsheetId', $result);
    }

    /**
     * @return array{0: User, 1: Household}
     */
    private function seedHousehold(): array
    {
        $user = User::factory()->create([
            'profile_completed' => true,
            'default_currency_code' => 'EUR',
        ]);

        $household = Household::create([
            'name' => 'Casa Test Export',
            'owner_user_id' => $user->id,
            'financial_management_type' => Household::FINANCIAL_MANAGEMENT_SHARED_WALLET,
        ]);

        $household->users()->attach($user->id, [
            'role' => 'owner',
            'permissions' => json_encode(['manage' => true, 'supervise' => true]),
        ]);

        $user->update(['active_household_id' => $household->id]);

        return [$user->fresh(), $household->fresh()];
    }
}
