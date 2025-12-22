@props(['for', 'bag' => 'default'])

@php
    $errorBag = $bag === 'default' ? $errors : $errors->getBag($bag);
@endphp

@if($errorBag->has($for))
    <p {{ $attributes->merge(['class' => 'text-sm text-red-600']) }}>{{ $errorBag->first($for) }}</p>
@endif
