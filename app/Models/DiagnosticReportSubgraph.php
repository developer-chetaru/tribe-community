<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosticReportSubgraph extends Model
{
    protected $table = 'diagnostic_report_subgraph';

    protected $fillable = [
        'orgId',
        'officeId',
        'departmentId',
        'categoryId',
        'quesId',
        'date',
        'with_weekend',
        'without_weekend',
        'status',
    ];

    protected $casts = [
        'date' => 'date',
        'with_weekend' => 'double',
        'without_weekend' => 'double',
    ];

    public function category()
    {
        return $this->belongsTo(DiagnosticQuestionsCategory::class, 'categoryId', 'id');
    }

    public function question()
    {
        return $this->belongsTo(DiagnosticQuestion::class, 'quesId', 'id');
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId', 'id');
    }
}
