@props(['as' => 'h2', 'class' => ''])
<{{ $as }} {{ $attributes->merge([
    'class' => 'inline-block font-intro text-[40px] md:text-[64px] md:leading-[64px] font-bold text-[#19191A] border-b-2 border-[#FF7500] '.$class
]) }}>
{{ $slot }}
</{{ $as }}>
