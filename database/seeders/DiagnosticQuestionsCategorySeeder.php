<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DiagnosticQuestionsCategory;

class DiagnosticQuestionsCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Personal Development',
            'Teamwork',
            'Leadership/Management',
            'Communication',
            'Stress',
            'Performance',
        ];

        foreach ($categories as $category) {
            DiagnosticQuestionsCategory::firstOrCreate(
                ['title' => $category],
                ['title' => $category]
            );
        }
    }
}

