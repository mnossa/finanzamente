<?php

namespace App\Http\Controllers;

use App\Services\PortfolioSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssetAllocationController extends Controller
{
    public function __construct(private readonly PortfolioSnapshotService $portfolioSnapshotService) {}

    public function index(): Response
    {
        $data = $this->portfolioSnapshotService->build(Auth::user());

        return Inertia::render('AssetAllocation/Index', [
            'positions' => $data['positions'],
            'allocation' => $data['allocation'],
            'totalValue' => $data['allocationTotalValue'],
            'riskIndex' => $data['allocationRiskIndex'],
            'riskLabel' => $data['allocationRiskLabel'],
            'classColors' => $data['classColors'],
            'classLabels' => $data['classLabels'],
        ]);
    }

    public function widget(): JsonResponse
    {
        $data = $this->portfolioSnapshotService->build(Auth::user());

        return response()->json([
            'total_value' => $data['allocationTotalValue'],
            'risk_index' => $data['allocationRiskIndex'],
            'risk_label' => $data['allocationRiskLabel'],
            'allocation' => $data['allocation'],
        ]);
    }
}
