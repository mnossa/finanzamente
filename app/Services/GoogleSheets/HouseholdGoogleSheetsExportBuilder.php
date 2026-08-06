<?php

namespace App\Services\GoogleSheets;

use App\Models\Account;
use App\Models\Attachment;
use App\Models\BankImportLayout;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Consent;
use App\Models\ConsentEvent;
use App\Models\Currency;
use App\Models\DashboardLayout;
use App\Models\DebtCredit;
use App\Models\DebtCreditAdjustment;
use App\Models\ExchangeRate;
use App\Models\FinancialGoal;
use App\Models\FinancialVariable;
use App\Models\FormulaWidget;
use App\Models\Household;
use App\Models\InterHouseholdTransfer;
use App\Models\Investment;
use App\Models\InvestmentAnalysis;
use App\Models\InvestmentAsset;
use App\Models\InvestmentPac;
use App\Models\MealVoucherLot;
use App\Models\MealVoucherLotMovement;
use App\Models\MealVoucherUnitValue;
use App\Models\RecurringTransaction;
use App\Models\Refund;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\Transfer;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Builds a Google-Sheets-ready workbook (tab => headers + rows) for one household.
 *
 * @phpstan-type SheetTable array{headers: list<string>, rows: list<list<scalar|null>>}
 */
class HouseholdGoogleSheetsExportBuilder
{
    /**
     * @return array<string, SheetTable>
     */
    public function build(Household $household, User $user, bool $withTrashed = false, bool $includeExchangeRates = true): array
    {
        $accountQuery = $this->scoped(Account::query()->where('household_id', $household->id), $withTrashed);
        $accounts = $accountQuery->orderBy('id')->get();
        $accountIds = $accounts->pluck('id')->all();

        $transactions = $this->scoped(
            Transaction::query()->whereIn('account_id', $accountIds),
            $withTrashed
        )->with('tags')->orderBy('id')->get();

        $transactionIds = $transactions->pluck('id')->all();

        $investments = $this->scoped(
            Investment::query()->where('household_id', $household->id),
            $withTrashed
        )->orderBy('id')->get();

        $assetIds = $investments->pluck('asset_id')
            ->merge(
                InvestmentPac::query()
                    ->where('household_id', $household->id)
                    ->pluck('investment_asset_id')
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        $debts = $this->scoped(
            DebtCredit::query()->where('household_id', $household->id),
            $withTrashed
        )->orderBy('id')->get();

        $debtIds = $debts->pluck('id')->all();

        $mealLots = MealVoucherLot::query()
            ->whereIn('account_id', $accountIds)
            ->orderBy('id')
            ->get();
        $lotIds = $mealLots->pluck('id')->all();

        $sheets = [];
        $sheets['Meta'] = $this->metaSheet($household, $user, $withTrashed);
        $sheets['Users'] = $this->usersSheet($household);
        $sheets['Household'] = $this->householdSheet($household);
        $sheets['Accounts'] = $this->table($accounts, [
            'id', 'household_id', 'name', 'type', 'initial_balance', 'current_balance',
            'interest_rate', 'ticket_unit_value', 'external_url', 'currency_code',
            'active', 'is_private', 'owner_user_id', 'created_at', 'updated_at', 'deleted_at',
        ]);
        $sheets['Categories'] = $this->table(
            $this->scoped(Category::query()->where('household_id', $household->id), $withTrashed)->orderBy('id')->get(),
            [
                'id', 'household_id', 'name', 'type', 'color', 'icon', 'is_fixed_expense',
                'exclude_from_lifestyle_score', 'expense_distribution', 'created_at', 'updated_at', 'deleted_at',
            ]
        );
        $sheets['Tags'] = $this->table(
            $this->scoped(Tag::query()->where('household_id', $household->id), $withTrashed)->orderBy('id')->get(),
            ['id', 'household_id', 'user_id', 'name', 'color', 'created_at', 'updated_at', 'deleted_at']
        );
        $sheets['Transactions'] = $this->table($transactions, [
            'id', 'user_id', 'account_id', 'category_id', 'amount', 'currency_code',
            'exchange_rate_to_base', 'amount_base', 'original_amount', 'original_currency_code',
            'date', 'description', 'recurring', 'recurring_transaction_id', 'investment_id',
            'investment_event', 'is_private', 'transfer_id', 'inter_household_transfer_id',
            'refund_id', 'debt_credit_id', 'split_group_id', 'is_split_primary',
            'is_tax_deductible', 'tax_deduction_rate', 'tax_deduction_type', 'tax_year',
            'created_at', 'updated_at', 'deleted_at',
        ]);
        $sheets['Transaction_Tag'] = $this->transactionTagSheet($transactions);
        $sheets['Recurring_Transactions'] = $this->table(
            $this->scoped(
                RecurringTransaction::query()->whereIn('account_id', $accountIds),
                $withTrashed
            )->orderBy('id')->get(),
            [
                'id', 'user_id', 'category_id', 'account_id', 'amount', 'currency_code',
                'frequency', 'day_of_month_mode', 'day_of_month', 'non_working_day_policy',
                'start_date', 'end_date', 'description', 'last_generated_date', 'debt_credit_id',
                'successor_recurring_transaction_id', 'predecessor_recurring_transaction_id',
                'created_at', 'updated_at', 'deleted_at',
            ]
        );
        $sheets['Transfers'] = $this->table(
            $this->scoped(
                Transfer::query()->where(function (Builder $q) use ($accountIds) {
                    $q->whereIn('source_account_id', $accountIds)
                        ->orWhereIn('destination_account_id', $accountIds);
                }),
                $withTrashed
            )->orderBy('id')->get(),
            [
                'id', 'uuid', 'source_account_id', 'destination_account_id', 'source_amount',
                'source_currency', 'dest_amount', 'dest_currency', 'exchange_rate', 'fee',
                'user_id', 'status', 'created_at', 'updated_at', 'deleted_at',
            ]
        );
        $sheets['Inter_Household_Transfers'] = $this->table(
            $this->scoped(
                InterHouseholdTransfer::query()->where(function (Builder $q) use ($household) {
                    $q->where('source_household_id', $household->id)
                        ->orWhere('dest_household_id', $household->id);
                }),
                $withTrashed
            )->orderBy('id')->get(),
            [
                'id', 'uuid', 'source_household_id', 'source_account_id', 'source_user_id',
                'dest_household_id', 'dest_account_id', 'dest_user_id', 'source_amount',
                'source_currency', 'dest_amount', 'dest_currency', 'exchange_rate', 'fee',
                'description', 'notes', 'transfer_date', 'exclude_from_stats', 'status',
                'source_transaction_id', 'dest_transaction_id', 'approved_at', 'approved_by',
                'rejected_at', 'rejected_by', 'rejection_reason',
                'created_at', 'updated_at', 'deleted_at',
            ]
        );
        $sheets['Refunds'] = $this->table(
            $this->scoped(
                Refund::query()->where(function (Builder $q) use ($transactionIds, $user) {
                    $q->whereIn('original_transaction_id', $transactionIds)
                        ->orWhere('user_id', $user->id);
                }),
                $withTrashed
            )->orderBy('id')->get(),
            [
                'id', 'uuid', 'original_transaction_id', 'user_id', 'amount', 'currency_code',
                'status', 'description', 'created_at', 'updated_at', 'deleted_at',
            ]
        );
        $sheets['Debts_Credits'] = $this->table($debts, [
            'id', 'household_id', 'user_id', 'counterparty', 'amount', 'initial_amount',
            'paid_amount', 'currency_code', 'type', 'due_date', 'start_date', 'status',
            'description', 'interest_rate', 'tan_rate', 'taeg_rate', 'interest_type',
            'interest_calculation_date', 'created_at', 'updated_at', 'deleted_at',
        ]);
        $sheets['Debt_Credit_Adjustments'] = $this->table(
            DebtCreditAdjustment::query()->whereIn('debt_credit_id', $debtIds)->orderBy('id')->get(),
            [
                'id', 'debt_credit_id', 'user_id', 'amount', 'kind', 'effective_date',
                'reason', 'notes', 'created_at', 'updated_at',
            ]
        );
        $sheets['Investment_Assets'] = $this->table(
            $this->scoped(
                InvestmentAsset::query()->whereIn('id', $assetIds),
                $withTrashed
            )->orderBy('id')->get(),
            [
                'id', 'type', 'allocation_asset_class', 'symbol', 'isin', 'exchange', 'name',
                'currency_code', 'extra_data', 'coupon_frequency', 'next_coupon_date',
                'coupon_rate_percent', 'coupon_rate_steps', 'income_policy',
                'created_at', 'updated_at', 'deleted_at',
            ]
        );
        $sheets['Investments'] = $this->table($investments, [
            'id', 'user_id', 'household_id', 'account_id', 'asset_id', 'investment_pac_id',
            'quantity', 'buy_price', 'nav_at_buy', 'buy_date', 'sell_price', 'sell_date',
            'fees', 'notes', 'is_private', 'created_at', 'updated_at', 'deleted_at',
        ]);
        $sheets['Investment_Pacs'] = $this->table(
            InvestmentPac::query()->where('household_id', $household->id)->orderBy('id')->get(),
            [
                'id', 'household_id', 'user_id', 'account_id', 'investment_asset_id', 'amount',
                'fees', 'adjust_for_inflation', 'inflation_rate_annual', 'last_inflation_adjusted_at',
                'currency_code', 'frequency', 'start_date', 'end_date', 'last_executed_at',
                'status', 'notes', 'created_at', 'updated_at',
            ]
        );
        $sheets['Investment_Analyses'] = $this->table(
            $this->scoped(
                InvestmentAnalysis::query()->where('household_id', $household->id),
                $withTrashed
            )->orderBy('id')->get(),
            [
                'id', 'user_id', 'household_id', 'name', 'template_type', 'start_date',
                'initial_cost', 'recurring_costs', 'savings', 'incentives', 'template_data',
                'total_annual_savings', 'breakeven_years', 'roi_percentage',
                'created_at', 'updated_at', 'deleted_at',
            ]
        );
        $sheets['Budgets'] = $this->table(
            $this->scoped(Budget::query()->where('household_id', $household->id), $withTrashed)->orderBy('id')->get(),
            [
                'id', 'household_id', 'category_id', 'amount', 'currency_code',
                'period_start', 'period_end', 'description', 'created_at', 'updated_at', 'deleted_at',
            ]
        );
        $sheets['Financial_Goals'] = $this->table(
            $this->scoped(
                FinancialGoal::query()->where('household_id', $household->id),
                $withTrashed
            )->orderBy('id')->get(),
            [
                'id', 'household_id', 'user_id', 'name', 'description', 'target_amount',
                'current_amount', 'currency_code', 'target_date', 'status', 'icon', 'color',
                'created_at', 'updated_at', 'deleted_at',
            ]
        );
        $sheets['Meal_Voucher_Lots'] = $this->table($mealLots, [
            'id', 'account_id', 'unit_value', 'quantity_remaining', 'acquired_on',
            'created_at', 'updated_at',
        ]);
        $sheets['Meal_Voucher_Lot_Movements'] = $this->table(
            MealVoucherLotMovement::query()->whereIn('lot_id', $lotIds)->orderBy('id')->get(),
            ['id', 'lot_id', 'transaction_id', 'quantity_delta', 'occurred_on', 'note', 'created_at', 'updated_at']
        );
        $sheets['Meal_Voucher_Unit_Values'] = $this->table(
            MealVoucherUnitValue::query()->whereIn('account_id', $accountIds)->orderBy('id')->get(),
            ['id', 'account_id', 'unit_value', 'effective_from', 'created_at', 'updated_at']
        );
        $sheets['Currencies'] = $this->table(
            Currency::query()->orderBy('code')->get(),
            ['code', 'name', 'symbol', 'created_at', 'updated_at']
        );

        if ($includeExchangeRates) {
            $currencyCodes = $accounts->pluck('currency_code')
                ->merge($transactions->pluck('currency_code'))
                ->merge($transactions->pluck('original_currency_code'))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $sheets['Exchange_Rates'] = $this->table(
                ExchangeRate::query()
                    ->where(function (Builder $q) use ($currencyCodes) {
                        $q->whereIn('base_code', $currencyCodes)
                            ->orWhereIn('quote_code', $currencyCodes);
                    })
                    ->orderBy('date')
                    ->orderBy('id')
                    ->get(),
                ['id', 'base_code', 'quote_code', 'date', 'rate', 'source', 'created_at', 'updated_at']
            );
        }

        $sheets['Attachments'] = $this->attachmentsSheet($transactionIds, $investments->pluck('id')->all(), $withTrashed);
        $sheets['Financial_Variables'] = $this->table(
            FinancialVariable::query()->where('user_id', $user->id)->orderBy('id')->get(),
            [
                'id', 'user_id', 'code', 'name', 'type', 'static_value', 'formula_string',
                'share_token', 'is_public', 'downloads_count', 'source_id',
                'is_official_template', 'template_slug', 'created_at', 'updated_at',
            ]
        );
        $sheets['Formula_Widgets'] = $this->table(
            $this->scoped(
                FormulaWidget::query()->where('user_id', $user->id),
                $withTrashed
            )->orderBy('id')->get(),
            [
                'id', 'user_id', 'financial_variable_id', 'name', 'display_type', 'period_preset',
                'chart_config', 'default_size', 'share_token', 'is_public', 'downloads_count',
                'source_id', 'is_official_template', 'template_slug',
                'created_at', 'updated_at', 'deleted_at',
            ]
        );
        $sheets['Dashboard_Layouts'] = $this->table(
            DashboardLayout::query()
                ->where('household_id', $household->id)
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->get(),
            [
                'id', 'user_id', 'household_id', 'name', 'is_home', 'sort_order', 'config',
                'created_at', 'updated_at',
            ]
        );
        $sheets['Bank_Import_Layouts'] = $this->table(
            $this->scoped(
                BankImportLayout::query()->where('household_id', $household->id),
                $withTrashed
            )->orderBy('id')->get(),
            [
                'id', 'user_id', 'household_id', 'name', 'model_type', 'bank_name', 'icon',
                'column_mapping', 'delimiter', 'date_format', 'has_header', 'encoding',
                'created_at', 'updated_at', 'deleted_at',
            ]
        );

        $consents = Consent::query()->where('user_id', $user->id)->orderBy('id')->get();
        $sheets['Consents'] = $this->table($consents, [
            'id', 'user_id', 'purpose', 'status', 'source', 'legal_basis', 'policy_version',
            'granted_at', 'revoked_at', 'expires_at', 'metadata', 'created_at', 'updated_at',
        ]);
        $sheets['Consent_Events'] = $this->table(
            ConsentEvent::query()
                ->where('user_id', $user->id)
                ->orderBy('id')
                ->get(),
            [
                'id', 'consent_id', 'user_id', 'event_type', 'old_status', 'new_status',
                'source', 'ip_hash', 'user_agent_hash', 'policy_version', 'occurred_at',
                'metadata', 'created_at',
            ]
        );

        return $sheets;
    }

    /**
     * @return SheetTable
     */
    private function metaSheet(Household $household, User $user, bool $withTrashed): array
    {
        return [
            'headers' => ['key', 'value'],
            'rows' => [
                ['export_version', '1.0'],
                ['exported_at', now()->toIso8601String()],
                ['household_id', $household->id],
                ['household_name', $household->name],
                ['user_id', $user->id],
                ['user_email', $user->email],
                ['with_trashed', $withTrashed ? 'TRUE' : 'FALSE'],
                ['base_currency', $user->default_currency_code ?: 'EUR'],
            ],
        ];
    }

    /**
     * @return SheetTable
     */
    private function usersSheet(Household $household): array
    {
        $members = $household->users()->orderBy('users.id')->get();

        return [
            'headers' => [
                'user_id', 'first_name', 'last_name', 'name', 'email', 'role',
                'permissions', 'default_currency_code', 'user_type',
            ],
            'rows' => $members->map(fn (User $member) => [
                $member->id,
                $member->first_name,
                $member->last_name,
                $member->name,
                $member->email,
                $member->pivot?->role,
                $this->encodeJson($member->pivot?->permissions),
                $member->default_currency_code,
                $member->user_type,
            ])->all(),
        ];
    }

    /**
     * @return SheetTable
     */
    private function householdSheet(Household $household): array
    {
        return $this->table(collect([$household]), [
            'id', 'name', 'owner_user_id', 'financial_management_type', 'balance_percentages',
            'enable_turn_suggestions', 'turn_suggestion_settings', 'last_turn_assignments',
            'exclude_inter_transfers_from_stats', 'created_at', 'updated_at', 'deleted_at',
        ]);
    }

    /**
     * @param  Collection<int, Transaction>  $transactions
     * @return SheetTable
     */
    private function transactionTagSheet(Collection $transactions): array
    {
        $rows = [];
        foreach ($transactions as $transaction) {
            foreach ($transaction->tags as $tag) {
                $rows[] = [$transaction->id, $tag->id];
            }
        }

        return [
            'headers' => ['transaction_id', 'tag_id'],
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<int>  $transactionIds
     * @param  list<int>  $investmentIds
     * @return SheetTable
     */
    private function attachmentsSheet(array $transactionIds, array $investmentIds, bool $withTrashed): array
    {
        $headers = [
            'id', 'attachable_type', 'attachable_id', 'file_path', 'filename', 'mime_type',
            'file_size', 'uploaded_at', 'uploaded_by', 'created_at', 'updated_at', 'deleted_at',
        ];

        if ($transactionIds === [] && $investmentIds === []) {
            return ['headers' => $headers, 'rows' => []];
        }

        $query = Attachment::query()->where(function (Builder $q) use ($transactionIds, $investmentIds) {
            if ($transactionIds !== []) {
                $q->orWhere(function (Builder $inner) use ($transactionIds) {
                    $inner->where('attachable_type', Transaction::class)
                        ->whereIn('attachable_id', $transactionIds);
                });
            }
            if ($investmentIds !== []) {
                $q->orWhere(function (Builder $inner) use ($investmentIds) {
                    $inner->where('attachable_type', Investment::class)
                        ->whereIn('attachable_id', $investmentIds);
                });
            }
        });

        return $this->table(
            $this->scoped($query, $withTrashed)->orderBy('id')->get(),
            $headers
        );
    }

    /**
     * @param  Collection<int, Model>|iterable<int, Model>  $models
     * @param  list<string>  $columns
     * @return SheetTable
     */
    private function table(iterable $models, array $columns): array
    {
        $rows = [];
        foreach ($models as $model) {
            $row = [];
            foreach ($columns as $column) {
                $row[] = $this->cellValue($model->getAttribute($column));
            }
            $rows[] = $row;
        }

        return [
            'headers' => $columns,
            'rows' => $rows,
        ];
    }

    private function scoped(Builder $query, bool $withTrashed): Builder
    {
        if ($withTrashed && in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', class_uses_recursive($query->getModel()), true)) {
            return $query->withTrashed();
        }

        return $query;
    }

    private function cellValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if ($value instanceof CarbonInterface) {
            return $value->format($value->format('H:i:s') === '00:00:00' ? 'Y-m-d' : 'Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return $this->encodeJson($value);
        }

        if (is_object($value)) {
            return $this->encodeJson($value);
        }

        return $value;
    }

    private function encodeJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
