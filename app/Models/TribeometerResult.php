<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TribeometerResult extends Model
{
    protected $table = 'tribeometer_results';

    protected $fillable = [
        'userId',
        'orgId',
        'value_id',
        'score',
        'average_score',
        'total_responses',
        'calculated_at',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'average_score' => 'decimal:2',
        'calculated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId');
    }

    public function value()
    {
        return $this->belongsTo(TribeometerValue::class, 'value_id');
    }
}
