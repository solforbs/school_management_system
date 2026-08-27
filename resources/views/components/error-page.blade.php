@props([
    'code',
    'title',
    'message',
    'icon' => 'question-mark-circle',
    'primaryLabel' => null,
    'primaryHref' => null,
])

@php
    $loggedIn = auth()->check();
    $primaryHref ??= $loggedIn ? route('dashboard') : route('home');
    $primaryLabel ??= $loggedIn ? 'Go to dashboard' : 'Go to homepage';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>
        {{ filled($title ?? null) ? $title . ' - ' . config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
    </title>

    <link rel="icon" href="{{ asset('logos/solforbs-logo.png') }}" type="image/png" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('logos/solforbs-logo.png') }}">

    @fonts

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>

<body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
    <div class="flex min-h-svh flex-col items-center justify-center gap-8 p-6 md:p-10">
        <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
            <span class="flex h-9 w-9 items-center justify-center rounded-md">
                <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
            </span>
            <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
        </a>

        <div class="flex w-full max-w-md flex-col items-center gap-6 text-center">
           
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-semibold text-zinc-900 dark:text-white">
                    {{ $title }}
                </h1>
                <p class="text-sm leading-relaxed text-zinc-500 dark:text-zinc-400">
                    {{ $message }}
                </p>
            </div>

            <div class="flex flex-col items-center gap-3 sm:flex-row">
                <a href="{{ $primaryHref }}"
                    class="inline-flex items-center justify-center rounded-md bg-zinc-900 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200">
                    {{ $primaryLabel }}
                </a>
                <a href="javascript:history.back()"
                    class="inline-flex items-center justify-center rounded-md border border-zinc-200 px-5 py-2.5 text-sm font-medium text-zinc-700 transition hover:bg-zinc-50 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5">
                    Go back
                </a>
            </div>

            {{ $slot ?? '' }}
        </div>
    </div>
</body>

</html>
