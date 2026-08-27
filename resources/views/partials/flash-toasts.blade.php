@php
    // Anything a controller flashed on a redirect, shown as a Flux toast
    // rather than an inline banner each page has to render for itself.
    // Livewire components raise their own toasts through Flux::toast() and
    // never come through here.
    $flashedToasts = collect([
        ['variant' => 'success', 'text' => session('success')],
        ['variant' => 'danger', 'text' => session('error')],
        ['variant' => 'warning', 'text' => session('warning')],
    ])->filter(fn ($toast) => filled($toast['text']))->values();
@endphp

@if ($flashedToasts->isNotEmpty())
    {{-- livewire:navigated fires once on a full load and again after every
    wire:navigate swap, and only once Flux's own scripts have booted - so the
    toast group is always listening by the time this runs. --}}
    <script data-navigate-once>
        (function () {
            var toasts = @js($flashedToasts);

            document.addEventListener('livewire:navigated', function () {
                toasts.forEach(function (toast) {
                    window.Flux && window.Flux.toast({ text: toast.text, variant: toast.variant });
                });
            }, { once: true });
        })();
    </script>
@endif
