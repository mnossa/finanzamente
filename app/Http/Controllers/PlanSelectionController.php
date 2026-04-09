<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use Illuminate\View\View;

class PlanSelectionController extends Controller
{
    public function __construct(private readonly PlanService $planService) {}

    /**
     * Mostra la pagina di selezione piano (prima della registrazione).
     */
    public function show(): View
    {
        return view('auth.select-plan', [
            'plans' => $this->planService->getPlansForFrontend(),
            'proEnabled' => $this->planService->isProEnabled(),
            'annualDiscountPercent' => $this->planService->getAnnualDiscountPercent(),
            'waitlistEnabled' => config('prelaunch.waitlist_enabled', false),
        ]);
    }
}
