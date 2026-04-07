<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalityTypeIndividualUserStatus extends Model
{
    protected $table = 'personality_type_individual_user_status';

    protected $fillable = [
        'userid',
        'orgId',
        'date',
        'completeStatus',
    ];
}
