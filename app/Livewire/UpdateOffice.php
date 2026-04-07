<?php

namespace App\Livewire;

use App\Models\Office;
use App\Models\Organisation;
use Livewire\Component;

class UpdateOffice extends Component
{
    public $officeId;
    public $organisationId;
    public $organisationName;

    public $officeName = '';
    public $officeAddress = '';
    public $officeCity = '';
    public $officeState = '';
    public $officeZip = '';
    public $officeCountry = '';
  	public $officePhone = '';
    public $officeCountryCode = '+91';
    public $isHeadOffice = false;

    protected function rules()
    {
        return [
            'officeName'    => 'required|string|max:255|unique:offices,name,' . $this->officeId . ',id,organisation_id,' . $this->organisationId,
            'officeAddress' => 'required|string|max:500',
            'officeCity'    => 'required|string|max:100',
            'officeState'   => 'required|string|max:100',
            'officeZip'     => 'required|string|max:20',
            'officeCountry' => 'required|string|max:100',
            'officePhone'   => 'required|string|max:15',
          	'officeCountryCode'  => 'required|string|max:5',
            'isHeadOffice'  => 'boolean',
        ];
    }

    public function mount($id)
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        // $id यहाँ Office का id होगा
        $office = Office::findOrFail($id);

        $this->officeId       = $office->id;
        $this->organisationId = $office->organisation_id;

        $organisation = Organisation::find($this->organisationId);
        $this->organisationName = $organisation?->name;

        // fill inputs
        $this->officeName    = $office->name;
        $this->officeAddress = $office->address;
        $this->officeCity    = $office->city;
        $this->officeState   = $office->state;
        $this->officeZip     = $office->zip_code;
        $this->officeCountry = $office->country;
        $this->officePhone   = $office->phone;
      	$this->officeCountryCode = $office->country_code ?? '+91';
        $this->isHeadOffice  = $office->is_head_office;
    }

    public function saveOffice()
    {
        $validated = $this->validate();

        $office = Office::findOrFail($this->officeId);

        $office->update([
            'name'           => $validated['officeName'],
            'address'        => $validated['officeAddress'],
            'city'           => $validated['officeCity'],
            'state'          => $validated['officeState'],
            'zip_code'       => $validated['officeZip'],
            'country'        => $validated['officeCountry'],
            'phone'          => $validated['officePhone'],
          	'country_code'  => $validated['officeCountryCode'],
            'is_head_office' => $validated['isHeadOffice'] ?? false,
        ]);

        session()->flash('success', 'Office updated successfully!');
        return redirect()->route('office-list', $this->organisationId);
    }

    public function resetOfficeForm()
    {
        $this->mount($this->officeId); // दुबारा reload कर दो ताकि reset पर original data भर जाए
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.update-office')->layout('layouts.app');
    }
}
