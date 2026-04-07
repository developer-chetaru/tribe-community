<div  x-data="{ newRowCount: 1 }" class=" min-h-[420px]">

    <div class="flex justify-between items-center mb-6">
        <h2 class="text-[24px] text-[#EB1C24] font-[500]">Add Employee</h2>

    <div>
        <input type="file" wire:model="csvFile" accept=".csv" class="hidden" id="csvInput">
        <button type="button" onclick="document.getElementById('csvInput').click()" class="bg-gray-100 flex items-center text-black px-6 py-2 rounded  hover:bg-gray-200 border border-[#808080] text-[14px] text-[#808080] font-[500]">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" class="mr-2">
    <path d="M9.99935 3.75V12.0833M9.99935 3.75C9.41585 3.75 8.32562 5.41192 7.91602 5.83333M9.99935 3.75C10.5828 3.75 11.6731 5.41192 12.0827 5.83333" stroke="#808080" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
    <path d="M17 11C17 15.1367 16.5468 16 14.375 16H5.625C3.45325 16 3 15.1367 3 11" stroke="#808080" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
 Import By CSV
        </button>
    </div>    </div>

    <!-- Table -->
    <div class="w-full border border-gray-300 rounded-[8px] mb-6">
    <table class="border-collapse w-full ">
        <thead>
            <tr class="bg-[#F8F9FA]">
                <th class="border-r p-3 bg-gray-100 text-[16px] text-[#808080] font-[500] text-left">First Name</th>
                <th class="border-r p-3 bg-gray-100 text-[16px] text-[#808080] font-[500] text-left">Last Name</th>
                <th class="border-r p-3 bg-gray-100 text-[16px] text-[#808080] font-[500] text-left">Email</th>
                <th class="border-r p-3 bg-gray-100 text-[16px] text-[#808080] font-[500] text-left">Department</th>
              <!-- Office Column Header -->
 @if(!empty($allOffices))
<th class="border-r p-3 bg-gray-100 text-[16px] text-[#808080] font-[500] text-left">Office</th>
 @endif
                <th class=" p-3 bg-gray-100 text-[16px] text-[#808080] font-[500] text-left">Phone</th>
            </tr>
        </thead>
        <tbody>
            @foreach($employees as $index => $employee)
                <tr wire:key="employee-{{ $index }}" class="border-b border-gray-300">
             
                 <!-- First Name -->
<td class="border-r p-3">
    <div class="flex flex-col">
        <input type="text" wire:model="employees.{{ $index }}.first_name" 
               placeholder="First Name" 
               class="w-full text-sm text-[16px] text-[#808080] font-[500] border-0 p-0 focus:ring-red-500 focus:border-red-500">
        <span class="text-red-500 text-xs mt-1 min-h-[1.25rem]">
            @error('employees.' . $index . '.first_name') {{ $message }} @enderror
        </span>
    </div>
</td>

<!-- Last Name -->
<td class="border-r p-3">
    <div class="flex flex-col">
        <input type="text" wire:model="employees.{{ $index }}.last_name" placeholder="Last Name" 
               class="w-full text-sm text-[16px] text-[#808080] font-[500] border-0 p-0 focus:ring-red-500 focus:border-red-500">
        <span class="text-red-500 text-xs mt-1 min-h-[1.25rem]">
            @error('employees.' . $index . '.last_name') {{ $message }} @enderror
        </span>
    </div>
</td>

<!-- Email -->
<td class="border-r p-3">
    <div class="flex flex-col">
        <input type="email" wire:model="employees.{{ $index }}.email" placeholder="Email" 
              class="w-full text-sm text-[16px] text-[#808080] font-[500] border-0 p-0 focus:ring-red-500 focus:border-red-500">
        <span class="text-red-500 text-xs mt-1 min-h-[1.25rem]">
            @error('employees.' . $index . '.email') {{ $message }} @enderror
        </span>
    </div>
</td>

<!-- Department -->
<td class="border-r p-3">
    <div class="flex flex-col">
        <select wire:model="employees.{{ $index }}.department" class="w-full text-sm border rounded px-2 py-1 focus:ring-red-500 focus:border-red-500 border-0 min-w-[130px] text-[16px] text-[#808080] font-[500]">
            <option value="">Select Department</option>
            @foreach($allDepartments ?? [] as $department)
                <option value="{{ $department->id }}">{{ $department->name }}</option>
            @endforeach
        </select>
        <span class="text-red-500 text-xs mt-1 min-h-[1.25rem]">
            @error('employees.' . $index . '.department') {{ $message }} @enderror
        </span>
    </div>
</td>
 @if(!empty($allOffices))
<td class="border-r p-3">
  <div class="flex flex-col">
   <select wire:model="employees.{{ $index }}.office" class="w-full mb-4 text-sm border rounded px-2 py-1 focus:ring-red-500 focus:border-red-500 border-0 min-w-[200px] text-[16px] text-[#808080] font-[500]">
    <option value="">Select Office</option>

    @if(!empty($allOffices))
        @foreach($allOffices as $office)
            <option value="{{ $office['id'] }}">
                {{ $office['name'] }} @if($office['is_head_office'])  @endif
            </option>
        @endforeach
    @endif
</select>

    <span class="text-red-500 text-xs mt-1 min-h-[1.25rem] absolute">
      @error('employees.' . $index . '.office') {{ $message }} @enderror
    </span>
  </div>
</td>

 @endif


					<!-- Phone -->
                    <td class="p-3">
                        <div wire:ignore class="flex flex-col">
                            <div x-data="{}"
                                x-init="initTelInput($refs.phoneInput{{ $index }}, @this, 'employees.{{ $index }}.phone', 'employees.{{ $index }}.country_code')"
                                style="width: 100% !important;">
                                <input x-ref="phoneInput{{ $index }}" type="tel"
                                    placeholder="Phone"
                                    class="w-full text-sm text-[16px] text-[#808080] font-[500] border-0 p-0 focus:ring-red-500 focus:border-red-500">
                            </div>
                            <span class="text-red-500 text-xs mt-1 min-h-[1.25rem]">
                                @error('employees.' . $index . '.phone') {{ $message }} @enderror
                            </span>
                            <span class="text-red-500 text-xs mt-1 min-h-[1.25rem]">
                                @error('employees.' . $index . '.country_code') {{ $message }} @enderror
                            </span>
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Add More Rows -->
    <div class="inline-flex border p-2 px-4 border-gray-300 mb-4 mt-4 items-center rounded-md ml-3">
        <span class="mr-3 text-[#808080]">Add More Rows</span>
        <input type="number" x-model="newRowCount" min="1" class="border rounded-md border-gray-300 mr-2" style="width: 52px; padding-left: 15px; height: 30px; padding-right: 0;">
        <button @click="$wire.addRows(newRowCount); newRowCount = 1;" class="bg-red-600 text-white  px-4 py-1 rounded-md shadow hover:bg-red-700">Add</button>
    </div>
</div>

<button type="button" wire:click="saveEmployees" class="text-black px-6 py-2 rounded-[8px] bg-[#EB1C24] hover:bg-gray-400 bg-red-600 text-[#fff]">
    Save
</button>
<button type="button" wire:click="resetTable" class="text-[#808080] px-3">Reset All</button>
  
    </div>
</div>

<script>
function employeeTable() {
    return {
        newRowCount: 1,

        addRows() {
            for (let i = 0; i < this.newRowCount; i++) {
                // Add directly to Livewire
                $wire.employees.push({
                    first_name: '', last_name: '', email: '',
                    department: '', office: '', phone: ''
                });
            }
            this.newRowCount = 1;
        },

        resetTable() {
            // Reset via Livewire
            $wire.employees = [
                { first_name: '', last_name: '', email: '', department: '', office: '', phone: '' }
            ];
        }
    }
}

function initTelInput(input, livewire, phoneField, countryField) {
    let iti = window.intlTelInput(input, {
        initialCountry: "auto",
        separateDialCode: true,
        geoIpLookup: function(callback) {
            fetch("https://ipapi.co/json")
                .then(res => res.json())
                .then(data => callback(data.country_code))
                .catch(() => callback("us"));
        },
        utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
    });

    function updateValues() {
        let phoneNumber = input.value.replace(/\D/g, '');
        let countryCode = iti.getSelectedCountryData().dialCode;

        livewire.set(phoneField, phoneNumber);
        livewire.set(countryField, `+${countryCode}`);
    }

    input.addEventListener("input", updateValues);
    input.addEventListener("countrychange", updateValues);

    updateValues();
}
</script>
<script>
window.addEventListener('clear-office-storage', event => {
    localStorage.removeItem('organisation_id');
    localStorage.removeItem('office_id');
    localStorage.removeItem('office_name');
    localStorage.removeItem('active_tab');
});
</script>

