<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HptmTeamFeedbackQuestion extends Model
{
    protected $table = 'hptm_team_feedback_questions';

    protected $fillable = [
        'question',
        'principle_id',
    ];

    public $timestamps = true;

    public function principle()
    {
        return $this->belongsTo(HptmPrinciple::class, 'principle_id', 'id');
    }
}
