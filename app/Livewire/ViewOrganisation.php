<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organisation;

class ViewOrganisation extends Component
{
    public $organisation;
    public $staffCount;
    public $officeCount;
    public $canDelete = false;

    public function mount($id)
    {
        $this->organisation = Organisation::with(['offices', 'users'])->findOrFail($id);
		
        $this->staffCount  = $this->organisation->users->count();
        $this->officeCount = $this->organisation->offices->count();

        $nonHeadOffices = $this->organisation->offices->where('is_head_office', 0);
        $this->canDelete = $this->staffCount === 0 && $nonHeadOffices->count() === 0;
    }

 	public function deleteOrganisation($id)
	{
    	$organisation = Organisation::with(['offices', 'users'])->findOrFail($id);
	
    	if ($organisation->users->count() > 0) {
        	session()->flash('error', 'Cannot delete organisation. Please remove all staff first.');
        	return;
    	}

    	$offices = $organisation->offices;

    	if ($offices->count() > 1) {
        	session()->flash('error', 'Cannot delete organisation. Please remove all non-head offices first.');
        	return;
    	}

   
    	$organisation->delete();

    	session()->flash('success', 'Organisation deleted successfully.');
    	return redirect()->route('organisations.index');
	}


    public function render()
    {
        return view('livewire.view-organisation')->layout('layouts.app');
    }
}
