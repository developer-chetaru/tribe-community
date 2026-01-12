<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organisation;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class UpdateOrganisation extends Component
{
    use WithFileUploads;

    public $organisationId;
    public $name;
    public $phone;
  	public $country_code = '+1';
    public $indus;
    public $turnover;
    public $profile_visibility;
    public $otherProfileVisibility;
    public $working_days = [];
    public $founded_year;
    public $url;
    public $image; 
    public $existingImage; 
    public $isEditing = false;
  	public $allIndustry;
  	public $organisation;
  	public $otherIndustry;
  	public $otherIndustryId;

    /**
     * Mount organisation details
     */
    public function mount($id)
    {
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

        $this->organisationId = $id;
        $org = Organisation::findOrFail($id);

        $this->name              = $org->name;
        $this->phone             = $org->phone;
        $this->country_code      = $org->country_code;
      
         if ($org->indus) {
            if ($org->indus->status == 1) {
                // Active industry → directly dropdown me select hoga
                $this->indus = $org->indus->id;
           		
                $this->otherIndustry = null;
                $this->otherIndustryId = null;
            } else {
                // Inactive/Other industry → "other" option + textbox fill
                $this->indus = 'other';
                $this->otherIndustry = $org->indus->name;
              	
                $this->otherIndustryId = $org->indus->id;
            }
        }

        $this->turnover          = $org->turnover;
        
        // Handle profile visibility - check if it's a custom value
        if ($org->profile_visibility && $org->profile_visibility !== '1' && $org->profile_visibility !== '0') {
            $this->profile_visibility = 'other';
            $this->otherProfileVisibility = $org->profile_visibility;
        } else {
            $this->profile_visibility = $org->profile_visibility;
            $this->otherProfileVisibility = null;
        }
        
        $this->working_days      = is_array($org->working_days) ? $org->working_days : explode(',', $org->working_days);
        $this->founded_year      = $org->founded_year;
        $this->url               = $org->url;
        $this->existingImage     = $org->image;
   		$this->allIndustry = \App\Models\Industry::where('status', 1)->get();
    }

    /**
     * Validation rules (dynamic for founded_year)
     */
public function rules()
{
    $rules = [
        'name'               => 'required|string|max:255',
        'phone'              => 'required|digits_between:7,15',
      	'country_code'       => 'required|string|max:5',
        'indus'              => 'required',
        'turnover'           => 'required|string',
        'profile_visibility' => 'required|string',
        'otherProfileVisibility' => [
            'nullable',
            'string',
            'max:255',
            \Illuminate\Validation\Rule::requiredIf(fn () => $this->profile_visibility === 'other'),
        ],
        'working_days'       => 'required|array|min:1',
        'founded_year'       => 'nullable|digits:4|integer|min:1900|max:' . date('Y'),
        'url'                => [
            'nullable',
            'regex:/^(https?:\/\/)?([\w\-]+\.)+[\w\-]+(\/[^\s]*)?$/i'
        ],
        'image'              => 'nullable|image|max:2048', // 2MB limit
    ];

 
    if ($this->indus === 'other') {
        $rules['otherIndustry'] = 'required|string|max:255';
    }

    return $rules;
}

    /**
     * Custom error messages
     */
protected $messages = [
    'name.required'               => 'Organisation name is required.',
    'phone.required'              => 'Phone number is required.',
    'phone.digits_between'        => 'Phone must be between 7 to 15 digits.',
  	'country_code.required'       => 'Country code is required.',
    'indus.required'           => 'Industry is required.',
    'turnover.required'           => 'Please select turnover.',
    'profile_visibility.required' => 'Profile visibility is required.',
    'working_days.required'       => 'Select at least one working day.',
    'founded_year.required'       => 'Founded year is required.',
    'founded_year.digits'         => 'Founded year must be 4 digits.',
    
    'url.required'                => 'The website field is required.',
  
    'url.regex'                   => 'The website field format is invalid.',
    
    'image.image'                 => 'Only image files are allowed.',
    'image.max'                   => 'Image size must not exceed 2MB.',
   'otherIndustry.required'      => 'Please enter the name of the other industry.',
   'otherProfileVisibility.required' => 'Please enter the other profile visibility.',
];


    /**
     * Real-time validation
     */
    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    /**
     * Toggle Edit mode
     */
    public function toggleEdit()
    {
        $this->isEditing = !$this->isEditing;
    }

    /**
     * Update organisation
     */
   public function update()
{
    $this->validate();

    $organisation = Organisation::findOrFail($this->organisationId);

    // 🔹 Handle image upload
    if ($this->image) {
        $path = $this->image->store('profile-photos', 'public');
        $organisation->image = $path;
    }

    // 🔹 Industry handling
    $industryId = null;

    if ($this->indus === 'other') {
        if ($this->otherIndustryId) {
            // Update existing "Other" industry
            $industry = \App\Models\Industry::find($this->otherIndustryId);
            if ($industry) {
                $industry->update([
                    'name'   => trim($this->otherIndustry),
                    'status' => 0, // wait for admin approval
                ]);
                $industryId = $industry->id;
            }
        } elseif ($this->otherIndustry) {
            // Create new "Other" industry if not exists
            $industry = \App\Models\Industry::create([
                'name'   => trim($this->otherIndustry),
                'status' => 0,
            ]);
            $industryId = $industry->id;
            $this->otherIndustryId = $industryId;
        }
    } else {
        // Existing active industry
        $industryId = (int) $this->indus;
    }

    // 🔹 Handle profile visibility
    $profileVisibilityValue = $this->profile_visibility;
    if ($this->profile_visibility === 'other') {
        $profileVisibilityValue = $this->otherProfileVisibility;
    }

    // 🔹 Update organisation
    $organisation->update([
        'name'               => $this->name,
        'phone'              => $this->phone,
      	'country_code'       => $this->country_code, 
        'industry_id'        => $industryId,
        'turnover'           => $this->turnover,
        'profile_visibility' => $profileVisibilityValue,
        'working_days'       => $this->working_days,
        'founded_year'       => $this->founded_year,
        'url'                => $this->url,
        'image'              => $organisation->image, // ensure latest image path
    ]);

    session()->flash('success', 'Organisation updated successfully!');
    $this->isEditing = false; // Exit edit mode
}

    /**
     * Delete organisation image
     */
    public function deleteImage()
    {
        $organisation = Organisation::findOrFail($this->organisationId);
        
        // Delete the image file from storage if it exists
        if ($organisation->image && Storage::disk('public')->exists($organisation->image)) {
            Storage::disk('public')->delete($organisation->image);
        }
        
        // Clear the image from database
        $organisation->update(['image' => null]);
        
        // Clear the existing image from component
        $this->existingImage = null;
        $this->image = null;
        
        session()->flash('success', 'Organisation logo deleted successfully!');
    }

    /**
     * Toggle working days
     */
    public function toggleDay($day)
    {
        if (in_array($day, $this->working_days)) {
            $this->working_days = array_diff($this->working_days, [$day]);
        } else {
            $this->working_days[] = $day;
        }
    }

    public function render()
    {
        return view('livewire.update-organisation')->layout('layouts.app');
    }
}
