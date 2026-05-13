<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IotDataSeeder extends Seeder
{
    public function run(): void
    {
        // Seed iot_feedback_statuses
        if (DB::table('iot_feedback_status')->count() === 0) {
            DB::table('iot_feedback_status')->insert([
                ['title' => 'Open',                 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
                ['title' => 'Close',                'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
                ['title' => 'Open awaiting R/A',    'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
                ['title' => 'Closed awaiting R/A',  'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
                ['title' => 'Need Info',             'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
                ['title' => 'No Further Action',    'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ]);
            $this->command->info('IotFeedbackStatus seeded.');
        } else {
            $this->command->info('IotFeedbackStatus already has data, skipping.');
        }

        // Seed iot_risk_priorities
        if (DB::table('iot_risk_priority')->count() === 0) {
            DB::table('iot_risk_priority')->insert([
                ['title' => 'Low',    'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
                ['title' => 'Medium', 'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
                ['title' => 'High',   'status' => 'Active', 'created_at' => now(), 'updated_at' => now()],
            ]);
            $this->command->info('IotRiskPriority seeded.');
        } else {
            $this->command->info('IotRiskPriority already has data, skipping.');
        }
    }
}
