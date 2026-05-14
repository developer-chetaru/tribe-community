<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CotQuestion;
use App\Models\CotRoleMapOption;
use App\Models\CotTeamRoleDescription;

class CotQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all role descriptions
        $roles = CotTeamRoleDescription::where('status', 'Active')->get()->keyBy('role_key');

        $questions = [
            [
                'question' => 'What I enjoy contributing to my team (10 points across 8 statements)',
                'order' => 1,
                'options' => [
                    'pioneer' => 'Identifying improved ways of doing things',
                    'motivator' => 'Making sure everyone in the team is happy',
                    'soloist' => 'Being the best at what I do',
                    'deliberator' => 'Assessing what is required and finding the best way forward',
                    'doer' => 'Completing the activity',
                    'value_driver' => 'Delivering value in everything I do',
                    'resourcer' => 'Using the most effective teams/resources to get things done better',
                    'auditor' => 'Ensuring quality is met by all the team',
                ],
            ],
            [
                'question' => 'When working on a project, I prefer to focus on (10 points across 8 statements)',
                'order' => 2,
                'options' => [
                    'pioneer' => 'Exploring new approaches and innovative solutions',
                    'motivator' => 'Ensuring team members feel supported and valued',
                    'soloist' => 'Perfecting my individual contribution',
                    'deliberator' => 'Analyzing all options and requirements thoroughly',
                    'doer' => 'Getting started and making progress quickly',
                    'value_driver' => 'Delivering measurable results and outcomes',
                    'resourcer' => 'Optimizing team composition and resource allocation',
                    'auditor' => 'Maintaining high standards and quality control',
                ],
            ],
            [
                'question' => 'In team meetings, I am most likely to (10 points across 8 statements)',
                'order' => 3,
                'options' => [
                    'pioneer' => 'Suggest creative and innovative ideas',
                    'motivator' => 'Check in on how team members are feeling',
                    'soloist' => 'Share my expertise and best practices',
                    'deliberator' => 'Ask detailed questions and analyze options',
                    'doer' => 'Push for action items and next steps',
                    'value_driver' => 'Focus on outcomes and benefits',
                    'resourcer' => 'Identify who should handle what tasks',
                    'auditor' => 'Point out potential issues and quality concerns',
                ],
            ],
            [
                'question' => 'When facing a challenge, I typically (10 points across 8 statements)',
                'order' => 4,
                'options' => [
                    'pioneer' => 'Look for innovative ways to solve it',
                    'motivator' => 'Ensure the team stays positive and united',
                    'soloist' => 'Work independently to master the solution',
                    'deliberator' => 'Analyze the problem thoroughly before acting',
                    'doer' => 'Take immediate action to address it',
                    'value_driver' => 'Focus on the value and impact of solving it',
                    'resourcer' => 'Identify the best people and resources needed',
                    'auditor' => 'Check for quality and potential risks',
                ],
            ],
            [
                'question' => 'My ideal work environment includes (10 points across 8 statements)',
                'order' => 5,
                'options' => [
                    'pioneer' => 'Freedom to experiment and try new things',
                    'motivator' => 'A collaborative and harmonious team atmosphere',
                    'soloist' => 'Opportunities to excel and demonstrate mastery',
                    'deliberator' => 'Time to think through problems carefully',
                    'doer' => 'Clear tasks and immediate action items',
                    'value_driver' => 'Focus on meaningful outcomes and results',
                    'resourcer' => 'Efficient use of team members and resources',
                    'auditor' => 'High standards and quality processes',
                ],
            ],
            [
                'question' => 'When making decisions, I prioritize (10 points across 8 statements)',
                'order' => 6,
                'options' => [
                    'pioneer' => 'Innovative and creative solutions',
                    'motivator' => 'Team consensus and harmony',
                    'soloist' => 'Personal excellence and mastery',
                    'deliberator' => 'Thorough analysis and careful consideration',
                    'doer' => 'Quick action and implementation',
                    'value_driver' => 'Results and value delivery',
                    'resourcer' => 'Optimal resource utilization',
                    'auditor' => 'Quality and risk management',
                ],
            ],
            [
                'question' => 'I get most satisfaction from (10 points across 8 statements)',
                'order' => 7,
                'options' => [
                    'pioneer' => 'Creating new and improved ways of working',
                    'motivator' => 'Seeing the team work well together',
                    'soloist' => 'Achieving personal excellence in my work',
                    'deliberator' => 'Finding the best solution through analysis',
                    'doer' => 'Completing tasks and making progress',
                    'value_driver' => 'Delivering valuable outcomes',
                    'resourcer' => 'Optimizing team performance',
                    'auditor' => 'Ensuring quality and standards',
                ],
            ],
            [
                'question' => 'When working with others, I contribute most by (10 points across 8 statements)',
                'order' => 8,
                'options' => [
                    'pioneer' => 'Bringing fresh ideas and innovation',
                    'motivator' => 'Maintaining team harmony and morale',
                    'soloist' => 'Providing expert knowledge and skills',
                    'deliberator' => 'Offering careful analysis and planning',
                    'doer' => 'Taking action and getting things done',
                    'value_driver' => 'Focusing on results and value',
                    'resourcer' => 'Organizing people and resources effectively',
                    'auditor' => 'Ensuring quality and attention to detail',
                ],
            ],
            [
                'question' => 'I am most energized when (10 points across 8 statements)',
                'order' => 9,
                'options' => [
                    'pioneer' => 'Exploring new possibilities and innovations',
                    'motivator' => 'The team is happy and working well together',
                    'soloist' => 'I can excel and demonstrate my expertise',
                    'deliberator' => 'I can analyze and plan thoroughly',
                    'doer' => 'I can take action and make progress',
                    'value_driver' => 'I can deliver meaningful results',
                    'resourcer' => 'I can optimize team and resource usage',
                    'auditor' => 'I can ensure quality and standards',
                ],
            ],
            [
                'question' => 'My natural approach to work is to (10 points across 8 statements)',
                'order' => 10,
                'options' => [
                    'pioneer' => 'Find better and more innovative ways',
                    'motivator' => 'Keep everyone happy and aligned',
                    'soloist' => 'Be the best at what I do',
                    'deliberator' => 'Think things through carefully',
                    'doer' => 'Get things done quickly',
                    'value_driver' => 'Focus on delivering value',
                    'resourcer' => 'Use resources most effectively',
                    'auditor' => 'Ensure quality in everything',
                ],
            ],
            [
                'question' => 'In a team project, I naturally take on the role of (10 points across 8 statements)',
                'order' => 11,
                'options' => [
                    'pioneer' => 'The innovator who suggests new approaches',
                    'motivator' => 'The team builder who keeps everyone happy',
                    'soloist' => 'The expert who delivers excellence',
                    'deliberator' => 'The analyst who plans carefully',
                    'doer' => 'The executor who gets things done',
                    'value_driver' => 'The results-focused member',
                    'resourcer' => 'The organizer who optimizes resources',
                    'auditor' => 'The quality controller',
                ],
            ],
            [
                'question' => 'When I see a problem, I immediately think (10 points across 8 statements)',
                'order' => 12,
                'options' => [
                    'pioneer' => 'How can we solve this in a new way?',
                    'motivator' => 'How is this affecting the team?',
                    'soloist' => 'How can I solve this best?',
                    'deliberator' => 'What are all the factors to consider?',
                    'doer' => 'What action can I take now?',
                    'value_driver' => 'What value will solving this create?',
                    'resourcer' => 'Who should handle this?',
                    'auditor' => 'What could go wrong?',
                ],
            ],
        ];

        foreach ($questions as $questionData) {
            // Create or update question
            $question = CotQuestion::updateOrCreate(
                ['question' => $questionData['question']],
                [
                    'question' => $questionData['question'],
                    'order' => $questionData['order'],
                    'status' => 'Active',
                ]
            );

            // Create 8 options for this question
            $optionOrder = 0;
            foreach ($questionData['options'] as $roleKey => $optionText) {
                $role = $roles->get($roleKey);
                
                if ($role) {
                    CotRoleMapOption::updateOrCreate(
                        [
                            'categoryId' => $question->id,
                            'role_description_id' => $role->id,
                        ],
                        [
                            'maper' => $optionText,
                            'maper_key' => strtolower(str_replace([' ', '?', '!'], '_', $optionText)),
                            'categoryId' => $question->id,
                            'role_description_id' => $role->id,
                            'short_description' => $optionText,
                            'long_description' => $optionText,
                            'status' => 'Active',
                        ]
                    );
                }
                $optionOrder++;
            }
        }
    }
}
