<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Convenience seeder that runs all IOT (Offloading) related seeders.
 * Use: php artisan db:seed --class=IotDataSeeder
 */
class IotDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            IotFeedbackStatusSeeder::class,
            IotRiskPrioritySeeder::class,
        ]);
    }
}
