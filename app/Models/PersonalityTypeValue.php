<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalityTypeValue extends Model
{
    use HasFactory;

    protected $table = 'personality_type_values';

    protected $fillable = [
        'dimension_key',
        'title',
        'description',
        'characteristics',
        'real_world_applications',
        'team_collaboration_tips',
        'order',
        'status',
    ];
}

