@props([
    'tooltipText' => 'Tooltip'
])

<div x-data="{ tooltip: false }" x-cloak  class="relative inline-block">
    <div 
        @mouseenter="tooltip = true" 
        @mouseleave="tooltip = false"
    >
        {{ $slot }}
    </div>

    <!-- Tooltip -->
    <div 
        x-show="tooltip" 
        x-transition
        class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 bg-red-500 text-white text-xs rounded py-1 px-2 whitespace-nowrap shadow-lg z-50"
    >
        {{ $tooltipText }}

        <!-- Arrow pointing down -->
        <div class="absolute bottom-[-4px] left-1/2 transform -translate-x-1/2 w-2 h-2 bg-red-500 rotate-45"></div>
    </div>
</div>
