<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivationQuestion extends Model
{
    protected $table = 'motivation_questions';

    protected $fillable = [
        'question',
        'order',
        'status',
    ];

    public function options()
    {
        return $this->hasMany(MotivationOption::class, 'question_id');
    }

    public function answers()
    {
        return $this->hasMany(MotivationAnswer::class, 'question_id');
    }
}
