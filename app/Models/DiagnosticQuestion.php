<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosticQuestion extends Model
{
    protected $table = 'diagnostic_questions';

    protected $fillable = [
        'question',
        'measure',
        'status',
        'category_id',
    ];

    public function category()
    {
        return $this->belongsTo(DiagnosticQuestionsCategory::class, 'category_id', 'id');
    }

    public function answers()
    {
        return $this->hasMany(DiagnosticAnswer::class, 'questionId', 'id');
    }
}
