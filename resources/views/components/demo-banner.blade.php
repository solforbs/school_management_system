@if (auth()->check() && currentInstitution()?->is_demo)
    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-sky-300 bg-sky-50 px-4 py-2 text-sm text-sky-900 dark:border-sky-700/60 dark:bg-sky-950/60 dark:text-sky-200">
        <div class="flex items-center gap-2">
            <flux:icon icon="beaker" class="size-4 shrink-0" />
            <span>
                You're exploring the <strong>demo school</strong> — anyone can change the data here, and it resets
                every few hours.
            </span>
        </div>
        @if (session()->has('demo.return_to'))
            <form method="POST" action="{{ route('demo.leave') }}">
                @csrf
                <flux:button type="submit" size="sm" variant="primary" icon="arrow-uturn-left">
                    Leave demo
                </flux:button>
            </form>
        @endif
    </div>
@endif
