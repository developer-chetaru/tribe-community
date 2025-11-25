<?php

namespace App\Livewire;

use Livewire\WithPagination;
use App\Models\DotValueList;
use Livewire\Component;

class DirectingValue extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind'; 

    public function delete($id)
    {
        DotValueList::findOrFail($id)->delete();
        session()->flash('message', 'Value deleted successfully!');
        session()->flash('type', 'error'); 

        if ($this->values()->isEmpty() && $this->page > 1) {
            $this->previousPage();
        }
    }

    public function values()
    {
        return DotValueList::orderBy('id', 'desc')->paginate(8);
    }

    public function render()
    {
        return view('livewire.directing-value', [
            'values' => $this->values()
        ])->layout('layouts.app');
    }
}
