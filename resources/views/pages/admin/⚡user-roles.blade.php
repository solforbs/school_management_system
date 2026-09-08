<?php

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\PermissionRegistrar;

/**
 * Promoting a school Director to a platform Admin, and taking it back.
 *
 * Admin-only. The search matches a Director by name, e-mail or numeric id;
 * promoting swaps their role wholesale - syncRoles(['Admin']) - so their
 * access is exactly the Admin role's permission set and nothing left over
 * from being a Director. Demoting is the same move in reverse.
 */
new #[Title('Admin Access')] class extends Component
{
    public string $search = '';

    /** The Director the promote dialog is confirming. */
    public ?int $confirmingPromoteId = null;

    /** The Admin the demote dialog is confirming. */
    public ?int $confirmingDemoteId = null;

    public function mount(): void
    {
        abort_unless(isAdmin(), 403);
        abort_unless(auth()->user()->can('update user'), 403);
    }

    /**
     * Directors who match the search, newest first. An empty search still
     * lists them (there are rarely many), so the screen is useful on load.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function directors(): Collection
    {
        $term = trim($this->search);

        return User::role('Director')
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%");

                    if (ctype_digit($term)) {
                        $inner->orWhere('id', (int) $term);
                    }
                });
            })
            ->with(['activeInstitution:id,name', 'roles'])
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'name', 'email', 'status', 'active_institution_id']);
    }

    /**
     * Everyone who currently holds Admin - the demote list.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function admins(): Collection
    {
        return User::role('Admin')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function promoteTarget(): ?User
    {
        return $this->confirmingPromoteId ? User::find($this->confirmingPromoteId) : null;
    }

    #[Computed]
    public function demoteTarget(): ?User
    {
        return $this->confirmingDemoteId ? User::find($this->confirmingDemoteId) : null;
    }

    public function promote(?int $userId = null): void
    {
        abort_unless(isAdmin(), 403);
        abort_unless(auth()->user()->can('update user'), 403);

        $userId ??= $this->confirmingPromoteId;
        $user = User::findOrFail($userId);

        abort_unless($user->hasRole('Director'), 422, 'Only a Director can be promoted here.');

        // Clear any stray direct permissions first, so what they end up
        // with is exactly the Admin role and nothing else.
        $user->syncPermissions([]);
        $user->syncRoles(['Admin']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Log::info('Director promoted to Admin', [
            'promoted_user_id' => $user->id,
            'by_user_id' => auth()->id(),
        ]);

        $this->confirmingPromoteId = null;
        Flux::modal('confirm-promote')->close();
        Flux::toast(text: "{$user->name} is now a platform Admin.", variant: 'success');
    }

    public function demote(?int $userId = null): void
    {
        abort_unless(isAdmin(), 403);
        abort_unless(auth()->user()->can('update user'), 403);

        $userId ??= $this->confirmingDemoteId;

        abort_if($userId === auth()->id(), 422, 'You cannot remove your own Admin access.');

        $user = User::findOrFail($userId);

        abort_unless($user->hasRole('Admin'), 422, 'That account is not an Admin.');

        // The platform owner's access is not another admin's to remove.
        abort_if($user->isProtectedAdmin(), 422, 'This account owns the platform and cannot have its Admin access removed here.');

        // Never leave the platform with no Admin at all.
        abort_if(User::role('Admin')->count() <= 1, 422, 'This is the only Admin account - promote someone else first.');

        $user->syncPermissions([]);
        $user->syncRoles(['Director']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Log::info('Admin demoted to Director', [
            'demoted_user_id' => $user->id,
            'by_user_id' => auth()->id(),
        ]);

        $this->confirmingDemoteId = null;
        Flux::modal('confirm-demote')->close();
        Flux::toast(text: "{$user->name} is back to Director access.", variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6 w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">
    {{-- Header --}}
    <div>
        <div class="flex items-center gap-2 mb-1">
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-400 border border-indigo-200/60">
                Platform Access
            </span>
        </div>
        <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Admin Access</flux:heading>
        <flux:text class="text-zinc-500 text-sm mt-0.5">
            Promote a school Director to a platform Admin, or return an Admin to Director access. A promotion replaces
            the Director role outright, so the account picks up exactly the Admin permission set.
        </flux:text>
    </div>

    {{-- Promote --}}
    <flux:card class="p-5">
        <flux:heading size="lg" class="mb-1">Promote a Director</flux:heading>
        <flux:text class="text-zinc-500 text-sm mb-4">Search by name, e-mail or account ID.</flux:text>

        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass"
            placeholder="e.g. jane@school.ac.ke, Jane Doe, or 42" class="max-w-md mb-4" />

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-zinc-500 border-b border-zinc-200/80 dark:border-zinc-700 text-[11px] font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-2.5 pr-4">Director</th>
                        <th class="py-2.5 pr-4">E-mail</th>
                        <th class="py-2.5 pr-4">School</th>
                        <th class="py-2.5 pr-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->directors as $director)
                        <tr wire:key="director-{{ $director->id }}">
                            <td class="py-3 pr-4">
                                <span class="font-semibold text-zinc-900 dark:text-white">{{ $director->name }}</span>
                                <span class="block text-[11px] text-zinc-400 font-mono">#{{ $director->id }}</span>
                            </td>
                            <td class="py-3 pr-4 text-zinc-600 dark:text-zinc-300">{{ $director->email }}</td>
                            <td class="py-3 pr-4 text-zinc-600 dark:text-zinc-300">{{ $director->activeInstitution?->name ?? '—' }}</td>
                            <td class="py-3 pr-4">
                                <div class="flex items-center justify-end gap-1.5">
                                    <x-impersonate-button :user="$director" />
                                    <flux:modal.trigger name="confirm-promote">
                                        <flux:button size="sm" variant="primary" icon="shield-check"
                                            wire:click="$set('confirmingPromoteId', {{ $director->id }})">
                                            Make Admin
                                        </flux:button>
                                    </flux:modal.trigger>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-6 text-center text-zinc-400">
                                {{ trim($this->search) === '' ? 'No Directors yet.' : 'No Director matches that search.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Current admins / demote --}}
    <flux:card class="p-5">
        <flux:heading size="lg" class="mb-1">Current Admins</flux:heading>
        <flux:text class="text-zinc-500 text-sm mb-4">Everyone with platform Admin access right now.</flux:text>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-zinc-500 border-b border-zinc-200/80 dark:border-zinc-700 text-[11px] font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-2.5 pr-4">Admin</th>
                        <th class="py-2.5 pr-4">E-mail</th>
                        <th class="py-2.5 pr-4 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->admins as $admin)
                        <tr wire:key="admin-{{ $admin->id }}">
                            <td class="py-3 pr-4">
                                <span class="font-semibold text-zinc-900 dark:text-white">{{ $admin->name }}</span>
                                <span class="block text-[11px] text-zinc-400 font-mono">#{{ $admin->id }}</span>
                            </td>
                            <td class="py-3 pr-4 text-zinc-600 dark:text-zinc-300">{{ $admin->email }}</td>
                            <td class="py-3 pr-4 text-right">
                                @if ($admin->isProtectedAdmin())
                                    <flux:badge size="sm" color="amber" icon="lock-closed">Platform owner</flux:badge>
                                @elseif ($admin->id === auth()->id())
                                    <flux:badge size="sm" color="zinc">You</flux:badge>
                                @else
                                    <flux:modal.trigger name="confirm-demote">
                                        <flux:button size="sm" variant="subtle" icon="shield-exclamation"
                                            wire:click="$set('confirmingDemoteId', {{ $admin->id }})">
                                            Revoke admin
                                        </flux:button>
                                    </flux:modal.trigger>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </flux:card>

    {{-- Confirm: promote --}}
    <flux:modal name="confirm-promote" class="max-w-md" wire:close="$set('confirmingPromoteId', null)">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Promote to platform Admin?</flux:heading>
                <flux:subheading>
                    @if ($this->promoteTarget)
                        <strong>{{ $this->promoteTarget->name }}</strong> ({{ $this->promoteTarget->email }}) will
                        lose their Director role and gain full platform Admin access. They will no longer be able to
                        run a school from this account.
                    @else
                        Select a Director to promote.
                    @endif
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="filled">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="primary" icon="shield-check" wire:click="promote"
                    wire:loading.attr="disabled">
                    Make Admin
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Confirm: demote --}}
    <flux:modal name="confirm-demote" class="max-w-md" wire:close="$set('confirmingDemoteId', null)">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Revoke Admin access?</flux:heading>
                <flux:subheading>
                    @if ($this->demoteTarget)
                        <strong>{{ $this->demoteTarget->name }}</strong> ({{ $this->demoteTarget->email }}) will
                        return to Director access and lose every platform Admin permission.
                    @else
                        Select an Admin to revoke.
                    @endif
                </flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button type="button" variant="filled">Cancel</flux:button>
                </flux:modal.close>
                <flux:button type="button" variant="danger" icon="shield-exclamation" wire:click="demote"
                    wire:loading.attr="disabled">
                    Revoke admin
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
