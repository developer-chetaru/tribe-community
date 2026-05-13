<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiagnosticReportGraph extends Model
{
    protected $table = 'diagnostic_report_graph';

    protected $fillable = [
        'date',
        'orgId',
        'officeId',
        'departmentId',
        'categoryId',
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

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId', 'id');
    }
}
