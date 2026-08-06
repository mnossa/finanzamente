<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transfer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'source_account_id', 'destination_account_id',
        'source_amount', 'source_currency', 'dest_amount', 'dest_currency',
        'exchange_rate', 'fee', 'user_id', 'status',
    ];

    public function sourceAccount()
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    public function destinationAccount()
    {
        return $this->belongsTo(Account::class, 'destination_account_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Backwards-compatible alias
    public function initiatedBy()
    {
        return $this->user();
    }

    protected static function booted()
    {
        // When a transfer is soft-deleted, soft-delete its linked transactions too
        static::deleting(function (Transfer $transfer) {
            if (method_exists($transfer, 'transactions')) {
                $transfer->transactions()->get()->each(function ($tx) {
                    // Use delete() so Transaction's SoftDeletes are used
                    $tx->delete();
                });
            }
        });
    }
}
