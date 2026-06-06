<?php

namespace App\Http\Controllers;

use App\Services\InvestmentLedgerService;
use App\Services\PortfolioSnapshotService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PatrimonioController extends Controller
{
    public function __construct(
        private readonly PortfolioSnapshotService $portfolioSnapshotService,
        private readonly InvestmentLedgerService $investmentLedgerService,
    ) {}

    public function index(): Response
    {
        $user = Auth::user();
        $snapshot = $this->portfolioSnapshotService->build($user);

        return Inertia::render('Patrimonio/Index', [
            'totalValue' => $snapshot['totalValue'],
            'liquidValue' => $snapshot['liquidValue'],
            'investedValue' => $snapshot['investedValue'],
            'investedLinkedValue' => $snapshot['investedLinkedValue'],
            'investedUnlinkedValue' => $snapshot['investedUnlinkedValue'],
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
            'investmentSyncPendingCount' => $this->investmentLedgerService->countPendingSync($user),
        ]);
    }
}
