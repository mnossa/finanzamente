<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankImportLayout extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'household_id',
        'name',
        'bank_name',
        'icon',
        'column_mapping',
        'delimiter',
        'date_format',
        'has_header',
        'encoding',
    ];

    protected $casts = [
        'column_mapping' => 'array',
        'has_header' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function household()
    {
        return $this->belongsTo(Household::class);
    }
}
