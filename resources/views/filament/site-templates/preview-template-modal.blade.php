@if($error)
    <div style="padding:12px;border-radius:10px;background:#fef2f2;color:#991b1b;border:1px solid #fecaca;">
        <div style="font-weight:700;margin-bottom:6px;">Не удалось построить предпросмотр</div>
        <div>{{ $error }}</div>
    </div>
@else
    <div style="max-height:75vh;overflow:auto;border:1px solid #e5e7eb;border-radius:12px;background:#fff;">
        {!! $html !!}
    </div>
@endif
