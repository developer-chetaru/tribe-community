<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivationAnswer extends Model
{
    protected $table = 'motivation_answers';

    protected $fillable = [
        'userId',
        'orgId',
        'question_id',
        'option_id',
        'motivation_value_id',
        'rating',
        'assessment_date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function question()
    {
        return $this->belongsTo(MotivationQuestion::class, 'question_id');
    }

    public function option()
    {
        return $this->belongsTo(MotivationOption::class, 'option_id');
    }

    public function motivationValue()
    {
        return $this->belongsTo(MotivationValue::class, 'motivation_value_id');
    }
}
