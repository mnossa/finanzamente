<?php

namespace App\Services;

use App\Models\DashboardLayout;
use App\Models\FormulaWidget;
use App\Models\User;

class FormulaWidgetLayoutNormalizer
{
    /** @var list<string> */
    private const SALDO_DUPLICATE_SLUGS = [
        'official.totale_investimenti',
        'official.patrimonio_netto',
    ];

    /**
     * Allinea chart_config e default_size dei clone agli template ufficiali.
     */
    public function syncTemplateConfigs(User $user): void
    {
        $templatesBySlug = $this->officialTemplatesBySlug();

        FormulaWidget::query()
            ->where('user_id', $user->id)
            ->whereNotNull('source_id')
            ->with('source')
            ->each(function (FormulaWidget $widget) use ($templatesBySlug) {
                $slug = $widget->source?->template_slug;
                if ($slug === null || ! isset($templatesBySlug[$slug])) {
                    return;
                }

                $template = $templatesBySlug[$slug];
                $chartConfig = $template['chart_config'] ?? $widget->chart_config;
                $defaultSize = $template['default_size'] ?? $widget->default_size;

                if ($widget->chart_config === $chartConfig && $widget->default_size === $defaultSize) {
                    return;
                }

                $widget->forceFill([
                    'chart_config' => $chartConfig,
                    'default_size' => $defaultSize,
                ])->saveQuietly();
            });
    }

    /**
     * Rimuove riferimenti formula_widget_* orfani e rimappa ID template ufficiali ai clone utente.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function sanitizeFormulaWidgets(User $user, array $config): array
    {
        $widgets = $config['widgets'] ?? [];

        $userOwnedIds = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->flip();

        $userCloneBySourceId = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->whereNotNull('source_id')
            ->pluck('id', 'source_id');

        $officialTemplateIds = FormulaWidget::query()
            ->where('is_official_template', true)
            ->pluck('id')
            ->flip();

        $sanitized = [];
        $seenLayoutIds = [];
        $position = 0;

        foreach ($widgets as $entry) {
            $layoutId = (string) ($entry['id'] ?? '');

            if (! preg_match('/^formula_widget_(\d+)$/', $layoutId, $matches)) {
                $entry['position'] = $position++;
                $sanitized[] = $entry;

                continue;
            }

            $numericId = (int) $matches[1];
            $resolvedId = $numericId;

            if (! isset($userOwnedIds[$numericId]) && isset($officialTemplateIds[$numericId])) {
                $resolvedId = (int) ($userCloneBySourceId[$numericId] ?? 0);
            }

            if ($resolvedId <= 0 || ! isset($userOwnedIds[$resolvedId])) {
                continue;
            }

            $resolvedLayoutId = "formula_widget_{$resolvedId}";

            if (isset($seenLayoutIds[$resolvedLayoutId])) {
                continue;
            }

            $seenLayoutIds[$resolvedLayoutId] = true;
            $entry['id'] = $resolvedLayoutId;
            $entry['position'] = $position++;
            $sanitized[] = $entry;
        }

        $config['widgets'] = $sanitized;

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalize(User $user, array $config): array
    {
        $config = $this->sanitizeFormulaWidgets($user, $config);

        $templatesBySlug = $this->officialTemplatesBySlug();
        $widgets = $config['widgets'] ?? [];
        $userWidgets = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->with('source')
            ->get()
            ->keyBy('id');

        $slugByLayoutId = [];
        $hasSaldo = false;

        foreach ($widgets as $entry) {
            $layoutId = $entry['id'] ?? '';
            if (! preg_match('/^formula_widget_(\d+)$/', (string) $layoutId, $matches)) {
                continue;
            }

            $slug = $userWidgets->get((int) $matches[1])?->source?->template_slug;
            if ($slug === null) {
                continue;
            }

            $slugByLayoutId[$layoutId] = $slug;
            if ($slug === 'official.saldo_liquidita') {
                $hasSaldo = true;
            }
        }

        $normalized = [];
        $position = 0;

        foreach ($widgets as $entry) {
            $layoutId = $entry['id'] ?? '';
            $slug = $slugByLayoutId[$layoutId] ?? null;

            if ($hasSaldo && $slug !== null && in_array($slug, self::SALDO_DUPLICATE_SLUGS, true)) {
                continue;
            }

            if ($slug !== null && isset($templatesBySlug[$slug]['default_size'])) {
                $entry['size'] = $templatesBySlug[$slug]['default_size'];
            }

            $entry['position'] = $position++;
            $normalized[] = $entry;
        }

        $config['widgets'] = $normalized;

        return $config;
    }

    /**
     * Costruisce la Home Essenziale: KPI (slug→size) in cima + built-in D3.
     *
     * @return array{widgets: list<array{id: string, visible: bool, position: int, size: string}>}
     */
    public function buildHomeEssentialConfig(User $user): array
    {
        /** @var array<string, string> $slugSizes */
        $slugSizes = config('financial_variables.home_essential_formula_widgets');

        if (! is_array($slugSizes) || $slugSizes === []) {
            /** @var list<string> $legacySlugs */
            $legacySlugs = config('financial_variables.home_essential_formula_slugs', []);
            $slugSizes = array_fill_keys($legacySlugs, 'md');
        }

        $slugs = array_keys($slugSizes);

        $clonesBySlug = FormulaWidget::query()
            ->where('user_id', $user->id)
            ->where('is_official_template', false)
            ->whereHas('source', fn ($query) => $query->whereIn('template_slug', $slugs))
            ->with('source')
            ->orderBy('created_at')
            ->get()
            ->unique(fn (FormulaWidget $widget) => $widget->source?->template_slug)
            ->keyBy(fn (FormulaWidget $widget) => $widget->source?->template_slug);

        $widgets = [];
        $position = 0;

        foreach ($slugSizes as $slug => $size) {
            $clone = $clonesBySlug->get($slug);
            if ($clone === null) {
                continue;
            }

            $widgets[] = [
                'id' => "formula_widget_{$clone->id}",
                'visible' => true,
                'position' => $position++,
                'size' => $size,
            ];
        }

        foreach (DashboardLayout::essentialConfig()['widgets'] as $entry) {
            $entry['position'] = $position++;
            $widgets[] = $entry;
        }

        return ['widgets' => $widgets];
    }

    /**
     * Aggiunge al layout i widget formula installati dall'utente ma assenti dalla config
     * (es. dopo "Ripristina default" o layout salvato senza formula_widget_*).
     * Non usare su Home: la Home usa buildHomeEssentialConfig / essentialConfigForUser.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function mergeInstalledFormulaWidgets(User $user, array $config): array
    {
        $widgets = $config['widgets'] ?? [];
        $existingLayoutIds = array_flip(array_column($widgets, 'id'));
        $maxPosition = $widgets === [] ? -1 : (int) max(array_column($widgets, 'position'));

        FormulaWidget::query()
            ->where('user_id', $user->id)
            ->where('is_official_template', false)
            ->orderBy('created_at')
            ->each(function (FormulaWidget $widget) use (&$widgets, &$existingLayoutIds, &$maxPosition): void {
                $layoutId = "formula_widget_{$widget->id}";

                if (isset($existingLayoutIds[$layoutId])) {
                    return;
                }

                $maxPosition++;
                $widgets[] = [
                    'id' => $layoutId,
                    'visible' => true,
                    'position' => $maxPosition,
                    'size' => $widget->default_size ?? 'md',
                ];
                $existingLayoutIds[$layoutId] = true;
            });

        $config['widgets'] = $widgets;

        return $config;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function officialTemplatesBySlug(): array
    {
        $templates = [];

        foreach (config('formula_widget_templates', []) as $template) {
            $slug = $template['template_slug'] ?? null;
            if (is_string($slug) && $slug !== '') {
                $templates[$slug] = $template;
            }
        }

        return $templates;
    }
}
