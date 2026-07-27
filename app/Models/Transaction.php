<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Transaction
 *
 * Rappresenta una singola operazione finanziaria collegata a un account e
 * (opzionalmente) a una categoria, tag e a un'eventuale ricorrenza.
 * Il campo `is_private` determina la visibilità della transazione per
 * altri membri della household.
 */
class Transaction extends Model
{
    use DispatchesModelEvents, HasFactory, SoftDeletes;

    /**
     * Default sicuri per la coerenza multi-currency: se chi salva la transazione
     * non specifica i nuovi campi (chiamanti legacy), assumiamo "1:1 con EUR".
     * Un Observer/saving listener qui rende la pipeline robusta senza dover
     * aggiornare ogni singolo controller esistente in un colpo solo.
     */
    protected static function booted(): void
    {
        static::saving(function (self $tx): void {
            if ($tx->exchange_rate_to_base === null || (float) $tx->exchange_rate_to_base <= 0) {
                $tx->exchange_rate_to_base = 1;
            }
            if ($tx->amount_base === null || (float) $tx->amount_base === 0.0) {
                $tx->amount_base = round((float) $tx->amount * (float) $tx->exchange_rate_to_base, 2);
            }
        });
    }

    protected $fillable = [
        'user_id',
        'account_id',
        'category_id',
        'amount',
        'currency_code',
        'exchange_rate_to_base',
        'amount_base',
        'original_amount',
        'original_currency_code',
        'date',
        'description',
        'recurring',
        'recurring_transaction_id',
        'investment_id',
        'is_private',
        'transfer_id',
        'inter_household_transfer_id',
        'refund_id',
        'debt_credit_id',
        'split_group_id',
        'is_split_primary',
        'is_tax_deductible',
        'tax_deduction_rate',
        'tax_deduction_type',
        'tax_year',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'exchange_rate_to_base' => 'decimal:10',
        'amount_base' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'date' => 'date',
        'recurring' => 'boolean',
        'is_private' => 'boolean',
        'is_split_primary' => 'boolean',
        'is_tax_deductible' => 'boolean',
        'tax_deduction_rate' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'transaction_tag');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function recurringTransaction()
    {
        return $this->belongsTo(RecurringTransaction::class, 'recurring_transaction_id');
    }

    public function investment()
    {
        return $this->belongsTo(Investment::class);
    }

    public function isInvestmentLedger(): bool
    {
        return $this->investment_id !== null;
    }

    public function isPacLedger(): bool
    {
        if ($this->investment_id === null) {
            return false;
        }

        $this->loadMissing('investment');

        return $this->investment?->investment_pac_id !== null;
    }

    /**
     * Transazioni idonee al rilevamento automatico delle ricorrenze.
     */
    public function scopeEligibleForRecurrenceDetection($query)
    {
        return $query
            ->whereNull('recurring_transaction_id')
            ->whereNull('transfer_id')
            ->whereNull('refund_id')
            ->whereNull('inter_household_transfer_id')
            ->whereNull('investment_id');
    }

    /**
     * Entrate/uscite operative (consumo): esclude trasferimenti e ledger investimenti.
     */
    public function scopeOperationalStats($query)
    {
        return $query
            ->whereNull('transfer_id')
            ->whereNull('investment_id')
            ->excludeInterHouseholdStats();
    }

    public function transfer()
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * Relazione con il trasferimento inter-household che ha generato questa transazione.
     */
    public function interHouseholdTransfer()
    {
        return $this->belongsTo(InterHouseholdTransfer::class, 'inter_household_transfer_id');
    }

    /**
     * Relazione con il rimborso (se questa transazione è parte di un rimborso).
     */
    public function refund()
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * Relazione con il debito/credito associato.
     */
    public function debtCredit()
    {
        return $this->belongsTo(DebtCredit::class, 'debt_credit_id');
    }

    /**
     * Rimborsi collegati a questa transazione (se è una spesa rimborsata).
     */
    public function refunds()
    {
        return $this->hasMany(Refund::class, 'original_transaction_id');
    }

    /**
     * Verifica se la transazione è parte di un trasferimento.
     */
    public function isTransfer(): bool
    {
        return $this->transfer_id !== null;
    }

    /**
     * Verifica se la transazione è parte di un trasferimento inter-household.
     */
    public function isInterHouseholdTransfer(): bool
    {
        return $this->inter_household_transfer_id !== null;
    }

    /**
     * Scope che esclude le transazioni generate da trasferimenti inter-household
     * marcati come exclude_from_stats=true.
     *
     * Applica la logica OR: se source o dest household hanno il flag attivo,
     * la transazione viene esclusa dai calcoli statistici.
     */
    public function scopeExcludeInterHouseholdStats($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('inter_household_transfer_id')
                ->orWhereHas('interHouseholdTransfer', fn ($q) => $q->where('exclude_from_stats', false)
                );
        });
    }

    /**
     * Verifica se la transazione è parte di un rimborso.
     */
    public function isRefund(): bool
    {
        return $this->refund_id !== null;
    }

    /**
     * Verifica se la transazione ha ricevuto dei rimborsi.
     */
    public function hasRefunds(): bool
    {
        return $this->refunds()->exists();
    }

    /**
     * Calcola l'importo totale rimborsato per questa transazione.
     */
    public function getTotalRefundedAmount(): float
    {
        return (float) $this->refunds()
            ->where('status', 'completed')
            ->sum('amount');
    }

    /**
     * Calcola l'importo netto (importo originale - rimborsi).
     */
    public function getNetAmount(): float
    {
        $amount = (float) $this->amount;
        $refunded = $this->getTotalRefundedAmount();

        // Se è una spesa (negativa), il netto è meno negativo
        if ($amount < 0) {
            return $amount + $refunded;
        }

        return $amount - $refunded;
    }

    /**
     * Calcola l'importo detraibile in base alla percentuale.
     */
    public function getTaxDeductibleAmount(): float
    {
        if (! $this->is_tax_deductible || ! $this->tax_deduction_rate) {
            return 0.0;
        }

        $baseAmount = abs((float) $this->amount);

        return $baseAmount * ((float) $this->tax_deduction_rate / 100);
    }

    /**
     * Verifica se la transazione è detraibile per l'anno fiscale specificato.
     */
    public function isDeductibleForYear(int $year): bool
    {
        return $this->is_tax_deductible && $this->tax_year === $year;
    }

    /**
     * Verifica se la transazione è associata a un debito/credito.
     */
    public function isDebtPayment(): bool
    {
        return $this->debt_credit_id !== null;
    }
}
