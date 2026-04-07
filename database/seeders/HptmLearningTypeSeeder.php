<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HptmLearningType;

class HptmLearningTypeSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'title' => 'Module Coaching Video',
                'score' => 200,
                'priority' => 1,
                'created_at' => '2021-05-12 12:36:12',
                'updated_at' => '2025-07-31 12:21:48',
            ],
            [
                'title' => 'BiteSize Module Coaching Video',
                'score' => 50,
                'priority' => 2,
                'created_at' => '2021-05-12 12:36:12',
                'updated_at' => '2021-05-31 15:11:37',
            ],
            [
                'title' => 'Module Handouts',
                'score' => 30,
                'priority' => 3,
                'created_at' => '2021-05-12 12:36:33',
                'updated_at' => null,
            ],
            [
                'title' => 'Module Slides',
                'score' => 500,
                'priority' => 4,
                'created_at' => '2021-05-12 12:36:12',
                'updated_at' => '2025-07-25 07:14:07',
            ],
        ];

        HptmLearningType::insert($data);
    }
}
