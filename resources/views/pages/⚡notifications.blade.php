<?php

use App\Jobs\SendParentNotificationJob;
use App\Models\NotificationTemplate;
use App\Models\ParentNotification;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Where a school's Director builds the notices parents rely on - term
 * dates, mid-term start, closures - and sends them to every parent by SMS,
 * e-mail, or both, now or on a set date.
 *
 * Everything is scoped to the Director's current institution.
 */
new #[Title('Parent Notifications')] class extends Component
{
    public string $tab = 'send';

    // --- Compose / send ------------------------------------------------
    public ?int $sourceTemplateId = null;

    public string $subject = '';

    public string $body = '';

    /** @var array<string, string> value for each custom {{placeholder}} */
    public array $placeholderValues = [];

    /** @var array<int, string> */
    public array $channels = ['email'];

    public string $mode = 'now';

    public string $scheduledFor = '';

    // --- Template editor ---------------------------------------------------
    public ?int $editingTemplateId = null;

    public string $tplName = '';

    public string $tplSubject = '';

    public string $tplBody = '';

    public ?int $confirmingDeleteTemplateId = null;

    // --- History ---------------------------------------------------------
    public ?int $confirmingCancelId = null;

    public ?int $viewingId = null;

    public function mount(): void
    {
        abort_unless(currentInstitution() && auth()->user()->can('view notification'), 403);
    }

    private function institutionId(): int
    {
        return (int) currentInstitutionId();
    }

    // =====================================================================
    // Compose / send
    // =====================================================================

    #[Computed]
    public function templates(): Collection
    {
        return NotificationTemplate::where('institution_id', $this->institutionId())->latest()->get();
    }

    #[Computed]
    public function parentCount(): int
    {
        return (int) currentInstitution()->parents()->distinct()->count('users.id');
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function customPlaceholders(): array
    {
        return array_values(array_diff(
            ParentNotification::extractPlaceholders($this->subject.' '.$this->body),
            ParentNotification::AUTO_PLACEHOLDERS,
        ));
    }

    /**
     * @return array{subject: string, body: string}
     */
    #[Computed]
    public function preview(): array
    {
        $context = ['institution' => currentInstitution()->name, 'parent' => 'the parent'];

        return [
            'subject' => ParentNotification::render($this->subject, $context, $this->placeholderValues),
            'body' => ParentNotification::render($this->body, $context, $this->placeholderValues),
        ];
    }

    public function updatedSourceTemplateId(mixed $value): void
    {
        $template = $value
            ? NotificationTemplate::where('institution_id', $this->institutionId())->find($value)
            : null;

        $this->subject = $template->subject ?? '';
        $this->body = $template->body ?? '';
        $this->placeholderValues = [];
    }

    public function send(): void
    {
        abort_unless(auth()->user()->can('send notification'), 403);

        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(ParentNotification::CHANNELS)],
            'mode' => ['required', Rule::in(['now', 'schedule'])],
            'scheduledFor' => [Rule::requiredIf($this->mode === 'schedule'), 'nullable', 'date', 'after:now'],
        ], [], ['scheduledFor' => 'send date']);

        foreach ($this->customPlaceholders as $name) {
            if (trim($this->placeholderValues[$name] ?? '') === '') {
                $this->addError('placeholderValues.'.$name, 'Fill in a value for {{'.$name.'}}.');

                return;
            }
        }

        if (in_array('sms', $this->channels, true) && ! featureEnabled('sms')) {
            $this->addError('channels', 'SMS is switched off in Site Settings - a parent with no e-mail on file will be missed.');
        }

        $scheduled = $this->mode === 'schedule' ? Carbon::parse($this->scheduledFor) : null;

        $notification = ParentNotification::create([
            'institution_id' => $this->institutionId(),
            'template_id' => $this->sourceTemplateId,
            'name' => Str::limit($this->subject, 80, ''),
            'subject' => $this->subject,
            'body' => $this->body,
            'channels' => array_values($this->channels),
            'placeholder_values' => collect($this->customPlaceholders)
                ->mapWithKeys(fn ($name) => [$name => trim($this->placeholderValues[$name] ?? '')])
                ->all(),
            'scheduled_for' => $scheduled,
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        if (! $scheduled) {
            SendParentNotificationJob::dispatch($notification->id);
        }

        $this->reset(['sourceTemplateId', 'subject', 'body', 'placeholderValues', 'scheduledFor']);
        $this->channels = ['email'];
        $this->mode = 'now';
        $this->tab = 'history';
        unset($this->history);

        Flux::toast(
            text: $scheduled
                ? 'Notification scheduled for '.$scheduled->format('j M Y, H:i').'.'
                : 'Notification queued for delivery to '.$this->parentCount.' parent(s).',
            variant: 'success',
        );
    }

    // =====================================================================
    // Template editor
    // =====================================================================

    public function newTemplate(): void
    {
        $this->reset(['editingTemplateId', 'tplName', 'tplSubject', 'tplBody']);
        $this->resetErrorBag(['tplName', 'tplSubject', 'tplBody']);
        Flux::modal('template-form')->show();
    }

    public function editTemplate(int $id): void
    {
        $template = NotificationTemplate::where('institution_id', $this->institutionId())->findOrFail($id);

        $this->editingTemplateId = $template->id;
        $this->tplName = $template->name;
        $this->tplSubject = $template->subject;
        $this->tplBody = $template->body;
        $this->resetErrorBag(['tplName', 'tplSubject', 'tplBody']);
        Flux::modal('template-form')->show();
    }

    public function saveTemplate(): void
    {
        abort_unless(auth()->user()->can('create notification'), 403);

        $data = $this->validate([
            'tplName' => ['required', 'string', 'max:120'],
            'tplSubject' => ['required', 'string', 'max:255'],
            'tplBody' => ['required', 'string', 'max:2000'],
        ]);

        $existing = $this->editingTemplateId
            ? NotificationTemplate::where('institution_id', $this->institutionId())->find($this->editingTemplateId)
            : null;

        NotificationTemplate::updateOrCreate(
            ['id' => $existing?->id],
            [
                'institution_id' => $this->institutionId(),
                'name' => $data['tplName'],
                'subject' => $data['tplSubject'],
                'body' => $data['tplBody'],
                'created_by' => $existing?->created_by ?? auth()->id(),
            ],
        );

        unset($this->templates);
        $this->reset(['editingTemplateId', 'tplName', 'tplSubject', 'tplBody']);
        Flux::modal('template-form')->close();
        Flux::toast(text: 'Template saved.', variant: 'success');
    }

    public function deleteTemplate(?int $id = null): void
    {
        abort_unless(auth()->user()->can('delete notification'), 403);

        $id ??= $this->confirmingDeleteTemplateId;
        abort_unless($id, 404);

        NotificationTemplate::where('institution_id', $this->institutionId())->whereKey($id)->delete();

        $this->confirmingDeleteTemplateId = null;
        Flux::modal('confirm-delete-template')->close();
        unset($this->templates);
        Flux::toast(text: 'Template deleted.', variant: 'success');
    }

    // =====================================================================
    // History
    // =====================================================================

    #[Computed]
    public function history(): Collection
    {
        return ParentNotification::where('institution_id', $this->institutionId())
            ->with('template')
            ->latest()
            ->limit(40)
            ->get();
    }

    #[Computed]
    public function viewing(): ?ParentNotification
    {
        return $this->viewingId
            ? ParentNotification::where('institution_id', $this->institutionId())
                ->with(['recipients.user'])
                ->find($this->viewingId)
            : null;
    }

    public function cancel(?int $id = null): void
    {
        abort_unless(auth()->user()->can('send notification'), 403);

        $id ??= $this->confirmingCancelId;
        $notification = ParentNotification::where('institution_id', $this->institutionId())->findOrFail($id);

        abort_unless($notification->isCancellable(), 422, 'That notification has already gone out.');

        $notification->update(['status' => 'canceled']);

        $this->confirmingCancelId = null;
        Flux::modal('confirm-cancel-notification')->close();
        unset($this->history);
        Flux::toast(text: 'Scheduled notification canceled.', variant: 'success');
    }
}; ?>

<div class="flex flex-col gap-6 w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold tracking-wide uppercase bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-400 border border-indigo-200/60">
                Parent Communication
            </span>
        </div>
        <flux:heading size="xl" class="font-black tracking-tight text-zinc-900 dark:text-white">Parent Notifications</flux:heading>
        <flux:text class="text-zinc-500 text-sm mt-0.5">
            Build the notices parents rely on — term dates, mid-term start, closures — and send them to every parent
            by SMS, e-mail, or both.
        </flux:text>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 border-b border-zinc-200 dark:border-zinc-800">
        @foreach (['send' => 'Compose & send', 'templates' => 'Templates', 'history' => 'History'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                @class([
                    'px-4 py-2 text-sm font-semibold -mb-px border-b-2 transition',
                    'border-indigo-500 text-indigo-600 dark:text-indigo-400' => $tab === $key,
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== $key,
                ])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ============================ SEND ============================ --}}
    @if ($tab === 'send')
        <div class="grid gap-6 lg:grid-cols-5">
            <div class="lg:col-span-3 space-y-4">
                <flux:select wire:model.live="sourceTemplateId" label="Start from a template" placeholder="Blank message">
                    @foreach ($this->templates as $template)
                        <flux:select.option :value="$template->id">{{ $template->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="subject" label="Subject / title"
                    placeholder="e.g. Term 1 opens on @{{opening_date}}" />
                @error('subject') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                <flux:textarea wire:model="body" rows="6" label="Message"
                    placeholder="Dear @{{parent}}, @{{institution}} reopens on @{{opening_date}}..." />
                <flux:text size="sm" class="text-zinc-400">
                    Placeholders: <code>@{{institution}}</code> is your school and <code>@{{parent}}</code>
                    is filled in for each parent. <code>@{{date}}</code> is today. Any other
                    <code>@{{name}}</code> you use becomes a field below.
                </flux:text>
                @error('body') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                @if (count($this->customPlaceholders))
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-800 p-3 space-y-3">
                        <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wide">Fill in</p>
                        @foreach ($this->customPlaceholders as $name)
                            <flux:input wire:model="placeholderValues.{{ $name }}" :label="$name" size="sm"
                                placeholder="Value for this placeholder" />
                            @error('placeholderValues.' . $name)
                                <p class="text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        @endforeach
                    </div>
                @endif

                <div>
                    <flux:label>Channels</flux:label>
                    <div class="flex gap-4 mt-1">
                        <flux:checkbox wire:model="channels" value="email" label="E-mail" />
                        <flux:checkbox wire:model="channels" value="sms" label="SMS" />
                    </div>
                    @error('channels') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    <p class="text-[11px] text-zinc-400 mt-1">A parent chosen for SMS with no number on file is e-mailed instead.</p>
                    @unless (featureEnabled('sms'))
                        <p class="text-[11px] text-amber-600 mt-1">SMS is currently off in Site Settings.</p>
                    @endunless
                </div>

                <div>
                    <flux:label>When</flux:label>
                    <div class="flex flex-wrap items-center gap-4 mt-1">
                        <flux:radio.group wire:model.live="mode" variant="segmented">
                            <flux:radio value="now" label="Send now" />
                            <flux:radio value="schedule" label="Schedule" />
                        </flux:radio.group>
                        @if ($mode === 'schedule')
                            <flux:input type="datetime-local" wire:model="scheduledFor" size="sm" />
                        @endif
                    </div>
                    @error('scheduledFor') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <flux:button variant="primary" icon="paper-airplane" wire:click="send" wire:loading.attr="disabled">
                        {{ $mode === 'schedule' ? 'Schedule notification' : 'Send to '.$this->parentCount.' parent(s)' }}
                    </flux:button>
                </div>
            </div>

            {{-- Live preview --}}
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-900/40 p-4 sticky top-4">
                    <p class="text-xs font-semibold text-zinc-500 uppercase tracking-wide mb-2">Preview</p>
                    <p class="text-sm font-bold text-zinc-900 dark:text-white">{{ $this->preview['subject'] ?: 'Subject' }}</p>
                    <p class="text-sm text-zinc-600 dark:text-zinc-300 mt-2 whitespace-pre-line">{{ $this->preview['body'] ?: 'Your message will appear here.' }}</p>
                    <p class="text-[11px] text-zinc-400 mt-3">Each parent sees their own name in place of the placeholders.</p>
                </div>
            </div>
        </div>
    @endif

    {{-- ========================== TEMPLATES ========================== --}}
    @if ($tab === 'templates')
        <div class="space-y-4">
            <div class="flex justify-end">
                <flux:button variant="primary" icon="plus" wire:click="newTemplate">New template</flux:button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                <table class="w-full text-left text-sm">
                    <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 text-[11px] font-bold uppercase tracking-wider">
                        <tr>
                            <th class="py-2.5 px-4">Name</th>
                            <th class="py-2.5 px-4">Subject</th>
                            <th class="py-2.5 px-4">Placeholders</th>
                            <th class="py-2.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($this->templates as $template)
                            <tr wire:key="tpl-{{ $template->id }}">
                                <td class="py-3 px-4 font-semibold text-zinc-900 dark:text-white">{{ $template->name }}</td>
                                <td class="py-3 px-4 text-zinc-600 dark:text-zinc-300">{{ $template->subject }}</td>
                                <td class="py-3 px-4 text-xs text-zinc-400 font-mono">
                                    {{ implode(', ', $template->placeholders()) ?: '—' }}
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <flux:button size="sm" variant="subtle" icon="pencil-square"
                                        wire:click="editTemplate({{ $template->id }})" />
                                    <flux:modal.trigger name="confirm-delete-template">
                                        <flux:button size="sm" variant="subtle" icon="trash" class="text-rose-600"
                                            wire:click="$set('confirmingDeleteTemplateId', {{ $template->id }})" />
                                    </flux:modal.trigger>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-zinc-400">No templates yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- =========================== HISTORY =========================== --}}
    @if ($tab === 'history')
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 text-[11px] font-bold uppercase tracking-wider">
                    <tr>
                        <th class="py-2.5 px-4">Notification</th>
                        <th class="py-2.5 px-4">Channels</th>
                        <th class="py-2.5 px-4">When</th>
                        <th class="py-2.5 px-4">Result</th>
                        <th class="py-2.5 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($this->history as $item)
                        <tr wire:key="hist-{{ $item->id }}">
                            <td class="py-3 px-4">
                                <span class="font-semibold text-zinc-900 dark:text-white">{{ $item->name }}</span>
                                <span class="block text-[11px] text-zinc-400">{{ $item->subject }}</span>
                            </td>
                            <td class="py-3 px-4 text-xs uppercase text-zinc-500">{{ implode(' + ', $item->channels ?? []) }}</td>
                            <td class="py-3 px-4 text-xs text-zinc-500">
                                @if ($item->status === 'pending' && $item->scheduled_for)
                                    Scheduled {{ $item->scheduled_for->format('j M Y, H:i') }}
                                @elseif ($item->sent_at)
                                    Sent {{ $item->sent_at->format('j M Y, H:i') }}
                                @else
                                    {{ $item->created_at->diffForHumans() }}
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                @php
                                    $badge = match ($item->status) {
                                        'sent' => ['emerald', 'Sent'],
                                        'sending' => ['blue', 'Sending'],
                                        'pending' => ['amber', $item->scheduled_for ? 'Scheduled' : 'Queued'],
                                        'failed' => ['red', 'Failed'],
                                        'canceled' => ['zinc', 'Canceled'],
                                        default => ['zinc', ucfirst($item->status)],
                                    };
                                @endphp
                                <flux:badge size="sm" :color="$badge[0]">{{ $badge[1] }}</flux:badge>
                                @if (in_array($item->status, ['sent', 'failed'], true))
                                    <span class="block text-[11px] text-zinc-400 mt-0.5">
                                        {{ $item->delivered_count }} delivered · {{ $item->failed_count }} failed
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <flux:button size="sm" variant="subtle" icon="eye"
                                    wire:click="$set('viewingId', {{ $item->id }})" />
                                @if ($item->isCancellable())
                                    <flux:modal.trigger name="confirm-cancel-notification">
                                        <flux:button size="sm" variant="subtle" icon="x-mark" class="text-rose-600"
                                            wire:click="$set('confirmingCancelId', {{ $item->id }})" />
                                    </flux:modal.trigger>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-zinc-400">Nothing sent yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- ============================ MODALS ============================ --}}
    <flux:modal name="template-form" class="max-w-lg">
        <div class="space-y-4">
            <flux:heading size="lg">{{ $editingTemplateId ? 'Edit template' : 'New template' }}</flux:heading>
            <flux:input wire:model="tplName" label="Template name" placeholder="e.g. Term opening" />
            @error('tplName') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <flux:input wire:model="tplSubject" label="Subject / title" />
            @error('tplSubject') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <flux:textarea wire:model="tplBody" rows="6" label="Message"
                placeholder="Use @{{institution}}, @{{parent}} and your own @{{placeholders}}." />
            @error('tplBody') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Cancel</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="saveTemplate">Save template</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="confirm-delete-template" class="max-w-md" wire:close="$set('confirmingDeleteTemplateId', null)">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Delete this template?</flux:heading>
                <flux:subheading>Notifications already sent from it are not affected.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Cancel</flux:button></flux:modal.close>
                <flux:button variant="danger" icon="trash" wire:click="deleteTemplate">Delete template</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="confirm-cancel-notification" class="max-w-md" wire:close="$set('confirmingCancelId', null)">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Cancel this scheduled notification?</flux:heading>
                <flux:subheading>It will not be sent. You can compose it again later.</flux:subheading>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Keep it</flux:button></flux:modal.close>
                <flux:button variant="danger" icon="x-mark" wire:click="cancel">Cancel notification</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal :open="$viewingId !== null" wire:close="$set('viewingId', null)" class="max-w-2xl">
        @if ($this->viewing)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ $this->viewing->name }}</flux:heading>
                    <flux:subheading>{{ $this->viewing->subject }}</flux:subheading>
                </div>
                <p class="text-sm text-zinc-600 dark:text-zinc-300 whitespace-pre-line rounded-lg bg-zinc-50 dark:bg-zinc-900/50 p-3">{{ $this->viewing->body }}</p>

                <div class="text-xs text-zinc-500">
                    {{ $this->viewing->recipients_count }} attempt(s) —
                    {{ $this->viewing->delivered_count }} delivered, {{ $this->viewing->failed_count }} failed.
                </div>

                <div class="max-h-72 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 uppercase">
                            <tr>
                                <th class="py-2 px-3">Parent</th>
                                <th class="py-2 px-3">Channel</th>
                                <th class="py-2 px-3">To</th>
                                <th class="py-2 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->viewing->recipients as $r)
                                <tr wire:key="rcpt-{{ $r->id }}">
                                    <td class="py-1.5 px-3">{{ $r->user?->name ?? '—' }}</td>
                                    <td class="py-1.5 px-3 uppercase">{{ $r->channel }}</td>
                                    <td class="py-1.5 px-3 text-zinc-400">{{ $r->destination ?? '—' }}</td>
                                    <td class="py-1.5 px-3">
                                        <flux:badge size="sm" :color="$r->status === 'sent' ? 'emerald' : ($r->status === 'failed' ? 'red' : 'zinc')">
                                            {{ ucfirst($r->status) }}
                                        </flux:badge>
                                        @if ($r->error)
                                            <span class="block text-[10px] text-red-500">{{ $r->error }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end">
                    <flux:modal.close><flux:button variant="filled">Close</flux:button></flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
