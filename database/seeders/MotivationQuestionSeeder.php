<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MotivationQuestion;
use App\Models\MotivationOption;
use App\Models\MotivationValue;

class MotivationQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $values = [];
        foreach (['financial_security', 'stress_free', 'risk_free', 'job_structure', 'teamwork', 'recognition', 'appreciation', 'leadership', 'freedom', 'self_growth'] as $key) {
            $values[$key] = MotivationValue::where('value_key', $key)->first();
        }

        $questions = [
            [
                'question' => 'I enjoy work more when:',
                'options' => [
                    ['text' => 'I work as part of a team', 'value' => 'teamwork', 'label' => 'Option A'],
                    ['text' => 'I am not under pressure', 'value' => 'stress_free', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'My biggest fears in relation to work are:',
                'options' => [
                    ['text' => 'My role being threatened or in jeopardy', 'value' => 'financial_security', 'label' => 'Option A'],
                    ['text' => 'Having conflict within my team', 'value' => 'teamwork', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'I believe I work better when:',
                'options' => [
                    ['text' => 'I feel given credit or being recognized', 'value' => 'recognition', 'label' => 'Option A'],
                    ['text' => 'I am not judged and can try new way to average success', 'value' => 'risk_free', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'What matters most to me is:',
                'options' => [
                    ['text' => 'Having a stable income and job security', 'value' => 'financial_security', 'label' => 'Option A'],
                    ['text' => 'Having opportunities to learn and grow', 'value' => 'self_growth', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'I prefer work environments that:',
                'options' => [
                    ['text' => 'Have clear structure and defined processes', 'value' => 'job_structure', 'label' => 'Option A'],
                    ['text' => 'Allow me freedom to work independently', 'value' => 'freedom', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'I am most motivated by:',
                'options' => [
                    ['text' => 'Public recognition and awards', 'value' => 'recognition', 'label' => 'Option A'],
                    ['text' => 'Personal appreciation and feeling valued', 'value' => 'appreciation', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'I thrive when:',
                'options' => [
                    ['text' => 'I have opportunities to lead and influence', 'value' => 'leadership', 'label' => 'Option A'],
                    ['text' => 'I can work in a calm, low-pressure environment', 'value' => 'stress_free', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'I value:',
                'options' => [
                    ['text' => 'Collaborating with others on projects', 'value' => 'teamwork', 'label' => 'Option A'],
                    ['text' => 'Working on tasks with minimal risk and uncertainty', 'value' => 'risk_free', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'I prefer:',
                'options' => [
                    ['text' => 'Clear job descriptions and defined responsibilities', 'value' => 'job_structure', 'label' => 'Option A'],
                    ['text' => 'Flexibility to explore and try new approaches', 'value' => 'freedom', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'I am driven by:',
                'options' => [
                    ['text' => 'Opportunities for professional development', 'value' => 'self_growth', 'label' => 'Option A'],
                    ['text' => 'Financial stability and security', 'value' => 'financial_security', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'I work best when:',
                'options' => [
                    ['text' => 'I receive genuine appreciation for my efforts', 'value' => 'appreciation', 'label' => 'Option A'],
                    ['text' => 'I have leadership responsibilities', 'value' => 'leadership', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'I prefer:',
                'options' => [
                    ['text' => 'Low-stress, balanced work environment', 'value' => 'stress_free', 'label' => 'Option A'],
                    ['text' => 'Clear structure and organized processes', 'value' => 'job_structure', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'I am motivated by:',
                'options' => [
                    ['text' => 'Working with a team towards common goals', 'value' => 'teamwork', 'label' => 'Option A'],
                    ['text' => 'Having autonomy in how I complete my work', 'value' => 'freedom', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'I value:',
                'options' => [
                    ['text' => 'Public acknowledgment of my achievements', 'value' => 'recognition', 'label' => 'Option A'],
                    ['text' => 'Continuous learning and skill development', 'value' => 'self_growth', 'label' => 'Option B'],
                ],
            ],
            [
                'question' => 'I prefer work that:',
                'options' => [
                    ['text' => 'Has established procedures and minimal risk', 'value' => 'risk_free', 'label' => 'Option A'],
                    ['text' => 'Allows me to lead and make decisions', 'value' => 'leadership', 'label' => 'Option B'],
                ],
            ],
        ];

        foreach ($questions as $index => $qData) {
            $question = MotivationQuestion::create([
                'question' => $qData['question'],
                'order' => $index + 1,
                'status' => 'Active',
            ]);

            foreach ($qData['options'] as $optIndex => $option) {
                $motivationValue = $values[$option['value']] ?? $values['teamwork'];

                MotivationOption::create([
                    'question_id' => $question->id,
                    'motivation_value_id' => $motivationValue->id,
                    'option_text' => $option['text'],
                    'option_label' => $option['label'],
                    'order' => $optIndex + 1,
                    'status' => 'Active',
                ]);
            }
        }
    }
}
