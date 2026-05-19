<?php

namespace App\Console\Commands;

use App\Models\PersonalityTypeOption;
use App\Models\PersonalityTypeQuestion;
use App\Models\PersonalityTypeValue;
use Illuminate\Console\Command;

class SeedPersonalityTypeCommand extends Command
{
    protected $signature = 'personality-type:seed {--force : Run without confirmation in production}';

    protected $description = 'Seed personality type dimensions, questions, and options (required for the assessment page)';

    public function handle(): int
    {
        if ($this->laravel->environment('production') && ! $this->option('force')) {
            if (! $this->confirm('Application is in production. Continue seeding personality type data?')) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }
        }

        $this->info('Seeding personality type values…');
        $this->call('db:seed', ['--class' => 'PersonalityTypeValueSeeder', '--force' => true]);

        $this->info('Seeding personality type questions…');
        $this->call('db:seed', ['--class' => 'PersonalityTypeQuestionSeeder', '--force' => true]);

        $dimensions = PersonalityTypeValue::where('status', 'Active')->count();
        $questions = PersonalityTypeQuestion::where('status', 'Active')->count();
        $options = PersonalityTypeOption::where('status', 'Active')->count();

        $this->newLine();
        $this->table(
            ['Table', 'Active rows'],
            [
                ['personality_type_values', $dimensions],
                ['personality_type_questions', $questions],
                ['personality_type_options', $options],
            ]
        );

        if ($questions === 0) {
            $this->error('No questions were seeded. Check migrations and database connection.');

            return self::FAILURE;
        }

        $this->info('Personality Type assessment data is ready.');

        return self::SUCCESS;
    }
}
