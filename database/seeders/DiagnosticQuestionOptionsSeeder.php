<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DiagnosticQuestionOption;

class DiagnosticQuestionOptionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $options = [
            ['option_name' => 'Entirely false', 'option_rating' => 0],
            ['option_name' => 'Mostly False', 'option_rating' => 1],
            ['option_name' => 'Equally true and false', 'option_rating' => 2],
            ['option_name' => 'Mostly True', 'option_rating' => 3],
            ['option_name' => 'Entirely true', 'option_rating' => 4],
        ];

        foreach ($options as $option) {
            DiagnosticQuestionOption::firstOrCreate(
                ['option_rating' => $option['option_rating']],
                [
                    'option_name' => $option['option_name'],
                    'option_rating' => $option['option_rating'],
                    'status' => 'Active',
                ]
            );
        }
    }
}

