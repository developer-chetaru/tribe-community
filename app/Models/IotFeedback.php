<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotFeedback extends Model
{
    use HasFactory;

    protected $table = 'iot_feedbacks';

    protected $fillable = [
        'message',
        'image',
        'userId',
        'orgId',
        'SWOT',
        'themeId',
        'feedbackSummary',
        'initialRiskScore',
        'actionTaken',
        'feedbackStatus',
        'mitigatedScore',
        'updatedText',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId');
    }

    public function messages()
    {
        return $this->hasMany(IotMessage::class, 'feedbackId');
    }

    public function adminChatMessages()
    {
        return $this->hasMany(IotAdminChatMessage::class, 'feedbackId');
    }

    public function allocatedThemes()
    {
        return $this->hasMany(IotAllocatedTheme::class, 'feedbackId');
    }

    public function themes()
    {
        return $this->belongsToMany(IotTheme::class, 'iot_allocated_themes', 'feedbackId', 'themeId')
            ->wherePivot('status', 'Active')
            ->withTimestamps();
    }
}

