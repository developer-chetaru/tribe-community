<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivationIndividualUserStatus extends Model
{
    protected $table = 'motivation_individual_user_status';

    protected $fillable = [
        'userid',
        'orgId',
        'date',
        'completeStatus',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }
}
