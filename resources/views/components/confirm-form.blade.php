@props([
    // Where the confirmed form posts to, and how.
    'action',
    'method' => 'DELETE',

    // Dialog copy.
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'confirm' => 'Delete',
    'cancel' => 'Cancel',
    'confirmVariant' => 'danger',

    // Trigger button. A null label renders an icon-only button.
    'label' => null,
    'icon' => 'trash',
    'iconTrailing' => null,
    'variant' => 'subtle',
    'size' => 'sm',
    'triggerClass' => 'text-red-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-500/10',
    'tooltip' => null,

    // Optional extra hidden fields for the form, as [name => value].
    'fields' => [],
])

@php($modalName = 'confirm-'.\Illuminate\Support\Str::random(12))

<flux:modal.trigger :name="$modalName" @class(['inline-flex', $attributes->get('class')])>
    <flux:button
        type="button"
        :icon="$icon"
        :icon-trailing="$iconTrailing"
        :size="$size"
        :variant="$variant"
        :class="$triggerClass"
        :tooltip="$tooltip"
    >{{ $label }}</flux:button>
</flux:modal.trigger>

<flux:modal :name="$modalName" class="max-w-md">
    <div class="space-y-5">
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            <flux:subheading>{{ $message }}</flux:subheading>
        </div>

        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button type="button" variant="filled">{{ $cancel }}</flux:button>
            </flux:modal.close>

            <form action="{{ $action }}" method="POST">
                @csrf
                @if (strtoupper($method) !== 'POST')
                    @method($method)
                @endif
                @foreach ($fields as $name => $value)
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                @endforeach
                <flux:button type="submit" :variant="$confirmVariant">{{ $confirm }}</flux:button>
            </form>
        </div>
    </div>
</flux:modal>
