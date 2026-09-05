@props([
    'baseRoute',
    'module',
    'params' => [],
    'label' => 'Export',
])

@php
    $params = collect($params)->filter(fn ($value) => filled($value))->all();
@endphp

@can('export '.$module)
    @if (Route::has($baseRoute))
        <flux:dropdown position="bottom" align="end">
            <flux:button icon="arrow-down-tray" icon-trailing="chevron-down" variant="ghost" class="hover:bg-zinc-100 dark:hover:bg-zinc-800">
                {{ $label }}
            </flux:button>

            <flux:menu>
                <flux:menu.item :href="route($baseRoute, [...$params, 'format' => 'xlsx'])" icon="table-cells">
                    Export as Excel
                </flux:menu.item>
                <flux:menu.item :href="route($baseRoute, [...$params, 'format' => 'pdf'])" icon="document-text">
                    Export as PDF
                </flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    @endif
@endcan
