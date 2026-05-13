<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotAllocatedTheme extends Model
{
    use HasFactory;

    protected $table = 'iot_allocated_themes';

    protected $fillable = [
        'feedbackId',
        'themeId',
        'status',
    ];

    public function feedback()
    {
        return $this->belongsTo(IotFeedback::class, 'feedbackId');
    }

    public function theme()
    {
        return $this->belongsTo(IotTheme::class, 'themeId');
    }
}

