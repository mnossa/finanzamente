<?php

namespace App\Models;

use Database\Factories\FinancialVariableFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialVariable extends Model
{
    /** @use HasFactory<FinancialVariableFactory> */
    use HasFactory;

    public const TYPE_STATIC = 'static';

    public const TYPE_FORMULA = 'formula';

    protected $fillable = [
        'user_id',
        'code',
        'name',
        'type',
        'static_value',
        'formula_string',
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
            'static_value' => 'decimal:2',
            'is_public' => 'boolean',
            'downloads_count' => 'integer',
            'is_official_template' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_id');
    }

    public function clones(): HasMany
    {
        return $this->hasMany(self::class, 'source_id');
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(FormulaWidget::class);
    }

    public function isFormula(): bool
    {
        return $this->type === self::TYPE_FORMULA;
    }

    public function isStatic(): bool
    {
        return $this->type === self::TYPE_STATIC;
    }
}
