<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
         $this->call([
            RoleSeeder::class,
            AdminSeeder::class,
            HappyIndexMoodValueSeeder::class,
            HptmLearningTypeSeeder::class,
            HptmPrincipleSeeder::class,

            // Offloading (IOT)
            IotFeedbackStatusSeeder::class,
            IotRiskPrioritySeeder::class,

            // Assessment: COT (Team Role Map)
            CotTeamRoleDescriptionSeeder::class,
            CotQuestionSeeder::class,

            // Assessment: Personality Type
            PersonalityTypeValueSeeder::class,
            PersonalityTypeQuestionSeeder::class,

            // Supercharging: Culture Structure
            CultureStructureTypeSeeder::class,
            CultureStructureQuestionSeeder::class,

            // Supercharging: Motivation
            MotivationValueSeeder::class,
            MotivationQuestionSeeder::class,

            // Diagnostic Assessment
            DiagnosticQuestionsCategorySeeder::class,
            DiagnosticQuestionOptionsSeeder::class,
            DiagnosticQuestionsSeeder::class,

            // Tribeometer Assessment
            TribeometerSeeder::class,
        ]);
    }
}
