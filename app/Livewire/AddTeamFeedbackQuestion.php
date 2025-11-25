<?php
namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmTeamFeedbackQuestion;
use App\Models\HptmPrinciple;

class AddTeamFeedbackQuestion extends Component
{
    public $question_text = '';
    public $principle_id = '';
    public $principles = [];

    public function mount()
    {
        $this->principles = HptmPrinciple::orderBy('title')->get();
    }

    public function save()
    {
        $this->validate([
            'question_text' => 'required|string|max:500',
            'principle_id' => 'required|exists:hptm_principles,id',
        ]);

        HptmTeamFeedbackQuestion::create([
            'question' => $this->question_text,
            'principle_id' => $this->principle_id,
        ]);

        session()->flash('message', 'Question added successfully!');
        return redirect()->route('team-feedback.list');
    }

    public function render()
    {
        return view('livewire.add-team-feedback-question')->layout('layouts.app');
    }
}
