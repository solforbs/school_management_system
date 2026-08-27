<x-layouts::app :title="__('Plans')">
    <div class="p-4">
        <div class="mb-2 flex flex-row justify-between">
            <flux:heading size="lg">Plans</flux:heading>
            <flux:button href="{{ route('admin.plans.create') }}" wire:navigate>Add Plan</flux:button>
        </div>

        <flux:card>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Price</flux:table.column>
                    <flux:table.column>Billing Cycle</flux:table.column>
                    <flux:table.column>Modules</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Actions</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($plans as $plan)
                        <flux:table.row>
                            <flux:table.cell>{{ $plan->name }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($plan->price, 2) }}</flux:table.cell>
                            <flux:table.cell>{{ ucfirst($plan->billing_cycle) }}</flux:table.cell>
                            <flux:table.cell>{{ count($plan->modules ?? []) }} modules</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$plan->is_active ? 'emerald' : 'zinc'">
                                    {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex gap-2">
                                    <flux:button size="sm" href="{{ route('admin.plans.edit', $plan) }}" wire:navigate>Edit</flux:button>
                                    <form action="{{ route('admin.plans.destroy', $plan) }}" method="POST"
                                        onsubmit="return confirm('Delete this plan? Institutions on it will lose access to its modules.');">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button size="sm" variant="danger" type="submit">Delete</flux:button>
                                    </form>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">No plans yet.</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </flux:card>
    </div>
</x-layouts::app>
