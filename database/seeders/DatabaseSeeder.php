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
            // ─────────────────────────────────────────────────
            // Core / System
            // ─────────────────────────────────────────────────
            RoleSeeder::class,
            AdminSeeder::class,

            // ─────────────────────────────────────────────────
            // Sidebar: Happy Index (user & admin)
            // ─────────────────────────────────────────────────
            HappyIndexMoodValueSeeder::class,

            // ─────────────────────────────────────────────────
            // Sidebar: HPTM (user & admin)
            // ─────────────────────────────────────────────────
            HptmLearningTypeSeeder::class,
            HptmPrincipleSeeder::class,

            // ─────────────────────────────────────────────────
            // Sidebar: Offloading (user & admin)
            //   → Admin: Dashboard / Feedback List / Theme List
            //   → User: Offloading (create / list / chat)
            // ─────────────────────────────────────────────────
            IotFeedbackStatusSeeder::class,   // Feedback status dropdown in admin chatbox
            IotRiskPrioritySeeder::class,      // Risk priority dropdown in admin chatbox

            // ─────────────────────────────────────────────────
            // Sidebar: Assessments → Connecting (Team Role Map)
            //   → Admin: Connecting > Questions / Descriptions / Results
            //   → User: Assessments > Team Role Map
            // ─────────────────────────────────────────────────
            CotTeamRoleDescriptionSeeder::class, // 8 role descriptions (Pioneer, Motivator, etc.)
            CotQuestionSeeder::class,             // 12 questions with 8 options each

            // ─────────────────────────────────────────────────
            // Sidebar: Assessments → Personality Type
            //   → Admin: Personality Type > Questions / Options / Values
            //   → User: Assessments > Personality Type
            // ─────────────────────────────────────────────────
            PersonalityTypeValueSeeder::class,    // 8 dimensions (Int, Ext, Innov, Lgc, etc.)
            PersonalityTypeQuestionSeeder::class, // 29 questions with 5-point Likert scale options

            // ─────────────────────────────────────────────────
            // Sidebar: Supercharging → Culture Structure
            //   → Admin: Supercharging > Culture Structure > Questions / Types / Results
            //   → User: Supercharging > Culture Structure
            // ─────────────────────────────────────────────────
            CultureStructureTypeSeeder::class,    // 4 culture types (Clan, Adhocracy, Market, Hierarchy)
            CultureStructureQuestionSeeder::class, // 10 questions with 4 options each

            // ─────────────────────────────────────────────────
            // Sidebar: Supercharging → Motivation
            //   → Admin: Supercharging > Motivation > Questions / Values / Results
            //   → User: Supercharging > Motivation
            // ─────────────────────────────────────────────────
            MotivationValueSeeder::class,    // 10 motivation values (Financial Security, Teamwork, etc.)
            MotivationQuestionSeeder::class, // 15 paired-choice questions

            // ─────────────────────────────────────────────────
            // Sidebar: Diagnostics Assessment
            //   → Admin: Diagnostics > Questions / Categories / Results
            //   → User: Diagnostics
            // ─────────────────────────────────────────────────
            DiagnosticQuestionsCategorySeeder::class, // 6 categories (Personal Dev, Teamwork, etc.)
            DiagnosticQuestionOptionsSeeder::class,   // 5 rating options (Entirely False → Entirely True)
            DiagnosticQuestionsSeeder::class,         // 30 diagnostic questions

            // ─────────────────────────────────────────────────
            // Sidebar: Tribeometer Assessment
            //   → Admin: Tribeometer > Questions / Values / Results
            //   → User: Tribeometer
            // ─────────────────────────────────────────────────
            TribeometerSeeder::class, // 4 values + 4 options + 16 questions
        ]);
    }
}
