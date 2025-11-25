<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reflection extends Model
{
    use HasFactory;

    protected $table = 'hptm_reflections';

    protected $fillable = [
        'userId',
        'orgId',
        'topic',
        'message',
        'image',
        'status',
    ];

    /**
     * Relationship: Reflection belongs to a User
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Relationship: Reflection belongs to an Organisation
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class, 'orgId');
    }

    /**
     * Optional: get the office of the user
     */
    public function office()
    {
        return $this->hasOneThrough(
            Office::class,     // final model
            User::class,       // intermediate model
            'id',              // Foreign key on User table
            'id',              // Foreign key on Office table
            'userId',          // Local key on Reflection
            'officeId'         // Local key on User
        );
    }

    /**
     * Optional: get department of the user
     */
    public function department()
    {
        return $this->hasOneThrough(
            AllDepartment::class,
            Department::class,
            'id',         // FK on Department
            'id',         // FK on AllDepartment
            'userId',     // Local key on Reflection
            'departmentId'// Local key on Department
        );
    }
}