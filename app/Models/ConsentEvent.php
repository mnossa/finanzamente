<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsentEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'consent_id',
        'user_id',
        'event_type',
        'old_status',
        'new_status',
        'source',
        'ip_hash',
        'user_agent_hash',
        'policy_version',
        'occurred_at',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function consent(): BelongsTo
    {
        return $this->belongsTo(Consent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
