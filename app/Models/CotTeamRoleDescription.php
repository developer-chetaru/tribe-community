<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotTeamRoleDescription extends Model
{
    use HasFactory;

    protected $table = 'cot_team_role_descriptions';

    protected $fillable = [
        'role_key',
        'title',
        'value_focus',
        'description',
        'focus',
        'standard_questions',
        'disruption',
        'order',
        'status',
    ];
}

