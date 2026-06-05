<?php

namespace App\Http\Controllers;

use App\Services\PortfolioSnapshotService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PatrimonioController extends Controller
{
    public function __construct(private readonly PortfolioSnapshotService $portfolioSnapshotService) {}

    public function index(): Response
    {
        $snapshot = $this->portfolioSnapshotService->build(Auth::user());

        return Inertia::render('Patrimonio/Index', [
            'totalValue' => $snapshot['totalValue'],
            'liquidValue' => $snapshot['liquidValue'],
            'investedValue' => $snapshot['investedValue'],
            'riskIndex' => $snapshot['riskIndex'],
            'riskLabel' => $snapshot['riskLabel'],
            'allocation' => $snapshot['allocation'],
            'accounts' => $snapshot['accounts'],
            'positions' => collect($snapshot['positions'])
                ->where('type', 'investment')
                ->values()
                ->all(),
            'classColors' => $snapshot['classColors'],
            'classLabels' => $snapshot['classLabels'],
        ]);
    }
}
