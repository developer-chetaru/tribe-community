<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotMessage extends Model
{
    use HasFactory;

    protected $table = 'iot_messages';

    protected $fillable = [
        'feedbackId',
        'message',
        'sendTo',
        'sendFrom',
        'file',
        'status',
    ];

    public function feedback()
    {
        return $this->belongsTo(IotFeedback::class, 'feedbackId');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'sendTo');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sendFrom');
    }
}

