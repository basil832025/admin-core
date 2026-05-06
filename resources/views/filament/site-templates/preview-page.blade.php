<x-filament-panels::page>
    @if($this->renderError)
        <div style="padding:12px;border-radius:10px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;">
            <div style="font-weight:700;margin-bottom:6px;">Не удалось построить предпросмотр</div>
            <div>{{ $this->renderError }}</div>
        </div>
    @else
        <div style="border:1px solid #e5e7eb;border-radius:12px;background:#fff;overflow:auto;min-height:70vh;">
            {!! $this->renderedHtml !!}
        </div>
    @endif
</x-filament-panels::page>
