<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan',
        'billing_cycle',
        'mollie_subscription_id',
        'mollie_mandate_id',
        'status',
        'currency',
        'amount_cents',
        'trial_ends_at',
        'next_payment_at',
        'ends_at',
        'billing_name',
        'billing_email',
        'billing_address',
        'billing_city',
        'billing_zip',
        'billing_country',
        'billing_vat',
        'billing_company',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'next_payment_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Importo formattato in euro con separatore italiano.
     */
    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount_cents / 100, 2, ',', '.') . ' €';
    }
}
