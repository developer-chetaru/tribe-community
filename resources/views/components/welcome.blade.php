<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            {{-- Controls --}}
            <div class="flex justify-between items-center mb-6">
                <div class="flex space-x-2 items-center">
                    <span class="text-lg font-semibold text-gray-700">Comparative Period:</span>
                    @foreach (['Daily', 'Weekly', 'Monthly', 'Yearly'] as $option)
                        <button class="px-4 py-2 rounded-md bg-white text-gray-700 hover:bg-gray-100 transition">
                            {{ $option }}
                        </button>
                    @endforeach
                </div>

                <div class="flex space-x-4 items-center">
                    <select class="px-4 py-2 border rounded-md text-gray-700">
                        <option value="50">50</option>
                    </select>
                    <input type="text" placeholder="Search by name" class="px-4 py-2 border rounded-md text-gray-700" />
                </div>
            </div>

            {{-- Cards --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach ($cards as $card)
                    <div class="bg-white p-6 rounded-lg shadow hover:shadow-lg transition">
                        <h2 class="text-xl font-semibold text-gray-700">{{ $card['name'] }}</h2>

                        <p class="text-gray-500 mt-4">
                            Culture:
                            <span class="{{ $card['culture'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                {{ $card['culture'] }}
                            </span>
                        </p>

                        <p class="text-gray-500">
                            Engagement:
                            <span class="{{ $card['engagement'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                {{ $card['engagement'] }}
                            </span>
                        </p>

                        <p class="text-gray-500">
                            Good Day:
                            <span class="{{ $card['goodDay'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                {{ $card['goodDay'] }}%
                            </span>
                        </p>

                        <p class="text-gray-500">
                            Bad Day:
                            <span class="{{ $card['badDay'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                {{ $card['badDay'] }}%
                            </span>
                        </p>

                        <p class="text-gray-500">
                            HPTM:
                            <span class="{{ $card['hptm'] >= 0 ? 'text-green-500' : 'text-red-500' }}">
                                {{ $card['hptm'] }}
                            </span>
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
