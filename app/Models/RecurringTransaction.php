<?php

namespace App\Models;

use App\Models\Concerns\DispatchesModelEvents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * RecurringTransaction
 *
 * Rappresenta una transazione ricorrente (es. affitto mensile). Contiene
 * la frequenza, data di inizio/fine e il riferimento all'account e alla
 * categoria.
 */
class RecurringTransaction extends Model
{
    use DispatchesModelEvents, HasFactory, SoftDeletes;

    public const DAY_OF_MONTH_MODE_START_DATE = 'start_date';

    public const DAY_OF_MONTH_MODE_FIXED = 'fixed';

    public const DAY_OF_MONTH_MODE_LAST_DAY = 'last_day';

    public const NON_WORKING_DAY_POLICY_POSTPONE = 'postpone';

    public const NON_WORKING_DAY_POLICY_ANTICIPATE = 'anticipate';

    public const NON_WORKING_DAY_POLICY_KEEP = 'keep';

    public const DAY_OF_MONTH_MODES = [
        self::DAY_OF_MONTH_MODE_START_DATE,
        self::DAY_OF_MONTH_MODE_FIXED,
        self::DAY_OF_MONTH_MODE_LAST_DAY,
    ];

    public const NON_WORKING_DAY_POLICIES = [
        self::NON_WORKING_DAY_POLICY_POSTPONE,
        self::NON_WORKING_DAY_POLICY_ANTICIPATE,
        self::NON_WORKING_DAY_POLICY_KEEP,
    ];

    protected $fillable = [
        'user_id',
        'category_id',
        'account_id',
        'amount',
        'currency_code',
        'frequency',
        'day_of_month_mode',
        'day_of_month',
        'non_working_day_policy',
        'start_date',
        'end_date',
        'description',
        'last_generated_date',
        'debt_credit_id',
        'successor_recurring_transaction_id',
        'predecessor_recurring_transaction_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'day_of_month' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'last_generated_date' => 'date',
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

    /**
     * Relazione con il debito/credito associato.
     */
    public function debtCredit()
    {
        return $this->belongsTo(DebtCredit::class, 'debt_credit_id');
    }

    /**
     * Verifica se la ricorrenza è associata a un debito/credito.
     */
    public function isDebtPayment(): bool
    {
        return $this->debt_credit_id !== null;
    }

    /**
     * Ricorrenza chiusa (data di fine impostata e già trascorsa).
     */
    public function isEnded(?Carbon $on = null): bool
    {
        if ($this->end_date === null) {
            return false;
        }

        $on ??= Carbon::today();

        return $this->end_date->lte($on);
    }
}
