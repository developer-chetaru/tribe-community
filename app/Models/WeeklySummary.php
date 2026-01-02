<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WeeklySummary extends Model
{
    use HasFactory;

    protected $table = 'weekly_summaries';

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'week_number',
        'week_label',
        'summary',
    ];

    /**
     * Get the user that owns the weekly summary.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

