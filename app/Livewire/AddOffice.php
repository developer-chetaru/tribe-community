<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Office;
use App\Models\Organisation;

class AddOffice extends Component
{
    public $organisationId;
    public $officeName = '';
    public $officeAddress = '';
    public $officeCity = '';
    public $officeState = '';
    public $officeZip = '';
    public $officeCountry = '';
    public $officePhone = '';
  	public $officeCountryCode = '';
    public $officeEmployees = '';
    public $isHeadOffice = false;
  	public $organisationName;

   protected function rules()
{
    return [
        'officeName'       => 'required|string|max:255|unique:offices,name,NULL,id,organisation_id,' . $this->organisationId,
        'officeAddress'    => 'required|string|max:500',
        'officeCity'       => 'required|string|max:100',
        'officeState'      => 'required|string|max:100',
        'officeZip'        => 'required|string|max:20',
        'officeCountry'    => 'required|string|max:100',
        'officePhone'      => 'required|string|max:15',
       	'officeCountryCode'=> 'required|string|max:5',
        'isHeadOffice'     => 'boolean',
    ];
}


    public function mount($id)
    {
        $this->organisationId = $id;
  		$organisation = Organisation::find($this->organisationId);
        $this->organisationName = $organisation ? $organisation->name : null;
    }

public function saveOffice()
{
    $validated = $this->validate();

    // 🔹 Check if organisation already has a head office
    $hasHeadOffice = \App\Models\Office::where('organisation_id', $this->organisationId)
                                       ->where('is_head_office', true)
                                       ->exists();

    Office::create([
        'organisation_id' => $this->organisationId,
        'name'            => $validated['officeName'],
        'address'         => $validated['officeAddress'],
        'city'            => $validated['officeCity'],
        'state'           => $validated['officeState'],
        'zip_code'        => $validated['officeZip'],
        'country'         => $validated['officeCountry'],
        'phone'           => $validated['officePhone'],
        'country_code'    => $validated['officeCountryCode'],
        // 🔹 Automatically set head office if none exists
        'is_head_office'  => $hasHeadOffice ? ($validated['isHeadOffice'] ?? false) : true,
        'status'          => 1,
    ]);

    session()->flash('success', 'Office added successfully.');
    
  

    $this->resetOfficeForm();
}


    public function resetOfficeForm()
    {
        $this->officeName = '';
        $this->officeAddress = '';
        $this->officeCity = '';
        $this->officeState = '';
        $this->officeZip = '';
        $this->officeCountry = '';
        $this->officePhone = '';
    	$this->officeCountryCode = '';
        $this->isHeadOffice = false;

        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.add-office')->layout('layouts.app');
    }
}
