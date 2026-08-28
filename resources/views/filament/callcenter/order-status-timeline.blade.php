@php
    use App\Enums\OrderStatus;
    use Illuminate\Support\Carbon;

    $current = (string) ($current ?? OrderStatus::New->value);
    $currentRank = (int) ($ranks[$current] ?? OrderStatus::New->rank());
    $cancelled = (bool) ($cancelled ?? false);
    $allowed = $allowed ?? [];
    $times = $times ?? [];
    $timeAliases = $timeAliases ?? [];
    $statePath = trim((string) ($statePath ?? 'data'), '.');
    $fieldPath = static fn (string $field): string => $statePath === '' ? $field : "{$statePath}.{$field}";

    $formatTime = static function (?string $status) use ($times, $timeAliases): ?string {
        $keys = array_filter(array_merge([$status], $timeAliases[$status] ?? []));
        $value = null;

        foreach ($keys as $key) {
            if (! empty($times[$key])) {
                $value = $times[$key];
                break;
            }
        }

        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->format('d.m.Y H:i');
        } catch (Throwable) {
            return (string) $value;
        }
    };
@endphp

@once
    <style>
        .np-status-timeline {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .np-status-timeline__title {
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            line-height: 16px;
            text-transform: uppercase;
        }

        .np-status-timeline__cancelled {
            border: 1px solid #fecaca;
            border-radius: 10px;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 14px;
            font-weight: 700;
            padding: 8px 12px;
        }

        .np-status-timeline__list {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .np-status-timeline__list.is-cancelled {
            opacity: 0.45;
        }

        .np-status-timeline__row {
            align-items: flex-start;
            background: #ffffff;
            border: 1px solid transparent;
            border-radius: 10px;
            color: #111827;
            cursor: pointer;
            display: flex;
            gap: 12px;
            padding: 8px 10px;
            text-align: left;
            transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
            width: 100%;
        }

        .np-status-timeline__row:hover:not(:disabled) {
            background: #f8f7ff;
            border-color: #e8e5ff;
        }

        .np-status-timeline__row:disabled {
            cursor: not-allowed;
        }

        .np-status-timeline__row.is-active {
            background: #f3f0ff;
            border-color: #ece9ff;
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.07);
            color: #4338ca;
        }

        .np-status-timeline__row.is-future {
            color: #64748b;
        }

        .np-status-timeline__axis {
            align-items: center;
            display: flex;
            flex-direction: column;
            padding-top: 2px;
        }

        .np-status-timeline__marker {
            align-items: center;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            color: #94a3b8;
            display: flex;
            height: 22px;
            justify-content: center;
            width: 22px;
        }

        .np-status-timeline__row.is-done .np-status-timeline__marker {
            background: #3cb371;
            border-color: #3cb371;
            box-shadow: 0 3px 8px rgba(60, 179, 113, 0.2);
            color: #ffffff;
        }

        .np-status-timeline__row.is-active .np-status-timeline__marker {
            background: #4f46e5;
            border-color: #4f46e5;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.24);
            color: #ffffff;
        }

        .np-status-timeline__future-dot {
            background: currentColor;
            border-radius: 999px;
            height: 6px;
            width: 6px;
        }

        .np-status-timeline__line {
            background: #cbd5e1;
            height: 28px;
            margin-top: 4px;
            width: 1px;
        }

        .np-status-timeline__line.is-done {
            background: #3cb371;
        }

        .np-status-timeline__content {
            flex: 1;
            min-width: 0;
            padding-bottom: 4px;
        }

        .np-status-timeline__label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            line-height: 20px;
        }

        .np-status-timeline__time {
            color: #64748b;
            display: block;
            font-size: 12px;
            line-height: 18px;
        }

        .np-status-timeline__row.is-active .np-status-timeline__time {
            color: #4338ca;
        }

        .np-status-timeline__cancel-button {
            background: #ffffff;
            border: 1px solid #fecaca;
            border-radius: 10px;
            color: #b91c1c;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            padding: 8px 12px;
            transition: background-color 0.15s ease;
            width: 100%;
        }

        .np-status-timeline__cancel-button:hover:not(:disabled) {
            background: #fef2f2;
        }

        .np-status-timeline__cancel-button:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }
    </style>
@endonce

<div class="np-status-timeline">
    <div class="np-status-timeline__title">
        Статус замовлення
    </div>

    @if($cancelled)
        <div class="np-status-timeline__cancelled">
            Скасовано
        </div>
    @endif

    <div class="np-status-timeline__list {{ $cancelled ? 'is-cancelled' : '' }}">
        @foreach($statuses as $status => $label)
            @php
                $rank = (int) ($ranks[$status] ?? 0);
                $isActive = ! $cancelled && $status === $current;
                $isDone = ! $cancelled && $rank < $currentRank;
                $isFuture = ! $isActive && ! $isDone;
                $time = $formatTime($status);
                $isRollback = ! $cancelled && $rank < $currentRank;
                $isAllowed = array_key_exists($status, $allowed);
                $isDisabled = $cancelled || ! $isAllowed || ($isRollback && ! $canDowngrade);
                $rowStateClass = $isActive ? 'is-active' : ($isDone ? 'is-done' : 'is-future');
            @endphp

            <button
                type="button"
                class="np-status-timeline__row {{ $rowStateClass }}"
                @if($isDisabled)
                    disabled
                @elseif($isRollback)
                    x-data
                    x-on:click="
                        $wire.set(@js($fieldPath('pending_status')), @js($status));
                        $wire.set(@js($fieldPath('downgrade_pending')), true);
                        $wire.set(@js($fieldPath('status_ui')), @js($current));
                    "
                @else
                    x-data
                    x-on:click="
                        $wire.set(@js($fieldPath('status')), @js($status));
                        $wire.set(@js($fieldPath('status_ui')), @js($status));
                        $wire.set(@js($fieldPath('pending_status')), null);
                        $wire.set(@js($fieldPath('downgrade_pending')), false);
                        $wire.set(@js($fieldPath('downgrade_reason')), null);
                    "
                @endif
            >
                <span class="np-status-timeline__axis">
                    <span class="np-status-timeline__marker">
                        @if($isDone)
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M3.25 8.2L6.45 11.4L12.75 4.6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @elseif($isActive)
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                <path d="M13.25 2.75L7.45 13.25L6.25 8.75L2.75 6.65L13.25 2.75Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        @else
                            <span class="np-status-timeline__future-dot"></span>
                        @endif
                    </span>
                    @if(! $loop->last)
                        <span class="np-status-timeline__line {{ $rank < $currentRank ? 'is-done' : '' }}"></span>
                    @endif
                </span>

                <span class="np-status-timeline__content">
                    <span class="np-status-timeline__label">{{ $label }}</span>
                    @if($time && ($isDone || $isActive))
                        <span class="np-status-timeline__time">{{ $time }}</span>
                    @elseif($isActive)
                        <span class="np-status-timeline__time">поточний</span>
                    @endif
                </span>
            </button>
        @endforeach
    </div>

    <button
        type="button"
        class="np-status-timeline__cancel-button"
        @if($cancelled || ! array_key_exists($cancelStatus, $allowed))
            disabled
        @else
            x-data
            x-on:click="
                $wire.set(@js($fieldPath('status')), @js($cancelStatus));
                $wire.set(@js($fieldPath('status_ui')), @js($cancelStatus));
                $wire.set(@js($fieldPath('pending_status')), null);
                $wire.set(@js($fieldPath('downgrade_pending')), false);
                $wire.set(@js($fieldPath('downgrade_reason')), null);
            "
        @endif
    >
        Скасувати замовлення
    </button>
</div>
