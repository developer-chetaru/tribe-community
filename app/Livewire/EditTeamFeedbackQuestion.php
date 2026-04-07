<?php
namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmTeamFeedbackQuestion;
use App\Models\HptmPrinciple;

class EditTeamFeedbackQuestion extends Component
{
    public $question_id;
    public $question_text = '';
    public $principle_id = '';
    public $principles = [];

    public function mount($id)
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $this->principles = HptmPrinciple::orderBy('title')->get();

        $question = HptmTeamFeedbackQuestion::findOrFail($id);
        $this->question_id = $question->id;
        $this->question_text = $question->question;
        $this->principle_id = $question->principle_id;
    }

    public function update()
    {
        $this->validate([
            'question_text' => 'required|string|max:500',
            'principle_id' => 'required|exists:hptm_principles,id',
        ]);

        $question = HptmTeamFeedbackQuestion::findOrFail($this->question_id);
        $question->update([
            'question' => $this->question_text,
            'principle_id' => $this->principle_id,
        ]);

        session()->flash('message', 'Question updated successfully!');
        return redirect()->route('team-feedback.list');
    }

    public function render()
    {
        return view('livewire.edit-team-feedback-question')->layout('layouts.app');
    }
}
