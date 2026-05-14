<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\IotRiskPriority;

class IotRiskPrioritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $priorities = [
            ['title' => 'Low', 'status' => 'Active'],
            ['title' => 'Medium', 'status' => 'Active'],
            ['title' => 'High', 'status' => 'Active'],
        ];

        foreach ($priorities as $priority) {
            IotRiskPriority::create($priority);
        }
    }
}
