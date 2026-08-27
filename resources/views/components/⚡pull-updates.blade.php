<?php

use App\Services\DeployResult;
use App\Services\GitDeploy;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public ?string $status = null;

    public ?string $detail = null;

    public bool $dependenciesChanged = false;

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

        Flux::toast(
            text: $result->message,
            variant: $result->toastVariant(),
        );

        if ($result->dependenciesChanged) {
            Flux::toast(
                text: 'Dependencies changed in this release - run composer install on the server.',
                variant: 'warning',
            );
        }
    }
};
?>

{{-- Always a root element: Livewire needs one even when this renders
     nothing, which it does for everyone who is not an Admin. --}}
<div>
    @if (auth()->user()?->hasRole('Admin') && config('deploy.enabled'))
    <flux:card class="mb-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="lg">Application updates</flux:heading>
                <flux:text class="text-zinc-500">
                    Pull the latest <span class="font-mono">{{ config('deploy.branch') }}</span> onto this server.
                    Nothing is applied if a step fails.
                </flux:text>

                @if ($detail)
                    <flux:text class="mt-2 {{ in_array($status, ['deployed', 'up_to_date']) ? 'text-green-600' : 'text-amber-600' }}">
                        {{ $detail }}
                    </flux:text>
                @endif
            </div>

            {{-- wire:click keeps this on the page - no navigation, no reload. --}}
            <flux:button wire:click="pullUpdates" wire:loading.attr="disabled" wire:target="pullUpdates" variant="primary"
                color="blue" icon="arrow-down-tray" class="shrink-0">
                <span wire:loading.remove wire:target="pullUpdates">Pull updates</span>
                <span wire:loading wire:target="pullUpdates">Pulling…</span>
            </flux:button>
        </div>
    </flux:card>
    @endif
</div>
