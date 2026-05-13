<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotTheme extends Model
{
    use HasFactory;

    protected $table = 'iot_themes';

    protected $fillable = [
        'dateOpened',
        'title',
        'description',
        'type',
        'orgId',
        'submission',
        'initialLikelihood',
        'initialConsequence',
        'initialRiskRating',
        'currentLikelihood',
        'currentConsequence',
        'currentRiskRating',
        'linkedAction',
        'comment',
        'themeStatus',
        'status',
    ];

    protected $casts = [
        'dateOpened' => 'datetime',
    ];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId');
    }

    public function allocatedFeedbacks()
    {
        return $this->hasMany(IotAllocatedTheme::class, 'themeId');
    }

    public function feedbacks()
    {
        return $this->belongsToMany(IotFeedback::class, 'iot_allocated_themes', 'themeId', 'feedbackId')
            ->wherePivot('status', 'Active')
            ->withTimestamps();
    }
}

