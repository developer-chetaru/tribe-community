<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CultureStructureType;

class CultureStructureTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'type_key' => 'clan',
                'title' => 'Clan Culture',
                'summary' => 'Collaborative/Family-Oriented',
                'description' => 'Teamwork and employee participation. Collaborative environment with shared values. Mentoring and nurturing leadership. Loyalty and tradition. Focus on morale and cohesion.',
                'characteristics' => 'Teamwork, employee participation, collaborative environment, shared values, mentoring, nurturing leadership, loyalty, tradition, morale, cohesion',
                'icon' => 'clan.svg',
                'order' => 1,
                'status' => 'Active',
            ],
            [
                'type_key' => 'adhocracy',
                'title' => 'Adhocracy Culture',
                'summary' => 'Innovative/Dynamic',
                'description' => 'Innovation and creativity. Risk-taking and entrepreneurship. Dynamic, innovative leadership. Commitment to experimentation. Emphasis on cutting-edge solutions.',
                'characteristics' => 'Innovation, creativity, risk-taking, entrepreneurship, dynamic leadership, experimentation, cutting-edge solutions',
                'icon' => 'adhocracy.svg',
                'order' => 2,
                'status' => 'Active',
            ],
            [
                'type_key' => 'market',
                'title' => 'Market Culture',
                'summary' => 'Competitive/Results-Oriented',
                'description' => 'Results-focused and competitive. Hard-driving, demanding leadership. Achievement of measurable goals. Market superiority and profitability. External positioning and competitiveness.',
                'characteristics' => 'Results-focused, competitive, hard-driving leadership, measurable goals, market superiority, profitability, external positioning',
                'icon' => 'market.svg',
                'order' => 3,
                'status' => 'Active',
            ],
            [
                'type_key' => 'hierarchy',
                'title' => 'Hierarchy Culture',
                'summary' => 'Structured/Controlled',
                'description' => 'Formalized procedures and structure. Coordinating, organizing leadership. Stability and efficiency. Rules, policies, and procedures. Smooth operations and predictability.',
                'characteristics' => 'Formalized procedures, structure, coordinating leadership, stability, efficiency, rules, policies, procedures, predictability',
                'icon' => 'hierarchy.svg',
                'order' => 4,
                'status' => 'Active',
            ],
        ];

        foreach ($types as $type) {
            CultureStructureType::create($type);
        }
    }
}
