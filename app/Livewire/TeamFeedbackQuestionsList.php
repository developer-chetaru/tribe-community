<?php
namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmTeamFeedbackQuestion;

class TeamFeedbackQuestionsList extends Component
{
    public $questions;

    public function mount()
    {
        $this->loadQuestions();
    }

    public function loadQuestions()
    {
        $this->questions = HptmTeamFeedbackQuestion::with('principle')
            ->orderBy('created_at', 'desc')->get();
    }

    public function delete($id)
    {
        HptmTeamFeedbackQuestion::findOrFail($id)->delete();
        session()->flash('message', 'Question deleted successfully!');
        $this->loadQuestions();
    }

    public function render()
    {
        return view('livewire.team-feedback-questions-list')->layout('layouts.app');
    }
}
