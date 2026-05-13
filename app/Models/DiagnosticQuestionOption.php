<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosticQuestionOption extends Model
{
    protected $table = 'diagnostic_question_options';

    protected $fillable = [
        'option_name',
        'option_rating',
        'status',
    ];

    public function answers()
    {
        return $this->hasMany(DiagnosticAnswer::class, 'optionId', 'id');
    }
}
