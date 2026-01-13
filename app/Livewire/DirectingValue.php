<?php

namespace App\Livewire;

use Livewire\WithPagination;
use App\Models\DotValueList;
use Livewire\Component;

class DirectingValue extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind'; 

    public $sortBy = 'id';
    public $sortDirection = 'desc';

    public function delete($id)
    {
        DotValueList::findOrFail($id)->delete();
        session()->flash('message', 'Value deleted successfully!');
        session()->flash('type', 'error'); 

        if ($this->values()->isEmpty() && $this->page > 1) {
            $this->previousPage();
        }
    }

    public function sort($field)
    {
        if ($this->sortBy === $field) {
            // Toggle sort direction if same field
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            // New field, default to ascending
            $this->sortBy = $field;
            $this->sortDirection = 'asc';
        }
        
        // Reset to first page when sorting
        $this->resetPage();
    }

    public function values()
    {
        return DotValueList::orderBy($this->sortBy, $this->sortDirection)->paginate(8);
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
