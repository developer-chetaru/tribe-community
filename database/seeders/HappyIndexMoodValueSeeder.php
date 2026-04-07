<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HappyIndexMoodValue;

class HappyIndexMoodValueSeeder extends Seeder
{
    public function run(): void
    {
        $moods = [
            ['moodName' => 'Sad', 'status' => 'active'],
            ['moodName' => 'Average', 'status' => 'active'],
            ['moodName' => 'Happy', 'status' => 'active'],
        ];

        foreach ($moods as $mood) {
            HappyIndexMoodValue::create($mood);
        }
    }
}
