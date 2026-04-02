<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardLayout extends Model
{
    use HasFactory;

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
                ['id' => 'total_balance',       'visible' => true, 'position' => 0,  'size' => 'xl'],
                ['id' => 'monthly_stats',        'visible' => true, 'position' => 1,  'size' => 'xl'],
                ['id' => 'annual_revenue',       'visible' => true, 'position' => 2,  'size' => 'lg'],
                ['id' => 'tax_thermometer',      'visible' => true, 'position' => 3,  'size' => 'lg'],
                ['id' => 'lifestyle_widget',     'visible' => true, 'position' => 4,  'size' => 'xl'],
                ['id' => 'accounts',             'visible' => true, 'position' => 5,  'size' => 'md'],
                ['id' => 'recent_transactions',  'visible' => true, 'position' => 6,  'size' => 'md'],
                ['id' => 'active_budgets',       'visible' => true, 'position' => 7,  'size' => 'md'],
                ['id' => 'debts_credits',        'visible' => true, 'position' => 8,  'size' => 'md'],
                ['id' => 'quick_actions',        'visible' => true, 'position' => 9,  'size' => 'xl'],
                ['id' => 'asset_allocation',     'visible' => true, 'position' => 10, 'size' => 'md'],
                ['id' => 'net_worth',            'visible' => true, 'position' => 11, 'size' => 'md'],
                ['id' => 'cash_flow',            'visible' => true, 'position' => 12, 'size' => 'md'],
                ['id' => 'expense_treemap',       'visible' => true, 'position' => 13, 'size' => 'md'],
                ['id' => 'financial_goals',      'visible' => true, 'position' => 14, 'size' => 'md'],
            ],
        ];
    }
}
