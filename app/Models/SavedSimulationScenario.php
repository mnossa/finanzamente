<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSimulationScenario extends Model
{
    public const TABS = [
        'compound',
        'debt_vs_invest',
        'emergency',
        'stress_test',
        'historical_projection',
    ];

    protected $fillable = [
        'household_id',
        'user_id',
        'name',
        'tab',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
