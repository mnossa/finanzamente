<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductAnalyticsDaily extends Model
{
    protected $table = 'product_analytics_daily';

    protected $fillable = [
        'day',
        'event_kind',
        'feature_key',
        'event_name',
        'dimensions_hash',
        'dimensions',
        'event_count',
    ];

    protected $casts = [
        'dimensions' => 'array',
        'event_count' => 'integer',
    ];
}
