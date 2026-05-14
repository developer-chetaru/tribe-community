<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DiagnosticQuestion;
use App\Models\DiagnosticQuestionsCategory;

class DiagnosticQuestionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get categories
        $categories = DiagnosticQuestionsCategory::all()->keyBy('title');

        $questions = [
            // Personal Development
            [
                'question' => 'Everyone in this organisation promotes and prioritises personal development',
                'category' => 'Personal Development',
                'measure' => 'positive',
            ],
            [
                'question' => 'All the training I have received is relevant to me',
                'category' => 'Personal Development',
                'measure' => 'relevance',
            ],
            [
                'question' => 'Everyone receives the right level of training to do their job',
                'category' => 'Personal Development',
                'measure' => 'basic',
            ],
            [
                'question' => 'I have a personal development plan in place',
                'category' => 'Personal Development',
                'measure' => 'planned',
            ],

            // Teamwork
            [
                'question' => 'I am made to feel an important part of my team',
                'category' => 'Teamwork',
                'measure' => 'valued individuals',
            ],
            [
                'question' => 'Communication within our team is amazing',
                'category' => 'Teamwork',
                'measure' => 'communication',
            ],
            [
                'question' => 'In my team we work exceptionally well together',
                'category' => 'Teamwork',
                'measure' => 'teamwork',
            ],
            [
                'question' => 'In emergencies we pull together as a team',
                'category' => 'Teamwork',
                'measure' => 'problem solving',
            ],
            [
                'question' => 'My team is very well led',
                'category' => 'Teamwork',
                'measure' => 'leadership',
            ],

            // Leadership/Management
            [
                'question' => 'I like and trust my manager',
                'category' => 'Leadership/Management',
                'measure' => 'like and trust',
            ],
            [
                'question' => 'My manager constantly encourages me and supports me to develop my skills',
                'category' => 'Leadership/Management',
                'measure' => 'development',
            ],
            [
                'question' => 'My manager appreciates me and demonstrates this',
                'category' => 'Leadership/Management',
                'measure' => 'appreciation',
            ],
            [
                'question' => 'My manager helps me keep a realistic workload',
                'category' => 'Leadership/Management',
                'measure' => 'workload management',
            ],
            [
                'question' => 'My manager knows how to get the most out of me',
                'category' => 'Leadership/Management',
                'measure' => 'catalyst',
            ],
            [
                'question' => 'My manager has the right focus',
                'category' => 'Leadership/Management',
                'measure' => 'focus',
            ],

            // Communication
            [
                'question' => 'Communication in my organisation is excellent',
                'category' => 'Communication',
                'measure' => 'communication',
            ],
            [
                'question' => 'The organisation makes sure I only receive relevant information',
                'category' => 'Communication',
                'measure' => 'relevance',
            ],
            [
                'question' => 'I know what action is required from me in all communication',
                'category' => 'Communication',
                'measure' => 'actionable',
            ],
            [
                'question' => 'Information is always delivered well in advance of being needed',
                'category' => 'Communication',
                'measure' => 'timeliness',
            ],
            [
                'question' => 'All communication results in action',
                'category' => 'Communication',
                'measure' => 'follow up',
            ],
            [
                'question' => 'I always feel "in the loop"',
                'category' => 'Communication',
                'measure' => 'abundance',
            ],

            // Stress
            [
                'question' => 'My full talent is not being used or appreciated in this organisation',
                'category' => 'Stress',
                'measure' => 'wasted',
            ],
            [
                'question' => 'I feel stressed by the amount I work and what is expected from me',
                'category' => 'Stress',
                'measure' => 'overworked',
            ],
            [
                'question' => 'People don\'t always speak to me in a nice way',
                'category' => 'Stress',
                'measure' => 'disrespected',
            ],
            [
                'question' => 'Some people demonstrate they don\'t like me in this Organisation',
                'category' => 'Stress',
                'measure' => 'victimised',
            ],

            // Performance
            [
                'question' => 'I know what my daily targets are and I understand them',
                'category' => 'Performance',
                'measure' => 'clarity',
            ],
            [
                'question' => 'We discuss targets/goals regularly within my team',
                'category' => 'Performance',
                'measure' => 'in focus',
            ],
            [
                'question' => 'My targets motivate me to work as hard as I can',
                'category' => 'Performance',
                'measure' => 'motivational',
            ],
            [
                'question' => 'My targets are relevant to me',
                'category' => 'Performance',
                'measure' => 'relevance',
            ],
            [
                'question' => 'I can achieve my targets within normal working hours',
                'category' => 'Performance',
                'measure' => 'achievable',
            ],
        ];

        foreach ($questions as $questionData) {
            $category = $categories->get($questionData['category']);
            
            if ($category) {
                DiagnosticQuestion::firstOrCreate(
                    [
                        'question' => $questionData['question'],
                    ],
                    [
                        'question' => $questionData['question'],
                        'measure' => $questionData['measure'],
                        'category_id' => $category->id,
                        'status' => 'Active',
                    ]
                );
            }
        }
    }
}

