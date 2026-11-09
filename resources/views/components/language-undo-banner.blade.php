{{--
    Shown once, right after a person switches language. It speaks the
    language they came from, so a switch made by mistake is one tap to undo.
--}}
@php
    $languages = config('locales.available');
    $previous = session('locale_previous');
    $current = app()->getLocale();
@endphp

@if (is_string($previous) && isset($languages[$previous]) && $previous !== $current)
    @php($phrases = $languages[$previous]['switcher'])
    <div translate="no" lang="{{ $previous }}" dir="{{ ($languages[$previous]['rtl'] ?? false) ? 'rtl' : 'ltr' }}"
        x-data="{ open: true }" x-show="open" x-init="setTimeout(() => open = false, 30000)"
        class="notranslate flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-zinc-200 bg-zinc-50 px-4 py-2 text-sm text-zinc-900 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-100"
        data-test="language-undo-banner">
        <div class="flex items-center gap-2">
            <flux:icon icon="language" class="size-4 shrink-0" />
            <span>{{ str_replace(':language', $languages[$current]['native'], $phrases['changed']) }}</span>
        </div>
        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('language.update') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $previous }}">
                <input type="hidden" name="undo" value="1">
                <flux:button type="submit" size="sm" variant="primary" icon="arrow-uturn-left">
                    {{ str_replace(':language', $languages[$previous]['native'], $phrases['undo']) }}
                </flux:button>
            </form>
            <flux:button size="sm" variant="ghost" icon="x-mark" x-on:click="open = false" />
        </div>
    </div>
@endif
