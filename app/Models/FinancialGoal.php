<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * FinancialGoal
 *
 * Rappresenta un obiettivo finanziario (es. risparmio per vacanza, fondo
 * emergenza). Contiene l'importo target, l'importo attuale raggiunto e
 * la data target opzionale.
 */
class FinancialGoal extends Model
{
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    protected $fillable = [
        'household_id',
        'user_id',
        'name',
        'description',
        'target_amount',
        'current_amount',
        'currency_code',
        'target_date',
        'status',
        'icon',
        'color',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'target_date' => 'date',
    ];

    /**
     * Stati disponibili per gli obiettivi.
     */
    public const STATUSES = [
        'in_progress' => 'In corso',
        'reached' => 'Raggiunto',
        'cancelled' => 'Annullato',
    ];

    /**
     * Icone suggerite per gli obiettivi.
     */
    public const SUGGESTED_ICONS = [
        '🎯', '🏠', '🚗', '✈️', '💰', '🎓', '💍', '👶',
        '🏥', '📱', '💻', '🎸', '🏋️', '🌴', '🎁', '💎',
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
     * Calcola la percentuale di completamento.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ((float) $this->target_amount <= 0) {
            return 0;
        }

        $percentage = ((float) $this->current_amount / (float) $this->target_amount) * 100;
        return min($percentage, 100);
    }

    /**
     * Calcola l'importo rimanente.
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, (float) $this->target_amount - (float) $this->current_amount);
    }

    /**
     * Verifica se l'obiettivo è stato raggiunto.
     */
    public function isReached(): bool
    {
        return (float) $this->current_amount >= (float) $this->target_amount;
    }

    /**
     * Verifica se l'obiettivo è scaduto.
     */
    public function isOverdue(): bool
    {
        if (!$this->target_date) {
            return false;
        }

        return $this->target_date->lt(now()) && $this->status === 'in_progress';
    }
}
