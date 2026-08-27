<footer class="border-t border-zinc-200 bg-zinc-50 dark:border-white/10 dark:bg-zinc-950">
    <x-frontend.container class="py-16">
        <div class="grid gap-12 lg:grid-cols-3">
            <div class="lg:max-w-sm">
                <a href="{{ route('home') }}" class="flex items-center gap-2 font-semibold text-zinc-900 dark:text-white" wire:navigate>
                    <span class="flex size-8 items-center justify-center rounded-lg bg-zinc-900 text-white dark:bg-white dark:text-zinc-900">
                        <x-app-logo-icon class="size-4 fill-current" />
                    </span>
                    {{ config('app.name') }}
                </a>
                <p class="mt-4 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
                    A single platform for admissions, attendance, fees, exams and parent communication —
                    built so school offices spend less time on paperwork and more time teaching.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-8 lg:col-span-2 lg:grid-cols-3">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Company</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li><a href="{{ route('about') }}" wire:navigate class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">About</a></li>
                        <li><a href="{{ route('services') }}" wire:navigate class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">Services</a></li>
                        <li><a href="{{ route('plans') }}" wire:navigate class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">Plans</a></li>
                        <li><a href="{{ route('contact') }}" wire:navigate class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">Contact</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Account</h3>
                    <ul class="mt-4 space-y-3 text-sm">
                        <li><a href="{{ route('login') }}" wire:navigate class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">Log in</a></li>
                        <li><a href="{{ route('register') }}" wire:navigate class="text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-white">Create an account</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Modules</h3>
                    <ul class="mt-4 space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
                        <li>Attendance &amp; biometrics</li>
                        <li>Fees &amp; payments</li>
                        <li>Exams &amp; report cards</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-zinc-200 pt-8 sm:flex-row dark:border-white/10">
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                &copy; {{ now()->year }} {{ config('app.name') }}. All rights reserved.
            </p>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                Have a question? Use the chat assistant in the corner, or
                <a href="{{ route('contact') }}" wire:navigate class="font-medium text-zinc-700 hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-white">get in touch</a>.
            </p>
        </div>
    </x-frontend.container>
</footer>
