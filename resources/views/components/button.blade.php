<div class="w-full text-left">
    <button {{ $attributes->merge([
        'type' => 'submit',
        'class' => '!bg-red-500 !text-white py-2 px-4 rounded hover:!bg-red-600'
    ]) }}>
        {{ $slot }}
    </button>
</div>
