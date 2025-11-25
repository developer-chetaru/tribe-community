<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Industry as IndustryName;
use Livewire\WithPagination;

class Industry extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public $valueId;

    public function delete($id)
    {
        IndustryName::findOrFail($id)->delete();

        session()->flash('message', 'Value deleted successfully!');
        session()->flash('type', 'success'); // changed from 'error' to 'success'

        // Handle pagination if last item on page deleted
        if ($this->values()->isEmpty() && $this->page > 1) {
            $this->previousPage();
        }
    }

    public function values()
    {
        return IndustryName::orderBy('id', 'desc')->paginate(8);
    }

    public function render()
    {
        return view('livewire.industry', [
            'values' => $this->values()
        ])->layout('layouts.app');
    }
}
