<?php

use App\Services\GitDeploy;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Component;

new class extends Component
{
    /**
     * Authorisation is checked here rather than only in the view: a
     * Livewire action is a request endpoint of its own, and a hidden
     * button is not a closed door.
     */
    public function switchVersion(string $tag, GitDeploy $deploy): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('Admin'), 403);

        $result = $deploy->switchToVersion($tag);

        Flux::toast(text: $result->message, variant: $result->toastVariant());

        if ($result->dependenciesChanged) {
            Flux::toast(
                text: 'Dependencies changed in this release - run composer install on the server.',
                variant: 'warning',
            );
        }
    }

    /**
     * @return list<array{tag: string, commit: string, subject: string, date: string, current: bool, relation: string}>
     */
    public function getVersionsProperty(): array
    {
        return app(GitDeploy::class)->listVersions();
    }
};
?>

{{-- Always a root element: Livewire needs one even when this renders
     nothing, which it does for everyone who is not an Admin. --}}
<div>
    @if (auth()->user()?->hasRole('Admin') && config('deploy.enabled'))
        <flux:card>
            <flux:heading size="lg">Versions</flux:heading>
            <flux:text class="text-zinc-500">
                Every tagged release on GitHub. Switch to any version, or roll back if a new one causes trouble -
                this only ever moves the code. The database schema is never changed automatically, so rolling
                back past a migration leaves it exactly as it is.
            </flux:text>

            @if ($this->versions === [])
                <flux:text class="mt-4 text-sm text-zinc-500">No tags found on the remote yet.</flux:text>
            @else
                <div class="mt-4 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($this->versions as $version)
                        @php($modalName = 'version-switch-'.Str::slug($version['tag']))
                        <div class="flex items-center justify-between gap-4 py-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono font-bold text-sm text-zinc-900 dark:text-white">{{ $version['tag'] }}</span>
                                    @if ($version['current'])
                                        <flux:badge size="sm" color="green">Current</flux:badge>
                                    @elseif ($version['relation'] === 'rollback')
                                        <flux:badge size="sm" color="amber">Older</flux:badge>
                                    @elseif ($version['relation'] === 'upgrade')
                                        <flux:badge size="sm" color="blue">Newer</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="zinc">Diverged</flux:badge>
                                    @endif
                                </div>
                                <flux:text size="sm" class="text-zinc-500 truncate block">
                                    {{ $version['subject'] }} &bull; {{ Carbon::parse($version['date'])->diffForHumans() }}
                                </flux:text>
                            </div>

                            @unless ($version['current'])
                                <flux:modal.trigger :name="$modalName">
                                    <flux:button type="button" size="sm" variant="subtle" class="shrink-0 font-semibold">
                                        {{ $version['relation'] === 'rollback' ? 'Roll back' : 'Switch' }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:modal :name="$modalName" class="max-w-md">
                                    <div class="space-y-5">
                                        <div>
                                            <flux:heading size="lg">
                                                {{ $version['relation'] === 'rollback' ? 'Roll back to' : 'Switch to' }} {{ $version['tag'] }}?
                                            </flux:heading>
                                            <flux:subheading>
                                                This checks out {{ $version['tag'] }} on the server
                                                @if ($version['relation'] === 'upgrade')
                                                    and runs any migrations it adds.
                                                @else
                                                    . The database schema is left exactly as it is - if this version
                                                    predates a migration that's already run, verify compatibility
                                                    before relying on it.
                                                @endif
                                            </flux:subheading>
                                        </div>
                                        <div class="flex justify-end gap-2">
                                            <flux:modal.close>
                                                <flux:button type="button" variant="filled">Cancel</flux:button>
                                            </flux:modal.close>
                                            <flux:button type="button" variant="primary"
                                                wire:click="switchVersion('{{ $version['tag'] }}')"
                                                x-on:click="$flux.modal('{{ $modalName }}').close()"
                                                wire:loading.attr="disabled" wire:target="switchVersion('{{ $version['tag'] }}')">
                                                <span wire:loading.remove wire:target="switchVersion('{{ $version['tag'] }}')">Confirm</span>
                                                <span wire:loading wire:target="switchVersion('{{ $version['tag'] }}')">Switching…</span>
                                            </flux:button>
                                        </div>
                                    </div>
                                </flux:modal>
                            @endunless
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    @endif
</div>
