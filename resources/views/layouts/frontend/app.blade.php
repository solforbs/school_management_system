@props([
    'title' => null,
])
<!DOCTYPE html>
<html lang="en" dir="{{ config('locales.available.'.app()->getLocale().'.rtl') ? 'rtl' : 'ltr' }}" class="scroll-smooth">

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
