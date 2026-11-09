<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Link already used') }}</title>
    @vite(['resources/css/app.css'])
</head>

<body class="flex min-h-screen items-center justify-center bg-zinc-50 p-6">
    <div class="w-full max-w-md rounded-xl bg-white p-8 text-center shadow-sm">
        <h1 class="text-lg font-semibold text-zinc-900">{{ __('This link has already been used') }}</h1>

        <p class="mt-3 text-sm text-zinc-600">
            {{ __('For security, a report card download link works only once. If you still need a copy, please contact the school and they can send you a new link.') }}
        </p>

        @if ($reportCard->downloaded_at)
            <p class="mt-4 text-xs text-zinc-400">
                {{ __('Downloaded on :date', ['date' => $reportCard->downloaded_at->format('d M Y, h:i A')]) }}
            </p>
        @endif
    </div>
</body>

</html>
