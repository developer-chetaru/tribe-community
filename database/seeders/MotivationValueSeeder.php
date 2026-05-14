<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MotivationValue;

class MotivationValueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $values = [
            [
                'value_key' => 'financial_security',
                'title' => 'Financial Security',
                'description' => 'Stability and predictable income. Benefits and financial rewards. Job security and compensation. Financial planning opportunities.',
                'characteristics' => 'Stable income, comprehensive benefits, clear compensation structures, job security, financial planning',
                'management_strategy' => 'Emphasize job security, transparent pay scales, retirement plans, comprehensive benefits package',
                'order' => 1,
                'status' => 'Active',
            ],
            [
                'value_key' => 'stress_free',
                'title' => 'Stress-Free Environment',
                'description' => 'Low-pressure atmosphere. Work-life balance. Manageable workload. Supportive environment.',
                'characteristics' => 'Calm workplaces, flexible schedules, reasonable deadlines, supportive environment, work-life balance',
                'management_strategy' => 'Offer flexible hours, wellness programs, clear expectations, manageable workloads, supportive culture',
                'order' => 2,
                'status' => 'Active',
            ],
            [
                'value_key' => 'risk_free',
                'title' => 'Risk-Free Work',
                'description' => 'Safe, predictable tasks. Established processes. Minimal uncertainty. Proven methods.',
                'characteristics' => 'Clear guidelines, proven processes, minimal ambiguity, safe tasks, predictable outcomes',
                'management_strategy' => 'Provide detailed procedures, structured environment, clear roles, proven methods, minimal ambiguity',
                'order' => 3,
                'status' => 'Active',
            ],
            [
                'value_key' => 'job_structure',
                'title' => 'Job Structure',
                'description' => 'Clear roles and responsibilities. Defined processes and workflows. Organized work environment. Systematic approach.',
                'characteristics' => 'Clear expectations, organized systems, defined hierarchies, structured projects, clear reporting lines',
                'management_strategy' => 'Offer detailed job descriptions, structured projects, clear reporting lines, defined processes',
                'order' => 4,
                'status' => 'Active',
            ],
            [
                'value_key' => 'teamwork',
                'title' => 'Teamwork',
                'description' => 'Collaboration and cooperation. Group achievements. Social connections at work. Collective problem-solving.',
                'characteristics' => 'Team settings, relationships, collaborative projects, group achievements, social connections',
                'management_strategy' => 'Create team-based projects, foster collaboration, organize team events, encourage group problem-solving',
                'order' => 5,
                'status' => 'Active',
            ],
            [
                'value_key' => 'recognition',
                'title' => 'Recognition',
                'description' => 'Public acknowledgment. Formal awards and appreciation. Visibility for contributions. Status and prestige.',
                'characteristics' => 'Public praise, awards, titles, acknowledgment, visibility, status, prestige',
                'management_strategy' => 'Implement recognition programs, celebrate wins publicly, offer awards, provide visibility for contributions',
                'order' => 6,
                'status' => 'Active',
            ],
            [
                'value_key' => 'appreciation',
                'title' => 'Appreciation',
                'description' => 'Personal acknowledgment. Gratitude for efforts. Feeling valued. Sincere thanks.',
                'characteristics' => 'Genuine thanks, personal acknowledgment, feeling valued, gratitude, sincere appreciation',
                'management_strategy' => 'Regular one-on-ones, personal thank-yous, appreciation notes, show genuine gratitude',
                'order' => 7,
                'status' => 'Active',
            ],
            [
                'value_key' => 'leadership',
                'title' => 'Leadership Opportunities',
                'description' => 'Influencing others. Managing teams or projects. Decision-making authority. Strategic responsibilities.',
                'characteristics' => 'Management roles, leading, authority, influence, decision-making, strategic responsibilities',
                'management_strategy' => 'Provide leadership training, delegation opportunities, project ownership, management roles',
                'order' => 8,
                'status' => 'Active',
            ],
            [
                'value_key' => 'freedom',
                'title' => 'Freedom/Autonomy',
                'description' => 'Independence in work. Flexible methods and timing. Self-directed work. Minimal supervision.',
                'characteristics' => 'Autonomous work, flexible schedules, independent decision-making, self-directed, minimal supervision',
                'management_strategy' => 'Offer remote work, flexible hours, outcome-based management, independence, minimal micromanagement',
                'order' => 9,
                'status' => 'Active',
            ],
            [
                'value_key' => 'self_growth',
                'title' => 'Self-Growth',
                'description' => 'Learning and development. Skill acquisition. Career advancement. Personal mastery.',
                'characteristics' => 'Learning opportunities, professional development, new challenges, skill acquisition, career advancement',
                'management_strategy' => 'Provide training, mentorship, challenging projects, career paths, learning resources',
                'order' => 10,
                'status' => 'Active',
            ],
        ];

        foreach ($values as $value) {
            MotivationValue::create($value);
        }
    }
}
