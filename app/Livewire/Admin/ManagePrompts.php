<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Log;

class ManagePrompts extends Component
{
    public $weeklySummaryPrompt;
    public $monthlySummaryPrompt;
    public $showSuccessMessage = false;

    public function mount()
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        // Load existing prompts
        $this->weeklySummaryPrompt = AppSetting::getValue('weekly_summary_prompt', '');
        $this->monthlySummaryPrompt = AppSetting::getValue('monthly_summary_prompt', '');
    }

    public function savePrompts()
    {
        $this->validate([
            'weeklySummaryPrompt' => 'required|string',
            'monthlySummaryPrompt' => 'required|string',
        ]);

        try {
            AppSetting::setValue(
                'weekly_summary_prompt',
                $this->weeklySummaryPrompt,
                'textarea',
                'Prompt template for weekly summary generation. Use {weekLabel} and {entries} as placeholders.'
            );

            AppSetting::setValue(
                'monthly_summary_prompt',
                $this->monthlySummaryPrompt,
                'textarea',
                'Prompt template for monthly summary generation. Use {monthName} and {entries} as placeholders.'
            );

            $this->showSuccessMessage = true;
            session()->flash('success', 'Prompts updated successfully!');
            
            Log::info('Prompts updated by admin: ' . auth()->user()->id);
        } catch (\Exception $e) {
            Log::error('Error updating prompts: ' . $e->getMessage());
            session()->flash('error', 'Failed to update prompts: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.manage-prompts')->layout('layouts.app');
    }
}
