{{-- resources/views/components/ui/choice-row.blade.php --}}
@props(['value','left'=>'','right'=>''])

<button type="button"
        x-on:click="selected = @js($value)"
        class="desk:w-[354px] md:w-[336px] w-[331px] flex items-center justify-between rounded-lg border px-3 py-2 transition-colors"
        :class="selected === @js($value)
        ? 'bg-[#FF7500] border-transparent text-white'
        : 'bg-white border-[#E5E7EB] text-[#666666] hover:border-[#FF7500]/50'">

    <span class="inline-flex items-center gap-2">
        {{ $left ?? '' }}
    </span>

    <span class="inline-flex items-center gap-2">
        {{ $right ?? '' }}
    </span>
</button>
