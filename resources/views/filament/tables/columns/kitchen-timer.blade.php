@php
    $record = $getRecord();
    $processingFromOrder = data_get($record?->order?->status_times ?? [], 'processing');
    $processingAt = $processingFromOrder ? \Carbon\Carbon::parse($processingFromOrder) : null;
    $ticketAt = $record?->processing_at;

    if ($processingAt && $ticketAt) {
        $startAt = $processingAt->gt($ticketAt) ? $processingAt : $ticketAt;
    } else {
        $startAt = $processingAt ?: $ticketAt;
    }

    $startTs = $startAt?->timestamp;
@endphp

@once
    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('kitchenTimer', (startTs) => ({
                    startTs: Number(startTs),
                    display: '00:00:00',
                    timer: null,
                    start() {
                        this.tick();
                        this.timer = setInterval(() => this.tick(), 1000);
                    },
                    stop() {
                        if (this.timer) {
                            clearInterval(this.timer);
                            this.timer = null;
                        }
                    },
                    tick() {
                        const now = Math.floor(Date.now() / 1000);
                        let diff = now - this.startTs;
                        if (diff < 0) diff = 0;
                        const max = 24 * 60 * 60;
                        if (diff > max) diff = max;
                        this.display = this.format(diff);
                        if (diff >= max) {
                            this.stop();
                        }
                    },
                    format(sec) {
                        const h = String(Math.floor(sec / 3600)).padStart(2, '0');
                        const m = String(Math.floor((sec % 3600) / 60)).padStart(2, '0');
                        const s = String(sec % 60).padStart(2, '0');
                        return `${h}:${m}:${s}`;
                    },
                }));
            });
        </script>
    @endpush
@endonce

@if (! $startTs)
    <span class="text-gray-400">—</span>
@else
    <div
        x-data="kitchenTimer({{ (int) $startTs }})"
        x-init="start()"
        x-on:alpine:destroy="stop()"
        class="tabular-nums text-sm font-semibold"
        style="color: #dc2626;"
    >
        <span x-text="display"></span>
    </div>
@endif
