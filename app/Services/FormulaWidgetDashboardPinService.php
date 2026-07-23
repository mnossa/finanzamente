<?php

namespace App\Services;

use App\Models\DashboardLayout;
use App\Models\FormulaWidget;
use App\Models\User;
use Illuminate\Support\Collection;
use RuntimeException;

class FormulaWidgetDashboardPinService
{
    public const RESULT_PINNED = 'pinned';

    public const RESULT_ALREADY = 'already';

    public const RESULT_NO_CUSTOM_BOARD = 'no_custom_board';

    public const RESULT_NEEDS_BOARD_CHOICE = 'needs_board_choice';

    /**
     * Aggiunge il widget a una board.
     * Se $boardId è null: una sola board → pin lì; più board → RESULT_NEEDS_BOARD_CHOICE.
     *
     * @return self::RESULT_*
     */
    public function pin(User $user, FormulaWidget $widget, ?int $boardId = null): string
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return self::RESULT_NO_CUSTOM_BOARD;
        }

        $boards = $this->listBoards($user->id, $householdId);

        if ($boards->isEmpty()) {
            return self::RESULT_NO_CUSTOM_BOARD;
        }

        if ($boardId !== null) {
            $layout = $boards->firstWhere('id', $boardId);
            if ($layout === null) {
                return self::RESULT_NO_CUSTOM_BOARD;
            }
        } elseif ($boards->count() > 1) {
            return self::RESULT_NEEDS_BOARD_CHOICE;
        } else {
            $layout = $boards->first();
        }

        return $this->pinToBoard($layout, $widget);
    }

    /**
     * @return self::RESULT_PINNED|self::RESULT_ALREADY
     */
    public function pinToBoard(DashboardLayout $layout, FormulaWidget $widget): string
    {
        $widgetId = "formula_widget_{$widget->id}";
        $widgets = $layout->config['widgets'] ?? [];

        foreach ($widgets as $entry) {
            if (($entry['id'] ?? '') === $widgetId) {
                return self::RESULT_ALREADY;
            }
        }

        $maxPosition = collect($widgets)->max('position') ?? -1;

        $widgets[] = [
            'id' => $widgetId,
            'visible' => true,
            'position' => $maxPosition + 1,
            'size' => $widget->default_size ?? 'md',
        ];

        $layout->update(['config' => ['widgets' => $widgets]]);

        return self::RESULT_PINNED;
    }

    /**
     * Board ordinate: Home prima.
     *
     * @return Collection<int, DashboardLayout>
     */
    public function listBoards(int $userId, int $householdId): Collection
    {
        return DashboardLayout::query()
            ->where('user_id', $userId)
            ->where('household_id', $householdId)
            ->orderByDesc('is_home')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function defaultBoardId(int $userId, int $householdId): ?int
    {
        $home = DashboardLayout::findHome($userId, $householdId);

        return $home?->id;
    }

    /**
     * Board usata dopo un pin riuscito (per redirect).
     */
    public function resolvePinnedBoard(User $user, FormulaWidget $widget, ?int $boardId = null): ?DashboardLayout
    {
        $householdId = $user->active_household_id;
        if ($householdId === null) {
            return null;
        }

        $boards = $this->listBoards($user->id, $householdId);
        if ($boardId !== null) {
            return $boards->firstWhere('id', $boardId);
        }

        $widgetLayoutId = "formula_widget_{$widget->id}";

        return $boards->first(function (DashboardLayout $board) use ($widgetLayoutId) {
            foreach ($board->config['widgets'] ?? [] as $entry) {
                if (($entry['id'] ?? '') === $widgetLayoutId) {
                    return true;
                }
            }

            return false;
        }) ?? $boards->first();
    }

    /**
     * Rimuove il widget da tutte le board dell'utente (Home inclusa, se presente).
     */
    public function removeFromLayout(User $user, FormulaWidget $widget): void
    {
        $householdId = $user->active_household_id;

        if ($householdId === null) {
            return;
        }

        $widgetLayoutId = "formula_widget_{$widget->id}";

        $layouts = DashboardLayout::query()
            ->where('user_id', $user->id)
            ->where('household_id', $householdId)
            ->get();

        foreach ($layouts as $layout) {
            $widgets = array_values(array_filter(
                $layout->config['widgets'] ?? [],
                fn (array $entry) => ($entry['id'] ?? '') !== $widgetLayoutId,
            ));

            if (count($widgets) === count($layout->config['widgets'] ?? [])) {
                continue;
            }

            foreach ($widgets as $index => $entry) {
                $widgets[$index]['position'] = $index;
            }

            $layout->update(['config' => ['widgets' => $widgets]]);
        }
    }

    /**
     * @deprecated Preferire resolvePinnedBoard()
     */
    public function resolveTargetBoard(int $userId, int $householdId): ?DashboardLayout
    {
        return DashboardLayout::findHome($userId, $householdId)
            ?? $this->listBoards($userId, $householdId)->first();
    }

    /**
     * @throws RuntimeException
     */
    public function assertPinned(string $result): void
    {
        if ($result === self::RESULT_NO_CUSTOM_BOARD) {
            throw new RuntimeException(
                'Nessuna dashboard disponibile per aggiungere il widget.'
            );
        }

        if ($result === self::RESULT_NEEDS_BOARD_CHOICE) {
            throw new RuntimeException(
                'Scegli in quale dashboard aggiungere il widget.'
            );
        }
    }
}
