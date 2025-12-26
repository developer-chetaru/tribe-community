<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'subscription_id',
        'organisation_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'user_count',
        'price_per_user',
        'subtotal',
        'tax_amount',
        'total_amount',
        'status',
        'paid_date',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'paid_date' => 'date',
        'user_count' => 'integer',
        'price_per_user' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Get the subscription that owns the invoice.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SubscriptionRecord::class, 'subscription_id');
    }

    /**
     * Get the organisation that owns the invoice.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the payments for the invoice.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Generate unique invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        $lastInvoice = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();
        
        $number = $lastInvoice ? (int) substr($lastInvoice->invoice_number, -4) + 1 : 1;
        
        return 'INV-' . $year . $month . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}
