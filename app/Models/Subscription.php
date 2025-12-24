<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    protected $fillable = [
        'organisation_id',
        'user_count',
        'price_per_user',
        'total_amount',
        'status',
        'start_date',
        'end_date',
        'next_billing_date',
        'billing_cycle',
        'notes',
    ];

    protected $casts = [
        'user_count' => 'integer',
        'price_per_user' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'next_billing_date' => 'date',
    ];

    /**
     * Get the organisation that owns the subscription.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the invoices for the subscription.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Calculate total amount based on user count and price per user.
     */
    public function calculateTotal(): void
    {
        $this->total_amount = $this->user_count * $this->price_per_user;
    }
}
