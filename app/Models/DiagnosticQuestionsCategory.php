<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosticQuestionsCategory extends Model
{
    protected $table = 'diagnostic_questions_category';

    protected $fillable = [
        'title',
    ];

    public function questions()
    {
        return $this->hasMany(DiagnosticQuestion::class, 'category_id', 'id');
    }
}
