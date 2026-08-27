@php
    $selectedModules = old('modules', $plan->modules ?? []);
    $selectedFeatures = old('features', $plan->features ?? []);
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
    <div>
        <flux:input type="text" name="name" label="Plan Name"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            value="{{ old('name', $plan->name ?? '') }}" required />
        @error('name')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <flux:input type="number" step="0.01" min="0" name="price" label="Price"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            value="{{ old('price', $plan->price ?? '0.00') }}" required />
        @error('price')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <flux:select name="billing_cycle" label="Billing Cycle"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            @foreach (['monthly' => 'Monthly', 'yearly' => 'Yearly', 'lifetime' => 'Lifetime'] as $value => $label)
                <option value="{{ $value }}"
                    {{ old('billing_cycle', $plan->billing_cycle ?? 'monthly') === $value ? 'selected' : '' }}>
                    {{ $label }}</option>
            @endforeach
        </flux:select>
        @error('billing_cycle')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="flex items-end gap-6">
        <flux:checkbox name="is_active" value="1" label="Active"
            {{ old('is_active', $plan->is_active ?? true) ? 'checked' : '' }} />

        <flux:checkbox name="is_featured" value="1" label="Most popular"
            {{ old('is_featured', $plan->is_featured ?? false) ? 'checked' : '' }} />

        <flux:checkbox name="is_custom_priced" value="1" label="Quoted price"
            description="Shows &quot;Custom&quot; instead of the amount, and sends buyers to contact us."
            {{ old('is_custom_priced', $plan->is_custom_priced ?? false) ? 'checked' : '' }} />
    </div>

    <div class="md:col-span-2">
        <flux:textarea name="description" rows="2" label="Description"
            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $plan->description ?? '') }}</flux:textarea>
        @error('description')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-2">Modules included</label>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            @foreach (\App\Models\Plan::MODULES as $module)
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="modules[]" value="{{ $module }}"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        {{ in_array($module, $selectedModules) ? 'checked' : '' }} />
                    {{ $module }}
                </label>
            @endforeach
        </div>
        @error('modules')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-2">Pro features</label>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
            @foreach (\App\Models\Plan::FEATURES as $key => $label)
                <label class="flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="features[]" value="{{ $key }}"
                        class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        {{ in_array($key, $selectedFeatures) ? 'checked' : '' }} />
                    {{ $label }}
                </label>
            @endforeach
        </div>
        @error('features')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
