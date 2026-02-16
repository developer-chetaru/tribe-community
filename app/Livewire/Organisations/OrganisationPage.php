<?php

namespace App\Livewire\Organisations;
use App\Models\AllDepartment;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use App\Models\Office;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Password; 
use App\Services\BrevoService;
use App\Services\ActivityLogService;
use Illuminate\Validation\Rule;
use App\Services\OneSignalService;

class OrganisationPage extends Component
{
    use WithFileUploads;

public $organisationName = '';
public $phoneNumber = '';
public $country_code = '';
public $officeCountryCode = '';
public $indus = '';
public $turnover = '';
public $founded_year = '';
public $website = '';
public $logo;
#[Validate('required|array|min:1')]
public $workingDays = [];  
public $officeName = '';
public $officeAddress = '';
public $officeCity = '';
public $officeState = '';
public $officeZip = '';
public $officeCountry = '';
public $officePhone = '';
public $isHeadOffice = false;
public $allDepartments;  
public $progressStep;
public $offices = [];
public $branches = [];
public $csvFile;
public $profile_visibility; 
public $otherProfileVisibility;
public $allOffices;
public $otherIndustryId;
public $allIndustry;
public $employees = [
    ['first_name'=>'', 'last_name'=>'', 'email'=>'', 'department'=>'', 'office'=>'', 'phone'=>'']
];
public $activeTab = 'organisation';
public $logoPreview;
public $employeeName;
public $organisationId = null;
public $officeId;
public $email;
public $otherIndustry;
  
  /**
 	* Initialize component state when the Livewire component is mounted.
 	*
 	* This method sets up default values for properties such as active tab,
 	* departments, offices, and industries. If an organisation ID is provided,
 	* it loads the organisation's details, including name, phone, turnover,
 	* profile visibility, website, founded year, industry, and parent offices.
 	*
 	* @return void
 	*/
	public function mount()
	{
        // Check if user has super_admin role
        if (!auth()->user()->hasRole('super_admin')) {
            abort(403, 'Unauthorized access. Admin privileges required.');
        }

    	$this->activeTab = 'organisation';
    	$this->allDepartments = \App\Models\AllDepartment::all();
    	$this->offices = collect();
    	$this->allIndustry = \App\Models\Industry::where('status', 1)->get();

    	if ($this->organisationId) {
        	$organisation = \App\Models\Organisation::with('indus')->find($this->organisationId);
		
    	    if ($organisation) {
        	    $this->organisationName   = $organisation->name;
        	    $this->phoneNumber        = $organisation->phone;
           	 	$this->turnover           = $organisation->turnover;
          	  	// Handle profile visibility - check if it's a custom value
          	  	if ($organisation->profile_visibility && $organisation->profile_visibility !== '1' && $organisation->profile_visibility !== '0') {
          	  	    $this->profile_visibility = 'other';
          	  	    $this->otherProfileVisibility = $organisation->profile_visibility;
          	  	} else {
          	  	    $this->profile_visibility = $organisation->profile_visibility;
          	  	    $this->otherProfileVisibility = null;
          	  	}
           	 	$this->website            = $organisation->url;
           	 	$this->founded_year       = $organisation->founded_year;
          	if ($organisation->indus) {
    			if ($organisation->indus->status == 1) {
        			$this->indus = $organisation->industry_id;
       	 			$this->otherIndustry = null;
    			} else {
        			$this->indus = 'other';
        			$this->otherIndustry = $organisation->industry_id;
    				}
				}
            	$this->offices = \App\Models\Office::where('organisation_id', $organisation->id)->whereNull('parent_office_id')->get();
        	}
    	}
	}

  /**
 	* Validate form inputs based on the current step in a multi-step form.
 	*
 	* @param string $step The current step to validate ('organisation' or 'office').
 	* 
 	* @return array The validated data for the given step.
 	*
 	* @throws \Illuminate\Validation\ValidationException
 	* 
 	* Step-specific rules:
 	* - 'organisation': validates organisation details such as name, phone, industry, turnover, founded year, website, logo, and working days.
 	* - 'office': validates office details including name, address, city, state, zip, country, and phone.
	*/
	public function validateStep($step)
	{
    	if ($step === 'organisation') {
        	return $this->validate([
            	'organisationName' => [
                'required',
                'string',
                'max:255',
                Rule::unique('organisations', 'name')->ignore($this->organisationId), 
            ],
            'phoneNumber' => 'required|digits_between:7,10',
             'country_code'  => 'required|string|max:5',
				'indus' => 'required|string',
            	'otherIndustry' => [
                	'nullable',
                	'string',
                	'max:255',
                	Rule::requiredIf(fn () => $this->indus === 'other'), 
            	],

            	'turnover' => 'required|string',
            	'founded_year' => 'nullable|digits:4|integer|min:1900|max:' . (date('Y')),

            'profile_visibility'=> 'nullable|string',
            'otherProfileVisibility' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $this->profile_visibility === 'other'),
            ],
           	 'website' => [
                	'nullable',
                	'regex:/^(https?:\/\/)?([\w\-]+\.)+[\w\-]+(\/[^\s]*)?$/i'
            	],
            	'logo' => $this->organisationId ? 'nullable|image|max:1024' : 'required|image|max:1024',
            	'workingDays' => 'nullable|array|min:1',
        	], [
           	 'indus.required'         => 'Please select an industry.',
            	'otherIndustry.required' => 'Please enter the name of the other industry.',
            	'otherProfileVisibility.required' => 'Please enter the other profile visibility.',
        	]);
    	}
    	elseif ($step === 'office') {
       	 	return $this->validate([
            	'officeName'      => 'required|string|max:255',
            	'officeAddress'   => 'required|string|max:500',
            	'officeCity'      => 'required|string|min:3',
            	'officeState'     => 'required|string|min:3|max:100',
            	'officeZip'       => 'required|string|min:4|max:20',
           	 	'officeCountry'   => 'required|string|max:100',
            	'officePhone'     => 'required|string|max:15',
              	'officeCountryCode' => 'required|string|max:5',
        	], [
            	'officeName.required'    => 'Office name is required.',
            	'officeAddress.required' => 'Office address is required.',
            	'officeCity.required'    => 'City is required.',
            	'officeCity.min'         => 'City must be at least 3 characters.',
            	'officeState.required'   => 'State is required.',
            	'officeState.min'        => 'State must be at least 3 characters.',
            	'officeZip.required'     => 'Zip code is required.',
            	'officeZip.min'          => 'Zip code must be at least 4 characters.',
              	'officeCountryCode.required' => 'Country code is required.',
        	]);
    	}
	}

	/**
 	* Toggle a day in the workingDays array.
 	*
 	* Adds the day to the array if it does not exist, 
 	* or removes it if it is already present.
 	*
 	* @param string $day The day to toggle (e.g., 'Monday', 'Tuesday').
 	* @return void
 	*/
	public function toggleDay($day)
	{
    	if (in_array($day, $this->workingDays)) {
        	$this->workingDays = array_values(array_diff($this->workingDays, [$day]));
    	} else {
        	$this->workingDays[] = $day;
    	}
	}

  /**
 	* Save organisation information including logo, industry, and working days.
 	*
 	* Validates the organisation step, handles working days, uploads logo,
 	* and processes industry selection (including "other" industry creation or update).
 	*
 	* @return void
 	*/
	public function saveOrganisation()
    {
        $validated = $this->validateStep('organisation');
        $validated['working_days'] = $this->workingDays;

        $image = $this->logo ? $this->logo->store('profile-photos', 'public') : null;

        $industryId = null;

        if ($this->indus === 'other') {
            if ($this->otherIndustryId) {
                // Update existing "other" industry
                $indus = \App\Models\Industry::find($this->otherIndustryId);
                if ($indus) {
                    $indus->update([
                        'name'   => trim($this->otherIndustry),
                        'status' => 0,
                    ]);
                    $industryId = $indus->id;
                }
            } elseif ($this->otherIndustry) {
                // Create new "other" industry
                $indus = \App\Models\Industry::create([
                    'name'   => trim($this->otherIndustry),
                    'status' => 0,
                ]);
                $industryId = $indus->id;
                $this->otherIndustryId = $industryId;
            }

            $this->indus = $industryId;
        } else {
            $industryId = (int) $this->indus;
        }

        // Handle profile visibility
        $profileVisibilityValue = $this->profile_visibility;
        if ($this->profile_visibility === 'other') {
            $profileVisibilityValue = $this->otherProfileVisibility;
        }

        if ($this->organisationId) {
            $org = \App\Models\Organisation::find($this->organisationId);

            if ($org) {
                // Capture old values for logging
                $oldValues = [
                    'name' => $org->name,
                    'phone' => $org->phone,
                    'country_code' => $org->country_code,
                    'turnover' => $org->turnover,
                    'industry_id' => $org->industry_id,
                    'working_days' => $org->working_days,
                    'profile_visibility' => $org->profile_visibility,
                    'url' => $org->url,
                    'founded_year' => $org->founded_year,
                ];

                $org->update([
                    'name'               => $this->organisationName,
                    'phone'              => $this->phoneNumber,
                    'country_code'       => $this->country_code,
                    'turnover'           => $this->turnover,
                    'industry_id'        => $industryId,
                    'working_days'       => $validated['working_days'],
                    'profile_visibility' => $profileVisibilityValue ?? '1',
                    'url'                => $this->website,
                    'founded_year'       => $this->founded_year,
                    'image'              => $image ?? $org->image,
                ]);

                // Log activity
                try {
                    $newValues = [
                        'name' => $org->name,
                        'phone' => $org->phone,
                        'country_code' => $org->country_code,
                        'turnover' => $org->turnover,
                        'industry_id' => $org->industry_id,
                        'working_days' => $org->working_days,
                        'profile_visibility' => $org->profile_visibility,
                        'url' => $org->url,
                        'founded_year' => $org->founded_year,
                    ];
                    ActivityLogService::logOrganisationUpdated($org, $oldValues, $newValues);
                } catch (\Exception $e) {
                    \Log::warning('Failed to log organisation update activity: ' . $e->getMessage());
                }
            }
        } else {
            $org = \App\Models\Organisation::create([
                'name'               => $this->organisationName,
                'phone'              => $this->phoneNumber,
                'country_code'       => $this->country_code,
                'industry_id'        => $industryId,
                'turnover'           => $this->turnover,
                'working_days'       => $validated['working_days'],
                'profile_visibility' => $profileVisibilityValue ?? '1',
                'url'                => $this->website,
                'founded_year'       => $this->founded_year,
                'image'              => $image,
                'status'             => 1,
            ]);

            $this->organisationId = $org->id;

            // Log activity
            try {
                ActivityLogService::logOrganisationCreated($org, [
                    'name' => $org->name,
                    'phone' => $org->phone,
                    'turnover' => $org->turnover,
                    'industry_id' => $org->industry_id,
                ]);
            } catch (\Exception $e) {
                \Log::warning('Failed to log organisation creation activity: ' . $e->getMessage());
            }
        }

        $this->otherIndustry = null;
        $this->otherProfileVisibility = null;
        $this->allIndustry   = \App\Models\Industry::where('status', 1)->get();
        $this->activeTab     = 'office';
    }

	public function loadOrganisation($id)
	{
    	$org = \App\Models\Organisation::with('indus', 'offices')->find($id);

    	if ($org) {
        	$this->organisationId     = $org->id;
        	$this->organisationName   = $org->name;
        	$this->phoneNumber        = $org->phone;
          	$this->country_code        = $org->country_code;
        	$this->turnover           = $org->turnover;
        	$this->establishedYear    = $org->founded_year;
        	$this->website            = $org->url;
        	// Handle profile visibility - check if it's a custom value
        	if ($org->profile_visibility && $org->profile_visibility !== '1' && $org->profile_visibility !== '0') {
        	    $this->profile_visibility = 'other';
        	    $this->otherProfileVisibility = $org->profile_visibility;
        	} else {
        	    $this->profile_visibility = $org->profile_visibility;
        	    $this->otherProfileVisibility = null;
        	}
        	$this->workingDays        = $org->working_days ?? [];

        	$this->logoPreview = $org->image ? asset('storage/' . $org->image) : null;

        	if ($org->indus) {
            	if ($org->indus->status == 1) {
                	$this->indus = $org->indus->id;
                	$this->otherIndustry = null;
                	$this->otherIndustryId = null;
            } else {
                $this->indus = 'other';
                $this->otherIndustry = $org->indus->name;
                $this->otherIndustryId = $org->indus->id;
            }
        }

     
        $this->headOffice = $org->offices->where('is_head_office', 1)->first()?->toArray();

    
        $this->branches = $org->offices
            ->where('is_head_office', 0)
            ->map(fn($office) => [
                'id' => $office->id,
                'name' => $office->name ?? '',
                'address' => $office->address ?? '',
                'city' => $office->city ?? '',
                'state' => $office->state ?? '',
                'country' => $office->country ?? '',
                'zip' => $office->zip ?? '',
            ])->values()->toArray();

     
        $this->allOffices = $org->offices->map(fn($office) => [
            'id' => $office->id,
            'name' => $office->name,
            'is_head_office' => $office->is_head_office,
            'city' => $office->city ?? null,
            'state' => $office->state ?? null,
            'country' => $office->country ?? null,
            'zip' => $office->zip ?? null,
            'address' => $office->address ?? null,
        ])->toArray() ?? [];
    }
}


protected function validationAttributes()
{
    $attributes = [];

    // Branches
    foreach ($this->branches as $index => $branch) {
        $attributes["branches.$index.name"] = "Branch Name";
        $attributes["branches.$index.address"] = "Branch Address";
        $attributes["branches.$index.zip"] = "Branch Zip Code";
        $attributes["branches.$index.city"] = "Branch City";
        $attributes["branches.$index.state"] = "Branch State";
        $attributes["branches.$index.country"] = "Branch Country";
    }

    // Employees
    foreach ($this->employees as $index => $employee) {
        $attributes["employees.$index.first_name"] = "First Name";
        $attributes["employees.$index.last_name"] = "Last Name";
        $attributes["employees.$index.email"] = "Email Address";
        $attributes["employees.$index.department"] = "Department";
        $attributes["employees.$index.office"] = "Office";
        $attributes["employees.$index.phone"] = "Phone Number";
    }

    return $attributes;
}

public function saveOffice()
{

    $validated = $this->validateStep('office');

    $organisationId = $this->organisationId;
    if (!$organisationId) {
        $this->addError('office', 'Please select organisation first.');
        return;
    }

  
    $office = \App\Models\Office::updateOrCreate(
        ['id' => $this->officeId],
        [
            'organisation_id' => $organisationId,
            'name' => $validated['officeName'],
            'address' => $validated['officeAddress'],
            'city' => $validated['officeCity'],
            'state' => $validated['officeState'],
            'zip_code' => $validated['officeZip'],
            'country' => $validated['officeCountry'],
            'phone' => $validated['officePhone'],
          	'country_code'    => $validated['officeCountryCode'],
            'is_head_office' => $validated['isHeadOffice'] ?? true,
            'status' => 1,
        ]
    );

    $this->officeId = $office->id;
    $this->officeName = $office->name;

  

   
    if (!empty($this->branches)) {
        $branchRules = [];
        foreach ($this->branches as $index => $branch) {
            $branchRules["branches.$index.name"] = 'required|string|max:255';
            $branchRules["branches.$index.address"] = 'required|string|max:500';
            $branchRules["branches.$index.city"] = 'required|string|max:255';
            $branchRules["branches.$index.state"] = 'required|string|max:255';
            $branchRules["branches.$index.zip"] = 'required|string';
            $branchRules["branches.$index.country"] = 'required|string|max:255';
        }

        $this->validate($branchRules);
    }


    foreach ($this->branches as $index => $branch) {
        $branchOffice = \App\Models\Office::updateOrCreate(
            ['id' => $branch['id'] ?? null],
            [
                'organisation_id' => $organisationId,
                'parent_office_id' => $office->id,
                'name' => $branch['name'],
                'address' => $branch['address'],
                'city' => $branch['city'] ?? '',
                'state' => $branch['state'] ?? '',
                'zip_code' => $branch['zip'] ?? '',
                'country' => $branch['country'] ?? '',
                'status' => 1,
            ]
        );

        $this->branches[$index] = [
            'id' => $branchOffice->id,
            'name' => $branchOffice->name,
            'address' => $branchOffice->address,
            'city' => $branchOffice->city,
            'state' => $branchOffice->state,
            'zip' => $branchOffice->zip_code,
            'country' => $branchOffice->country,
        ];
    }


    $this->activeTab = 'employee';
    return redirect()->route('organisations.create', ['id' =>  $this->organisationId, 'tab' => 'employee']);
}


public function saveEmployees()
{

    $validatedData = $this->validate([
        'employees.*.first_name' => 'required|string|max:255',
        'employees.*.last_name'  => 'required|string|max:255',
        'employees.*.email'      => 'required|string|max:255|unique:users,email',
        'employees.*.department' => 'required|integer',
        'employees.*.office'     => 'nullable|integer',
        'employees.*.phone'      => 'required|string|max:15',
      	'employees.*.country_code' => 'required|string|max:5',
    ]);

    $organisation = \App\Models\Organisation::latest()->first();

    foreach ($this->employees as $emp) {
	$existingUser = \App\Models\User::where('email', $emp['email'])->first();
  	$officeId = isset($emp['office']) && $emp['office'] !== '' ? $emp['office'] : null;

        if (!$existingUser) {
            $department = \App\Models\Department::firstOrCreate(
                [
                    'organisation_id'   => $organisation->id,
                    'office_id'         => $officeId,
                    'all_department_id' => $emp['department'],
                ],
                [
                    'status' => 1,
                    'numberOfEmployees' => 2,
                    'department' => 'testing',
                ]
            );

            $departmentId = $department->id;

            $user = \App\Models\User::create([
                'orgId'        => $organisation->id,
                'first_name'   => $emp['first_name'],
                'last_name'    => $emp['last_name'] ?? null,
                'email'        => $emp['email'],
                'password'     => bcrypt('sourabh@chetaru.com'),
                'departmentId' => $departmentId,
                'officeId'     => $officeId,
                'phone'        => $emp['phone'] ?? null,
              	'country_code' => $emp['country_code'] ?? null,
                'status'       => false,
            ]);

          
            \Illuminate\Support\Facades\Password::broker('users')->sendResetLink(['email' => $user->email]);
            try {
                $oneSignal = new OneSignalService();
                $oneSignal->registerEmailUser($user->email, $user->id);
            } catch (\Throwable $e) {
                \Log::error('❌ OneSignal registration failed for new user', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        } else {
            $user = $existingUser;
        }

   
        if (!$user->hasRole('organisation_user')) {
            $user->assignRole('organisation_user');
        }
    }

  
    $organisation->update(['progress_step' => 4]);

  
    $this->organisationId = null;
    $this->officeId = null;
    $this->employees = [
        ['first_name' => '', 'last_name' => '', 'email' => '', 'department' => '', 'office' => '', 'phone' => '','country_code' => '']
    ];

   
    $this->dispatch('clear-office-storage');

 
    return redirect()->route('organisations.index'); 
}

  protected $listeners = [
    'updateOfficeAddressFields',
    'updateBranchAddressFields'
];


public function updateOfficeAddressFields($data) {
    $this->officeCity = $data['city'];
    $this->officeState = $data['state'];
    $this->officeCountry = $data['country'];
    $this->officeZip = $data['zip_code'];
}

  

public function loadOffice($id)
{
    $office = \App\Models\Office::find($id);
    if (!$office) return;

    $this->officeId = $office->id;
    $this->officeName = $office->name;
    $this->officeAddress = $office->address;
    $this->officeCity = $office->city;
    $this->officeState = $office->state;
    $this->officeCountry = $office->country;
    $this->officeZip = $office->zip_code;
    $this->officePhone = $office->phone;
    $this->isHeadOffice = $office->is_head_office;

    $this->lastAddedBranchIndex = count($this->branches) - 1;
    $this->dispatch('branches-loaded');

}


  public function updatedCsvFile()
{
    $path = $this->csvFile->getRealPath();
    $rows = array_map('str_getcsv', file($path));
    
   
    $header = array_map('trim', array_shift($rows));

    $this->employees = [];

    foreach ($rows as $row) {
        $data = array_combine($header, $row);

        $this->employees[] = [
            'first_name' => $data['first_name'] ?? '',
            'last_name'  => $data['last_name'] ?? '',
            'email'      => $data['email'] ?? '',
            'department' => $data['department'] ?? '',
            'office'     => $data['office'] ?? '',
            'phone'      => $data['phone'] ?? '',
        ];
    }
}

public function updateBranchAddressFields($data) {
    $index = $data['index'];
    $this->branches[$index]['city'] = $data['city'];
    $this->branches[$index]['state'] = $data['state'];
    $this->branches[$index]['country'] = $data['country'];
    $this->branches[$index]['zip'] = $data['zip'];
}
public function addBranch()
{
    $this->branches[] = [
        'id' => '',
        'name' => '',
        'address' => '',
        'city' => '',
        'state' => '',
        'zip' => '',
        'country' => ''
    ];

    $this->lastAddedBranchIndex = count($this->branches) - 1; 

   
    $this->dispatch('branch-added', ['index' => $this->lastAddedBranchIndex]);
}



public function removeBranch($index)
{
    $branch = $this->branches[$index] ?? null;

    if ($branch && !empty($branch['id']) && is_numeric($branch['id'])) {
       
        \App\Models\Office::where('id', $branch['id'])->delete();
    }

   
    unset($this->branches[$index]);
    $this->branches = array_values($this->branches);
}


public function resetTable()
{
    $this->employees = [
        ['first_name'=>'', 'last_name'=>'', 'email'=>'', 'department'=>'', 'office'=>'', 'phone'=>'']
    ];
}
public function addRows($count = 1)
{
    for ($i = 0; $i < $count; $i++) {
        $this->employees[] = [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'department' => '',
            'office' => '',
            'phone' => ''
        ];
    }
}


public function resetForm()
{
 
    $this->organisationName = '';
    $this->phoneNumber = '';
    $this->industry = '';
    $this->turnover = '';
    $this->establishedYear = '';
    $this->website = '';
    $this->logo = null;
    $this->logoPreview = null;
    $this->workingDays = [];

    $this->resetValidation();
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
    $this->isHeadOffice = false;

    $this->branches = [];

    $this->resetValidation();
}


    public function render()
    {
        return view('livewire.organisations.organisation-page')
            ->layout('layouts.app');
    }
}
