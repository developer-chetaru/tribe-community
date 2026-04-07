<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SendNotification extends Model
{
    use HasFactory;

    protected $table = 'send_notifications';

    protected $fillable = [
        'orgId',
        'officeId',
        'departmentId',
        'title',
        'description',
        'links',
        'status',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * 🔗 Organisation Relationship
     * Each notification belongs to one organisation.
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId', 'id');
    }

    /**
     * 🔗 Office Relationship
     * Each notification belongs to one office.
     */
    public function office()
    {
        return $this->belongsTo(Office::class, 'officeId', 'id');
    }

    /**
     * 🔗 Department Relationship
     * Each notification belongs to one department.
     */
    public function department()
    {
        // ✅ Confirmed: You are using AllDepartment model for departments
        return $this->belongsTo(AllDepartment::class, 'departmentId', 'id');
    }

    /**
     * 🔗 IoT Notification Relationship
     * A notification may have many IoT-specific notifications linked.
     */
    public function iotNotifications()
    {
        return $this->hasMany(IotNotification::class, 'sendNotificationId', 'id');
    }

	public function allDepartment()
    {
        return $this->belongsTo(AllDepartment::class, 'departmentId');
    }
}