<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CotTeamRoleDescription;

class CotTeamRoleDescriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'role_key' => 'pioneer',
                'title' => 'Pioneer',
                'value_focus' => 'Innovation',
                'description' => 'Identifies improved ways of doing things. Focuses on innovation and finding better approaches.',
                'focus' => 'Identifying improved ways of doing things',
                'standard_questions' => 'Why are we doing this? What will be the outcome? Who\'ll benefit?',
                'disruption' => 'Extreme frustration with any perceived non-value adding tasks. Lack of attention to people\'s needs.',
                'order' => 1,
                'status' => 'Active',
            ],
            [
                'role_key' => 'motivator',
                'title' => 'Motivator',
                'value_focus' => 'Team Harmony',
                'description' => 'Makes sure everyone in the team is happy. Focuses on team harmony and collaboration.',
                'focus' => 'Making sure everyone in the team is happy',
                'standard_questions' => 'How is everyone feeling? Are we all aligned? What can we do to support each other?',
                'disruption' => 'Conflict avoidance. Difficulty making tough decisions that might upset team members.',
                'order' => 2,
                'status' => 'Active',
            ],
            [
                'role_key' => 'soloist',
                'title' => 'Soloist',
                'value_focus' => 'Excellence',
                'description' => 'Being the best at what I do. Focuses on personal excellence and mastery.',
                'focus' => 'Being the best at what I do',
                'standard_questions' => 'How can I improve? What skills do I need to develop? Am I performing at my best?',
                'disruption' => 'Perfectionism. Difficulty delegating. May struggle with team collaboration.',
                'order' => 3,
                'status' => 'Active',
            ],
            [
                'role_key' => 'deliberator',
                'title' => 'Deliberator',
                'value_focus' => 'Analysis',
                'description' => 'Assessing requirements and finding the best way forward. Focuses on thorough analysis.',
                'focus' => 'Assessing what is required and finding the best way forward',
                'standard_questions' => 'What are the requirements? What are the options? What are the risks?',
                'disruption' => 'Analysis paralysis. Overthinking. Delayed decision-making.',
                'order' => 4,
                'status' => 'Active',
            ],
            [
                'role_key' => 'doer',
                'title' => 'Doer',
                'value_focus' => 'Action',
                'description' => 'Completing the activity. Focuses on getting things done and taking action.',
                'focus' => 'Completing the activity',
                'standard_questions' => 'What needs to be done? When does it need to be done? How do we get started?',
                'disruption' => 'Rushing into action without planning. Impatience with process. May skip important steps.',
                'order' => 5,
                'status' => 'Active',
            ],
            [
                'role_key' => 'value_driver',
                'title' => 'Value Driver',
                'value_focus' => 'Results',
                'description' => 'Delivering value in everything I do. Focuses on results and value creation.',
                'focus' => 'Delivering value in everything I do',
                'standard_questions' => 'Why are we doing this? What will be the outcome? Who\'ll benefit?',
                'disruption' => 'Extreme frustration with any perceived non-value adding tasks. Lack of attention to people\'s needs.',
                'order' => 6,
                'status' => 'Active',
            ],
            [
                'role_key' => 'resourcer',
                'title' => 'Resourcer',
                'value_focus' => 'Optimization',
                'description' => 'Using the most effective teams/resources to get things done better. Focuses on resource optimization.',
                'focus' => 'Using the most effective teams/resources to get things done better',
                'standard_questions' => 'Who has the right skills? What resources do we need? How can we optimize?',
                'disruption' => 'Over-optimization. May lose sight of the bigger picture. Resource hoarding.',
                'order' => 7,
                'status' => 'Active',
            ],
            [
                'role_key' => 'auditor',
                'title' => 'Auditor',
                'value_focus' => 'Quality',
                'description' => 'Ensuring that quality is met by all the team. Focuses on quality assurance.',
                'focus' => 'Ensuring quality is met by all the team',
                'standard_questions' => 'Is this meeting our standards? What could go wrong? Have we checked everything?',
                'disruption' => 'Overly critical. May slow down progress. Perfectionism that blocks delivery.',
                'order' => 8,
                'status' => 'Active',
            ],
        ];

        foreach ($roles as $role) {
            CotTeamRoleDescription::updateOrCreate(
                ['role_key' => $role['role_key']],
                $role
            );
        }
    }
}
