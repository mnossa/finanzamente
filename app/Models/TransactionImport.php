<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionImport extends Model
{
    protected $fillable = [
        'user_id',
        'household_id',
        'status',
        'rows_total',
        'rows_imported',
        'rows_skipped',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }
}
