<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
</head>
<body style="margin:0;padding:16px;background:#f8fafc;font-family:Arial,sans-serif;">
    <div style="max-width:1400px;margin:0 auto;border:1px solid #e5e7eb;border-radius:12px;background:#fff;overflow:auto;min-height:calc(100vh - 32px);">
        {!! $html !!}
    </div>
</body>
</html>
