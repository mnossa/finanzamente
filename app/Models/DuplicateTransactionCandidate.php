<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DuplicateTransactionCandidate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'primary_transaction_id',
        'candidate_transaction_id',
        'status',
        'distance_days',
        'cluster_transaction_ids',
    ];

    protected $casts = [
        'cluster_transaction_ids' => 'array',
    ];

    public function primaryTransaction()
    {
        return $this->belongsTo(Transaction::class, 'primary_transaction_id');
    }

    public function candidateTransaction()
    {
        return $this->belongsTo(Transaction::class, 'candidate_transaction_id');
    }
}
