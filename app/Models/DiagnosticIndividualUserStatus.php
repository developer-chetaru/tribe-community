<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosticIndividualUserStatus extends Model
{
    protected $table = 'diagnostic_individual_user_status';

    protected $fillable = [
        'userId',
        'orgId',
        'date',
        'completeStatus',
    ];

    protected $casts = [
        'date' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId', 'id');
    }
}
