<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('text'); // text, textarea, json
            $table->text('description')->nullable();
            $table->timestamps();
        });
        
        // Insert default prompts
        DB::table('app_settings')->insert([
            [
                'key' => 'weekly_summary_prompt',
                'value' => 'Generate a professional weekly emotional summary for the user based strictly on the following daily sentiment data from {weekLabel}:

{entries}

Important writing requirements:
- Do NOT start with greetings.
- Do NOT address the user directly.
- Write a polished, insightful summary of emotional trends.
- Provide 3–5 sentences analyzing patterns across the week.
- Tone should be professional, warm, supportive, and not casual.
- Focus only on the user\'s emotional journey.
- Do NOT include organisational-level references.',
                'type' => 'textarea',
                'description' => 'Prompt template for weekly summary generation. Use {weekLabel} and {entries} as placeholders.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'monthly_summary_prompt',
                'value' => 'Create a polished and professional monthly emotional summary for the user based on their daily mood entries.

Month: {monthName}

Daily Entries:
{entries}

Writing Guidelines:
- Do NOT start with any greeting (no "Hi", "Hello", "Hey", etc.).
- Do NOT speak directly to the user.
- Start immediately with a clear insight about the month.
- Use a neutral, warm, and professional tone.
- Summarize the overall emotional trend for the month.
- Highlight periods of consistency, improvements, or challenges.
- Provide gentle encouragement without sounding overly casual.
- Avoid repeating words or notes exactly from the user\'s entries.
- Keep the summary concise: 4–6 sentences maximum.
- End with an uplifting, forward-looking statement.',
                'type' => 'textarea',
                'description' => 'Prompt template for monthly summary generation. Use {monthName} and {entries} as placeholders.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
