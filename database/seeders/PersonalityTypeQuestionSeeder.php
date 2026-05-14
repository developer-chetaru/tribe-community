<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PersonalityTypeQuestion;
use App\Models\PersonalityTypeOption;
use App\Models\PersonalityTypeValue;

class PersonalityTypeQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all personality dimensions
        $dimensions = PersonalityTypeValue::where('status', 'Active')->get()->keyBy('dimension_key');

        // Standard 5-point Likert scale options (same for all questions)
        $likertOptions = [
            ['text' => 'Disagree', 'score' => 1],
            ['text' => 'Mostly Disagree', 'score' => 2],
            ['text' => 'Neutral', 'score' => 3],
            ['text' => 'Mostly Agree', 'score' => 4],
            ['text' => 'Agree', 'score' => 5],
        ];

        $questions = [
            // Introversion/Extroversion Questions
            [
                'question' => 'I love working on my own in peace and quiet',
                'category' => 'Int',
                'summary_trait' => 'Solitary',
                'dimension_key' => 'int',
                'order' => 1,
            ],
            [
                'question' => 'I find it easy to mix in a group of people I don\'t know',
                'category' => 'Ext',
                'summary_trait' => 'Socially Versatile',
                'dimension_key' => 'ext',
                'order' => 2,
            ],
            [
                'question' => 'People find it easy to get to know me well at work',
                'category' => 'Ext',
                'summary_trait' => 'Personal',
                'dimension_key' => 'ext',
                'order' => 3,
            ],
            [
                'question' => 'I prefer to process information internally before sharing my thoughts',
                'category' => 'Int',
                'summary_trait' => 'Thinker',
                'dimension_key' => 'int',
                'order' => 4,
            ],
            [
                'question' => 'I gain energy from social interactions and group activities',
                'category' => 'Ext',
                'summary_trait' => 'Social',
                'dimension_key' => 'ext',
                'order' => 5,
            ],
            [
                'question' => 'I need quiet time alone to recharge after social activities',
                'category' => 'Int',
                'summary_trait' => 'Solitary',
                'dimension_key' => 'int',
                'order' => 6,
            ],

            // Innovative vs Logical Questions
            [
                'question' => 'I assess my ideas/opinions before voicing them',
                'category' => 'Int',
                'summary_trait' => 'Thinker',
                'dimension_key' => 'lgc',
                'order' => 7,
            ],
            [
                'question' => 'I enjoy brainstorming and exploring creative solutions',
                'category' => 'Innov',
                'summary_trait' => 'Innovative',
                'dimension_key' => 'innov',
                'order' => 8,
            ],
            [
                'question' => 'I prefer to use proven methods rather than experiment with new approaches',
                'category' => 'Lgc',
                'summary_trait' => 'Logical',
                'dimension_key' => 'lgc',
                'order' => 9,
            ],
            [
                'question' => 'I am comfortable with ambiguity and open-ended problems',
                'category' => 'Innov',
                'summary_trait' => 'Innovative',
                'dimension_key' => 'innov',
                'order' => 10,
            ],
            [
                'question' => 'I value data and evidence over intuition when making decisions',
                'category' => 'Lgc',
                'summary_trait' => 'Logical',
                'dimension_key' => 'lgc',
                'order' => 11,
            ],
            [
                'question' => 'I enjoy challenging conventional approaches and finding new ways',
                'category' => 'Innov',
                'summary_trait' => 'Innovative',
                'dimension_key' => 'innov',
                'order' => 12,
            ],

            // People-Focused vs Task-Focused Questions
            [
                'question' => 'I prioritize maintaining team harmony over completing tasks quickly',
                'category' => 'Ppl',
                'summary_trait' => 'People-Focused',
                'dimension_key' => 'ppl',
                'order' => 13,
            ],
            [
                'question' => 'I focus on results and getting things done efficiently',
                'category' => 'Tsk',
                'summary_trait' => 'Task-Focused',
                'dimension_key' => 'tsk',
                'order' => 14,
            ],
            [
                'question' => 'I consider how decisions will affect team members emotionally',
                'category' => 'Ppl',
                'summary_trait' => 'People-Focused',
                'dimension_key' => 'ppl',
                'order' => 15,
            ],
            [
                'question' => 'I prefer direct, objective communication focused on facts',
                'category' => 'Tsk',
                'summary_trait' => 'Task-Focused',
                'dimension_key' => 'tsk',
                'order' => 16,
            ],
            [
                'question' => 'I value building relationships with team members',
                'category' => 'Ppl',
                'summary_trait' => 'People-Focused',
                'dimension_key' => 'ppl',
                'order' => 17,
            ],
            [
                'question' => 'I prefer working independently to maximize efficiency',
                'category' => 'Tsk',
                'summary_trait' => 'Task-Focused',
                'dimension_key' => 'tsk',
                'order' => 18,
            ],

            // Structured vs Flexible Questions
            [
                'question' => 'I prefer to plan and organize my work before starting',
                'category' => 'Stru',
                'summary_trait' => 'Structured',
                'dimension_key' => 'stru',
                'order' => 19,
            ],
            [
                'question' => 'I adapt easily to changing circumstances and unexpected situations',
                'category' => 'Flex',
                'summary_trait' => 'Flexible',
                'dimension_key' => 'flex',
                'order' => 20,
            ],
            [
                'question' => 'I follow schedules and deadlines strictly',
                'category' => 'Stru',
                'summary_trait' => 'Structured',
                'dimension_key' => 'stru',
                'order' => 21,
            ],
            [
                'question' => 'I am comfortable with spontaneity and last-minute changes',
                'category' => 'Flex',
                'summary_trait' => 'Flexible',
                'dimension_key' => 'flex',
                'order' => 22,
            ],
            [
                'question' => 'I complete one task before starting another',
                'category' => 'Stru',
                'summary_trait' => 'Structured',
                'dimension_key' => 'stru',
                'order' => 23,
            ],
            [
                'question' => 'I can easily multi-task and switch between different activities',
                'category' => 'Flex',
                'summary_trait' => 'Flexible',
                'dimension_key' => 'flex',
                'order' => 24,
            ],

            // Detail-Oriented/Observant Questions
            [
                'question' => 'I notice the small details around me',
                'category' => 'Int',
                'summary_trait' => 'Observant',
                'dimension_key' => 'int',
                'order' => 25,
            ],
            [
                'question' => 'I catch every detail of what is discussed with me',
                'category' => 'Int',
                'summary_trait' => 'Attentive',
                'dimension_key' => 'int',
                'order' => 26,
            ],
            [
                'question' => 'I pay attention to subtle cues and nuances in communication',
                'category' => 'Int',
                'summary_trait' => 'Observant',
                'dimension_key' => 'int',
                'order' => 27,
            ],

            // Reflective/Thinker Questions
            [
                'question' => 'I spend time each day reflecting on what has happened',
                'category' => 'Int',
                'summary_trait' => 'Reflective',
                'dimension_key' => 'int',
                'order' => 28,
            ],
            [
                'question' => 'I think carefully before making important decisions',
                'category' => 'Int',
                'summary_trait' => 'Thinker',
                'dimension_key' => 'int',
                'order' => 29,
            ],
        ];

        foreach ($questions as $questionData) {
            $dimension = $dimensions->get($questionData['dimension_key']);
            
            // Create or update question
            $question = PersonalityTypeQuestion::updateOrCreate(
                ['question' => $questionData['question']],
                [
                    'question' => $questionData['question'],
                    'category' => $questionData['category'],
                    'personality_type_value_id' => $dimension ? $dimension->id : null,
                    'summary_trait' => $questionData['summary_trait'],
                    'order' => $questionData['order'],
                    'status' => 'Active',
                ]
            );

            // Delete existing options and create new ones with 5-point Likert scale
            PersonalityTypeOption::where('question_id', $question->id)->delete();

            foreach ($likertOptions as $index => $optionData) {
                PersonalityTypeOption::create([
                    'question_id' => $question->id,
                    'option_text' => $optionData['text'],
                    'personality_type_value_id' => $dimension ? $dimension->id : null,
                    'score_value' => $optionData['score'],
                    'order' => $index,
                    'status' => 'Active',
                ]);
            }
        }
    }
}
