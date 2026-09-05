<?php

use App\Services\SitemapGenerator;
use Flux\Flux;
use Livewire\Component;

new class extends Component
{
    public ?string $lastGeneratedAt = null;

    public function mount(): void
    {
        $this->lastGeneratedAt = SitemapGenerator::lastGeneratedAt();
    }

    /**
     * Authorisation is checked here rather than only in the view: a
     * Livewire action is a request endpoint of its own, and a hidden
     * button is not a closed door.
     */
    public function generate(SitemapGenerator $generator): void
    {
        abort_unless(auth()->check() && auth()->user()->hasRole('Admin'), 403);

        $result = $generator->generate();

        $this->lastGeneratedAt = SitemapGenerator::lastGeneratedAt();

        Flux::toast(text: $result->message, variant: $result->toastVariant());
    }
};
?>

{{-- Always a root element: Livewire needs one even when this renders
     nothing, which it does for everyone who is not an Admin. --}}
<div>
    @if (auth()->user()?->hasRole('Admin'))
        <flux:card>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <flux:heading size="lg">{{ __('Sitemap') }}</flux:heading>
                    <flux:text class="text-zinc-500">
                        {{ __('Lists the public marketing pages (home, about, services, plans, contact) for search engines - never a dashboard, billing, or module page. Regenerated automatically every hour; submit') }}
                        <span class="font-mono">{{ url('/sitemap.xml') }}</span>
                        {{ __('to Google Search Console once and it will keep re-reading it.') }}
                    </flux:text>

                    <flux:text class="mt-2 text-sm text-zinc-500">
                        @if ($lastGeneratedAt)
                            {{ __('Last generated') }} {{ \Illuminate\Support\Carbon::parse($lastGeneratedAt)->diffForHumans() }}.
                        @else
                            {{ __('Not generated yet.') }}
                        @endif
                    </flux:text>
                </div>

                <div class="flex shrink-0 flex-col items-end gap-2">
                    <flux:button wire:click="generate" wire:loading.attr="disabled" wire:target="generate"
                        variant="primary" icon="arrow-path">
                        <span wire:loading.remove wire:target="generate">{{ __('Generate now') }}</span>
                        <span wire:loading wire:target="generate">{{ __('Generating…') }}</span>
                    </flux:button>

                    <flux:link href="/sitemap.xml" target="_blank">{{ __('View sitemap.xml') }}</flux:link>
                </div>
            </div>
        </flux:card>
    @endif
</div>
