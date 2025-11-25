<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\HptmPrinciple;

class HptmPrincipleSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            [
                'title' => 'Beliefe',
                'description' => 'Only do things you 100% believe in',
                'created_at' => '2025-07-17 09:10:42',
                'updated_at' => '2025-07-31 12:24:42',
            ],
            [
                'title' => 'Structure',
                'description' => 'Embrace structure, help it evolve',
                'created_at' => '2025-07-17 09:10:44',
                'updated_at' => '2025-07-17 09:10:44',
            ],
            [
                'title' => 'Balance',
                'description' => 'Prioritise yourself and manage your conditions',
                'created_at' => '2025-07-17 09:10:44',
                'updated_at' => '2025-07-17 09:10:44',
            ],
            [
                'title' => 'Honesty',
                'description' => 'Offload the moment things are off',
                'created_at' => '2025-07-17 09:10:44',
                'updated_at' => '2025-07-17 09:10:44',
            ],
            [
                'title' => 'Inclusive',
                'description' => 'Value everyone and everything around you',
                'created_at' => '2025-07-17 09:10:44',
                'updated_at' => '2025-08-04 05:51:53',
            ],
        ];

        HptmPrinciple::insert($data);
    }
}
