<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HappyIndexMoodValue extends Model
{
    protected $table = 'happy_index_mood_values';

    protected $fillable = [
        'moodName',
        'status',
    ];


	public function happyIndexes()
	{
    	return $this->hasMany(HappyIndex::class, 'mood_value');
	}
}
