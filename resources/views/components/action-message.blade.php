

@props(['on'])

<div x-data="{ shown: false, timeout: null }"
     x-init="@this.on('{{ $on }}', () => {
         shown = true;
         clearTimeout(timeout);
         timeout = setTimeout(() => { shown = false }, 2000);
     })"
     x-show.transition.out.opacity.duration.1500ms="shown"
     {{ $attributes->merge(['class' => 'text-red-600 font-medium']) }}>
    {{ $slot }}
</div>
