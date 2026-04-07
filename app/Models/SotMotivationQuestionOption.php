<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SotMotivationQuestionOption extends Model
{
    // Use default MySQL connection

    protected $table = 'sot_motivation_question_options';

    protected $fillable = [
        'question_id',
        'option_name',
        'category_id',
        'status',
    ];
}
