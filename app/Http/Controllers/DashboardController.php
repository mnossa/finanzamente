<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Mostra la dashboard principale con riepilogo finanziario.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        // Recupera i conti della household (escludendo quelli privati di altri utenti)
        $accounts = Account::where('household_id', $householdId)
            ->where('active', true)
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('owner_user_id', $user->id);
            })
            ->orderBy('name')
            ->get();

        // Calcola saldo totale per ogni conto (saldo iniziale + somma transazioni)
        $accountsWithBalance = $accounts->map(function ($account) use ($user) {
            $transactionsSum = Transaction::where('account_id', $account->id)
                ->where(function ($query) use ($user) {
                    $query->where('is_private', false)
                        ->orWhere('user_id', $user->id);
                })
                ->sum('amount');

            return [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency_code' => $account->currency_code,
                'initial_balance' => (float) $account->initial_balance,
                'current_balance' => (float) $account->initial_balance + (float) $transactionsSum,
                'is_private' => $account->is_private,
            ];
        });

        // Saldo totale (somma di tutti i conti in EUR)
        $totalBalance = $accountsWithBalance->sum('current_balance');

        // Transazioni recenti (ultime 10)
        $recentTransactions = Transaction::with(['account', 'category', 'user'])
            ->whereHas('account', function ($query) use ($householdId) {
                $query->where('household_id', $householdId);
            })
            ->where(function ($query) use ($user) {
                $query->where('is_private', false)
                    ->orWhere('user_id', $user->id);
            })
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'amount' => (float) $transaction->amount,
                    'date' => $transaction->date->format('Y-m-d'),
                    'description' => $transaction->description,
                    'category' => $transaction->category ? [
                        'id' => $transaction->category->id,
                        'name' => $transaction->category->name,
                        'color' => $transaction->category->color,
                        'icon' => $transaction->category->icon,
                    ] : null,
                    'account' => [
                        'id' => $transaction->account->id,
                        'name' => $transaction->account->name,
                    ],
                    'user' => [
                        'id' => $transaction->user->id,
                        'name' => $transaction->user->name,
                    ],
                ];
            });

        // Statistiche mensili (mese corrente)
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $monthlyStats = $this->getMonthlyStats($householdId, $user->id, $startOfMonth, $endOfMonth);

        // Statistiche mese precedente (per confronto)
        $startOfLastMonth = Carbon::now()->subMonth()->startOfMonth();
        $endOfLastMonth = Carbon::now()->subMonth()->endOfMonth();

        $lastMonthStats = $this->getMonthlyStats($householdId, $user->id, $startOfLastMonth, $endOfLastMonth);

        return Inertia::render('Dashboard', [
            'accounts' => $accountsWithBalance,
            'totalBalance' => $totalBalance,
            'recentTransactions' => $recentTransactions,
            'monthlyStats' => $monthlyStats,
            'lastMonthStats' => $lastMonthStats,
            'currentMonth' => Carbon::now()->translatedFormat('F Y'),
            'lastMonth' => Carbon::now()->subMonth()->translatedFormat('F Y'),
        ]);
    }

    /**
     * Calcola le statistiche per un determinato periodo.
     */
    private function getMonthlyStats(int $householdId, int $userId, Carbon $startDate, Carbon $endDate): array
    {
        $query = Transaction::whereHas('account', function ($query) use ($householdId) {
            $query->where('household_id', $householdId);
        })
            ->where(function ($query) use ($userId) {
                $query->where('is_private', false)
                    ->orWhere('user_id', $userId);
            })
            ->whereBetween('date', [$startDate, $endDate]);

        // Entrate (importi positivi)
        $income = (clone $query)->where('amount', '>', 0)->sum('amount');

        // Uscite (importi negativi, restituiti come valore assoluto)
        $expenses = abs((clone $query)->where('amount', '<', 0)->sum('amount'));

        // Saldo netto del periodo
        $net = $income - $expenses;

        // Numero di transazioni
        $transactionCount = (clone $query)->count();

        return [
            'income' => (float) $income,
            'expenses' => (float) $expenses,
            'net' => (float) $net,
            'transaction_count' => $transactionCount,
        ];
    }
}
