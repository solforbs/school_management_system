<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="{{ asset('logos/solforbs-logo.png') }}" sizes="any">
<link rel="icon" href="{{ asset('logos/solforbs-logo.png') }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset('logos/solforbs-logo.png') }}">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
{{-- Light mode by default --}}
