<?php

namespace App\Http\Controllers;

use App\Services\PortfolioSnapshotService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PatrimonioController extends Controller
{
    public function __construct(
        private readonly PortfolioSnapshotService $portfolioSnapshotService,
    ) {}

    public function index(): Response
    {
        $user = Auth::user();
        $snapshot = $this->portfolioSnapshotService->build($user);

        $investmentPositions = collect($snapshot['positions'])
            ->where('type', 'investment')
            ->values()
            ->all();

        $positionGroups = $this->portfolioSnapshotService->groupInvestmentPositionsForDisplay($investmentPositions);

        return Inertia::render('Patrimonio/Index', [
            'totalValue' => $snapshot['totalValue'],
            'liquidValue' => $snapshot['liquidValue'],
            'investedValue' => $snapshot['investedValue'],
            'investedLinkedValue' => $snapshot['investedLinkedValue'],
            'investedUnlinkedValue' => $snapshot['investedUnlinkedValue'],
            'riskIndex' => $snapshot['riskIndex'],
            'riskLabel' => $snapshot['riskLabel'],
            'allocation' => $this->enrichAllocationWithInstruments(
                $snapshot['allocation'],
                $positionGroups,
                $snapshot['accounts'],
            ),
            'accounts' => $snapshot['accounts'],
            'positionGroups' => $positionGroups,
            'positionMovementCount' => count($investmentPositions),
            'classColors' => $snapshot['classColors'],
            'classLabels' => $snapshot['classLabels'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $allocation
     * @param  array<int, array<string, mixed>>  $positionGroups
     * @param  array<int, array<string, mixed>>  $accounts
     * @return array<int, array<string, mixed>>
     */
    private function enrichAllocationWithInstruments(array $allocation, array $positionGroups, array $accounts): array
    {
        $byClass = [];

        foreach ($positionGroups as $group) {
            $class = $group['asset_class'] ?? 'other';
            $byClass[$class][] = [
                'name' => $group['name'],
                'symbol' => $group['symbol'],
                'value' => $group['value'],
                'detail' => ($group['kind'] ?? '') === 'pac'
                    ? ($group['movement_count'] ?? 0).' movimenti PAC'
                    : null,
            ];
        }

        foreach ($accounts as $account) {
            $byClass['liquidity'][] = [
                'name' => $account['name'],
                'symbol' => null,
                'value' => $account['balance'],
                'detail' => $account['type_label'] ?? 'Conto',
            ];
        }

        return array_map(function (array $entry) use ($byClass) {
            $instruments = $byClass[$entry['asset_class']] ?? [];
            usort($instruments, fn (array $a, array $b) => $b['value'] <=> $a['value']);
            $entry['instruments'] = array_values($instruments);

            return $entry;
        }, $allocation);
    }
}
