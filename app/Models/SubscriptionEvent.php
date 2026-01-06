<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEvent extends Model
{
    protected $fillable = [
        'subscription_id',
        'user_id',
        'organisation_id',
        'event_type',
        'event_data',
        'triggered_by',
        'triggered_by_user_id',
        'event_date',
        'notes',
    ];

    protected $casts = [
        'event_data' => 'array',
        'event_date' => 'datetime',
    ];

    /**
     * Get the subscription that owns the event.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRecord::class, 'subscription_id');
    }

    /**
     * Get the user associated with the event.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the organisation associated with the event.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the user who triggered the event.
     */
    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
