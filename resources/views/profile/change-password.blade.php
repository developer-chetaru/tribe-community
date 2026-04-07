<x-app-layout>
       <x-slot name="header">
        <h2 class="text-2xl font-bold capitalize text-[#ff2323]">
          Change Password
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="max-w-8xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @livewire('profile.update-password-form')
        </div>
    </div>
</x-app-layout>
