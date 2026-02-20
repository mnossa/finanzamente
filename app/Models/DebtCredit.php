<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DebtCredit
 *
 * Rappresenta prestiti o crediti (debts/credits) associati a una household
 * e a un utente. Tiene traccia di importo, scadenza e stato.
 */
class DebtCredit extends Model
{
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    protected $table = 'debts_credits';

    protected $fillable = [
        'household_id', 
        'user_id', 
        'counterparty', 
        'amount', 
        'initial_amount',
        'paid_amount',
        'currency_code', 
        'type', 
        'due_date', 
        'status', 
        'description',
        'interest_rate',
        'interest_type',
        'interest_calculation_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'initial_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'interest_rate' => 'decimal:2',
        'due_date' => 'date',
        'interest_calculation_date' => 'date',
    ];

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /**
     * Transazioni associate a questo debito/credito.
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'debt_credit_id');
    }

    /**
     * Transazioni ricorrenti associate a questo debito/credito.
     */
    public function recurringTransactions()
    {
        return $this->hasMany(RecurringTransaction::class, 'debt_credit_id');
    }

    /**
     * Calcola il saldo rimanente del debito/credito.
     */
    public function getRemainingAmount(): float
    {
        $initial = (float) ($this->initial_amount ?? $this->amount);
        $paid = (float) $this->paid_amount;
        
        return $initial - $paid;
    }

    /**
     * Calcola gli interessi maturati fino alla data specificata.
     * 
     * @param \DateTime|string|null $toDate Data fino alla quale calcolare gli interessi (default: oggi)
     * @return float
     */
    public function calculateAccruedInterest($toDate = null): float
    {
        // Se non c'è tasso di interesse, ritorna 0
        if (!$this->interest_rate || $this->interest_rate <= 0) {
            return 0.0;
        }

        $toDate = $toDate ? \Carbon\Carbon::parse($toDate) : now();
        $startDate = $this->interest_calculation_date 
            ? \Carbon\Carbon::parse($this->interest_calculation_date) 
            : $this->created_at;

        // Calcola i giorni trascorsi
        $days = $startDate->diffInDays($toDate);
        
        if ($days <= 0) {
            return 0.0;
        }

        $principal = $this->getRemainingAmount();
        $rate = (float) $this->interest_rate / 100; // Converte la percentuale

        if ($this->interest_type === 'compound') {
            // Interesse composto (giornaliero)
            $dailyRate = $rate / 365;
            return $principal * (pow(1 + $dailyRate, $days) - 1);
        }

        // Interesse semplice (default)
        return $principal * $rate * ($days / 365);
    }

    /**
     * Calcola l'importo totale comprensivo di interessi.
     */
    public function getTotalAmountWithInterest($toDate = null): float
    {
        return $this->getRemainingAmount() + $this->calculateAccruedInterest($toDate);
    }

    /**
     * Aggiorna l'importo pagato e lo stato del debito/credito.
     * 
     * @param float $paymentAmount Importo del pagamento
     * @return void
     */
    public function recordPayment(float $paymentAmount): void
    {
        $this->paid_amount = bcadd((string) $this->paid_amount, (string) $paymentAmount, 2);
        
        // Aggiorna lo stato
        $remaining = $this->getRemainingAmount();
        
        if ($remaining <= 0.01) { // Tolleranza per arrotondamenti
            $this->status = 'closed';
        } elseif ($this->due_date && now()->isAfter($this->due_date)) {
            $this->status = 'overdue';
        } else {
            $this->status = 'open';
        }
        
        $this->save();
    }

    /**
     * Verifica se il debito/credito è scaduto.
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue' || 
               ($this->due_date && now()->isAfter($this->due_date) && $this->status !== 'closed');
    }

    /**
     * Verifica se il debito/credito è completamente pagato.
     */
    public function isPaid(): bool
    {
        return $this->status === 'closed' || $this->getRemainingAmount() <= 0.01;
    }
}
