<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IotNotification extends Model
{
    use HasFactory;

    protected $table = 'iot_notifications';

    protected $fillable = [
        'title',
        'description',
        'to_bubble_user_id',
        'from_bubble_user_id',
        'notificationType',
        'notificationLinks',
        'sendNotificationId',
        'status',
        'archive',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // The user who received the notification
    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_bubble_user_id');
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_bubble_user_id');
    }

    public function sendNotification()
    {
        return $this->belongsTo(SendNotification::class, 'sendNotificationId');
    }
}
