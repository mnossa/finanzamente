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
}
