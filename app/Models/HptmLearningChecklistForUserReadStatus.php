<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HptmLearningChecklistForUserReadStatus extends Model
{
    protected $table = 'hptm_learning_checklist_for_user_read_status';

    protected $fillable = [
        'checklistId',
        'userId',
        'readStatus',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function checklist()
    {
        return $this->belongsTo(HptmLearningChecklist::class, 'checklistId');
    }
}
