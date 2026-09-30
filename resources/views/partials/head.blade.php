<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="{{ asset('logos/solforbs-logo.png') }}" type="image/png" sizes="any">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

{{-- PWA --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">
<meta name="theme-color" content="#0047FF">
<meta name="application-name" content="{{ config('app.name') }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Light mode by default --}}
