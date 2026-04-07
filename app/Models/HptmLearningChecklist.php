<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HptmLearningChecklist extends Model
{
    protected $table = 'hptm_learning_checklist';
    use SoftDeletes;

    protected $fillable = [
        'principleId',
        'output',
        'link',
        'document',
        'title',
        'description',
        'readStatus',
        'created_at',
        'updated_at',
    ];

    public $timestamps = true;

    public function principle()
    {
        return $this->belongsTo(HptmPrinciple::class, 'principleId');
    }

    public function outputType()
    {
        return $this->belongsTo(HptmLearningType::class, 'output');
    }

    public function userReadStatus()
    {
        return $this->hasMany(HptmLearningChecklistForUserReadStatus::class, 'checklistId');
    }

    public function learningType()
    {
        return $this->belongsTo(HptmLearningType::class, 'output');
    }

    public function checklists()
    {
        return $this->hasMany(HptmLearningChecklist::class, 'output');
    }
}
