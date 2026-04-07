<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRecord extends Model
{
    protected $fillable = [
        'organisation_id',
        'subscription_id',
        'stripe_invoice_id',
        'stripe_payment_intent_id',
        'stripe_refund_id',
        'paypal_sale_id',
        'paypal_capture_id',
        'paypal_refund_id',
        'amount',
        'currency',
        'status',
        'type',
        'failure_reason',
        'refund_amount',
        'paid_at',
        'refunded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Get the organisation that owns the payment.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the subscription that owns the payment.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRecord::class, 'subscription_id');
    }

    /**
     * Check if payment was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'succeeded';
    }

    /**
     * Check if payment failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if payment was refunded.
     */
    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }
}

