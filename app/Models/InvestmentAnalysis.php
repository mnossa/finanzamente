<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * InvestmentAnalysis
 *
 * Rappresenta un'analisi di investimento con calcolo di risparmio e ammortamento.
 * Supporta template predefiniti (fotovoltaico, auto elettrica, ecc.) e personalizzati.
 */
class InvestmentAnalysis extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'household_id',
        'name',
        'template_type',
        'start_date',
        'initial_cost',
        'recurring_costs',
        'savings',
        'incentives',
        'template_data',
        'total_annual_savings',
        'breakeven_years',
        'roi_percentage',
    ];

    protected $casts = [
        'start_date' => 'date',
        'initial_cost' => 'decimal:2',
        'recurring_costs' => 'array',
        'savings' => 'array',
        'incentives' => 'array',
        'template_data' => 'array',
        'total_annual_savings' => 'decimal:2',
        'breakeven_years' => 'decimal:2',
        'roi_percentage' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }
}
