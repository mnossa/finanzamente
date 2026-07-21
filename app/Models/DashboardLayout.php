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

    protected $fillable = [
        'user_id',
        'household_id',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
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
                ['id' => 'quick_actions',        'visible' => true, 'position' => 5,  'size' => 'xl'],
                ['id' => 'asset_allocation',     'visible' => true, 'position' => 6,  'size' => 'md'],
                ['id' => 'expense_treemap',      'visible' => true, 'position' => 7,  'size' => 'md'],
                ['id' => 'financial_goals',      'visible' => true, 'position' => 8,  'size' => 'md'],
                ['id' => 'expense_distribution', 'visible' => true, 'position' => 9,  'size' => 'md'],
            ],
        ];
    }

    /**
     * Rimuove i widget Tier A legacy dal layout (sostituiti da formula_widget_*).
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function stripTierALegacyWidgets(array $config): array
    {
        $widgets = array_values(array_filter(
            $config['widgets'] ?? [],
            fn (array $entry) => ! in_array($entry['id'] ?? '', self::TIER_A_LEGACY_WIDGET_IDS, true),
        ));

        foreach ($widgets as $index => $entry) {
            $widgets[$index]['position'] = $index;
        }

        $config['widgets'] = $widgets;

        return $config;
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
     * Layout essenziale per nuovi utenti.
     *
     * @return array<string, mixed>
     */
    public static function essentialConfigForUser(User $user): array
    {
        /** @var FormulaWidgetLayoutNormalizer $normalizer */
        $normalizer = app(FormulaWidgetLayoutNormalizer::class);

        $config = [
            'widgets' => [
                ['id' => 'accounts',             'visible' => true, 'position' => 0, 'size' => 'lg'],
                ['id' => 'expense_distribution', 'visible' => true, 'position' => 1, 'size' => 'md'],
                ['id' => 'active_budgets',       'visible' => true, 'position' => 2, 'size' => 'md'],
                ['id' => 'recent_transactions',  'visible' => true, 'position' => 3, 'size' => 'md'],
                ['id' => 'quick_actions',        'visible' => true, 'position' => 4, 'size' => 'sm'],
            ],
        ];

        $config = $normalizer->mergeInstalledFormulaWidgets($user, $config);

        return $normalizer->normalize($user, $config);
    }
}
