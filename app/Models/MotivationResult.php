<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivationResult extends Model
{
    protected $table = 'motivation_results';

    protected $fillable = [
        'userId',
        'orgId',
        'motivation_value_id',
        'value_key',
        'score',
        'rank',
        'assessment_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function motivationValue()
    {
        return $this->belongsTo(MotivationValue::class, 'motivation_value_id');
    }
}
