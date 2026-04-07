<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotTeamRoleIndividualUserStatus extends Model
{
    protected $table = 'cot_team_role_individual_user_status';

    protected $fillable = [
        'userid',
        'orgId',
        'date',
        'completeStatus',
    ];
}
