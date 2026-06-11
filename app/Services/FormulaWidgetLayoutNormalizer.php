<?php

namespace App\Services;

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
                $widget->forceFill([
                    'chart_config' => $template['chart_config'] ?? $widget->chart_config,
                    'default_size' => $template['default_size'] ?? $widget->default_size,
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
