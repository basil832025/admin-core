<x-filament::page>
    <div x-data="{ showParams: $wire.entangle('showParams') }" class="space-y-4">
        <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-3">
            <div class="text-sm font-medium text-gray-700">Параметры отчета</div>

            <x-filament::button
                color="gray"
                size="sm"
                type="button"
                x-on:click="showParams = !showParams"
            >
                <span x-text="showParams ? 'Скрыть параметры' : 'Показать параметры'"></span>
            </x-filament::button>
        </div>

        <div x-cloak x-show="showParams" x-transition>
            {{ $this->form }}
        </div>
    </div>

    @if ($hasResult)
        @php
            $srcDoc = '<!doctype html><html><head><meta charset="UTF-8"><style>html,body{margin:0;padding:0;background:#fff;}*{box-sizing:border-box;}</style></head><body>'
                . ($previewHtml ?? '')
                . '</body></html>';
        @endphp

        <div class="mt-6 rounded-lg border border-gray-200 bg-white p-4">
            <div class="mb-3 text-sm font-medium text-gray-700">Результат отчета</div>
            <iframe
                title="Report preview"
                sandbox="allow-same-origin"
                srcdoc="{!! htmlspecialchars($srcDoc, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') !!}"
                style="width:100%;height:760px;border:1px solid #d1d5db;border-radius:8px;background:#fff;"
            ></iframe>
        </div>
    @endif
</x-filament::page>
