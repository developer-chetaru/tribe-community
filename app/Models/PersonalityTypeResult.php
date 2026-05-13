<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalityTypeResult extends Model
{
    use HasFactory;

    protected $table = 'personality_type_results';

    protected $fillable = [
        'userId',
        'orgId',
        'personality_type_value_id',
        'dimension_key',
        'score',
        'percentage',
        'assessment_date',
    ];

    protected $casts = [
        'assessment_date' => 'date',
    ];

    public function personalityTypeValue()
    {
        return $this->belongsTo(PersonalityTypeValue::class, 'personality_type_value_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId', 'id');
    }
}

