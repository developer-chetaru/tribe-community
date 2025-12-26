<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionRecord extends Model
{
    protected $fillable = [
        'organisation_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'paypal_subscription_id',
        'paypal_subscriber_id',
        'tier',
        'user_count',
        'status',
        'current_period_start',
        'current_period_end',
        'next_billing_date',
        'last_payment_date',
        'payment_failed_count',
        'activated_at',
        'canceled_at',
        'suspended_at',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'next_billing_date' => 'datetime',
        'last_payment_date' => 'datetime',
        'activated_at' => 'datetime',
        'canceled_at' => 'datetime',
        'suspended_at' => 'datetime',
        'user_count' => 'integer',
        'payment_failed_count' => 'integer',
    ];

    /**
     * Get the organisation that owns the subscription.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the payment records for this subscription.
     */
    public function paymentRecords(): HasMany
    {
        return $this->hasMany(PaymentRecord::class, 'subscription_id');
    }

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if subscription is past due.
     */
    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    /**
     * Check if subscription is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}

