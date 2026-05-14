<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PersonalityTypeValue;

class PersonalityTypeValueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dimensions = [
            // Introversion vs Extroversion
            [
                'dimension_key' => 'int',
                'title' => 'Introverted',
                'description' => 'More likely to internalise ideas, thoughts and feelings. Preference for working alone or in small groups. Gains energy from solitary activities. Thinks before speaking. Prefers deep conversations over small talk. Reflects internally before acting.',
                'characteristics' => 'Internal processing, reflective, thoughtful, independent, focused, introspective',
                'real_world_applications' => 'Research roles, analytical positions, focused project work, individual contributor roles, remote work, research positions',
                'team_collaboration_tips' => 'Respect focused work time. Minimize interruptions. Provide clear objectives. Allow independent execution. Prefer written communication, async updates. Optional video, detailed agendas.',
                'order' => 1,
                'status' => 'Active',
            ],
            [
                'dimension_key' => 'ext',
                'title' => 'Extroverted',
                'description' => 'More likely to externalise ideas, thoughts and feelings. Thrives in group settings. Energized by social interaction. Thinks out loud. Enjoys networking and meeting new people. Acts then reflects.',
                'characteristics' => 'External processing, expressive, social, collaborative, energetic, outgoing',
                'real_world_applications' => 'Sales, customer service, team leadership, public relations, team-based projects, collaboration platforms, open office environments',
                'team_collaboration_tips' => 'Encourage collaboration. Provide social interaction. Value relationships. Include in team activities. Daily standup option, water cooler chat room. Video calls, real-time discussion.',
                'order' => 2,
                'status' => 'Active',
            ],
            // Innovative vs Logical
            [
                'dimension_key' => 'innov',
                'title' => 'Innovative',
                'description' => 'More likely to apply creative thinking to thoughts and actions. Creative thinking and brainstorming. Enjoys experimentation. Comfortable with ambiguity. Seeks novel solutions. Challenges conventional approaches.',
                'characteristics' => 'Creative, experimental, flexible, visionary, original, risk-taking',
                'real_world_applications' => 'Product development, marketing, R&D, strategic planning, design, innovation, brainstorming, marketing',
                'team_collaboration_tips' => 'Encourage brainstorming. Value new ideas. Allow creative freedom. Provide inspiration. Avoid rigid constraints. Pre-reading for processing ahead. Breakout rooms for discussion.',
                'order' => 3,
                'status' => 'Active',
            ],
            [
                'dimension_key' => 'lgc',
                'title' => 'Logical',
                'description' => 'More likely to apply logical progression of thoughts and actions. Analytical and systematic thinking. Prefers proven methods. Values data and evidence. Sequential problem-solving. Risk-averse approach.',
                'characteristics' => 'Analytical, systematic, methodical, data-driven, sequential, evidence-based',
                'real_world_applications' => 'Finance, operations, quality assurance, data analysis, technical architecture, code review',
                'team_collaboration_tips' => 'Provide detailed briefs before meetings. Allow time for analysis. Present data and facts. Avoid rushing decisions. Pre-meeting briefs, time to analyze.',
                'order' => 4,
                'status' => 'Active',
            ],
            // People-Focused vs Task-Focused
            [
                'dimension_key' => 'ppl',
                'title' => 'People-Focused',
                'description' => 'More likely to consider emotional outcomes than completing a task. Prioritizes relationships and harmony. Considers emotional impact of decisions. Values team morale. Empathetic communication style. Collaborative approach.',
                'characteristics' => 'Empathetic, relationship-oriented, harmony-seeking, team-focused, supportive, considerate',
                'real_world_applications' => 'HR, counseling, team building, client relations, team projects, customer relations, training',
                'team_collaboration_tips' => 'Value their empathy. Encourage collaboration. Provide social interaction. Value relationships. Include in team activities. Designate as team harmony keeper.',
                'order' => 5,
                'status' => 'Active',
            ],
            [
                'dimension_key' => 'tsk',
                'title' => 'Task-Focused',
                'description' => 'More likely to prioritise completing a task rather than considering emotional needs. Results and goal-oriented. Objective decision-making. Efficiency-driven. Direct communication style. Independent work preference.',
                'characteristics' => 'Goal-oriented, efficient, objective, direct, results-driven, independent',
                'real_world_applications' => 'Project management, engineering, production, logistics, technical troubleshooting, quality control',
                'team_collaboration_tips' => 'Focus on practical outcomes. Provide clear action steps. Value hands-on experience. Avoid over-theorizing. Recognize individual achievements. Don\'t require forced collaboration.',
                'order' => 6,
                'status' => 'Active',
            ],
            // Structured vs Flexible
            [
                'dimension_key' => 'stru',
                'title' => 'Structured',
                'description' => 'Prefer planned and organised approach to life. Prefers planning and organization. Follows schedules and deadlines. Values order and predictability. Methodical approach. Completes tasks before starting new ones.',
                'characteristics' => 'Organized, planned, methodical, predictable, systematic, disciplined',
                'real_world_applications' => 'Operations, compliance, accounting, administration, process improvement, quality control',
                'team_collaboration_tips' => 'Provide structure and clear processes. Set deadlines. Value organization. Allow planning time. Scheduled communication, same time daily.',
                'order' => 7,
                'status' => 'Active',
            ],
            [
                'dimension_key' => 'flex',
                'title' => 'Flexible',
                'description' => 'Adapts to changing circumstances. Comfortable with spontaneity. Open-ended approach. Multi-tasks easily. Explores options before committing.',
                'characteristics' => 'Adaptable, spontaneous, open-minded, multi-tasking, exploratory, versatile',
                'real_world_applications' => 'Consulting, crisis management, entrepreneurship, creative roles, fast-paced environments',
                'team_collaboration_tips' => 'Allow flexibility. Provide options. Value adaptability. Don\'t over-structure. Multiple channels, flexible timing, no pressure.',
                'order' => 8,
                'status' => 'Active',
            ],
        ];

        foreach ($dimensions as $dimension) {
            PersonalityTypeValue::updateOrCreate(
                ['dimension_key' => $dimension['dimension_key']],
                $dimension
            );
        }
    }
}
