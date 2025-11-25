<?php
namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination; // ✅ Pagination trait
use App\Models\AllDepartment;

class Department extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind'; 
public function delete($id)
{
    AllDepartment::findOrFail($id)->delete();
    session()->flash('message', 'Department deleted successfully!');
    session()->flash('type', 'error'); 

    // Delete ke baad agar current page empty ho gaya to previous page pe jaaye
    if ($this->departments()->currentPage() > 1 && $this->departments()->isEmpty()) {
        $this->previousPage();
    }
}


    public function departments()
    {
        return AllDepartment::orderBy('id', 'desc')->paginate(8); // ✅ 5 per page
    }

    public function render()
    {
        return view('livewire.department', [
            'departments' => $this->departments()
        ])->layout('layouts.app');
    }
}
