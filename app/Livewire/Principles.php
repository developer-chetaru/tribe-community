<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\HptmPrinciple;

class Principles extends Component
{
    public $principles = [];

    public function mount()
    {
        $this->loadPrinciples();
    }

    public function loadPrinciples()
    {
        $this->principles = HptmPrinciple::orderBy('priority', 'ASC')->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'priority' => $item->priority,
            ];
        })->toArray();
    }

    public function updatePrinciple($id)
    {
        $index = array_search($id, array_column($this->principles, 'id'));

        if ($index !== false) {
            $this->validate([
                "principles.$index.title" => 'required|string',
                "principles.$index.description" => 'required|string',
            ]);

            $principle = HptmPrinciple::find($id);
            if ($principle) {
                $principle->title = $this->principles[$index]['title'];
                $principle->description = $this->principles[$index]['description'];
                $principle->save();

                session()->flash('message', 'Principle updated successfully.');
                $this->loadPrinciples();
            }
        }
    }

    public function deletePrinciple($id)
    {
        $principle = HptmPrinciple::find($id);

        if ($principle) {
            $principle->delete();
            session()->flash('message', 'Principle deleted successfully.');
            $this->loadPrinciples();
        }
    }

    public function render()
    {
        return view('livewire.principles')->layout('layouts.app');
    }
}
