{{-- The institution's default language, shared by the create and edit forms. --}}
@props(['selected' => null])

<flux:select name="locale" label="Language"
    description="The language everyone in your institution starts in. Each person can still switch for themselves from the Language menu.">
    @foreach (config('locales.available') as $code => $language)
        <flux:select.option value="{{ $code }}" translate="no" class="notranslate"
            :selected="old('locale', $selected ?? config('locales.default')) === $code">
            {{ $language['name'] }} — {{ $language['native'] }}
        </flux:select.option>
    @endforeach
</flux:select>
