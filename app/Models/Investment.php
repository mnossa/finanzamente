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
    use DispatchesModelEvents, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id', 'household_id', 'account_id', 'asset_id', 'investment_pac_id', 'quantity', 'buy_price', 'nav_at_buy', 'buy_date', 'sell_price', 'sell_date', 'fees', 'notes', 'is_private',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'buy_price' => 'decimal:8',
        'nav_at_buy' => 'decimal:8',
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

    public function investmentPac()
    {
        return $this->belongsTo(InvestmentPac::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Calcola il valore totale di acquisto (quantity * buy_price).
     */
    public function getTotalBuyValueAttribute(): float
    {
        return (float) $this->quantity * (float) $this->buy_price;
    }

    /**
     * Calcola il valore totale di vendita (quantity * sell_price), se venduto.
     */
    public function getTotalSellValueAttribute(): ?float
    {
        if ($this->sell_price === null) {
            return null;
        }

        return (float) $this->quantity * (float) $this->sell_price;
    }

    /**
     * Calcola il profitto/perdita lordo (senza fees).
     */
    public function getGrossProfitAttribute(): ?float
    {
        if ($this->sell_price === null) {
            return null;
        }

        return $this->total_sell_value - $this->total_buy_value;
    }

    /**
     * Calcola il profitto/perdita netto (con fees).
     */
    public function getNetProfitAttribute(): ?float
    {
        if ($this->sell_price === null) {
            return null;
        }

        return $this->gross_profit - ((float) $this->fees ?? 0);
    }

    /**
     * Calcola la percentuale di rendimento.
     */
    public function getProfitPercentageAttribute(): ?float
    {
        if ($this->sell_price === null || $this->total_buy_value == 0) {
            return null;
        }

        return ($this->net_profit / $this->total_buy_value) * 100;
    }

    /**
     * Verifica se l'investimento è stato venduto.
     */
    public function isSold(): bool
    {
        return $this->sell_date !== null;
    }

    /**
     * Verifica se l'investimento è ancora aperto/attivo.
     */
    public function isOpen(): bool
    {
        return ! $this->isSold();
    }
}
