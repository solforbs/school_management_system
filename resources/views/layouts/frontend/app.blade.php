@props([
    'title' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    @include('partials.head')
</head>

<body class="bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <x-preloader />
    <x-frontend.navbar />

    <main>
        {{ $slot }}
    </main>

    <x-frontend.footer />

    <livewire:chat-widget />

    @fluxScripts
</body>

</html>
