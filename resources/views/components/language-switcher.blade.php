{{--
    Lets a person change the app language for themselves. Everything here
    is excluded from machine translation and written in each language's own
    words, so someone who switched by mistake can still find their way back.
    Each switch asks first, in both the new and the current language.
--}}
@props(['align' => 'start', 'position' => 'top'])

@php
    $languages = config('locales.available');
    $current = app()->getLocale();
    $currentLanguage = $languages[$current] ?? $languages[config('locales.default')];
    $modalPrefix = 'switch-language-'.\Illuminate\Support\Str::random(8).'-';
@endphp

{{-- x-data: Alpine only wires up the menu's x-on:click inside a component. --}}
<div x-data translate="no" {{ $attributes->merge(['class' => 'notranslate']) }}>
    <flux:dropdown :position="$position" :align="$align">
        <flux:button size="sm" variant="ghost" icon="language" icon:trailing="chevron-up-down"
            class="w-full justify-start" data-test="language-switcher">
            <span class="font-semibold uppercase">{{ $current }}</span>
            <span class="text-zinc-500 dark:text-zinc-400">{{ $currentLanguage['native'] }}</span>
        </flux:button>

        <flux:menu>
            <flux:menu.heading>{{ $currentLanguage['switcher']['language'] }}</flux:menu.heading>
            @foreach ($languages as $code => $language)
                @if ($code === $current)
                    <flux:menu.item icon="check" disabled lang="{{ $code }}">
                        {{ $language['native'] }}
                    </flux:menu.item>
                @else
                    <flux:menu.item lang="{{ $code }}" x-on:click="$flux.modal('{{ $modalPrefix.$code }}').show()">
                        {{ $language['native'] }}
                        @if ($language['native'] !== $language['name'])
                            <span class="ms-1 text-xs text-zinc-500">{{ $language['name'] }}</span>
                        @endif
                    </flux:menu.item>
                @endif
            @endforeach
        </flux:menu>
    </flux:dropdown>

    @foreach ($languages as $code => $language)
        @continue($code === $current)

        <flux:modal :name="$modalPrefix.$code" class="max-w-md" translate="no">
            <div class="space-y-5">
                @foreach (array_unique([$code, $current]) as $phraseLocale)
                    @php($phrases = $languages[$phraseLocale]['switcher'])
                    <div lang="{{ $phraseLocale }}" dir="{{ ($languages[$phraseLocale]['rtl'] ?? false) ? 'rtl' : 'ltr' }}"
                        @class(['border-t border-zinc-200 pt-4 dark:border-zinc-700' => ! $loop->first])>
                        <flux:heading size="lg">
                            {{ str_replace(':language', $language['native'], $phrases['question']) }}
                        </flux:heading>
                        <flux:subheading>{{ $phrases['note'] }}</flux:subheading>
                    </div>
                @endforeach

                {{-- Always English: the one language support staff can talk anyone through. --}}
                <flux:text lang="en" dir="ltr" class="text-xs">
                    Stuck in a language you can't read? Add <code>?lang=en</code> to the end of the address to return to English.
                </flux:text>

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="filled">
                            {{ $currentLanguage['switcher']['cancel'] }}
                        </flux:button>
                    </flux:modal.close>

                    <form method="POST" action="{{ route('language.update') }}">
                        @csrf
                        <input type="hidden" name="locale" value="{{ $code }}">
                        <flux:button type="submit" variant="primary" data-test="confirm-language-{{ $code }}">
                            {{ $language['switcher']['confirm'] }}
                            @if ($language['switcher']['confirm'] !== $currentLanguage['switcher']['confirm'])
                                / {{ $currentLanguage['switcher']['confirm'] }}
                            @endif
                        </flux:button>
                    </form>
                </div>
            </div>
        </flux:modal>
    @endforeach
</div>
