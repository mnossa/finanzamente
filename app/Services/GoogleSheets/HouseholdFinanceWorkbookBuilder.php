<?php

namespace App\Services\GoogleSheets;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\DebtCredit;
use App\Models\FinancialGoal;
use App\Models\Household;
use App\Models\Investment;
use App\Models\InvestmentAsset;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Workbook finanza per Sheets/AppSheet: join/lookup/dropdown sui system key `_id`
 * (stabili anche se l'utente rinomina l'etichetta), etichette editabili in chiaro
 * calcolate via formula, KPI/grafici a formule, niente fogli vuoti.
 *
 * @phpstan-type SheetTable array{
 *   headers: list<string>,
 *   rows: list<list<mixed>>,
 *   formulas?: array<int, array<int, string>>,
 *   skip_header?: bool,
 *   chart_meta?: array<string, int>,
 *   as_table?: bool,
 *   table_columns?: list<array<string, mixed>>,
 *   table_buffer_rows?: int
 * }
 */
class HouseholdFinanceWorkbookBuilder
{
    public const MODE = 'workbook';

    /**
     * @return array<string, SheetTable>
     */
    public function build(Household $household, User $user): array
    {
        $accounts = Account::query()
            ->where('household_id', $household->id)
            ->orderBy('name')
            ->get();
        $accountIds = $accounts->pluck('id')->all();

        $categories = Category::query()
            ->where('household_id', $household->id)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $tags = Tag::query()
            ->where('household_id', $household->id)
            ->orderBy('name')
            ->get();

        $transactions = $accountIds === []
            ? collect()
            : Transaction::query()
                ->whereIn('account_id', $accountIds)
                ->with(['tags', 'category', 'account', 'debtCredit', 'investment.asset'])
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->get();

        $investments = Investment::query()
            ->where('household_id', $household->id)
            ->with('asset')
            ->orderByDesc('buy_date')
            ->get();

        $assetIds = $investments->pluck('asset_id')->filter()->unique()->all();
        $assets = InvestmentAsset::query()->whereIn('id', $assetIds)->get()->keyBy('id');

        $debts = DebtCredit::query()
            ->where('household_id', $household->id)
            ->orderBy('status')
            ->orderBy('due_date')
            ->get();

        $budgets = Budget::query()
            ->where('household_id', $household->id)
            ->orderByDesc('period_start')
            ->get();

        $goals = FinancialGoal::query()
            ->where('household_id', $household->id)
            ->orderBy('status')
            ->get();

        // Liste id (stringhe) per i dropdown ONE_OF_LIST: join stabile su `_id`, non sul nome.
        $accountIdList = $accounts->pluck('id')->map(fn ($id) => (string) $id)->all();
        $categoryIdList = $categories->pluck('id')->map(fn ($id) => (string) $id)->all();
        $debtIdList = $debts->pluck('id')->map(fn ($id) => (string) $id)->all();
        $assetIdList = $assets->keys()->map(fn ($id) => (string) $id)->all();

        $sheets = [];
        $sheets['Dashboard'] = $this->dashboardSheet($household, $user);
        // 1 riga = 1 card AppSheet (formule live; niente foglio KPI intermedio)
        $sheets['KPI Cards'] = $this->kpiCardsSheet();

        if ($accounts->isNotEmpty()) {
            $contoRows = $this->accountRows($accounts);
            $sheets['Conti'] = [
                'headers' => ['_id', 'Nome', 'Tipo', 'Valuta', 'Saldo Iniziale', 'Saldo', 'Attivo', 'Vincolato', 'Privato'],
                'rows' => $contoRows['rows'],
                'formulas' => $contoRows['formulas'],
                'as_table' => true,
                'table_buffer_rows' => 20,
                'table_columns' => [
                    ['columnIndex' => 0, 'columnName' => '_id', 'columnType' => 'NUMBER'],
                    ['columnIndex' => 1, 'columnName' => 'Nome', 'columnType' => 'TEXT'],
                    ['columnIndex' => 2, 'columnName' => 'Tipo', 'columnType' => 'TEXT'],
                    ['columnIndex' => 3, 'columnName' => 'Valuta', 'columnType' => 'TEXT'],
                    ['columnIndex' => 4, 'columnName' => 'Saldo Iniziale', 'columnType' => 'CURRENCY'],
                    ['columnIndex' => 5, 'columnName' => 'Saldo', 'columnType' => 'CURRENCY'],
                    ['columnIndex' => 6, 'columnName' => 'Attivo', 'columnType' => 'BOOLEAN'],
                    ['columnIndex' => 7, 'columnName' => 'Vincolato', 'columnType' => 'BOOLEAN'],
                    ['columnIndex' => 8, 'columnName' => 'Privato', 'columnType' => 'BOOLEAN'],
                ],
                'checkbox_columns' => [6, 7, 8],
            ];
        }

        if ($categories->isNotEmpty()) {
            $distribuzioneValues = ['Necessità', 'Extra', 'Investimenti'];
            $tipoCategoriaValues = ['Entrata', 'Uscita'];
            $sheets['Categorie'] = [
                'headers' => ['_id', 'Nome', 'Tipo', 'Escludi Lifestyle', 'Spesa Fissa', 'Distribuzione'],
                'rows' => $categories->map(fn (Category $c) => [
                    (int) $c->id,
                    $c->name,
                    $this->categoryTypeLabel($c->type),
                    (bool) $c->exclude_from_lifestyle_score,
                    (bool) $c->is_fixed_expense,
                    $this->expenseDistributionLabel($c->expense_distribution),
                ])->all(),
                'as_table' => true,
                'table_buffer_rows' => 20,
                'table_columns' => [
                    ['columnIndex' => 0, 'columnName' => '_id', 'columnType' => 'NUMBER'],
                    ['columnIndex' => 1, 'columnName' => 'Nome', 'columnType' => 'TEXT'],
                    [
                        'columnIndex' => 2,
                        'columnName' => 'Tipo',
                        'columnType' => 'DROPDOWN',
                        'dataValidationRule' => [
                            'condition' => [
                                'type' => 'ONE_OF_LIST',
                                'values' => array_map(fn ($v) => ['userEnteredValue' => $v], $tipoCategoriaValues),
                            ],
                        ],
                    ],
                    ['columnIndex' => 3, 'columnName' => 'Escludi Lifestyle', 'columnType' => 'BOOLEAN'],
                    ['columnIndex' => 4, 'columnName' => 'Spesa Fissa', 'columnType' => 'BOOLEAN'],
                    [
                        'columnIndex' => 5,
                        'columnName' => 'Distribuzione',
                        'columnType' => 'DROPDOWN',
                        'dataValidationRule' => [
                            'condition' => [
                                'type' => 'ONE_OF_LIST',
                                'values' => array_map(fn ($v) => ['userEnteredValue' => $v], $distribuzioneValues),
                            ],
                        ],
                    ],
                ],
                'checkbox_columns' => [3, 4],
            ];
        }

        if ($tags->isNotEmpty()) {
            $sheets['Tag'] = [
                'headers' => ['_id', 'Nome'],
                'rows' => $tags->map(fn (Tag $t) => [(int) $t->id, $t->name])->all(),
                'as_table' => true,
                'table_buffer_rows' => 20,
                'table_columns' => [
                    ['columnIndex' => 0, 'columnName' => '_id', 'columnType' => 'NUMBER'],
                    ['columnIndex' => 1, 'columnName' => 'Nome', 'columnType' => 'TEXT'],
                ],
            ];
        }

        // Registri prima dei movimenti (dropdown Transazioni)
        if ($debts->isNotEmpty()) {
            $sheets['Debiti'] = $this->debtRows($debts);
        }

        if ($investments->isNotEmpty()) {
            // Portfolio = master asset (join su _id); Investimenti riferisce _asset_id/_conto_id
            $sheets['Portfolio'] = $this->portfolioRows($investments, $assets, $accountIdList);
            $sheets['Investimenti'] = $this->investmentRows($investments, $assets, $accountIdList, $assetIdList);
        }

        if ($transactions->isNotEmpty()) {
            $txBuilt = $this->transactionRows($transactions);
            $sheets['Transazioni'] = [
                'headers' => [
                    'Data', 'Descrizione', 'Importo', 'Valuta', 'Tipo',
                    '_conto_id', 'Conto', '_categoria_id', 'Categoria',
                    '_debito_id', 'Controparte', '_asset_id', 'Asset', 'Tag',
                ],
                'rows' => $txBuilt['rows'],
                'formulas' => $this->transactionLabelFormulas(),
                'as_table' => true,
                'table_buffer_rows' => 200,
                'table_columns' => $this->transactionTableColumns(
                    $accountIdList,
                    $categoryIdList,
                    $debtIdList,
                    $assetIdList,
                ),
            ];
        }

        if ($budgets->isNotEmpty()) {
            $sheets['Budget'] = $this->budgetRows($budgets, $categoryIdList);
        }

        if ($goals->isNotEmpty()) {
            $sheets['Obiettivi'] = $this->goalRows($goals);
        }

        // Grafici dinamici: solo se ci sono TX o Conti
        if ($transactions->isNotEmpty() || $accounts->isNotEmpty()) {
            $sheets['_Grafici'] = $this->dynamicChartSheet($accounts, $categories);
        }

        return $sheets;
    }

    /**
     * @return SheetTable
     */
    private function dashboardSheet(Household $household, User $user): array
    {
        // Layout fisso: brand → KPI griglia → lifestyle → note (grafici overlay API)
        $rows = [
            ['FINANZAMENTE', $household->name, '', '', '=TESTO(OGGI();"dd/mm/yyyy HH:mm")'],
            [$user->email, 'Valuta: '.($user->default_currency_code ?: 'EUR'), '', '', ''],
            [],
            ['RIEPILOGO', 'Valore', '', 'MESE CORRENTE', 'Valore'],
            ['Liquidità disponibile', '=SE.ERRORE(SOMMA.PIÙ.SE(Conti!F:F;Conti!G:G;VERO;Conti!H:H;FALSO);0)', '', 'Entrate', '=SE.ERRORE(SOMMA.PIÙ.SE(Transazioni!C:C;Transazioni!A:A;">="&FINE.MESE(OGGI();-1)+1;Transazioni!A:A;"<="&FINE.MESE(OGGI();0);Transazioni!E:E;"Entrata");0)'],
            ['Liquidità vincolata', '=SE.ERRORE(SOMMA.PIÙ.SE(Conti!F:F;Conti!G:G;VERO;Conti!H:H;VERO);0)', '', 'Uscite', '=SE.ERRORE(SOMMA.PIÙ.SE(Transazioni!C:C;Transazioni!A:A;">="&FINE.MESE(OGGI();-1)+1;Transazioni!A:A;"<="&FINE.MESE(OGGI();0);Transazioni!E:E;"Uscita");0)'],
            ['Portfolio (costo aperto)', '=SE.ERRORE(SOMMA.SE(Portfolio!K:K;"aperto";Portfolio!I:I);0)', '', 'Netto', '=E5+E6'],
            ['Patrimonio', '=B5+B6+B7'],
            [],
            ['LIFESTYLE (lifetime)', 'Valore'],
            ['Reddito', '=SE.ERRORE(SOMMA.SE(Transazioni!E:E;"Entrata";Transazioni!C:C);0)'],
            ['Spese effettive', '=SE.ERRORE(-SOMMA(_Grafici!L2:L);0)'],
            ['Lifestyle Inflation Score %', '=SE(B11<=0;"";ARROTONDA((B11-B12)/B11;3))'],
            [],
            ['GUIDA', 'AppSheet home = KPI Cards (Deck, filtro Visibile) · Prefisso _ = chiavi sistema'],
        ];

        return [
            'headers' => [],
            'rows' => $rows,
            'skip_header' => true,
        ];
    }

    /**
     * Metriche in forma lunga (1 riga = 1 card) per AppSheet Deck/Card.
     * Valore = testo "€ …" o "…%" (niente currency Sheets → evita $ in AppSheet).
     * Visibile = checkbox per Slice/filtro in app.
     *
     * @return SheetTable
     */
    private function kpiCardsSheet(): array
    {
        $liqDisp = 'SE.ERRORE(SOMMA.PIÙ.SE(Conti!F:F;Conti!G:G;VERO;Conti!H:H;FALSO);0)';
        $liqVinc = 'SE.ERRORE(SOMMA.PIÙ.SE(Conti!F:F;Conti!G:G;VERO;Conti!H:H;VERO);0)';
        $invAperti = 'SE.ERRORE(SOMMA.SE(Portfolio!K:K;"aperto";Portfolio!I:I);0)';
        $patrimonio = '('.$liqDisp.')+('.$liqVinc.')+('.$invAperti.')';

        $ent30 = 'SE.ERRORE(SOMMA.PIÙ.SE(Transazioni!C:C;Transazioni!A:A;">="&OGGI()-29;Transazioni!A:A;"<="&OGGI();Transazioni!E:E;"Entrata");0)';
        $usc30 = 'SE.ERRORE(-SOMMA.PIÙ.SE(Transazioni!C:C;Transazioni!A:A;">="&OGGI()-29;Transazioni!A:A;"<="&OGGI();Transazioni!E:E;"Uscita");0)';
        $net30 = '('.$ent30.')-('.$usc30.')';

        $entPrec = 'SE.ERRORE(SOMMA.PIÙ.SE(Transazioni!C:C;Transazioni!A:A;">="&OGGI()-59;Transazioni!A:A;"<="&OGGI()-30;Transazioni!E:E;"Entrata");0)';
        $uscPrec = 'SE.ERRORE(-SOMMA.PIÙ.SE(Transazioni!C:C;Transazioni!A:A;">="&OGGI()-59;Transazioni!A:A;"<="&OGGI()-30;Transazioni!E:E;"Uscita");0)';
        $netPrec = '('.$entPrec.')-('.$uscPrec.')';

        $deltaEnt = 'SE(('.$entPrec.')=0;"";ARROTONDA((('.$ent30.')-('.$entPrec.'))/ASS('.$entPrec.');3))';
        $deltaUsc = 'SE(('.$uscPrec.')=0;"";ARROTONDA((('.$usc30.')-('.$uscPrec.'))/ASS('.$uscPrec.');3))';

        $reddito = 'SE.ERRORE(SOMMA.SE(Transazioni!E:E;"Entrata";Transazioni!C:C);0)';
        $speseLs = 'SE.ERRORE(-SOMMA(_Grafici!L2:L);0)';
        $lifestyle = 'SE(('.$reddito.')<=0;"";ARROTONDA((('.$reddito.')-('.$speseLs.'))/('.$reddito.');3))';

        // [id, gruppo, metrica, formula valore, ordine, visibile]
        $cards = [
            [1, 'Saldi', 'Liquidità disponibile', $this->euroTextFormula($liqDisp), 10, true],
            [2, 'Saldi', 'Liquidità vincolata', $this->euroTextFormula($liqVinc), 20, true],
            [3, 'Saldi', 'Investimenti aperti', $this->euroTextFormula($invAperti), 30, true],
            [4, 'Saldi', 'Patrimonio', $this->euroTextFormula($patrimonio), 40, true],
            [5, 'Ultimi 30 giorni', 'Entrate', $this->euroTextFormula($ent30), 50, true],
            [6, 'Ultimi 30 giorni', 'Uscite', $this->euroTextFormula($usc30), 60, true],
            [7, 'Ultimi 30 giorni', 'Netto', $this->euroTextFormula($net30), 70, true],
            [8, 'Periodo precedente', 'Entrate prec.', $this->euroTextFormula($entPrec), 80, true],
            [9, 'Periodo precedente', 'Uscite prec.', $this->euroTextFormula($uscPrec), 90, true],
            [10, 'Periodo precedente', 'Netto prec.', $this->euroTextFormula($netPrec), 100, true],
            [11, 'Confronto', 'Δ Entrate', $this->pctTextFormula($deltaEnt), 110, true],
            [12, 'Confronto', 'Δ Uscite', $this->pctTextFormula($deltaUsc), 120, true],
            [13, 'Lifestyle', 'Lifestyle score', $this->pctTextFormula($lifestyle), 130, true],
        ];

        $rows = [];
        $formulas = [];
        foreach ($cards as $i => $card) {
            $sheetRow = $i + 2;
            $rows[] = [
                $card[0],
                $card[1],
                $card[2],
                null,
                $card[4],
                $card[5],
            ];
            $formulas[$sheetRow][3] = $card[3];
        }

        return [
            'headers' => ['_id', 'Gruppo', 'Metrica', 'Valore', 'Ordine', 'Visibile'],
            'rows' => $rows,
            'formulas' => $formulas,
            'as_table' => true,
            'table_buffer_rows' => 0,
            'table_columns' => [
                ['columnIndex' => 0, 'columnName' => '_id', 'columnType' => 'NUMBER'],
                ['columnIndex' => 4, 'columnName' => 'Ordine', 'columnType' => 'NUMBER'],
                ['columnIndex' => 5, 'columnName' => 'Visibile', 'columnType' => 'BOOLEAN'],
            ],
            'checkbox_columns' => [5],
        ];
    }

    /** Testo € forzato (esattamente 2 decimali via FISSO). */
    private function euroTextFormula(string $expr): string
    {
        // FISSO(...,2;FALSO) = migliaia + sempre 2 decimali (evita ,5000 del TESTO)
        return '=SE.ERRORE("€ "&FISSO(ARROTONDA('.$expr.';2);2;FALSO);"—")';
    }

    /** Percentuale come testo (ratio 0–1 o stringa vuota), 1 decimale. */
    private function pctTextFormula(string $expr): string
    {
        return '=SE.ERRORE(SE(('.$expr.')="";"—";TESTO(ARROTONDA('.$expr.';3);"0,0%"));"—")';
    }

    private function accountRows(Collection $accounts): array
    {
        $rows = [];
        $formulas = [];
        $rowIndex = 2;

        foreach ($accounts as $account) {
            $typeLabel = Account::uiTypes()[$account->type] ?? $account->type;
            if ($account->isSavingsDeposit()) {
                $typeLabel = 'Conto Deposito';
            }

            $rows[] = [
                (int) $account->id,
                $account->name,
                $typeLabel,
                $account->currency_code,
                (float) $account->initial_balance,
                null, // saldo via formula
                $account->active ? true : false,
                $account->isLockedBalance(),
                $account->is_private ? true : false,
            ];

            // Match su _id (A) contro Transazioni!F (_conto_id); importo = C; saldo_iniziale = E → saldo = F
            $formulas[$rowIndex][5] = sprintf(
                '=E%d+SE.ERRORE(SOMMA.SE(Transazioni!F:F;A%d;Transazioni!C:C);0)',
                $rowIndex,
                $rowIndex
            );
            $rowIndex++;
        }

        return ['rows' => $rows, 'formulas' => $formulas];
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return array{rows: list<list<mixed>>}
     */
    private function transactionRows(Collection $transactions): array
    {
        $rows = [];

        foreach ($transactions as $tx) {
            $assetId = $tx->investment?->asset_id;
            $rows[] = [
                $this->formatItalianDate($tx->date),
                $tx->description,
                (float) $tx->amount,
                $tx->currency_code,
                $this->transactionType($tx),
                (int) $tx->account_id,
                null, // Conto → ARRAYFORMULA riga 2
                $tx->category_id !== null ? (int) $tx->category_id : '',
                null, // Categoria → ARRAYFORMULA riga 2
                $tx->debt_credit_id !== null ? (int) $tx->debt_credit_id : '',
                null, // Controparte → ARRAYFORMULA riga 2
                $assetId !== null ? (int) $assetId : '',
                null, // Asset → ARRAYFORMULA riga 2
                $tx->tags->pluck('name')->sort()->implode(', '),
            ];
        }

        return ['rows' => $rows];
    }

    /**
     * Etichette calcolate (join su `_id`) valide su tutta la colonna, scritte solo in riga 2.
     *
     * @return array<int, array<int, string>>
     */
    private function transactionLabelFormulas(): array
    {
        return [
            2 => [
                6 => '=ARRAYFORMULA(SE(F2:F="";"";SE.ERRORE(CERCA.VERT(F2:F;Conti!A:B;2;FALSO);"")))',
                8 => '=ARRAYFORMULA(SE(H2:H="";"";SE.ERRORE(CERCA.VERT(H2:H;Categorie!A:B;2;FALSO);"")))',
                10 => '=ARRAYFORMULA(SE(J2:J="";"";SE.ERRORE(CERCA.VERT(J2:J;Debiti!A:C;3;FALSO);"")))',
                12 => '=ARRAYFORMULA(SE(L2:L="";"";SE.ERRORE(CERCA.VERT(L2:L;Portfolio!A:B;2;FALSO);"")))',
            ],
        ];
    }

    private function transactionType(Transaction $tx): string
    {
        if ($tx->transfer_id !== null || $tx->inter_household_transfer_id !== null) {
            return 'Trasferimento';
        }
        if ($tx->investment_id !== null) {
            return match ($tx->investment_event) {
                'purchase' => 'Investimento acquisto',
                'sale' => 'Investimento vendita',
                'coupon' => 'Cedola/dividendo',
                default => 'Investimento',
            };
        }
        if ($tx->refund_id !== null) {
            return 'Rimborso';
        }
        if ((float) $tx->amount >= 0) {
            return 'Entrata';
        }

        return 'Uscita';
    }

    /**
     * Colonne Transazioni: dropdown sulle chiavi di sistema `_conto_id`/`_categoria_id`/
     * `_debito_id`/`_asset_id` (liste id), le etichette (Conto/Categoria/Controparte/Asset)
     * restano TEXT perché calcolate via ARRAYFORMULA.
     *
     * @param  list<string>  $accountIds
     * @param  list<string>  $categoryIds
     * @param  list<string>  $debtIds
     * @param  list<string>  $assetIds
     * @return list<array<string, mixed>>
     */
    private function transactionTableColumns(
        array $accountIds,
        array $categoryIds,
        array $debtIds = [],
        array $assetIds = [],
    ): array {
        $tipoValues = [
            'Entrata', 'Uscita', 'Trasferimento', 'Rimborso',
            'Investimento', 'Investimento acquisto', 'Investimento vendita', 'Cedola/dividendo',
        ];

        $cols = [
            ['columnIndex' => 0, 'columnName' => 'Data', 'columnType' => 'DATE'],
            ['columnIndex' => 1, 'columnName' => 'Descrizione', 'columnType' => 'TEXT'],
            ['columnIndex' => 2, 'columnName' => 'Importo', 'columnType' => 'CURRENCY'],
            ['columnIndex' => 3, 'columnName' => 'Valuta', 'columnType' => 'TEXT'],
            [
                'columnIndex' => 4,
                'columnName' => 'Tipo',
                'columnType' => 'DROPDOWN',
                'dataValidationRule' => [
                    'condition' => [
                        'type' => 'ONE_OF_LIST',
                        'values' => array_map(fn ($v) => ['userEnteredValue' => $v], $tipoValues),
                    ],
                ],
            ],
            [
                'columnIndex' => 5,
                'columnName' => '_conto_id',
                'columnType' => 'DROPDOWN',
                'dataValidationRule' => [
                    'condition' => [
                        'type' => 'ONE_OF_LIST',
                        'values' => array_map(fn ($v) => ['userEnteredValue' => $v], $accountIds),
                    ],
                ],
            ],
            ['columnIndex' => 6, 'columnName' => 'Conto', 'columnType' => 'TEXT'],
            [
                'columnIndex' => 7,
                'columnName' => '_categoria_id',
                'columnType' => 'DROPDOWN',
                'dataValidationRule' => [
                    'condition' => [
                        'type' => 'ONE_OF_LIST',
                        'values' => array_map(fn ($v) => ['userEnteredValue' => $v], $categoryIds),
                    ],
                ],
            ],
            ['columnIndex' => 8, 'columnName' => 'Categoria', 'columnType' => 'TEXT'],
        ];

        if ($debtIds !== []) {
            $cols[] = [
                'columnIndex' => 9,
                'columnName' => '_debito_id',
                'columnType' => 'DROPDOWN',
                'dataValidationRule' => [
                    'condition' => [
                        'type' => 'ONE_OF_LIST',
                        'values' => array_map(fn ($v) => ['userEnteredValue' => $v], $debtIds),
                    ],
                ],
            ];
        } else {
            $cols[] = ['columnIndex' => 9, 'columnName' => '_debito_id', 'columnType' => 'TEXT'];
        }

        $cols[] = ['columnIndex' => 10, 'columnName' => 'Controparte', 'columnType' => 'TEXT'];

        if ($assetIds !== []) {
            $cols[] = [
                'columnIndex' => 11,
                'columnName' => '_asset_id',
                'columnType' => 'DROPDOWN',
                'dataValidationRule' => [
                    'condition' => [
                        'type' => 'ONE_OF_LIST',
                        'values' => array_map(fn ($v) => ['userEnteredValue' => $v], $assetIds),
                    ],
                ],
            ];
        } else {
            $cols[] = ['columnIndex' => 11, 'columnName' => '_asset_id', 'columnType' => 'TEXT'];
        }

        $cols[] = ['columnIndex' => 12, 'columnName' => 'Asset', 'columnType' => 'TEXT'];
        $cols[] = ['columnIndex' => 13, 'columnName' => 'Tag', 'columnType' => 'TEXT'];

        return $cols;
    }

    private function categoryTypeLabel(?string $type): string
    {
        return match ($type) {
            'income' => 'Entrata',
            'expense' => 'Uscita',
            default => (string) $type,
        };
    }

    private function expenseDistributionLabel(?string $value): string
    {
        return match ($value) {
            Category::DISTRIBUTION_NEEDS => 'Necessità',
            Category::DISTRIBUTION_WANTS => 'Extra',
            Category::DISTRIBUTION_INVESTMENTS => 'Investimenti',
            default => '',
        };
    }

    private function formatItalianDate(mixed $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format('d/m/Y'); // DD/MM/YYYY
        }

        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    /**
     * Chart data: cashflow (formule mese) + spese/categoria (join su _categoria_id) + saldi conti.
     *
     * @param  Collection<int, Account>  $accounts
     * @param  Collection<int, Category>  $categories
     * @return SheetTable
     */
    private function dynamicChartSheet(Collection $accounts, Collection $categories): array
    {
        $expenseCategories = $categories
            ->filter(fn (Category $c) => $c->type === 'expense')
            ->values();

        $maxExtra = max($expenseCategories->count(), $accounts->count(), 12);
        $rows = [];
        $formulas = [];

        // Header row
        $rows[] = ['Mese', 'Entrate', 'Uscite', 'Netto', '', 'Categoria', 'Totale', '', 'Conto', 'Saldo'];

        for ($offset = 0; $offset < 12; $offset++) {
            $monthOffset = -11 + $offset;
            $r = $offset + 2;
            $start = sprintf('FINE.MESE(OGGI();%d)+1', $monthOffset - 1);
            $end = sprintf('FINE.MESE(OGGI();%d)', $monthOffset);

            $row = array_fill(0, 10, '');
            $formulas[$r] = [
                0 => sprintf('=TESTO(FINE.MESE(OGGI();%d);"mmm yyyy")', $monthOffset),
                1 => sprintf(
                    '=SE.ERRORE(SOMMA.PIÙ.SE(Transazioni!C:C;Transazioni!A:A;">="&%s;Transazioni!A:A;"<="&%s;Transazioni!E:E;"Entrata");0)',
                    $start,
                    $end
                ),
                2 => sprintf(
                    '=SE.ERRORE(-SOMMA.PIÙ.SE(Transazioni!C:C;Transazioni!A:A;">="&%s;Transazioni!A:A;"<="&%s;Transazioni!E:E;"Uscita");0)',
                    $start,
                    $end
                ),
                3 => sprintf('=B%d-C%d', $r, $r),
            ];
            $rows[] = $row;
        }

        // Pad rows so category/account blocks fit
        while (count($rows) < $maxExtra + 1) {
            $rows[] = array_fill(0, 10, '');
        }

        // Category totals (F/G): nome categoria (label) + SOMMA.PIÙ.SE per _categoria_id (join su id)
        $catRow = 2;
        foreach ($expenseCategories as $category) {
            while (count($rows) < $catRow) {
                $rows[] = array_fill(0, 10, '');
            }
            $rows[$catRow - 1][5] = $category->name;
            $formulas[$catRow][6] = sprintf(
                '=SE.ERRORE(-SOMMA.PIÙ.SE(Transazioni!C:C;Transazioni!H:H;%d;Transazioni!E:E;"Uscita";Transazioni!A:A;">="&DATA(ANNO(OGGI());1;1);Transazioni!A:A;"<"&DATA(ANNO(OGGI())+1;1;1));0)',
                (int) $category->id
            );
            $catRow++;
        }
        if ($expenseCategories->isEmpty()) {
            $rows[1][5] = '(nessuna categoria)';
            $formulas[2][6] = '=0';
        }

        // Account balances (I/J): nome + saldo Conti (checkbox attivo)
        $accRow = 2;
        $contoIndex = 2; // Conti data starts at sheet row 2
        foreach ($accounts as $account) {
            while (count($rows) < $accRow) {
                $rows[] = array_fill(0, 10, '');
            }
            $formulas[$accRow][8] = sprintf('=Conti!B%d', $contoIndex);
            $formulas[$accRow][9] = sprintf('=SE(Conti!G%d=VERO;Conti!F%d;0)', $contoIndex, $contoIndex);
            $accRow++;
            $contoIndex++;
        }
        if ($accounts->isEmpty()) {
            $rows[1][8] = '(nessun conto)';
            $formulas[2][9] = '=0';
        }

        // Ensure header labels for F/G and I/J stay visible
        $rows[0][5] = 'Categoria';
        $rows[0][6] = 'Totale';
        $rows[0][8] = 'Conto';
        $rows[0][9] = 'Saldo';
        $rows[0][10] = '';
        $rows[0][11] = 'spesa_ls_helper';

        // Helper lifestyle (solo _Grafici, non in Transazioni): uscita × non esclusa (join _categoria_id)
        $formulas[2][11] = '=ARRAYFORMULA(SE(Transazioni!E2:E="";"";SE(Transazioni!E2:E<>"Uscita";0;SE(SE.ERRORE(CERCA.VERT(Transazioni!H2:H;Categorie!A:D;4;FALSO);FALSO)=VERO;0;Transazioni!C2:C))))';

        return [
            'headers' => [],
            'rows' => array_map(static function (array $row): array {
                $dense = [];
                for ($i = 0; $i < 12; $i++) {
                    $dense[] = $row[$i] ?? '';
                }

                return $dense;
            }, $rows),
            'formulas' => $formulas,
            'skip_header' => true,
            'chart_meta' => [
                'cashflow_rows' => 13,
                'category_rows' => max($expenseCategories->count() + 1, 2),
                'account_rows' => max($accounts->count() + 1, 2),
            ],
        ];
    }

    /**
     * @param  Collection<int, Investment>  $investments
     * @param  Collection<int, InvestmentAsset>  $assets
     * @param  list<string>  $accountIdList
     * @param  list<string>  $assetIdList
     * @return SheetTable
     */
    private function investmentRows(Collection $investments, Collection $assets, array $accountIdList, array $assetIdList): array
    {
        $rows = [];
        $formulas = [];
        $rowIndex = 2;
        $buffer = 20;

        $setRowFormulas = function (int $r) use (&$formulas): void {
            // A Data Acquisto B Data Vendita C Stato D _asset_id E Asset F Qty G Prezzo
            // H Costo I Prezzo Vendita J Fee K _conto_id L Conto M Movimenti Cassa N Note
            $formulas[$r][2] = sprintf('=SE(A%d="";"";SE(B%d="";"aperto";"chiuso"))', $r, $r);
            $formulas[$r][4] = sprintf('=SE(D%d="";"";SE.ERRORE(CERCA.VERT(D%d;Portfolio!A:B;2;FALSO);""))', $r, $r);
            $formulas[$r][7] = sprintf('=SE(A%d="";"";ARROTONDA(SE.ERRORE(F%d*G%d;0);2))', $r, $r, $r);
            $formulas[$r][11] = sprintf('=SE(K%d="";"";SE.ERRORE(CERCA.VERT(K%d;Conti!A:B;2;FALSO);""))', $r, $r);
            $formulas[$r][12] = sprintf(
                '=SE(D%d="";"";SE.ERRORE(SOMMA.SE(Transazioni!$L$2:$L;D%d;Transazioni!$C$2:$C);0))',
                $r,
                $r
            );
        };

        // Solo dati lotto: metadati asset restano in Portfolio (join su _asset_id/_conto_id)
        foreach ($investments as $inv) {
            $rows[] = [
                $this->formatItalianDate($inv->buy_date),
                $this->formatItalianDate($inv->sell_date),
                null, // Stato
                (int) $inv->asset_id, // _asset_id
                null, // Asset → CERCA.VERT su Portfolio
                (float) $inv->quantity,
                (float) $inv->buy_price,
                null, // Costo
                $inv->sell_price !== null ? (float) $inv->sell_price : null,
                (float) ($inv->fees ?? 0),
                (int) $inv->account_id, // _conto_id
                null, // Conto → CERCA.VERT su Conti
                null, // Movimenti Cassa
                $inv->notes,
            ];
            $setRowFormulas($rowIndex);
            $rowIndex++;
        }

        for ($i = 0; $i < $buffer; $i++) {
            $rows[] = array_fill(0, 14, '');
            $setRowFormulas($rowIndex);
            $rowIndex++;
        }

        return [
            'headers' => [
                'Data Acquisto', 'Data Vendita', 'Stato', '_asset_id', 'Asset',
                'Quantita', 'Prezzo Acquisto', 'Costo', 'Prezzo Vendita', 'Fee',
                '_conto_id', 'Conto', 'Movimenti Cassa', 'Note',
            ],
            'rows' => $rows,
            'formulas' => $formulas,
            'as_table' => true,
            'table_buffer_rows' => 0,
            'table_columns' => [
                ['columnIndex' => 0, 'columnName' => 'Data Acquisto', 'columnType' => 'DATE'],
                ['columnIndex' => 1, 'columnName' => 'Data Vendita', 'columnType' => 'DATE'],
                [
                    'columnIndex' => 3,
                    'columnName' => '_asset_id',
                    'columnType' => 'DROPDOWN',
                    'dataValidationRule' => [
                        'condition' => [
                            'type' => 'ONE_OF_LIST',
                            'values' => array_map(fn ($v) => ['userEnteredValue' => $v], $assetIdList),
                        ],
                    ],
                ],
                [
                    'columnIndex' => 10,
                    'columnName' => '_conto_id',
                    'columnType' => 'DROPDOWN',
                    'dataValidationRule' => [
                        'condition' => [
                            'type' => 'ONE_OF_LIST',
                            'values' => array_map(fn ($v) => ['userEnteredValue' => $v], $accountIdList),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Portfolio = anagrafica asset (join su _id) + qty/cost live da Investimenti (join su _asset_id).
     *
     * @param  Collection<int, Investment>  $investments
     * @param  Collection<int, InvestmentAsset>  $assets
     * @param  list<string>  $accountIdList
     * @return SheetTable
     */
    private function portfolioRows(Collection $investments, Collection $assets, array $accountIdList): array
    {
        $open = $investments->filter(fn (Investment $i) => $i->sell_date === null);
        $byAsset = $open->groupBy(fn (Investment $i) => (string) ($i->asset_id ?? ''));
        $rows = [];
        $formulas = [];
        $rowIndex = 2;
        $buffer = 15;

        $setRowFormulas = function (int $r) use (&$formulas): void {
            // A _id B Asset C Symbol D Isin E Tipo F _conto_id G Conto H Qty I Costo J PrezzoMedio K Stato
            $formulas[$r][6] = sprintf('=SE(F%d="";"";SE.ERRORE(CERCA.VERT(F%d;Conti!A:B;2;FALSO);""))', $r, $r);
            $formulas[$r][7] = sprintf(
                '=SE(A%d="";"";SE.ERRORE(MATR.SOMMA.PRODOTTO((Investimenti!$D$2:$D=A%d)*(Investimenti!$C$2:$C="aperto")*(Investimenti!$F$2:$F));0))',
                $r,
                $r
            );
            $formulas[$r][8] = sprintf(
                '=SE(A%d="";"";SE.ERRORE(MATR.SOMMA.PRODOTTO((Investimenti!$D$2:$D=A%d)*(Investimenti!$C$2:$C="aperto")*(Investimenti!$H$2:$H));0))',
                $r,
                $r
            );
            $formulas[$r][9] = sprintf('=SE(O(A%d="";H%d=0);0;ARROTONDA(I%d/H%d;6))', $r, $r, $r, $r);
            $formulas[$r][10] = sprintf('=SE(A%d="";"";SE(H%d>0;"aperto";"chiuso"))', $r, $r);
        };

        foreach ($byAsset as $assetIdKey => $group) {
            if ($assetIdKey === '') {
                continue;
            }
            $first = $group->first();
            $asset = $assets->get($first->asset_id);
            $rows[] = [
                (int) $first->asset_id,
                $asset?->name,
                $asset?->symbol,
                $asset?->isin,
                $asset?->type_label ?? $asset?->type,
                (int) $first->account_id,
                null, // Conto → formula
                null, // Quantita → formula
                null, // Costo Totale → formula
                null, // Prezzo Medio → formula
                null, // Stato → formula
            ];
            $setRowFormulas($rowIndex);
            $rowIndex++;
        }

        for ($i = 0; $i < $buffer; $i++) {
            $rows[] = array_fill(0, 11, '');
            $setRowFormulas($rowIndex);
            $rowIndex++;
        }

        return [
            'headers' => [
                '_id', 'Asset', 'Symbol', 'Isin', 'Tipo', '_conto_id', 'Conto',
                'Quantita', 'Costo Totale', 'Prezzo Medio', 'Stato',
            ],
            'rows' => $rows,
            'formulas' => $formulas,
            'as_table' => true,
            'table_buffer_rows' => 0,
            'table_columns' => [
                ['columnIndex' => 0, 'columnName' => '_id', 'columnType' => 'NUMBER'],
                [
                    'columnIndex' => 5,
                    'columnName' => '_conto_id',
                    'columnType' => 'DROPDOWN',
                    'dataValidationRule' => [
                        'condition' => [
                            'type' => 'ONE_OF_LIST',
                            'values' => array_map(fn ($v) => ['userEnteredValue' => $v], $accountIdList),
                        ],
                    ],
                ],
            ],
        ];
    }

    private function debtRows(Collection $debts): array
    {
        $rows = [];
        $formulas = [];
        $rowIndex = 2;
        $buffer = 20;
        $tipoValues = ['Debito', 'Credito'];

        $setRowFormulas = function (int $r) use (&$formulas): void {
            // A _id B Tipo C Controparte D ImportoIniziale E Pagato F Residuo G Valuta
            // H Stato I DataInizio J Scadenza K InteressePct L Descrizione
            $formulas[$r][4] = sprintf(
                '=SE(A%d="";"";SE.ERRORE(MATR.SOMMA.PRODOTTO((Transazioni!$J$2:$J=A%d)*(ASS(Transazioni!$C$2:$C)));0))',
                $r,
                $r
            );
            $formulas[$r][5] = sprintf('=SE(A%d="";"";ARROTONDA(D%d-E%d;2))', $r, $r, $r);
            $formulas[$r][7] = sprintf(
                '=SE(A%d="";"";SE(F%d<=0,01;"chiuso";SE(E(J%d<>"";J%d<OGGI());"scaduto";"aperto")))',
                $r,
                $r,
                $r,
                $r
            );
        };

        foreach ($debts as $debt) {
            $initial = (float) ($debt->initial_amount ?? $debt->amount);
            $rows[] = [
                (int) $debt->id,
                $debt->type === 'debt' ? 'Debito' : 'Credito',
                $debt->counterparty,
                $initial,
                null, // pagato via SUMPRODUCT su Transazioni._debito_id
                null, // residuo via formula
                $debt->currency_code,
                null, // stato via formula
                $this->formatItalianDate($debt->start_date),
                $this->formatItalianDate($debt->due_date),
                $debt->interest_rate !== null ? (float) $debt->interest_rate : null,
                $debt->description,
            ];
            $setRowFormulas($rowIndex);
            $rowIndex++;
        }

        for ($i = 0; $i < $buffer; $i++) {
            $rows[] = array_fill(0, 12, '');
            $setRowFormulas($rowIndex);
            $rowIndex++;
        }

        return [
            'headers' => [
                '_id', 'Tipo', 'Controparte', 'Importo Iniziale', 'Pagato', 'Residuo',
                'Valuta', 'Stato', 'Data Inizio', 'Scadenza', 'Interesse Pct', 'Descrizione',
            ],
            'rows' => $rows,
            'formulas' => $formulas,
            'as_table' => true,
            'table_buffer_rows' => 0,
            'table_columns' => [
                ['columnIndex' => 0, 'columnName' => '_id', 'columnType' => 'NUMBER'],
                [
                    'columnIndex' => 1,
                    'columnName' => 'Tipo',
                    'columnType' => 'DROPDOWN',
                    'dataValidationRule' => [
                        'condition' => [
                            'type' => 'ONE_OF_LIST',
                            'values' => array_map(fn ($v) => ['userEnteredValue' => $v], $tipoValues),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  list<string>  $categoryIdList
     */
    private function budgetRows(Collection $budgets, array $categoryIdList): array
    {
        $rows = [];
        foreach ($budgets as $budget) {
            $rows[] = [
                $budget->category_id !== null ? (int) $budget->category_id : '',
                null, // Categoria → ARRAYFORMULA riga 2
                (float) $budget->amount,
                $budget->currency_code,
                $this->formatItalianDate($budget->period_start),
                $this->formatItalianDate($budget->period_end),
                $budget->description,
            ];
        }

        return [
            'headers' => [
                '_categoria_id', 'Categoria', 'Importo', 'Valuta', 'Inizio', 'Fine', 'Descrizione',
            ],
            'rows' => $rows,
            'formulas' => [
                2 => [
                    1 => '=ARRAYFORMULA(SE(A2:A="";"";SE.ERRORE(CERCA.VERT(A2:A;Categorie!A:B;2;FALSO);"")))',
                ],
            ],
            'as_table' => true,
            'table_columns' => [
                [
                    'columnIndex' => 0,
                    'columnName' => '_categoria_id',
                    'columnType' => 'DROPDOWN',
                    'dataValidationRule' => [
                        'condition' => [
                            'type' => 'ONE_OF_LIST',
                            'values' => array_map(fn ($v) => ['userEnteredValue' => $v], $categoryIdList),
                        ],
                    ],
                ],
                ['columnIndex' => 1, 'columnName' => 'Categoria', 'columnType' => 'TEXT'],
            ],
        ];
    }

    /**
     * @param  Collection<int, FinancialGoal>  $goals
     * @return SheetTable
     */
    private function goalRows(Collection $goals): array
    {
        $rows = [];
        $formulas = [];
        $rowIndex = 2;
        foreach ($goals as $goal) {
            $rows[] = [
                (int) $goal->id,
                $goal->name,
                (float) $goal->target_amount,
                (float) $goal->current_amount,
                null,
                $goal->currency_code,
                $this->formatItalianDate($goal->target_date),
                $goal->status,
                $goal->description,
            ];
            // A _id B Nome C Obiettivo D Attuale E Progresso Pct
            $formulas[$rowIndex][4] = sprintf('=SE(C%d=0;0;ARROTONDA(D%d/C%d;3))', $rowIndex, $rowIndex, $rowIndex);
            $rowIndex++;
        }

        return [
            'headers' => [
                '_id', 'Nome', 'Obiettivo', 'Attuale', 'Progresso Pct',
                'Valuta', 'Data Target', 'Stato', 'Descrizione',
            ],
            'rows' => $rows,
            'formulas' => $formulas,
            'as_table' => true,
            'table_columns' => [
                ['columnIndex' => 0, 'columnName' => '_id', 'columnType' => 'NUMBER'],
            ],
        ];
    }
}
