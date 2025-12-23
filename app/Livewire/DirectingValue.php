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
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        return view('livewire.directing-value', [
            'values' => $this->values()
        ])->layout('layouts.app');
    }
}
