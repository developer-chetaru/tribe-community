<div class="md:col-span-2 flex justify-between">
    <div class="px-4 sm:px-0">
        <h3 class="text-xl font-semibold text-red-600">{{ $title }}</h3>

        @if(!empty($description))
            <p class="border-t border-red-600 mt-1 mb-5">
                {{ $description }}
            </p>
        @endif
    </div>

    <div class="px-4 sm:px-0">
        {{ $aside ?? '' }}
    </div>
</div>
