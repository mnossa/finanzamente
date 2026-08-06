<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvestmentPac extends Model
{
    use HasFactory;

    protected $fillable = [
        'household_id',
        'user_id',
        'account_id',
        'investment_asset_id',
        'amount',
        'fees',
        'adjust_for_inflation',
        'inflation_rate_annual',
        'last_inflation_adjusted_at',
        'currency_code',
        'frequency',
        'start_date',
        'end_date',
        'last_executed_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'fees' => 'decimal:2',
        'adjust_for_inflation' => 'boolean',
        'inflation_rate_annual' => 'decimal:2',
        'last_inflation_adjusted_at' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'last_executed_at' => 'date',
    ];

    public function asset()
    {
        return $this->belongsTo(InvestmentAsset::class, 'investment_asset_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function investments()
    {
        return $this->hasMany(Investment::class);
    }
}
