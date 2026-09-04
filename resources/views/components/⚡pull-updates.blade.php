<?php

use App\Services\GitDeploy;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public ?string $status = null;

    public ?string $detail = null;

    public bool $dependenciesChanged = false;

    public ?string $migrations = null;

    /** @var list<string> */
    public array $pendingSeeders = [];

    /**
     * Pull the deploy branch onto this server.
     *
     * Authorisation is checked here rather than only in the view: a Livewire
     * action is a request endpoint of its own, and a hidden button is not a
     * closed door.
     */
    public function pullUpdates(GitDeploy $deploy): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('Admin'), 403);

        $result = $deploy->pull();

        $this->status = $result->status;
        $this->detail = $result->message;
        $this->dependenciesChanged = $result->dependenciesChanged;
        $this->migrations = $result->migrations;
        $this->pendingSeeders = $result->pendingSeeders;

        Flux::toast(text: $result->message, variant: $result->toastVariant());

        if ($result->dependenciesChanged) {
            Flux::toast(
                text: 'Dependencies changed in this release - run composer install on the server.',
                variant: 'warning',
            );
        }
    }

    /**
     * Run the seeders this release changed, once an Admin has decided it is
     * safe. The list is re-checked in the service against real files.
     */
    public function runSeeders(GitDeploy $deploy): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('Admin'), 403);

        $result = $deploy->runSeeders($this->pendingSeeders);

        Flux::toast(text: $result->message, variant: $result->toastVariant());

        if ($result->ok()) {
            $this->pendingSeeders = [];
        }
    }
};
?>

{{-- Always a root element: Livewire needs one even when this renders
     nothing, which it does for everyone who is not an Admin. --}}
<div>
    @if (auth()->user()?->hasRole('Admin') && config('deploy.enabled'))
        <flux:card class="mb-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">Application updates</flux:heading>
                    <flux:text class="text-zinc-500">
                        Pull the latest <span class="font-mono">{{ config('deploy.branch') }}</span> onto this server.
                        Migrations a release adds are run automatically; nothing is applied if a step fails.
                    </flux:text>

                    @if ($detail)
                        <flux:text
                            class="mt-2 {{ in_array($status, ['deployed', 'up_to_date']) ? 'text-green-600' : ($status === 'needs_attention' ? 'text-red-600' : 'text-amber-600') }}">
                            {{ $detail }}
                        </flux:text>
                    @endif

                    @if ($migrations)
                        <flux:text class="mt-1 text-sm text-zinc-500">
                            <span class="font-semibold">Migrations:</span> {{ $migrations }}
                        </flux:text>
                    @endif
                </div>

                {{-- wire:click keeps this on the page - no navigation, no reload. --}}
                <flux:button wire:click="pullUpdates" wire:loading.attr="disabled" wire:target="pullUpdates"
                    variant="primary" color="blue" icon="arrow-down-tray" class="shrink-0">
                    <span wire:loading.remove wire:target="pullUpdates">Pull updates</span>
                    <span wire:loading wire:target="pullUpdates">Pulling…</span>
                </flux:button>
            </div>

            @if ($pendingSeeders)
                <flux:separator class="my-4" />
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <flux:heading size="sm">Seeders changed in this release</flux:heading>
                        <flux:text class="text-sm text-zinc-500">
                            These are not run automatically. Run them only when you know the release means them to.
                        </flux:text>
                        <ul class="mt-2 space-y-1">
                            @foreach ($pendingSeeders as $seeder)
                                <li class="font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $seeder }}</li>
                            @endforeach
                        </ul>
                    </div>
                    <flux:modal.trigger name="deploy-run-seeders">
                        <flux:button type="button" variant="filled" icon="circle-stack" class="shrink-0">
                            Run seeders
                        </flux:button>
                    </flux:modal.trigger>
                </div>
            @endif
        </flux:card>

        <flux:modal name="deploy-run-seeders" class="max-w-md">
            <div class="space-y-5">
                <div>
                    <flux:heading size="lg">Run these seeders?</flux:heading>
                    <flux:subheading>
                        Seeders can truncate and re-insert data. Only run them if this release is meant to reseed:
                    </flux:subheading>
                    <ul class="mt-2 space-y-1">
                        @foreach ($pendingSeeders as $seeder)
                            <li class="font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $seeder }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button type="button" variant="filled">Cancel</flux:button>
                    </flux:modal.close>
                    <flux:button type="button" variant="danger" icon="circle-stack"
                        wire:click="runSeeders" x-on:click="$flux.modal('deploy-run-seeders').close()"
                        wire:loading.attr="disabled" wire:target="runSeeders">
                        <span wire:loading.remove wire:target="runSeeders">Run seeders</span>
                        <span wire:loading wire:target="runSeeders">Running…</span>
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</div>
