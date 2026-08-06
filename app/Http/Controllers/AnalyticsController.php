<?php

namespace App\Http\Controllers;

use App\Services\DashboardAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function __construct(private DashboardAnalyticsService $analytics) {}

    public function netWorth(): Response
    {
        $user = Auth::user();
        $householdId = (int) $user->active_household_id;
        $series = $this->analytics->getNetWorthSeries($householdId, $user->id);

        return Inertia::render('Analytics/NetWorth', [
            'netWorthData' => $series,
            'summary' => $this->analytics->summarizeNetWorth($series),
        ]);
    }

    public function cashFlow(): Response
    {
        $user = Auth::user();
        $householdId = (int) $user->active_household_id;
        $series = $this->analytics->getCashFlowSeries($householdId, $user->id);

        return Inertia::render('Analytics/CashFlow', [
            'cashFlowData' => $series,
        ]);
    }

    public function expensesByCategory(Request $request): Response
    {
        $user = Auth::user();
        $householdId = (int) $user->active_household_id;

        $monthInput = $request->string('month')->toString();
        $month = $monthInput !== '' && preg_match('/^\d{4}-\d{2}$/', $monthInput)
            ? Carbon::createFromFormat('Y-m', $monthInput)->startOfMonth()
            : Carbon::now()->startOfMonth();

        $categories = $this->analytics->getExpenseCategorySeries($householdId, $user->id, $month);

        $options = [];
        $cursor = Carbon::now()->startOfMonth();
        for ($i = 0; $i < 24; $i++) {
            $options[] = [
                'value' => $cursor->format('Y-m'),
                'label' => $cursor->translatedFormat('F Y'),
            ];
            $cursor->subMonth();
        }

        return Inertia::render('Analytics/ExpensesByCategory', [
            'expenseCategories' => $categories,
            'selectedMonth' => $month->format('Y-m'),
            'selectedMonthLabel' => $month->translatedFormat('F Y'),
            'monthOptions' => $options,
        ]);
    }
}
