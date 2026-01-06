<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentFailureLog extends Model
{
    protected $fillable = [
        'user_id',
        'organisation_id',
        'subscription_id',
        'invoice_id',
        'payment_method',
        'transaction_id',
        'amount',
        'currency',
        'failure_reason',
        'failure_message',
        'retry_attempt',
        'failure_date',
        'status',
        'resolved_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'failure_date' => 'datetime',
        'resolved_at' => 'datetime',
        'retry_attempt' => 'integer',
    ];

    /**
     * Get the user that owns the payment failure log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the organisation that owns the payment failure log.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the subscription associated with the payment failure.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRecord::class, 'subscription_id');
    }

    /**
     * Get the invoice associated with the payment failure.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
