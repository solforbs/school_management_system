<div class="md:col-span-2">
    <label class="block text-sm font-medium text-gray-700 mb-2">Pro features</label>
    <p class="text-xs text-gray-500 mb-3">
        Capabilities this plan switches on inside the modules it already includes. Tick the ones it should carry, or
        add a new one below — it becomes available to every plan.
    </p>

    {{-- What the surrounding (plain POST) form actually submits. --}}
    @foreach ($selected as $key)
        <input type="hidden" name="features[]" value="{{ $key }}" />
    @endforeach

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        @foreach ($this->catalog as $key => $label)
            @php $isOn = in_array($key, $selected, true); @endphp
            <div wire:key="feature-{{ $key }}"
                class="flex items-start justify-between gap-2 rounded-md border px-3 py-2 text-sm transition
                    {{ $isOn ? 'border-blue-300 bg-blue-50/60' : 'border-gray-200 bg-white' }}">
                <label class="flex items-start gap-2 cursor-pointer flex-1">
                    <input type="checkbox" class="mt-0.5 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        wire:click="toggle('{{ $key }}')" @checked($isOn) />
                    <span>
                        <span class="font-medium text-gray-800">{{ $label }}</span>
                        <span class="block text-[11px] text-gray-400 font-mono">{{ $key }}</span>
                    </span>
                </label>

                @unless (array_key_exists($key, \App\Models\Plan::FEATURES))
                    <button type="button" wire:click="deleteFeature('{{ $key }}')"
                        title="Delete this feature definition"
                        class="text-gray-300 hover:text-red-500 shrink-0">
                        <flux:icon icon="trash" class="size-4" />
                    </button>
                @endunless
            </div>
        @endforeach
    </div>

    <div class="mt-3 flex flex-wrap items-start gap-2">
        <div class="flex-1 min-w-[200px]">
            <flux:input type="text" wire:model="newLabel" wire:keydown.enter.prevent="addFeature"
                placeholder="New feature name, e.g. Bulk SMS" class="w-full" />
            @error('newLabel')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        </div>
        <flux:button type="button" variant="filled" wire:click="addFeature" icon="plus">Add feature</flux:button>
    </div>

    @error('features')
        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
    @enderror
</div>
