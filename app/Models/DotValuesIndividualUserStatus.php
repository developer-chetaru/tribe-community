<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DotValuesIndividualUserStatus extends Model
{
    protected $table = 'dot_values_individual_user_status'; 

    protected $fillable = [
        'userId',
        'orgId',
        'date',
        'completeStatus',
    ];

    public $timestamps = true; 
}
