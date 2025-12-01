<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Investment
 *
 * Rappresenta un'operazione di investimento collegata a un asset e a un
 * account. Contiene dettagli su quantità, prezzi di acquisto/vendita e fee.
 */
class Investment extends Model
{
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    protected $fillable = [
        'user_id', 'household_id', 'account_id', 'asset_id', 'quantity', 'buy_price', 'buy_date', 'sell_price', 'sell_date', 'fees', 'notes', 'is_private',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'buy_price' => 'decimal:8',
        'sell_price' => 'decimal:8',
        'fees' => 'decimal:2',
        'buy_date' => 'date',
        'sell_date' => 'date',
        'is_private' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function asset()
    {
        return $this->belongsTo(InvestmentAsset::class, 'asset_id');
    }
}
