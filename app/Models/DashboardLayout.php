<?php

namespace App\Models;

use App\Services\FormulaWidgetLayoutNormalizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardLayout extends Model
{
    use HasFactory;

    /** @var list<string> Widget hardcoded Tier A sostituiti dai widget a formula. */
    public const TIER_A_LEGACY_WIDGET_IDS = [
        'total_balance',
        'monthly_stats',
        'net_worth',
        'cash_flow',
    ];

    /** @var list<string> Widget rimossi dal prodotto (strip silenzioso su load/save). */
    public const REMOVED_WIDGET_IDS = [
        'quick_actions',
    ];

    protected $fillable = [
        'user_id',
        'household_id',
        'name',
        'is_home',
        'sort_order',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
        'is_home' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Layout Home per utente + household (compat 1:1 legacy).
     */
    public static function homeQuery(int $userId, ?int $householdId)
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('household_id', $householdId)
            ->where('is_home', true);
    }

    public static function findHome(int $userId, ?int $householdId): ?self
    {
        return static::homeQuery($userId, $householdId)->first();
    }

    /**
     * Restituisce la configurazione di default del layout.
     * Deve corrispondere all'ordine e alle dimensioni attuali della dashboard.
     */
    public static function defaultConfig(): array
    {
        return [
            'widgets' => [
                ['id' => 'lifestyle_widget',     'visible' => true, 'position' => 0,  'size' => 'xl'],
                ['id' => 'accounts',             'visible' => true, 'position' => 1,  'size' => 'md'],
                ['id' => 'recent_transactions',  'visible' => true, 'position' => 2,  'size' => 'md'],
                ['id' => 'active_budgets',       'visible' => true, 'position' => 3,  'size' => 'md'],
                ['id' => 'debts_credits',        'visible' => true, 'position' => 4,  'size' => 'md'],
                ['id' => 'asset_allocation',     'visible' => true, 'position' => 5,  'size' => 'md'],
                ['id' => 'expense_treemap',      'visible' => true, 'position' => 6,  'size' => 'md'],
                ['id' => 'financial_goals',      'visible' => true, 'position' => 7,  'size' => 'md'],
                ['id' => 'expense_distribution', 'visible' => true, 'position' => 8,  'size' => 'md'],
            ],
        ];
    }

    /**
     * Rimuove widget non più supportati (Tier A legacy + rimossi).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function stripUnsupportedWidgets(array $config): array
    {
        $denied = array_merge(self::TIER_A_LEGACY_WIDGET_IDS, self::REMOVED_WIDGET_IDS);

        $widgets = array_values(array_filter(
            $config['widgets'] ?? [],
            fn (array $entry) => ! in_array($entry['id'] ?? '', $denied, true),
        ));

        foreach ($widgets as $index => $entry) {
            $widgets[$index]['position'] = $index;
        }

        $config['widgets'] = $widgets;

        return $config;
    }

    /**
     * @deprecated Usare stripUnsupportedWidgets()
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function stripTierALegacyWidgets(array $config): array
    {
        return self::stripUnsupportedWidgets($config);
    }

    /**
     * Layout predefinito con i widget formula installati dall'utente in coda.
     *
     * @return array<string, mixed>
     */
    public static function defaultConfigForUser(User $user): array
    {
        /** @var FormulaWidgetLayoutNormalizer $normalizer */
        $normalizer = app(FormulaWidgetLayoutNormalizer::class);

        $config = self::defaultConfig();
        $config = $normalizer->mergeInstalledFormulaWidgets($user, $config);

        return $normalizer->normalize($user, $config);
    }

    /**
     * Layout Essenziale canonico — built-in only (D3: righe md+md dopo KPI).
     * I KPI formula (saldo xl / entrate+uscite md) si risolvono in essentialConfigForUser().
     *
     * @return array{widgets: list<array{id: string, visible: bool, position: int, size: string}>}
     */
    public static function essentialConfig(): array
    {
        return [
            'widgets' => [
                ['id' => 'active_budgets',       'visible' => true, 'position' => 0, 'size' => 'md'],
                ['id' => 'expense_treemap',      'visible' => true, 'position' => 1, 'size' => 'md'],
                ['id' => 'recent_transactions',  'visible' => true, 'position' => 2, 'size' => 'md'],
                ['id' => 'accounts',             'visible' => true, 'position' => 3, 'size' => 'md'],
            ],
        ];
    }

    /**
     * Home Essenziale: KPI bootstrap (slug) + built-in. Non include tutti i formula installati.
     *
     * @return array<string, mixed>
     */
    public static function essentialConfigForUser(User $user): array
    {
        /** @var FormulaWidgetLayoutNormalizer $normalizer */
        $normalizer = app(FormulaWidgetLayoutNormalizer::class);

        return $normalizer->normalize($user, $normalizer->buildHomeEssentialConfig($user));
    }

    /**
     * True se la config è solo il template built-in Essenziale (senza formula).
     *
     * @param  array<string, mixed>  $config
     */
    public static function isBareEssentialConfig(array $config): bool
    {
        $expected = array_column(self::essentialConfig()['widgets'], 'id');
        $actual = array_column($config['widgets'] ?? [], 'id');

        return $expected === $actual;
    }

    /**
     * Board di proprietà utente+household, oppure null.
     */
    public static function findOwned(int $userId, ?int $householdId, int $boardId): ?self
    {
        return static::query()
            ->where('id', $boardId)
            ->where('user_id', $userId)
            ->where('household_id', $householdId)
            ->first();
    }
}
