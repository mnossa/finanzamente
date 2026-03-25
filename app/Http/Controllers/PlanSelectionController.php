<?php

namespace App\Http\Controllers;

use App\Services\PlanService;
use Inertia\Inertia;
use Inertia\Response;

class PlanSelectionController extends Controller
{
    public function __construct(private readonly PlanService $planService) {}

    /**
     * Mostra la pagina di selezione piano (prima della registrazione).
     */
    public function show(): Response
    {
        return Inertia::render('Auth/SelectPlan', [
            'plans' => $this->planService->getPlansForFrontend(),
            'proEnabled' => $this->planService->isProEnabled(),
            'annualDiscountPercent' => $this->planService->getAnnualDiscountPercent(),
        ]);
    }
}
