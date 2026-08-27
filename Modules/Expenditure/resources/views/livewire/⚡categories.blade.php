<?php

use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\Expenditure\Models\Expenditure;
use Modules\Expenditure\Models\ExpenditureCategory;

new #[Title('Expenditure Categories')] class extends Component
{
    /**
     * The row being edited, and the values being typed into it. Only one
     * row is ever open, so the form state is a single set of fields rather
     * than one per category.
     */
    public ?int $editingId = null;

    public string $editName = '';

    public string $editDescription = '';

    public bool $editIsActive = true;

    public string $newName = '';

    public string $newDescription = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->can('view expenditure'), 403);
    }

    public function edit(int $id): void
    {
        abort_unless(auth()->user()->can('edit expenditure'), 403);

        $category = $this->scoped()->findOrFail($id);

        $this->editingId = $category->id;
        $this->editName = $category->name;
        $this->editDescription = (string) $category->description;
        $this->editIsActive = (bool) $category->is_active;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'editName', 'editDescription', 'editIsActive']);
        $this->resetValidation();
    }

    public function update(): void
    {
        abort_unless(auth()->user()->can('edit expenditure'), 403);

        $category = $this->scoped()->findOrFail($this->editingId);

        $this->validate([
            'editName' => 'required|string|max:255',
            'editDescription' => 'nullable|string|max:255',
        ]);

        if ($this->nameTaken($category->institution_id, $this->editName, $category->id)) {
            $this->addError('editName', 'A category called "'.$this->editName.'" already exists.');

            return;
        }

        $category->update([
            'name' => $this->editName,
            'description' => $this->editDescription ?: null,
            'is_active' => $this->editIsActive,
        ]);

        $this->cancelEdit();

        Flux::toast(text: 'Category updated.', variant: 'success');
    }

    public function add(): void
    {
        abort_unless(auth()->user()->can('create expenditure'), 403);

        $this->validate([
            'newName' => 'required|string|max:255',
            'newDescription' => 'nullable|string|max:255',
        ]);

        $institutionId = $this->institutionId();

        if ($this->nameTaken($institutionId, $this->newName)) {
            $this->addError('newName', 'A category called "'.$this->newName.'" already exists.');

            return;
        }

        ExpenditureCategory::create([
            'institution_id' => $institutionId,
            'name' => $this->newName,
            'description' => $this->newDescription ?: null,
            'is_active' => true,
        ]);

        $this->reset(['newName', 'newDescription']);

        Flux::toast(text: 'Category added.', variant: 'success');
    }

    /**
     * Create whichever of the standard headings the school doesn't have
     * yet. Additive on purpose: run twice and nothing is duplicated, and a
     * school that has renamed or removed one keeps its own arrangement.
     */
    public function loadDefaults(): void
    {
        abort_unless(auth()->user()->can('create expenditure'), 403);

        $institutionId = $this->institutionId();

        $existing = ExpenditureCategory::where('institution_id', $institutionId)
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower($name));

        $added = 0;

        foreach (ExpenditureCategory::DEFAULTS as $name => $description) {
            if ($existing->contains(mb_strtolower($name))) {
                continue;
            }

            ExpenditureCategory::create([
                'institution_id' => $institutionId,
                'name' => $name,
                'description' => $description,
                'is_active' => true,
            ]);

            $added++;
        }

        Flux::toast(
            text: $added > 0 ? $added.' categories added.' : 'Every standard category is already set up.',
            variant: $added > 0 ? 'success' : 'warning',
        );
    }

    public function delete(int $id): void
    {
        abort_unless(auth()->user()->can('delete expenditure'), 403);

        $category = $this->scoped()->findOrFail($id);

        // Spending already filed here keeps its history - the category is
        // retired instead of deleted so past records don't quietly lose
        // their heading.
        if (Expenditure::where('expenditure_category_id', $category->id)->exists()) {
            $category->update(['is_active' => false]);

            Flux::toast(text: 'Category retired. Spending already filed under it keeps its heading.', variant: 'success');

            return;
        }

        $category->delete();

        Flux::toast(text: 'Category removed.', variant: 'success');
    }

    #[Computed]
    public function categories(): Collection
    {
        return $this->scoped()->withCount('expenditures')->orderBy('name')->get();
    }

    private function institutionId(): int
    {
        $institutionId = currentInstitution()?->id;

        abort_unless($institutionId, 422, 'No institution selected.');

        return $institutionId;
    }

    private function nameTaken(int $institutionId, string $name, ?int $ignoring = null): bool
    {
        return ExpenditureCategory::where('institution_id', $institutionId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring))
            ->exists();
    }

    private function scoped()
    {
        $query = ExpenditureCategory::query();

        if (! isAdmin()) {
            $query->where('institution_id', currentInstitution()?->id ?? 0);
        }

        return $query;
    }
}; ?>

<div class="p-4">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <flux:button href="{{ route('expenditure.index') }}" icon="arrow-left" variant="ghost" wire:navigate>
            Back to Expenditure
        </flux:button>

        @can('create expenditure')
            <flux:button type="button" icon="sparkles"
                :variant="$this->categories->isEmpty() ? 'primary' : 'ghost'" wire:click="loadDefaults">
                {{ $this->categories->isEmpty() ? 'Load the standard categories' : 'Add any missing standard categories' }}
            </flux:button>
        @endcan
    </div>

    <flux:card class="mb-6">
        <flux:heading size="lg" class="mb-4">Categories</flux:heading>

        @if ($this->categories->isEmpty())
            <flux:text class="text-zinc-500">
                No categories yet. Load the standard set above, or add your own below - spending can also be
                recorded without one and filed later.
            </flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>Name</flux:table.column>
                    <flux:table.column>Description</flux:table.column>
                    <flux:table.column>Records</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Actions</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->categories as $category)
                        <flux:table.row :key="$category->id">
                            @if ($editingId === $category->id)
                                <flux:table.cell>
                                    <flux:input size="sm" wire:model="editName" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:input size="sm" wire:model="editDescription" />
                                </flux:table.cell>
                                <flux:table.cell>{{ $category->expenditures_count }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:switch wire:model="editIsActive" label="Active" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex gap-2">
                                        <flux:button type="button" size="sm" icon="check" variant="primary"
                                            wire:click="update">save</flux:button>
                                        <flux:button type="button" size="sm" variant="ghost"
                                            wire:click="cancelEdit">cancel</flux:button>
                                    </div>
                                </flux:table.cell>
                            @else
                                <flux:table.cell>{{ $category->name }}</flux:table.cell>
                                <flux:table.cell>{{ $category->description ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $category->expenditures_count }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge :color="$category->is_active ? 'emerald' : 'zinc'">
                                        {{ $category->is_active ? 'Active' : 'Retired' }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex gap-2">
                                        @can('edit expenditure')
                                            <flux:button type="button" size="sm" icon="pencil" variant="ghost"
                                                wire:click="edit({{ $category->id }})">edit</flux:button>
                                        @endcan
                                        @can('delete expenditure')
                                            <flux:button type="button" size="sm" icon="trash" variant="ghost"
                                                wire:click="delete({{ $category->id }})"
                                                wire:confirm="Remove this category?">delete</flux:button>
                                        @endcan
                                    </div>
                                </flux:table.cell>
                            @endif
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <flux:error name="editName" class="mt-2 text-sm text-red-500" />
        @endif
    </flux:card>

    @can('create expenditure')
        <flux:card>
            <flux:heading size="lg" class="mb-4">Add a Category</flux:heading>

            <form wire:submit="add" class="grid grid-cols-1 items-end gap-3 md:grid-cols-3">
                <flux:input label="Name" wire:model="newName" placeholder="e.g. Boarding Supplies" />
                <flux:input label="Description" wire:model="newDescription"
                    placeholder="What belongs under this heading" />
                <flux:button type="submit" variant="primary" icon="plus">Add Category</flux:button>
            </form>
        </flux:card>
    @endcan
</div>
