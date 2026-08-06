<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RetentionPolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'policy_key',
        'description',
        'retention_days',
        'anonymize_after_days',
        'is_active',
        'version',
        'metadata',
    ];

    protected $casts = [
        'retention_days' => 'integer',
        'anonymize_after_days' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];
}
