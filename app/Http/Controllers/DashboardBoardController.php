<?php

namespace App\Http\Controllers;

use App\Models\DashboardLayout;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DashboardBoardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $this->ensureHomeExists($user->id, $householdId);

        $boards = DashboardLayout::query()
            ->where('user_id', $user->id)
            ->where('household_id', $householdId)
            ->orderByDesc('is_home')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (DashboardLayout $board) => [
                'id' => $board->id,
                'name' => $board->name,
                'is_home' => $board->is_home,
                'sort_order' => $board->sort_order,
                'widget_count' => count($board->config['widgets'] ?? []),
                'updated_at' => $board->updated_at?->toIso8601String(),
            ]);

        $limit = $this->boardLimit($user);

        return Inertia::render('Dashboard/Boards', [
            'boards' => $boards,
            'boardLimit' => $limit,
            'canCreate' => $boards->count() < $limit,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $householdId = $user->active_household_id;

        $this->ensureHomeExists($user->id, $householdId);

        $limit = $this->boardLimit($user);
        $count = DashboardLayout::query()
            ->where('user_id', $user->id)
            ->where('household_id', $householdId)
            ->count();

        if ($count >= $limit) {
            return back()->withErrors([
                'name' => "Hai raggiunto il limite di {$limit} dashboard.",
            ]);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('dashboard_layouts', 'name')->where(
                    fn ($query) => $query->where('user_id', $user->id)->where('household_id', $householdId)
                ),
            ],
            'template' => ['nullable', 'in:empty,essential,default'],
        ], [
            'name.required' => 'Inserisci un nome per la dashboard.',
            'name.unique' => 'Esiste già una dashboard con questo nome.',
        ]);

        $template = $validated['template'] ?? 'essential';
        $config = match ($template) {
            'default' => DashboardLayout::defaultConfigForUser($user),
            'empty' => ['widgets' => []],
            default => DashboardLayout::essentialConfigForUser($user),
        };

        $maxSort = (int) DashboardLayout::query()
            ->where('user_id', $user->id)
            ->where('household_id', $householdId)
            ->max('sort_order');

        DashboardLayout::create([
            'user_id' => $user->id,
            'household_id' => $householdId,
            'name' => $validated['name'],
            'is_home' => false,
            'sort_order' => $maxSort + 1,
            'config' => $config,
        ]);

        return redirect()->route('dashboard.boards.index')
            ->with('success', 'Dashboard creata.');
    }

    public function update(Request $request, DashboardLayout $dashboard_layout): RedirectResponse
    {
        $this->authorizeBoard($dashboard_layout);

        $user = Auth::user();
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:80',
                Rule::unique('dashboard_layouts', 'name')
                    ->where(fn ($query) => $query->where('user_id', $user->id)->where('household_id', $user->active_household_id))
                    ->ignore($dashboard_layout->id),
            ],
        ], [
            'name.required' => 'Inserisci un nome per la dashboard.',
            'name.unique' => 'Esiste già una dashboard con questo nome.',
        ]);

        $dashboard_layout->update(['name' => $validated['name']]);

        return redirect()->route('dashboard.boards.index')
            ->with('success', 'Dashboard aggiornata.');
    }

    public function destroy(DashboardLayout $dashboard_layout): RedirectResponse
    {
        $this->authorizeBoard($dashboard_layout);

        if ($dashboard_layout->is_home) {
            return back()->withErrors([
                'board' => 'Non puoi eliminare la dashboard Home.',
            ]);
        }

        $dashboard_layout->delete();

        return redirect()->route('dashboard.boards.index')
            ->with('success', 'Dashboard eliminata.');
    }

    public function setHome(DashboardLayout $dashboard_layout): RedirectResponse
    {
        $this->authorizeBoard($dashboard_layout);

        if ($dashboard_layout->is_home) {
            return redirect()->route('dashboard.boards.index');
        }

        $user = Auth::user();
        $householdId = $user->active_household_id;

        DashboardLayout::homeQuery($user->id, $householdId)->update(['is_home' => false]);
        $dashboard_layout->update(['is_home' => true, 'sort_order' => 0]);

        return redirect()->route('dashboard')
            ->with('success', "«{$dashboard_layout->name}» è ora la Home.");
    }

    private function authorizeBoard(DashboardLayout $board): void
    {
        $user = Auth::user();
        abort_unless(
            $board->user_id === $user->id && $board->household_id === $user->active_household_id,
            404
        );
    }

    private function ensureHomeExists(int $userId, ?int $householdId): void
    {
        if ($householdId === null) {
            return;
        }

        if (DashboardLayout::findHome($userId, $householdId) !== null) {
            return;
        }

        $user = Auth::user();

        DashboardLayout::create([
            'user_id' => $userId,
            'household_id' => $householdId,
            'name' => 'Home',
            'is_home' => true,
            'sort_order' => 0,
            'config' => DashboardLayout::essentialConfigForUser($user),
        ]);
    }

    private function boardLimit(User $user): int
    {
        unset($user);

        // Former Pro limit; no plan gating in open-source build.
        return 10;
    }
}
