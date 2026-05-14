<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CultureStructureQuestion;
use App\Models\CultureStructureOption;
use App\Models\CultureStructureType;

class CultureStructureQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clanType = CultureStructureType::where('type_key', 'clan')->first();
        $adhocracyType = CultureStructureType::where('type_key', 'adhocracy')->first();
        $marketType = CultureStructureType::where('type_key', 'market')->first();
        $hierarchyType = CultureStructureType::where('type_key', 'hierarchy')->first();

        $questions = [
            [
                'question' => 'The company\'s leadership style is characterized by...',
                'options' => [
                    ['text' => 'Nurturing and supportive', 'type' => 'clan'],
                    ['text' => 'Innovative and risk-taking', 'type' => 'adhocracy'],
                    ['text' => 'Competitive and aggressive', 'type' => 'market'],
                    ['text' => 'Coordinating and efficient', 'type' => 'hierarchy'],
                ],
            ],
            [
                'question' => 'When conflicts arise, they are typically resolved by...',
                'options' => [
                    ['text' => 'Team discussion and consensus', 'type' => 'clan'],
                    ['text' => 'Creative problem-solving', 'type' => 'adhocracy'],
                    ['text' => 'Competitive advantage analysis', 'type' => 'market'],
                    ['text' => 'Following established procedures', 'type' => 'hierarchy'],
                ],
            ],
            [
                'question' => 'What holds the organization together is...',
                'options' => [
                    ['text' => 'Loyalty and mutual trust', 'type' => 'clan'],
                    ['text' => 'Innovation and development', 'type' => 'adhocracy'],
                    ['text' => 'Achievement and goal accomplishment', 'type' => 'market'],
                    ['text' => 'Formal rules and policies', 'type' => 'hierarchy'],
                ],
            ],
            [
                'question' => 'KPIs here are used to...',
                'options' => [
                    ['text' => 'Empower what I do', 'type' => 'clan'],
                    ['text' => 'Control what we do', 'type' => 'hierarchy'],
                    ['text' => 'Inspire what we do, and control where we fail', 'type' => 'adhocracy'],
                    ['text' => 'Inspire what we do as collectivity to help the organization improve', 'type' => 'clan'],
                ],
            ],
            [
                'question' => 'People in the company are here because they...',
                'options' => [
                    ['text' => 'Believe in the job they do', 'type' => 'clan'],
                    ['text' => 'Feel secure', 'type' => 'hierarchy'],
                    ['text' => 'Feel to be here and believe in the job they do', 'type' => 'adhocracy'],
                    ['text' => 'Feel loved and motivated to trying it online', 'type' => 'clan'],
                ],
            ],
            [
                'question' => 'Managers in the organization...',
                'options' => [
                    ['text' => 'Guide and mentor where needed, administration is people', 'type' => 'clan'],
                    ['text' => 'Inspire and lead', 'type' => 'adhocracy'],
                    ['text' => 'Keep control, adherence nearly', 'type' => 'hierarchy'],
                    ['text' => 'Create and led team to become the next leader', 'type' => 'market'],
                ],
            ],
            [
                'question' => 'The organization emphasizes...',
                'options' => [
                    ['text' => 'Human development and high trust', 'type' => 'clan'],
                    ['text' => 'Innovation and growth', 'type' => 'adhocracy'],
                    ['text' => 'Competitive actions and achievement', 'type' => 'market'],
                    ['text' => 'Permanence and stability', 'type' => 'hierarchy'],
                ],
            ],
            [
                'question' => 'Success is defined as...',
                'options' => [
                    ['text' => 'Development of human resources and teamwork', 'type' => 'clan'],
                    ['text' => 'Having unique products and being a product leader', 'type' => 'adhocracy'],
                    ['text' => 'Winning in the marketplace and outpacing competition', 'type' => 'market'],
                    ['text' => 'Dependable delivery and smooth scheduling', 'type' => 'hierarchy'],
                ],
            ],
            [
                'question' => 'Decision-making is typically...',
                'options' => [
                    ['text' => 'Participative and consensus-based', 'type' => 'clan'],
                    ['text' => 'Entrepreneurial and risk-taking', 'type' => 'adhocracy'],
                    ['text' => 'Decisive and competitive', 'type' => 'market'],
                    ['text' => 'Cautious and procedural', 'type' => 'hierarchy'],
                ],
            ],
            [
                'question' => 'The work environment is...',
                'options' => [
                    ['text' => 'Personal and friendly like a family', 'type' => 'clan'],
                    ['text' => 'Dynamic and entrepreneurial', 'type' => 'adhocracy'],
                    ['text' => 'Demanding and results-driven', 'type' => 'market'],
                    ['text' => 'Formal and structured', 'type' => 'hierarchy'],
                ],
            ],
        ];

        foreach ($questions as $index => $qData) {
            $question = CultureStructureQuestion::create([
                'question' => $qData['question'],
                'order' => $index + 1,
                'status' => 'Active',
            ]);

            foreach ($qData['options'] as $optIndex => $option) {
                $typeMap = [
                    'clan' => $clanType,
                    'adhocracy' => $adhocracyType,
                    'market' => $marketType,
                    'hierarchy' => $hierarchyType,
                ];

                $cultureType = $typeMap[$option['type']] ?? $clanType;

                CultureStructureOption::create([
                    'question_id' => $question->id,
                    'culture_type_id' => $cultureType->id,
                    'option_text' => $option['text'],
                    'order' => $optIndex + 1,
                    'status' => 'Active',
                ]);
            }
        }
    }
}
