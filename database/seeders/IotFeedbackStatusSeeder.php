<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IotFeedbackStatus;

class IotFeedbackStatusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $statuses = [
            ['title' => 'Open', 'status' => 'Active'],
            ['title' => 'Close', 'status' => 'Active'],
            ['title' => 'Open awaiting R/A', 'status' => 'Active'],
            ['title' => 'Closed awaiting R/A', 'status' => 'Active'],
            ['title' => 'Need Info', 'status' => 'Active'],
            ['title' => 'No Further Action', 'status' => 'Active'],
        ];

        foreach ($statuses as $status) {
            // Check if status already exists to prevent duplicates
            IotFeedbackStatus::firstOrCreate(
                ['title' => $status['title']],
                ['status' => $status['status']]
            );
        }
    }
}
