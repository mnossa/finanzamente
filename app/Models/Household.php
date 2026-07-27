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
    use DispatchesModelEvents, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'owner_user_id',
        'financial_management_type',
        'balance_percentages',
        'enable_turn_suggestions',
        'exclude_inter_transfers_from_stats',
        'turn_suggestion_settings',
        'last_turn_assignments',
    ];

    protected $casts = [
        'financial_management_type' => 'string',
        'balance_percentages' => 'array',
        'enable_turn_suggestions' => 'boolean',
        'exclude_inter_transfers_from_stats' => 'boolean',
        'turn_suggestion_settings' => 'array',
        'last_turn_assignments' => 'array',
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
     * @param  array  $percentages  Array in formato [user_id => percentage, ...]
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
            if (! $this->users()->where('user_id', $userId)->exists()) {
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
        if (! empty($this->balance_percentages)) {
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
        return ! empty($this->balance_percentages);
    }

    /**
     * Verifica se le percentuali sono valide (sommano a 100%).
     */
    public function areBalancePercentagesValid(): bool
    {
        if (! $this->isDebtBalancingMode()) {
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

    /**
     * Verifica se i trasferimenti inter-household verso/da questa household
     * devono essere esclusi dai calcoli statistici per impostazione predefinita.
     */
    public function shouldExcludeInterTransfersFromStats(): bool
    {
        return (bool) $this->exclude_inter_transfers_from_stats;
    }

    /**
     * Calcola il valore di default per exclude_from_stats quando si crea
     * un trasferimento inter-household tra questa household e un'altra.
     * Ritorna true se ALMENO UNA delle due households ha il flag attivo.
     */
    public static function computeExcludeDefault(self $source, self $destination): bool
    {
        return $source->shouldExcludeInterTransfersFromStats()
            || $destination->shouldExcludeInterTransfersFromStats();
    }

    /**
     * Verifica se il suggeritore di turni è abilitato.
     */
    public function isTurnSuggestionsEnabled(): bool
    {
        return $this->enable_turn_suggestions && $this->isDebtBalancingMode();
    }

    /**
     * Ottiene le impostazioni del suggeritore di turni.
     */
    public function getTurnSuggestionsSettings(): array
    {
        return $this->turn_suggestion_settings ?? [];
    }

    /**
     * Aggiorna le impostazioni del suggeritore di turni.
     */
    public function setTurnSuggestionsSettings(array $settings): void
    {
        $this->turn_suggestion_settings = $settings;
        $this->save();
    }

    /**
     * Ottiene l'ultimo turno assegnato per una categoria.
     */
    public function getLastTurnAssignment(int $categoryId): ?int
    {
        $assignments = $this->last_turn_assignments ?? [];

        return $assignments[$categoryId] ?? null;
    }

    /**
     * Aggiorna l'ultimo turno assegnato per una categoria.
     */
    public function setLastTurnAssignment(int $categoryId, int $userId): void
    {
        $assignments = $this->last_turn_assignments ?? [];
        $assignments[$categoryId] = $userId;
        $this->last_turn_assignments = $assignments;
        $this->save();
    }

    /**
     * Suggerisce il prossimo utente per una categoria di spesa fissa.
     */
    public function suggestNextTurn(int $categoryId): ?int
    {
        if (! $this->isTurnSuggestionsEnabled()) {
            return null;
        }

        $members = $this->users()->pluck('id')->toArray();
        if (empty($members)) {
            return null;
        }

        $lastUserId = $this->getLastTurnAssignment($categoryId);

        // Se non c'è una assegnazione precedente, sceglie il primo membro
        if ($lastUserId === null) {
            return $members[0];
        }

        // Trova l'indice dell'ultimo utente e suggerisce il prossimo
        $currentIndex = array_search($lastUserId, $members);
        if ($currentIndex === false) {
            // L'ultimo utente non è più nella household, ricomincia dal primo
            return $members[0];
        }

        // Passa al prossimo utente, ciclando se necessario
        $nextIndex = ($currentIndex + 1) % count($members);

        return $members[$nextIndex];
    }

    /**
     * Calcola i contributi alle spese fisse per ogni membro della household.
     */
    public function getFixedExpenseContributions(): array
    {
        if (! $this->isDebtBalancingMode()) {
            return [];
        }

        $contributions = [];
        $members = $this->users()->pluck('name', 'id')->toArray();

        // Per ogni membro, inizializza i contributi
        foreach ($members as $userId => $userName) {
            $contributions[$userId] = [
                'user_name' => $userName,
                'total_contributed' => 0,
                'categories' => [],
            ];
        }

        // Ottiene le transazioni per le categorie di spese fisse
        $fixedExpenseCategories = Category::where('household_id', $this->id)
            ->where('is_fixed_expense', true)
            ->pluck('name', 'id')->toArray();

        foreach ($fixedExpenseCategories as $categoryId => $categoryName) {
            // Calcola il totale delle spese per questa categoria
            $totalExpenses = Transaction::whereIn('account_id',
                $this->accounts()->pluck('id')
            )
                ->where('category_id', $categoryId)
                ->where('amount', '<', 0) // Solo spese (negative)
                ->operationalStats()
                ->sum('amount');

            $totalExpenses = abs($totalExpenses);

            // Ogni membro contribuisce in base alle sue transazioni
            foreach ($members as $userId => $userName) {
                $userContribution = Transaction::whereIn('account_id',
                    $this->accounts()->where('owner_user_id', $userId)->pluck('id')
                )
                    ->where('category_id', $categoryId)
                    ->where('amount', '<', 0) // Solo spese (negative)
                    ->operationalStats()
                    ->sum('amount');

                $userContribution = abs($userContribution);

                $contributions[$userId]['categories'][$categoryName] = [
                    'contributed' => $userContribution,
                    'total_category' => $totalExpenses,
                    'percentage' => $totalExpenses > 0 ? ($userContribution / $totalExpenses) * 100 : 0,
                ];

                $contributions[$userId]['total_contributed'] += $userContribution;
            }
        }

        return $contributions;
    }
}
