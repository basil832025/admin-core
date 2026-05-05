@php
    /** @var string $statePath */
    /** @var string|null $label */
    $label = $label ?? null;
@endphp

<div
    x-data="{
        statePath: @js($statePath),
        setMinute(minute) {
            let current = this.$wire.get(this.statePath);
            current = (current ?? '').toString().trim();
            if (!current) {
                current = '00:00';
            }
            const m = current.match(/^(\d{1,2}):(\d{1,2})/);
            let h = 0;
            if (m) {
                h = Math.min(23, Math.max(0, parseInt(m[1], 10) || 0));
            }
            const hh = String(h).padStart(2, '0');
            const mm = String(minute).padStart(2, '0');
            this.$wire.set(this.statePath, `${hh}:${mm}`);
        },
    }"
    style="margin-top:-12px; width:100%; display:flex; flex-wrap:wrap; gap:4px; align-items:center; justify-content:flex-start;"
>
    <button type="button" x-on:click="setMinute(0)"
        style="font-size:9px;line-height:1;padding:3px 6px;border-radius:7px;border:1px solid #e5e7eb;background:#ffffff;color:#111827;">00</button>
    <button type="button" x-on:click="setMinute(15)"
        style="font-size:9px;line-height:1;padding:3px 6px;border-radius:7px;border:1px solid #e5e7eb;background:#ffffff;color:#111827;">15</button>
    <button type="button" x-on:click="setMinute(30)"
        style="font-size:9px;line-height:1;padding:3px 6px;border-radius:7px;border:1px solid #e5e7eb;background:#ffffff;color:#111827;">30</button>
    <button type="button" x-on:click="setMinute(45)"
        style="font-size:9px;line-height:1;padding:3px 6px;border-radius:7px;border:1px solid #e5e7eb;background:#ffffff;color:#111827;">45</button>
</div>
