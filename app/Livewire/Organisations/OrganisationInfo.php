<?php

namespace App\Livewire\Organisations;

use Livewire\Component;
use App\Models\Organisation;
use App\Services\ActivityLogService;
use Livewire\WithFileUploads;

class OrganisationInfo extends Component
{
    use WithFileUploads;

    public $image, $name, $phone, $industry, $revenue, $year, $website_url, $working_days = [];

    public function rules()
    {
        return [
            'image' => 'required|image|max:2048',
            'name' => 'required|string|max:255',
            'phone' => 'required|min:5',
            'industry' => 'required|string',
            'revenue' => 'required|string',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'website_url' => ['required','regex:/^(https?:\/\/)?([\w\-]+\.)+[\w\-]+(\/[^\s]*)?$/i'],
            'working_days' => 'required|array',
        ];
    }

    protected $messages = [
        'name.required' => 'Organisation name is required.',
        'phone.required' => 'Phone number is required.',
        'industry.required' => 'Please select an industry.',
        'revenue.required' => 'Please select a turnover range.',
        'image.required' => 'Uploaded file is required.',
        'image.image' => 'Uploaded file must be an image.',
        'image.max' => 'Logo must be under 2MB.',
        'year.required' => 'Year is required.',
        'website_url.required' => 'Website url is required.',
        'working_days.required' => 'Working days is required.',
        'website_url.regex' => 'Please enter a valid URL that starts with http:// or https://',
    ];

  /**
 	* Validate a single property when it is updated.
 	*
 	* @param string $property The name of the property being updated.
 	* @return void
 	*/
    public function updated($property)
    {
        $this->validateOnly($property);
    }
  
  	/**
 	 * Save a new organisation record.
     *
     * @return void
     */
    public function save()
    {
        $validated = $this->validate($this->rules());

        $imagePath = null;
        if ($this->image) {
            $folder = 'profile-photos/' . date('Y');
            $imagePath = $this->image->store($folder, 'public');
        }

        try {
            $organisation = Organisation::create([
                'user_id' => auth()->id(),
                'image' => $imagePath,
                'name' => $this->name,
                'phone' => $this->phone,
                'industry' => $this->industry,
                'revenue' => $this->revenue,
                'founded_year' => $this->year,
                'url' => $this->website_url,
                'working_days' => $this->working_days,
            ]);

            $user = auth()->user();
            $user->orgId = $organisation->id;
            $user->save();

            // Log activity
            try {
                ActivityLogService::logOrganisationCreated($organisation, [
                    'name' => $organisation->name,
                    'phone' => $organisation->phone,
                    'industry' => $organisation->industry,
                    'revenue' => $organisation->revenue,
                    'founded_year' => $organisation->founded_year,
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to log organisation creation activity: ' . $e->getMessage());
            }

            session()->flash('success', 'Organisation created successfully.');

            $this->dispatch('organisation-created', $organisation->id);

            $this->reset(['image', 'name', 'phone', 'industry', 'revenue', 'year', 'website_url', 'working_days']);
        } catch (\Exception $e) {
            session()->flash('error', 'Error creating organisation: ' . $e->getMessage());
        }
    }

   /**
 	* Render the Livewire component view.
 	*
 	* @return \Illuminate\View\View
 	*/
    public function render()
    {
        return view('livewire.organisations.organisation-info');
    }

  /**
 	* Reset the organisation form fields and validation errors.
 	*
 	* Clears all input properties and resets error messages and validation state.
 	*
 	* @return void
 	*/
    public function resetForm()
    {
        $this->reset([
            'image',
            'name',
            'phone',
            'industry',
            'revenue',
            'year',
            'website_url',
            'working_days',
        ]);

        $this->resetErrorBag();
        $this->resetValidation();
    }
}
