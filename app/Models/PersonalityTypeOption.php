<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalityTypeOption extends Model
{
    use HasFactory;

    protected $table = 'personality_type_options';

    protected $fillable = [
        'question_id',
        'option_text',
        'personality_type_value_id',
        'score_value',
        'order',
        'status',
    ];

    public function question()
    {
        return $this->belongsTo(PersonalityTypeQuestion::class, 'question_id', 'id');
    }

    public function personalityTypeValue()
    {
        return $this->belongsTo(PersonalityTypeValue::class, 'personality_type_value_id', 'id');
    }
}

