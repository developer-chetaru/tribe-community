<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HptmTeamFeedbackStatus extends Model
{
    protected $table = 'hptm_team_feedback_status'; // MySQL table name

    protected $fillable = [
        'toUserId',
        'fromUserId',
        'completeStatus',
        'created_at',
        'updated_at',
    ];

    public function toUser()
    {
        return $this->belongsTo(User::class, 'toUserId');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'fromUserId');
    }
}
