@php
    $rawName = trim(auth()->user()->name);
    $parts = preg_split('/\s+/', $rawName);
    $honorifics = [
        'mr',
        'mr.',
        'mrs',
        'mrs.',
        'ms',
        'ms.',
        'dr',
        'dr.',
        'prof',
        'prof.',
        'tr',
        'tr.',
        'teacher',
        'hon',
        'hon.',
    ];
    if (count($parts) > 1 && in_array(strtolower($parts[0]), $honorifics)) {
        $greetingName = $parts[0] . ' ' . $parts[1];
    } else {
        $greetingName = $parts[0] ?? 'User';
    }

    $portalBadge =
        currentInstitution()?->name ??
        (auth()->user()->hasRole('Teacher')
            ? 'Faculty Portal'
            : (auth()->user()->hasRole('Student')
                ? 'Student Portal'
                : (auth()->user()->hasRole('Parent')
                    ? 'Parent Portal'
                    : (auth()->user()->hasRole('Accountant')
                        ? 'Finance Portal'
                        : 'Institutional Portal'))));
@endphp

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
    <div class="space-y-1">
        <div class="flex items-center gap-2 flex-wrap">
            <span
                class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-emerald-50 text-emerald-700 border border-emerald-200/60 dark:bg-emerald-950/50 dark:text-emerald-400 dark:border-emerald-800/60 flex items-center gap-1.5">
                <span class="size-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                {{ $portalBadge }}
            </span>
            <span class="text-xs text-zinc-400">&bull;</span>
            <span class="text-xs text-zinc-500 font-medium">{{ now()->format('l, d F Y') }}</span>
        </div>

        <flux:heading size="xl" class="text-2xl font-black tracking-tight text-zinc-900 dark:text-white">
            Welcome back, {{ $greetingName }}
        </flux:heading>

        <flux:text class="text-xs text-zinc-500">
            @if ($roleName)
                Role: <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ $roleName }}</span>
            @endif
            @if (currentInstitution()?->isApproved())
                @can('view report')
                    <span class="text-zinc-300 mx-1">&bull;</span>
                    <a href="{{ route('report.index') }}" wire:navigate.hover
                        class="text-blue-600 dark:text-blue-400 hover:underline inline-flex items-center gap-0.5 font-medium">
                        Explore Analytics
                        <flux:icon icon="arrow-right" class="size-3" />
                    </a>
                @endcan
            @endif
        </flux:text>
    </div>

    @include('dashboard.partials._quick_actions')
</div>
