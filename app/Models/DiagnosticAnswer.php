<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosticAnswer extends Model
{
    protected $table = 'diagnostic_answers';

    protected $fillable = [
        'userId',
        'orgId',
        'questionId',
        'optionId',
        'answer',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId', 'id');
    }

    public function question()
    {
        return $this->belongsTo(DiagnosticQuestion::class, 'questionId', 'id');
    }

    public function option()
    {
        return $this->belongsTo(DiagnosticQuestionOption::class, 'optionId', 'id');
    }
}
