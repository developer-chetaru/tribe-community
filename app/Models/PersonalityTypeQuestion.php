<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalityTypeQuestion extends Model
{
    use HasFactory;

    protected $table = 'personality_type_questions';

    protected $fillable = [
        'question',
        'category',
        'summary_trait',
        'personality_type_value_id',
        'order',
        'status',
    ];

    public function options()
    {
        return $this->hasMany(PersonalityTypeOption::class, 'question_id', 'id');
    }

    public function personalityTypeValue()
    {
        return $this->belongsTo(PersonalityTypeValue::class, 'personality_type_value_id', 'id');
    }
}

