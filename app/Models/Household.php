<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Household
 *
 * Rappresenta una "household" (famiglia/gruppo) che contiene utenti,
 * conti, budget e altre entità condivise. La household ha un owner
 * (utente proprietario) e una relazione many-to-many con gli utenti
 * tramite la tabella pivot `household_user` che memorizza ruolo e
 * permessi.
 *
 * Relazioni principali:
 * - owner(): belongsTo(User)
 * - users(): belongsToMany(User) con pivot role/permissions
 * - accounts(): hasMany(Account)
 */
class Household extends Model
{
    use HasFactory, SoftDeletes, DispatchesModelEvents;

    protected $fillable = [
        'name',
        'owner_user_id',
        'financial_management_type',
        'balance_percentages',
    ];

    protected $casts = [
        'financial_management_type' => 'string',
        'balance_percentages' => 'array',
    ];

    // Costanti per i tipi di gestione finanziaria
    public const FINANCIAL_MANAGEMENT_DEBT_BALANCING = 'debt_balancing';
    public const FINANCIAL_MANAGEMENT_SHARED_WALLET = 'shared_wallet';

    public const FINANCIAL_MANAGEMENT_TYPES = [
        self::FINANCIAL_MANAGEMENT_DEBT_BALANCING => 'Bilanciamento Debiti',
        self::FINANCIAL_MANAGEMENT_SHARED_WALLET => 'Portafoglio Comune',
    ];

    /**
     * Ritorna il nome human-readable del tipo di gestione finanziaria.
     */
    public function getFinancialManagementTypeLabel(): string
    {
        return self::FINANCIAL_MANAGEMENT_TYPES[$this->financial_management_type] ?? 'Sconosciuto';
    }

    /**
     * Verifica se la household usa il bilanciamento debiti.
     */
    public function isDebtBalancingMode(): bool
    {
        return $this->financial_management_type === self::FINANCIAL_MANAGEMENT_DEBT_BALANCING;
    }

    /**
     * Verifica se la household usa il portafoglio comune.
     */
    public function isSharedWalletMode(): bool
    {
        return $this->financial_management_type === self::FINANCIAL_MANAGEMENT_SHARED_WALLET;
    }

    /**
     * Imposta le percentuali di bilanciamento per utenti specifici.
     * 
     * @param array $percentages Array in formato [user_id => percentage, ...]
     */
    public function setBalancePercentages(array $percentages): void
    {
        // Verifica che le percentuali sommino a 100
        $total = array_sum($percentages);
        if (abs($total - 100) > 0.01) { // Tolleranza per problemi di arrotondamento
            throw new \InvalidArgumentException('Le percentuali devono sommare esattamente al 100%');
        }

        // Verifica che tutti gli utenti appartengano alla household
        foreach ($percentages as $userId => $percentage) {
            if (!$this->users()->where('user_id', $userId)->exists()) {
                throw new \InvalidArgumentException("L'utente {$userId} non appartiene a questa household");
            }
        }

        $this->balance_percentages = $percentages;
    }

    /**
     * Ottiene le percentuali di bilanciamento.
     * Se non sono impostate, ritorna divisione equa tra tutti i membri.
     */
    public function getBalancePercentages(): array
    {
        if (!empty($this->balance_percentages)) {
            return $this->balance_percentages;
        }

        // Se non sono impostate, calcola divisione equa
        return $this->calculateEqualPercentages();
    }

    /**
     * Calcola percentuali eque per tutti i membri della household.
     */
    public function calculateEqualPercentages(): array
    {
        $memberIds = $this->users()->pluck('user_id')->toArray();
        $memberCount = count($memberIds);

        if ($memberCount === 0) {
            return [];
        }

        $equalPercentage = round(100 / $memberCount, 2);
        $percentages = [];

        foreach ($memberIds as $index => $userId) {
            // Per l'ultimo membro, aggiusto per arrivare esattamente a 100
            if ($index === $memberCount - 1) {
                $percentages[$userId] = round(100 - array_sum($percentages), 2);
            } else {
                $percentages[$userId] = $equalPercentage;
            }
        }

        return $percentages;
    }

    /**
     * Ottiene la percentuale di un utente specifico.
     */
    public function getUserBalance(int $userId): float
    {
        $percentages = $this->getBalancePercentages();
        return $percentages[$userId] ?? 0.0;
    }

    /**
     * Verifica se le percentuali di bilanciamento sono personalizzate.
     */
    public function hasCustomBalancePercentages(): bool
    {
        return !empty($this->balance_percentages);
    }

    /**
     * Verifica se le percentuali sono valide (sommano a 100%).
     */
    public function areBalancePercentagesValid(): bool
    {
        if (!$this->isDebtBalancingMode()) {
            return true; // Non applicabile per shared wallet
        }

        $percentages = $this->getBalancePercentages();
        if (empty($percentages)) {
            return false;
        }

        $total = array_sum($percentages);
        return abs($total - 100) <= 0.01;
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'household_user')
            ->withPivot(['role', 'permissions'])
            ->withTimestamps();
    }

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function budgets()
    {
        return $this->hasMany(Budget::class);
    }
}
