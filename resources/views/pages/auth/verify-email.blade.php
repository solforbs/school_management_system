<x-layouts::auth :title="__('Verify Your Email')">
    <div class="flex flex-col items-center text-center gap-6 py-2">
        <div class="size-16 rounded-2xl bg-indigo-50 dark:bg-indigo-950/50 border border-indigo-100 dark:border-indigo-900/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400 shadow-sm">
            <flux:icon icon="envelope-open" class="size-8" />
        </div>

        <div>
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">Check your email inbox</h2>
            <flux:text class="text-zinc-500 text-sm mt-1.5 max-w-sm">
                {{ __('We have sent a verification link to your registered email address. Please click the link to activate your account.') }}
            </flux:text>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="w-full rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200/80 dark:border-emerald-800/60 p-3.5 flex items-center gap-3 text-left">
                <flux:icon icon="check-circle" class="size-5 text-emerald-600 dark:text-emerald-400 shrink-0" />
                <span class="text-xs font-semibold text-emerald-900 dark:text-emerald-200">
                    {{ __('A new verification link has been dispatched to your email address.') }}
                </span>
            </div>
        @endif

        <div class="w-full flex flex-col gap-3 pt-2">
            <form method="POST" action="{{ route('verification.send') }}" class="w-full">
                @csrf
                <flux:button type="submit" variant="primary" class="w-full font-semibold shadow-sm">
                    {{ __('Resend Verification Email') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="w-full">
                @csrf
                <flux:button variant="ghost" type="submit" class="w-full text-xs text-zinc-500 hover:text-zinc-900" data-test="logout-button">
                    {{ __('Log out of current session') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts::auth>
