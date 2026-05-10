@props(['as' => 'h2', 'class' => '', 'underline' => true, 'size' => 'default'])
@php
    $sizeClasses = match ($size) {
        'catalog-subcategory' => 'text-[42px] leading-[46px]',
        default => 'text-[40px] md:text-[64px] md:leading-[64px]',
    };
@endphp
<{{ $as }} {{ $attributes->merge([
    'class' => 'inline-block font-intro '.$sizeClasses.' font-bold text-[#19191A] '.($underline ? 'border-b-2 border-[#FF7500] ' : '').$class
]) }}>
{{ $slot }}
</{{ $as }}>
