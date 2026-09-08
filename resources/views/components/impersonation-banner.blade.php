@php($impersonator = \App\Support\Impersonation::impersonator())

@if ($impersonator && auth()->check())
    <div class="sticky top-0 z-40 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-700/60 dark:bg-amber-950/60 dark:text-amber-200">
        <div class="flex items-center gap-2">
            <flux:icon icon="eye" class="size-4 shrink-0" />
            <span>
                Viewing as <strong>{{ auth()->user()->name }}</strong>
                ({{ \App\Support\Impersonation::roleLabel(auth()->user()) }}) &mdash;
                signed in as {{ $impersonator->name }}.
            </span>
        </div>
        <form method="POST" action="{{ route('impersonate.leave') }}">
            @csrf
            <flux:button type="submit" size="sm" variant="primary" icon="arrow-uturn-left">
                Return to your account
            </flux:button>
        </form>
    </div>
@endif
