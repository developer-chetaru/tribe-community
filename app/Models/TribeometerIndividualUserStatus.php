<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TribeometerIndividualUserStatus extends Model
{
    protected $table = 'tribeometer_individual_user_status';

    protected $fillable = [
        'userId',
        'orgId',
        'date',
        'completeStatus',
    ];

    protected $casts = [
        'date' => 'datetime',
        'completeStatus' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId');
    }
}
