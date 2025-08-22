@props(['type' => 'button', 'variant' => 'primary'])

@php
$classes = [
    'primary' => 'bg-blue-600 hover:bg-blue-700 text-white',
    'secondary' => 'bg-gray-600 hover:bg-gray-700 text-white',
    'danger' => 'bg-red-600 hover:bg-red-700 text-white',
    'outline' => 'border border-gray-300 bg-white hover:bg-gray-50 text-gray-700',
][$variant];
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => "inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150 $classes"]) }}>
    {{ $slot }}
</button>
