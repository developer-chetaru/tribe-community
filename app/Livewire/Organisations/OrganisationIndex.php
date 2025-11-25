<?php

namespace App\Livewire\Organisations;

use Livewire\Component;
use App\Models\Organisation;
use Livewire\WithPagination; 

class OrganisationIndex extends Component
{
	use WithPagination;
  	protected $paginationTheme = 'tailwind'; 
    public $search = '';
    public $industry = [];
    public $turnover = [];
    public $country = [];
    public $visibility = [];
    public $showFilter = false;
	public $activeTab = 'industry'; 

    public function resetFilters()
    {
        $this->industry = [];
        $this->turnover = [];
        $this->country = [];
        $this->visibility = [];
    }
  
	public function closeFilter()
	{
    	$this->industry = [];
    	$this->turnover = [];
    	$this->visibility = [];
    	$this->showFilter = false;
	}

   public function render()
	{
    	$organisations = Organisation::query()
        ->when($this->search, fn($q) =>
            $q->where('name', 'like', '%' . $this->search . '%')
        )
        ->when($this->industry, fn($q) =>
            $q->whereIn('industry', $this->industry)
        )
        ->when($this->turnover, fn($q) =>
            $q->whereIn('turnover', $this->turnover)
        )
        ->when($this->visibility, fn($q) =>
            $q->whereIn('profile_visibility', $this->visibility)
        )
        ->orderBy('name') 
        ->paginate(9); 

    	return view('livewire.organisations.organisation-index', [
        	'organisations' => $organisations
    	])->layout('layouts.app');
	}
}
