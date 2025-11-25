<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReflectionMessage extends Model
{
    use HasFactory;

    protected $table = 'reflection_messages';

    protected $fillable = [
        'reflectionId',
        'sendFrom',
        'sendTo',
        'message',
        'file',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'sendFrom');
    }

    public function reflection()
    {
        return $this->belongsTo(Reflection::class, 'reflectionId');
    }
}