<?php

namespace App\Models;

use Database\Factories\FormulaWidgetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FormulaWidget extends Model
{
    /** @use HasFactory<FormulaWidgetFactory> */
    use HasFactory, SoftDeletes;

    public const DISPLAY_KPI = 'kpi';

    public const DISPLAY_LINE = 'line';

    public const DISPLAY_AREA = 'area';

    public const DISPLAY_BAR = 'bar';

    public const DISPLAY_STACKED_BAR = 'stacked_bar';

    public const DISPLAY_HORIZONTAL_BAR = 'horizontal_bar';

    public const DISPLAY_PIE = 'pie';

    public const DISPLAY_TREEMAP = 'treemap';

    public const DISPLAY_PROGRESS = 'progress';

    /** @return list<string> */
    public static function displayTypes(): array
    {
        return [
            self::DISPLAY_KPI,
            self::DISPLAY_LINE,
            self::DISPLAY_AREA,
            self::DISPLAY_BAR,
            self::DISPLAY_HORIZONTAL_BAR,
            self::DISPLAY_STACKED_BAR,
            self::DISPLAY_PIE,
            self::DISPLAY_TREEMAP,
            self::DISPLAY_PROGRESS,
        ];
    }

    public const SIZES = ['sm', 'md', 'lg', 'xl'];

    protected $fillable = [
        'user_id',
        'financial_variable_id',
        'name',
        'display_type',
        'period_preset',
        'chart_config',
        'default_size',
        'share_token',
        'is_public',
        'downloads_count',
        'source_id',
        'is_official_template',
        'template_slug',
    ];

    protected function casts(): array
    {
        return [
            'chart_config' => 'array',
            'is_public' => 'boolean',
            'downloads_count' => 'integer',
            'is_official_template' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function financialVariable(): BelongsTo
    {
        return $this->belongsTo(FinancialVariable::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_id');
    }

    public function clones(): HasMany
    {
        return $this->hasMany(self::class, 'source_id');
    }

    /**
     * Template ufficiale o clone installato da un template ufficiale: non eliminabile.
     */
    public function isOfficialProtected(): bool
    {
        if ($this->is_official_template) {
            return true;
        }

        if ($this->source_id === null) {
            return false;
        }

        if ($this->relationLoaded('source')) {
            return (bool) $this->source?->is_official_template;
        }

        return self::query()
            ->where('id', $this->source_id)
            ->where('is_official_template', true)
            ->exists();
    }
}
