<x-slot name="header">
 <h2 class="text-2xl font-bold capitalize text-[#ff2323]">
   Learning Type
  </h2>
</x-slot>
	<div class="flex-1 overflow-auto">
			<div class=" mx-auto">
				<div class="flex items-center justify-end">
    <a href="{{ route('learningtype.add') }}">
        <button type="button" class="bg-[#EB1C24] text-white px-5 py-2 rounded-sm shadow font-medium">
            + Add new Learning Type
        </button>
    </a>
</div>


					<div class=" flex bg-[#ffffff] p-4 border border-gray-200 mt-7 rounded-md flex-wrap">
						  @if (session()->has('message'))
        <div x-data="{ show: true }" 
             x-init="setTimeout(() => show = false, 2000)" 
             x-show="show" x-transition
             class="gap-2 w-full items-center mb-4 px-4 py-2 rounded-lg border {{ session('type') === 'error' ? 'bg-red-500 border-red-400 text-white' : 'bg-red-500 border-red-400 text-white' }}">
            <span>{{ session('message') }}</span>
        </div>
    @endif
						<div class="flex w-full border border-gray-200 rounded-md ">
							<table class="w-full">
								  <thead>
								    <tr bgcolor="#F8F9FA">
								      <th class="px-8 py-6 font-semibold max-w-[1050px] w-full text-[14] text-[#020202] text-left">Title</th>
								      <th class="px-8 py-6 font-semibold text-[#020202] text-[14px] text-left">Score</th>
								      <th class="px-8 py-6 font-semibold text-[#020202] text-[14px] text-left">Actions</th>
								  
								    </tr>
								  </thead>
								<tbody>
    @forelse ($learningTypes as $type)
        <tr class="{{ $loop->even ? 'bg-[#F8F9FA]' : '' }} border border-gray-300 hover:bg-red-50">
            <!-- Title -->
            <td class="px-8 py-6 font-medium max-w-[1050px] w-full text-[14px] text-[#020202]">
                {{ $type->title }}
            </td>

            <!-- Score -->
            <td class="px-8 py-6 text-[14px] text-[#020202]">
                <span class="flex justify-center items-center py-2 px-3 bg-[#ffeaec] border rounded-md border-[#FF9AA0]">
                    {{ $type->score }}
                </span>
            </td>

            <!-- Action Buttons -->
            <td class="px-8 py-6 text-[14px] text-[#020202] flex">
                <!-- Edit Button -->
                <a href="{{ route('learningtype.edit', $type->id) }}" 
                   class="flex justify-center items-center py-2 px-3 bg-[#ffeaec] border rounded-md border-[#FF9AA0] mr-2" 
                   title="Edit">
                    <svg width="21" height="20" viewBox="0 0 21 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3.95229 13.591L3.30078 17.5L7.20983 16.8485C7.88865 16.7354 8.51516 16.413 9.00177 15.9263L17.8173 7.11073C18.4619 6.46601 18.4619 5.42075 17.8172 4.77605L16.0247 2.98353C15.3799 2.33881 14.3346 2.33882 13.6899 2.98356L4.87446 11.7992C4.38784 12.2857 4.06542 12.9122 3.95229 13.591Z" stroke="#EB1C24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M12.4648 5L15.7982 8.33333" stroke="#EB1C24" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>

  <div x-data="{ showConfirm: false, valueId: null }"> <!-- <-- x-data wrapper -->


             <button  @click="showConfirm = true; valueId = {{ $type->id }}" 
                            class="flex justify-center items-center py-2 px-3 bg-[#ffeaec] border rounded-md border-[#FF9AA0]" 
                            title="Delete"
                           >
                        <svg width="21" height="20" viewBox="0 0 21 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M17.0508 4.58301L16.5344 12.9373C16.4024 15.0717 16.3364 16.1389 15.8014 16.9063C15.5369 17.2856 15.1964 17.6058 14.8014 17.8463C14.0025 18.333 12.9333 18.333 10.7947 18.333C8.65338 18.333 7.5827 18.333 6.78332 17.8454C6.38811 17.6044 6.04745 17.2837 5.78301 16.9037C5.24818 16.1352 5.18366 15.0664 5.05462 12.929L4.55078 4.58301" stroke="#EB1C24" stroke-width="1.25" stroke-linecap="round"/>
                            <path d="M3.30078 4.58366H18.3008M14.1805 4.58366L13.6117 3.4101C13.2338 2.63054 13.0448 2.24076 12.7189 1.99767C12.6466 1.94374 12.57 1.89578 12.4899 1.85424C12.129 1.66699 11.6959 1.66699 10.8295 1.66699C9.94145 1.66699 9.49745 1.66699 9.13051 1.86209C9.0492 1.90533 8.9716 1.95524 8.89852 2.0113C8.56881 2.26424 8.38464 2.66828 8.01629 3.47638L7.51155 4.58366" stroke="#EB1C24" stroke-width="1.25" stroke-linecap="round"/>
                            <path d="M8.71484 13.75V8.75" stroke="#EB1C24" stroke-width="1.25" stroke-linecap="round"/>
                            <path d="M12.8867 13.75V8.75" stroke="#EB1C24" stroke-width="1.25" stroke-linecap="round"/>
                        </svg>
                    </button>
   
    <!-- Delete Confirmation Modal -->
    <div x-show="showConfirm" x-cloak
         class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            <h2 class="text-lg font-semibold text-red-600 mb-4">Confirm Deletion</h2>
            <p class="text-gray-700 mb-6">Are you sure you want to delete this value?</p>

            <div class="flex justify-end gap-3">
                <button @click="showConfirm = false" 
                        class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">
                    Cancel
                </button>
                <button @click="$wire.delete(valueId); showConfirm = false" 
                        class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                    Delete
                </button>
            </div>
        </div>
    </div>

</div>

                  
             
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="3" class="px-8 py-6 text-center text-gray-500">
                No learning types found.
            </td>
        </tr>
    @endforelse
</tbody>

								</table>
						</div>

					</div>
				</div>
			</div>
	</div>
