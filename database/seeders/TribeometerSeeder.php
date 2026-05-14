<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TribeometerValue;
use App\Models\TribeometerOption;
use App\Models\TribeometerQuestion;
use Illuminate\Support\Facades\DB;

class TribeometerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Create default values
            $values = [
                [
                    'value_key' => 'directed',
                    'title' => 'Directed',
                    'description' => 'Clarity of vision, strategic alignment, and goal orientation',
                    'order' => 1,
                    'status' => 'Active',
                ],
                [
                    'value_key' => 'committed',
                    'title' => 'Committed',
                    'description' => 'Dedication, loyalty, and investment in organizational success',
                    'order' => 2,
                    'status' => 'Active',
                ],
                [
                    'value_key' => 'selfless',
                    'title' => 'Selfless',
                    'description' => 'Team orientation, collaboration, and putting collective goals above individual interests',
                    'order' => 3,
                    'status' => 'Active',
                ],
                [
                    'value_key' => 'honesty',
                    'title' => 'Honesty',
                    'description' => 'Transparency, integrity, and truthfulness in all interactions',
                    'order' => 4,
                    'status' => 'Active',
                ],
            ];

            $valueIds = [];
            foreach ($values as $valueData) {
                $value = TribeometerValue::updateOrCreate(
                    ['value_key' => $valueData['value_key']],
                    $valueData
                );
                $valueIds[$valueData['value_key']] = $value->id;
            }

            // Create default options (4-point scale)
            $options = [
                [
                    'option_name' => 'Completely Disagree',
                    'value_score' => 0,
                    'status' => 'Active',
                ],
                [
                    'option_name' => 'Disagree More Than Agree',
                    'value_score' => 1,
                    'status' => 'Active',
                ],
                [
                    'option_name' => 'Agree More Than Disagree',
                    'value_score' => 2,
                    'status' => 'Active',
                ],
                [
                    'option_name' => 'Completely Agree',
                    'value_score' => 3,
                    'status' => 'Active',
                ],
            ];

            foreach ($options as $optionData) {
                TribeometerOption::updateOrCreate(
                    ['value_score' => $optionData['value_score']],
                    $optionData
                );
            }

            // Create sample questions for each value
            $questions = [
                // Directed questions
                [
                    'question' => 'I hear our Vision every day',
                    'measure' => 'Vision Communication',
                    'value_id' => $valueIds['directed'],
                    'status' => 'Active',
                ],
                [
                    'question' => 'I understand how my work contributes to our vision',
                    'measure' => 'Vision Understanding',
                    'value_id' => $valueIds['directed'],
                    'status' => 'Active',
                ],
                [
                    'question' => 'Our leadership provides clear strategic direction',
                    'measure' => 'Strategic Clarity',
                    'value_id' => $valueIds['directed'],
                    'status' => 'Active',
                ],
                [
                    'question' => 'I am confident in where the organization is headed',
                    'measure' => 'Confidence in Direction',
                    'value_id' => $valueIds['directed'],
                    'status' => 'Active',
                ],
                // Committed questions
                [
                    'question' => 'Our Vision makes me proud',
                    'measure' => 'Pride in Vision',
                    'value_id' => $valueIds['committed'],
                    'status' => 'Active',
                ],
                [
                    'question' => 'I would recommend this organization as a great place to work',
                    'measure' => 'Advocacy',
                    'value_id' => $valueIds['committed'],
                    'status' => 'Active',
                ],
                [
                    'question' => 'I am willing to put in extra effort to help the organization succeed',
                    'measure' => 'Extra Effort',
                    'value_id' => $valueIds['committed'],
                    'status' => 'Active',
                ],
                [
                    'question' => 'I feel a strong sense of belonging to this organization',
                    'measure' => 'Belonging',
                    'value_id' => $valueIds['committed'],
                    'status' => 'Active',
                ],
                // Selfless questions
                [
                    'question' => 'We all put everything we\'ve got into achieving our Vision',
                    'measure' => 'Collective Effort',
                    'value_id' => $valueIds['selfless'],
                    'status' => 'Active',
                ],
                [
                    'question' => 'I prioritize team success over personal recognition',
                    'measure' => 'Team Priority',
                    'value_id' => $valueIds['selfless'],
                    'status' => 'Active',
                ],
                [
                    'question' => 'I willingly share knowledge and resources with colleagues',
                    'measure' => 'Knowledge Sharing',
                    'value_id' => $valueIds['selfless'],
                    'status' => 'Active',
                ],
                [
                    'question' => 'I support my teammates even when it doesn\'t directly benefit me',
                    'measure' => 'Supportive Behavior',
                    'value_id' => $valueIds['selfless'],
                    'status' => 'Active',
                ],
                // Honesty questions
                [
                    'question' => 'Our leadership is transparent about organizational challenges',
                    'measure' => 'Transparency',
                    'value_id' => $valueIds['honesty'],
                    'status' => 'Active',
                ],
                [
                    'question' => 'I feel safe speaking up about problems or concerns',
                    'measure' => 'Psychological Safety',
                    'value_id' => $valueIds['honesty'],
                    'status' => 'Active',
                ],
                [
                    'question' => 'The organization acts with integrity in all situations',
                    'measure' => 'Integrity',
                    'value_id' => $valueIds['honesty'],
                    'status' => 'Active',
                ],
                [
                    'question' => 'Leaders do what they say they will do',
                    'measure' => 'Leader Consistency',
                    'value_id' => $valueIds['honesty'],
                    'status' => 'Active',
                ],
            ];

            foreach ($questions as $questionData) {
                TribeometerQuestion::updateOrCreate(
                    [
                        'question' => $questionData['question'],
                        'value_id' => $questionData['value_id'],
                    ],
                    $questionData
                );
            }
        });
    }
}
