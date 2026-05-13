<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivationOption extends Model
{
    protected $table = 'motivation_options';

    protected $fillable = [
        'question_id',
        'motivation_value_id',
        'option_text',
        'option_label',
        'order',
        'status',
    ];

    public function question()
    {
        return $this->belongsTo(MotivationQuestion::class, 'question_id');
    }

    public function motivationValue()
    {
        return $this->belongsTo(MotivationValue::class, 'motivation_value_id');
    }
}
