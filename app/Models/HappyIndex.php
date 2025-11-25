<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HappyIndex extends Model
{
    protected $table = 'happy_indexes';

    protected $fillable = [
        'user_id',
        'mood_value',
        'description',
        'status',
        'created_at',
        'updated_at'
    ];

    public $timestamps = true;
  
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function mood()
    {
        return $this->belongsTo(HappyIndexMoodValue::class, 'mood_value');
    }
}
