<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlySummary extends Model
{
    use HasFactory;

    protected $table = 'monthly_summaries';

    protected $fillable = [
        'user_id',
        'year',
        'month',
        'summary',
    ];

    // Optional: Relation to user
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}