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
    /**
     * Out-of-the-box pre-made notification templates available to all schools.
     */
    public const PREMADE_TEMPLATES = [
        'fee' => [
            'name' => 'School Fee Balance Reminder',
            'category' => 'Finance',
            'subject' => 'School Fee Balance Notice - {{institution}}',
            'body' => 'Dear {{parent}}, kindly note that the school fees balance for your child is due. We kindly request that you clear the balance before {{closing_date}} via our school Paybill. Thank you for your continued cooperation.',
            'placeholders' => ['parent', 'institution', 'closing_date'],
        ],
        'opening' => [
            'name' => 'Term Opening & Reopening Notice',
            'category' => 'Term Dates',
            'subject' => 'School Reopening Notice - {{institution}}',
            'body' => 'Dear {{parent}}, {{institution}} will reopen for the new term on {{opening_date}} at 8:00 AM. Kindly ensure learners report on time in full school uniform.',
            'placeholders' => ['parent', 'institution', 'opening_date'],
        ],
        'closing' => [
            'name' => 'End of Term Closing Notice',
            'category' => 'Term Dates',
            'subject' => 'End of Term Closing Notice - {{institution}}',
            'body' => 'Dear {{parent}}, {{institution}} officially closes for the holiday on {{closing_date}}. We wish all our students and parents a peaceful and safe break. School reopens on {{opening_date}}.',
            'placeholders' => ['parent', 'institution', 'closing_date', 'opening_date'],
        ],
        'midterm' => [
            'name' => 'Mid-Term Break Notice',
            'category' => 'Term Dates',
            'subject' => 'Mid-Term Break Notice - {{institution}}',
            'body' => 'Dear {{parent}}, {{institution}} will break for mid-term on {{closing_date}} at 12:00 PM and resume on {{opening_date}} at 8:00 AM. Thank you.',
            'placeholders' => ['parent', 'institution', 'closing_date', 'opening_date'],
        ],
        'agm' => [
            'name' => 'Annual Parents Meeting (AGM)',
            'category' => 'Events',
            'subject' => 'Invitation: Parents Meeting (AGM) - {{institution}}',
            'body' => 'Dear {{parent}}, you are cordially invited to attend the Annual Parents Meeting on {{opening_date}} at 9:00 AM at the school grounds. Your attendance is highly appreciated.',
            'placeholders' => ['parent', 'institution', 'opening_date'],
        ],
        'results' => [
            'name' => 'Examination Results Release Notice',
            'category' => 'Academic',
            'subject' => 'Exam Results Released - {{institution}}',
            'body' => 'Dear {{parent}}, terminal examination results for your child have been released. Kindly log in to the parent portal or visit the school office to review the report card. Thank you.',
            'placeholders' => ['parent', 'institution'],
        ],
        'biometrics' => [
            'name' => 'New Digital System & Biometric Attendance',
            'category' => 'Technology & Safety',
            'subject' => 'New Digital System & Morning Attendance - {{institution}}',
            'body' => 'Dear {{parent}}, welcome to our new school digital system! We now track student attendance using biometrics every morning when your child arrives safely at school. You will receive direct updates, attendance alerts, and important notices here. Thank you for partnering with {{institution}}.',
            'placeholders' => ['parent', 'institution'],
        ],
    ];

    public string $tab = 'send';

    // --- Compose / send ------------------------------------------------
    public ?int $editingScheduledId = null;

    public int|string|null $sourceTemplateId = null;

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
        if (is_string($value) && str_starts_with($value, 'premade:')) {
            $key = substr($value, 8);
            $premade = self::PREMADE_TEMPLATES[$key] ?? null;
            if ($premade) {
                $this->subject = $premade['subject'];
                $this->body = $premade['body'];
                $this->populateDefaultPlaceholders();
                $this->dispatch('template-loaded',
                    subject: $this->subject,
                    body: $this->body,
                    placeholders: $this->placeholderValues
                );

                return;
            }
        }

        $template = $value
            ? NotificationTemplate::where('institution_id', $this->institutionId())->find($value)
            : null;

        $this->subject = $template->subject ?? '';
        $this->body = $template->body ?? '';
        $this->populateDefaultPlaceholders();
        $this->dispatch('template-loaded',
            subject: $this->subject,
            body: $this->body,
            placeholders: $this->placeholderValues
        );
    }

    private function populateDefaultPlaceholders(): void
    {
        $this->placeholderValues = [];
        $text = $this->subject.' '.$this->body;
        $placeholders = ParentNotification::extractPlaceholders($text);

        if (in_array('opening_date', $placeholders, true)) {
            $this->placeholderValues['opening_date'] = now()->next(Carbon::MONDAY)->format('l, jS F Y');
        }
        if (in_array('closing_date', $placeholders, true)) {
            $this->placeholderValues['closing_date'] = now()->next(Carbon::FRIDAY)->format('l, jS F Y');
        }
        if (in_array('due_date', $placeholders, true)) {
            $this->placeholderValues['due_date'] = now()->addDays(7)->format('jS F Y');
        }
    }

    public function usePremadeTemplate(string $key): void
    {
        $this->sourceTemplateId = 'premade:'.$key;
        $this->updatedSourceTemplateId('premade:'.$key);
        $this->tab = 'send';
        Flux::toast(text: 'Template loaded into composer.', variant: 'success');
    }

    public function copyPremadeToCustom(string $key): void
    {
        $premade = self::PREMADE_TEMPLATES[$key] ?? null;
        if (! $premade) {
            return;
        }

        $this->editingTemplateId = null;
        $this->tplName = $premade['name'];
        $this->tplSubject = $premade['subject'];
        $this->tplBody = $premade['body'];
        Flux::modal('template-form')->show();
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

        // The shared demo school never messages anyone: the notice is
        // recorded as delivered to every parent so the history looks real,
        // but no job runs and the scheduler (which only picks up 'pending')
        // never sees it.
        $demo = currentInstitution()?->is_demo === true;

        $placeholderVals = collect($this->customPlaceholders)
            ->mapWithKeys(fn ($name) => [$name => trim($this->placeholderValues[$name] ?? '')])
            ->all();

        if ($this->editingScheduledId) {
            $notification = ParentNotification::where('institution_id', $this->institutionId())->findOrFail($this->editingScheduledId);
            abort_unless($notification->isCancellable(), 422, 'This notification can no longer be edited.');

            $notification->update([
                'template_id' => $this->sourceTemplateId,
                'name' => Str::limit($this->subject, 80, ''),
                'subject' => $this->subject,
                'body' => $this->body,
                'channels' => array_values($this->channels),
                'placeholder_values' => $placeholderVals,
                'scheduled_for' => $scheduled,
            ]);

            $this->reset(['sourceTemplateId', 'subject', 'body', 'placeholderValues', 'scheduledFor', 'editingScheduledId']);
            $this->channels = ['email'];
            $this->mode = 'now';
            $this->tab = 'history';
            unset($this->history);

            Flux::modal('confirm-send-modal')->close();
            Flux::toast(text: 'Scheduled notification updated successfully.', variant: 'success');

            return;
        }

        $notification = ParentNotification::create([
            'institution_id' => $this->institutionId(),
            'template_id' => $this->sourceTemplateId,
            'name' => Str::limit($this->subject, 80, ''),
            'subject' => $this->subject,
            'body' => $this->body,
            'channels' => array_values($this->channels),
            'placeholder_values' => $placeholderVals,
            'scheduled_for' => $scheduled,
            'status' => $demo ? 'sent' : 'pending',
            'delivered_count' => $demo ? $this->parentCount : 0,
            'sent_at' => $demo ? now() : null,
            'created_by' => auth()->id(),
        ]);

        if (! $scheduled && ! $demo) {
            SendParentNotificationJob::dispatch($notification->id);
        }

        $this->reset(['sourceTemplateId', 'subject', 'body', 'placeholderValues', 'scheduledFor', 'editingScheduledId']);
        $this->channels = ['email'];
        $this->mode = 'now';
        $this->tab = 'history';
        unset($this->history);
        Flux::modal('confirm-send-modal')->close();

        Flux::toast(
            text: match (true) {
                $demo => 'Demo school: notification recorded for '.$notification->delivered_count.' parent(s), but nothing is actually sent from the demo.',
                $scheduled !== null => 'Notification scheduled for '.$scheduled->format('j M Y, H:i').'.',
                default => 'Notification queued for delivery to '.$this->parentCount.' parent(s).',
            },
            variant: 'success',
        );
    }

    public function editScheduled(int $id): void
    {
        $notification = ParentNotification::where('institution_id', $this->institutionId())->findOrFail($id);
        abort_unless($notification->isCancellable(), 422, 'Only scheduled or pending notices can be edited.');

        $this->editingScheduledId = $notification->id;
        $this->sourceTemplateId = $notification->template_id;
        $this->subject = $notification->subject;
        $this->body = $notification->body;
        $this->channels = $notification->channels ?? ['email'];
        $this->placeholderValues = $notification->placeholder_values ?? [];
        $this->mode = $notification->scheduled_for ? 'schedule' : 'now';
        $this->scheduledFor = $notification->scheduled_for?->format('Y-m-d\TH:i') ?? '';
        $this->tab = 'send';
        Flux::toast(text: 'Editing scheduled notification.', variant: 'info');
    }

    public function reuseNotification(int $id): void
    {
        $notification = ParentNotification::where('institution_id', $this->institutionId())->findOrFail($id);

        $this->editingScheduledId = null;
        $this->sourceTemplateId = null;
        $this->subject = str_starts_with($notification->subject, 'Correction:') ? $notification->subject : 'Correction: '.$notification->subject;
        $this->body = $notification->body;
        $this->channels = $notification->channels ?? ['email'];
        $this->placeholderValues = $notification->placeholder_values ?? [];
        $this->mode = 'now';
        $this->tab = 'send';
        Flux::toast(text: 'Notice loaded into composer for editing / correction.', variant: 'success');
    }

    public function cancelEditingScheduled(): void
    {
        $this->editingScheduledId = null;
        $this->reset(['sourceTemplateId', 'subject', 'body', 'placeholderValues', 'scheduledFor']);
        $this->channels = ['email'];
        $this->mode = 'now';
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

<div x-data="{
    localSubject: '',
    localBody: '',
    localPlaceholderValues: {},
    customTagName: '',
    customTagModalOpen: false,

    init() {
        this.localSubject = $wire.subject || '';
        this.localBody = $wire.body || '';
        this.localPlaceholderValues = { ...($wire.placeholderValues || {}) };
    },

    syncInputs() {
        const b = document.getElementById('composer-body');
        const bEl = b?.tagName === 'TEXTAREA' ? b : b?.querySelector('textarea');
        if (bEl) bEl.value = this.localBody;

        const s = document.getElementById('composer-subject');
        const sEl = s?.tagName === 'INPUT' ? s : s?.querySelector('input');
        if (sEl) sEl.value = this.localSubject;
    },

    onBodyInput(e) {
        this.localBody = e.target.value;
    },

    onSubjectInput(e) {
        this.localSubject = e.target.value;
    },

    applyPreset(type) {
        $wire.usePremadeTemplate(type);
    },

    autoPersonalize() {
        if (!this.localBody) return;
        let updated = this.localBody.replace(/dear\s+parents?/gi, 'Dear @{{parent}}');
        if (!updated.includes('@{{parent}}')) {
            updated = 'Dear @{{parent}}, ' + updated;
        }
        this.localBody = updated;
        this.syncInputs();
        $wire.set('body', updated);
    },

    formatDateChoice(isoStr, style = 'natural') {
        if (!isoStr) return '';
        const parts = isoStr.split('-');
        if (parts.length !== 3) return isoStr;
        const y = parseInt(parts[0]);
        const m = parseInt(parts[1]) - 1;
        const d = parseInt(parts[2]);
        const dt = new Date(y, m, d);

        if (style === 'slash') {
            const dd = String(d).padStart(2, '0');
            const mm = String(m + 1).padStart(2, '0');
            return `${dd}/${mm}/${y}`;
        }
        if (style === 'day') {
            return dt.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
        }

        const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        const j = d % 10, k = d % 100;
        let suffix = 'th';
        if (j === 1 && k !== 11) suffix = 'st';
        else if (j === 2 && k !== 12) suffix = 'nd';
        else if (j === 3 && k !== 13) suffix = 'rd';

        return `${d}${suffix} ${months[m]} ${y}`;
    },

    insertFormattedDate(isoStr, style = 'natural', target = 'body') {
        const text = this.formatDateChoice(isoStr, style);
        this.insertText(text, target);
    },

    dragTag(e, tagName) {
        if (e?.dataTransfer) {
            e.dataTransfer.setData('text/plain', String.fromCharCode(123, 123) + tagName + String.fromCharCode(125, 125));
        }
    },

    insertText(textToInsert, target = 'body') {
        let elementId = '';
        if (target === 'body') elementId = 'composer-body';
        else if (target === 'subject') elementId = 'composer-subject';
        else if (target === 'tplBody') elementId = 'tpl-body';
        else if (target === 'tplSubject') elementId = 'tpl-subject';

        const wrapper = document.getElementById(elementId);
        const el = (wrapper?.tagName === 'TEXTAREA' || wrapper?.tagName === 'INPUT') 
            ? wrapper 
            : wrapper?.querySelector('textarea, input');

        if (el) {
            const start = el.selectionStart ?? el.value.length;
            const end = el.selectionEnd ?? el.value.length;
            const val = el.value || '';
            const before = val.substring(0, start);
            const after = val.substring(end);

            const needLeadingSpace = before.length > 0 && !/[\s\n]$/.test(before);
            const needTrailingSpace = after.length > 0 && !/^[\s\n]/.test(after);
            const insertVal = (needLeadingSpace ? ' ' : '') + textToInsert + (needTrailingSpace ? ' ' : '');

            el.value = before + insertVal + after;
            const newPos = start + insertVal.length;
            el.setSelectionRange(newPos, newPos);
            el.focus();

            if (target === 'body') this.localBody = el.value;
            if (target === 'subject') this.localSubject = el.value;

            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            if (target === 'body') {
                const updated = (this.localBody ? this.localBody + ' ' : '') + textToInsert;
                this.localBody = updated;
                $wire.set('body', updated);
            } else if (target === 'subject') {
                const updated = (this.localSubject ? this.localSubject + ' ' : '') + textToInsert;
                this.localSubject = updated;
                $wire.set('subject', updated);
            } else if (target === 'tplBody') {
                $wire.set('tplBody', ($wire.tplBody ? $wire.tplBody + ' ' : '') + textToInsert);
            } else if (target === 'tplSubject') {
                $wire.set('tplSubject', ($wire.tplSubject ? $wire.tplSubject + ' ' : '') + textToInsert);
            }
        }
    },

    formatDate(isoStr) {
        return this.formatDateChoice(isoStr, 'day');
    },

    insertTag(tagName, target = 'body') {
        const tag = String.fromCharCode(123, 123) + tagName + String.fromCharCode(125, 125);
        this.insertText(tag, target);
    },

    insertCustom(target = 'body') {
        let name = this.customTagName.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_').replace(/^_+|_+$/g, '');
        if (!name) return;
        this.insertTag(name, target);
        this.customTagName = '';
        this.customTagModalOpen = false;
    },

    getPreview(text = '') {
        if (!text) return '';
        let res = text;
        res = res.replace(new RegExp('\\{\\{\\s*parent\\s*\\}\\}', 'gi'), 'Jane Doe');
        res = res.replace(new RegExp('\\{\\{\\s*institution\\s*\\}\\}', 'gi'), '{{ addslashes(currentInstitution()->name) }}');
        res = res.replace(new RegExp('\\{\\{\\s*date\\s*\\}\\}', 'gi'), '{{ now()->format("j M Y") }}');

        if (this.localPlaceholderValues) {
            for (const [k, v] of Object.entries(this.localPlaceholderValues)) {
                if (v && String(v).trim()) {
                    res = res.replace(new RegExp('\\{\\{\\s*' + k + '\\s*\\}\\}', 'gi'), String(v).trim());
                }
            }
        }
        return res;
    },

    getSmsStats(text = '') {
        const str = text || '';
        const len = str.length;
        if (len === 0) return { chars: 0, credits: 0, isUnicode: false, perCredit: 160 };

        const isUnicode = /[^\u0020-\u007E\u00A0-\u00FF\n\r]/.test(str);
        const singleLimit = isUnicode ? 70 : 160;
        const multiLimit = isUnicode ? 67 : 153;

        let credits = 1;
        if (len > singleLimit) {
            credits = Math.ceil(len / multiLimit);
        }
        return {
            chars: len,
            credits: credits,
            isUnicode: isUnicode,
            perCredit: len <= singleLimit ? singleLimit : multiLimit
        };
    }
}" @template-loaded.window="
    localSubject = $event.detail.subject || '';
    localBody = $event.detail.body || '';
    localPlaceholderValues = { ...($event.detail.placeholders || {}) };
    syncInputs();
" class="flex flex-col gap-6 w-full max-w-5xl mx-auto p-4 sm:p-6 lg:p-8">
    <div>
        <flux:heading size="xl" class="font-bold tracking-tight text-zinc-900 dark:text-white">Parent Notifications</flux:heading>
        <flux:subheading class="text-zinc-500 text-sm mt-1">
            Compose and broadcast school announcements, term dates, and fee notices to parents via SMS and email.
        </flux:subheading>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 border-b border-zinc-200 dark:border-zinc-800 overflow-x-auto no-scrollbar scroll-smooth">
        @foreach (['send' => 'Compose & send', 'templates' => 'Templates', 'history' => 'History'] as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')"
                @class([
                    'px-4 py-2.5 text-sm font-semibold -mb-px border-b-2 transition whitespace-nowrap shrink-0 flex items-center gap-1.5 cursor-pointer',
                    'border-zinc-900 text-zinc-900 dark:border-white dark:text-white' => $tab === $key,
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== $key,
                ])>
                @if ($key === 'send')
                    <flux:icon icon="paper-airplane" class="size-4" />
                @elseif ($key === 'templates')
                    <flux:icon icon="document-text" class="size-4" />
                @else
                    <flux:icon icon="clock" class="size-4" />
                @endif
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ============================ SEND ============================ --}}
    @if ($tab === 'send')
        <div class="grid gap-6 lg:grid-cols-5">
            <div class="lg:col-span-3 space-y-4">

                {{-- Alert when editing a scheduled notice --}}
                @if ($editingScheduledId)
                    <div class="p-3.5 rounded-lg border border-amber-200 dark:border-amber-800/80 bg-amber-50 dark:bg-amber-950/30 flex items-center justify-between text-xs text-amber-900 dark:text-amber-200 shadow-xs">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="pencil-square" class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
                            <span><strong>Editing Scheduled Notice:</strong> Saving changes will update the notice before it is sent.</span>
                        </div>
                        <button type="button" wire:click="cancelEditingScheduled" class="underline font-semibold hover:text-amber-700 ml-2 shrink-0 cursor-pointer">Cancel Edit</button>
                    </div>
                @endif

                {{-- Quick Message Starters --}}
                <div class="space-y-2.5 p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/60 dark:bg-zinc-900/50">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider flex items-center gap-1.5">
                            <flux:icon icon="sparkles" class="size-3.5 text-zinc-500" />
                            Quick Templates
                        </span>
                        <span class="text-xs text-zinc-500 hidden sm:inline">Pre-filled school announcements</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                        <button type="button" @click="applyPreset('fee')"
                            class="p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-800 hover:border-zinc-400 dark:hover:border-zinc-600 hover:bg-zinc-50/80 dark:hover:bg-zinc-750 text-left transition shadow-xs group cursor-pointer">
                            <div class="flex items-center gap-1.5">
                                <flux:icon icon="banknotes" class="size-4 text-zinc-600 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-white shrink-0" />
                                <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate">Fee Reminder</span>
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 truncate">Balance clearance</p>
                        </button>

                        <button type="button" @click="applyPreset('opening')"
                            class="p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-800 hover:border-zinc-400 dark:hover:border-zinc-600 hover:bg-zinc-50/80 dark:hover:bg-zinc-750 text-left transition shadow-xs group cursor-pointer">
                            <div class="flex items-center gap-1.5">
                                <flux:icon icon="academic-cap" class="size-4 text-zinc-600 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-white shrink-0" />
                                <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate">Term Opening</span>
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 truncate">Reporting dates</p>
                        </button>

                        <button type="button" @click="applyPreset('closing')"
                            class="p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-800 hover:border-zinc-400 dark:hover:border-zinc-600 hover:bg-zinc-50/80 dark:hover:bg-zinc-750 text-left transition shadow-xs group cursor-pointer">
                            <div class="flex items-center gap-1.5">
                                <flux:icon icon="calendar" class="size-4 text-zinc-600 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-white shrink-0" />
                                <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate">Term Closing</span>
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 truncate">Holiday closure</p>
                        </button>

                        <button type="button" @click="applyPreset('agm')"
                            class="p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-800 hover:border-zinc-400 dark:hover:border-zinc-600 hover:bg-zinc-50/80 dark:hover:bg-zinc-750 text-left transition shadow-xs group cursor-pointer">
                            <div class="flex items-center gap-1.5">
                                <flux:icon icon="user-group" class="size-4 text-zinc-600 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-white shrink-0" />
                                <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate">Parents AGM</span>
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 truncate">General meeting</p>
                        </button>

                        <button type="button" @click="applyPreset('biometrics')"
                            class="p-2.5 rounded-lg border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-800 hover:border-zinc-400 dark:hover:border-zinc-600 hover:bg-zinc-50/80 dark:hover:bg-zinc-750 text-left transition shadow-xs group cursor-pointer col-span-2 sm:col-span-1">
                            <div class="flex items-center gap-1.5">
                                <flux:icon icon="finger-print" class="size-4 text-zinc-600 dark:text-zinc-400 group-hover:text-zinc-900 dark:group-hover:text-white shrink-0" />
                                <span class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate">Biometrics</span>
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1 truncate">Attendance notice</p>
                        </button>
                    </div>
                </div>

                <flux:select wire:model.live="sourceTemplateId" label="Choose a template" placeholder="Write from scratch or pick a template">
                    @foreach (self::PREMADE_TEMPLATES as $key => $p)
                        <flux:select.option value="premade:{{ $key }}">{{ $p['name'] }} (System)</flux:select.option>
                    @endforeach

                    @foreach ($this->templates as $template)
                        <flux:select.option :value="$template->id">{{ $template->name }} (School)</flux:select.option>
                    @endforeach
                </flux:select>

                {{-- Subject with Quick Inserts --}}
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between">
                        <flux:label>Subject / title</flux:label>
                        <div class="flex items-center gap-1.5 text-xs text-zinc-500">
                            <span class="text-zinc-400 hidden sm:inline">Insert:</span>
                            <button type="button" @click="insertTag('institution', 'subject')" class="px-2 py-0.5 rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition cursor-pointer text-xs">+ School</button>
                            <button type="button" @click="insertTag('date', 'subject')" class="px-2 py-0.5 rounded-md border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-100 dark:hover:bg-zinc-700 transition cursor-pointer text-xs">+ Date</button>
                        </div>
                    </div>
                    <flux:input id="composer-subject" wire:model.live.debounce.1200ms="subject" @input="onSubjectInput($event)"
                        placeholder="e.g. School Fee Balance Reminder - {{ currentInstitution()->name }}" />
                    @error('subject') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Message Textarea with Variable Insertion Toolbar --}}
                <div class="space-y-2">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                        <flux:label>Message</flux:label>
                        <span class="text-xs text-zinc-500">Use variable chips to personalize text</span>
                    </div>

                    {{-- Variable Insertion Toolbar --}}
                    <div class="p-2.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-900/60">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-xs font-semibold text-zinc-600 dark:text-zinc-400 uppercase tracking-wider mr-1 flex items-center gap-1">
                                <flux:icon icon="variable" class="size-3.5 text-zinc-500" />
                                Insert:
                            </span>

                            {{-- Opening Date / Interactive Date Picker --}}
                            <div x-data="{
                                openDatePop: false,
                                dateVal: '{{ now()->addDays(7)->format('Y-m-d') }}'
                            }" class="relative inline-block">
                                <button type="button" @click="openDatePop = !openDatePop"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 shadow-xs transition cursor-pointer"
                                    title="Insert opening date or pick any date from calendar">
                                    <flux:icon icon="calendar" class="size-3.5 text-zinc-500" />
                                    <span>Opening Date</span>
                                    <flux:icon icon="chevron-down" class="size-3 opacity-60" />
                                </button>

                                <div x-show="openDatePop" @click.outside="openDatePop = false" style="display: none;"
                                    class="absolute left-0 mt-1.5 z-40 w-80 p-4 rounded-xl shadow-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 space-y-3">
                                    <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-700/60 pb-2">
                                        <p class="text-xs font-semibold text-zinc-900 dark:text-white flex items-center gap-1.5">
                                            <flux:icon icon="calendar" class="size-4 text-zinc-600 dark:text-zinc-400" />
                                            Select Date to Insert
                                        </p>
                                        <button type="button" @click="openDatePop = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 text-xs cursor-pointer"><flux:icon icon="x-mark" class="size-3.5" /></button>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-zinc-700 dark:text-zinc-300 mb-1">Calendar date:</label>
                                        <input type="date" x-model="dateVal"
                                            class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-white font-medium focus:ring-2 focus:ring-zinc-500" />
                                    </div>
                                    
                                    <div class="space-y-1.5 pt-1">
                                        <label class="block text-xs font-medium text-zinc-500 uppercase tracking-wider">Format preview:</label>
                                        
                                        <button type="button" @click="insertFormattedDate(dateVal, 'natural', 'body'); openDatePop = false;"
                                            class="w-full py-2 px-2.5 text-xs font-medium rounded-lg bg-zinc-50 hover:bg-zinc-100 text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-850 border border-zinc-200 dark:border-zinc-700 flex items-center justify-between cursor-pointer transition">
                                            <span class="text-xs text-zinc-500">Natural English:</span>
                                            <strong x-text="formatDateChoice(dateVal, 'natural')"></strong>
                                        </button>

                                        <button type="button" @click="insertFormattedDate(dateVal, 'slash', 'body'); openDatePop = false;"
                                            class="w-full py-2 px-2.5 text-xs font-medium rounded-lg bg-zinc-50 hover:bg-zinc-100 text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-850 border border-zinc-200 dark:border-zinc-700 flex items-center justify-between cursor-pointer transition">
                                            <span class="text-xs text-zinc-500">Standard DD/MM/YYYY:</span>
                                            <strong x-text="formatDateChoice(dateVal, 'slash')"></strong>
                                        </button>

                                        <button type="button" @click="insertFormattedDate(dateVal, 'day', 'body'); openDatePop = false;"
                                            class="w-full py-2 px-2.5 text-xs font-medium rounded-lg bg-zinc-50 hover:bg-zinc-100 text-zinc-900 dark:bg-zinc-900 dark:text-zinc-100 dark:hover:bg-zinc-850 border border-zinc-200 dark:border-zinc-700 flex items-center justify-between cursor-pointer transition">
                                            <span class="text-xs text-zinc-500">With Day Name:</span>
                                            <strong x-text="formatDateChoice(dateVal, 'day')"></strong>
                                        </button>
                                    </div>

                                    <div class="pt-2 border-t border-zinc-100 dark:border-zinc-700/60 flex justify-between items-center text-xs">
                                        <button type="button" @click="insertTag('opening_date', 'body'); openDatePop = false;" class="text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white underline">
                                            Insert tag &#123;&#123;opening_date&#125;&#125;
                                        </button>
                                        <button type="button" @click="openDatePop = false" class="text-zinc-400 hover:text-zinc-600">Close</button>
                                    </div>
                                </div>
                            </div>

                            {{-- Parent Name Chip --}}
                            <button type="button" @click="insertTag('parent', 'body')" draggable="true" @dragstart="dragTag($event, 'parent')"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 shadow-xs transition cursor-pointer"
                                title="Click to insert parent's personalized name">
                                <flux:icon icon="user" class="size-3.5 text-zinc-500" />
                                <span>Parent Name</span>
                            </button>

                            {{-- School Name Chip --}}
                            <button type="button" @click="insertTag('institution', 'body')" draggable="true" @dragstart="dragTag($event, 'institution')"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 shadow-xs transition cursor-pointer"
                                title="Click to insert school name">
                                <flux:icon icon="building-library" class="size-3.5 text-zinc-500" />
                                <span>School Name</span>
                            </button>

                            {{-- Fee Balance / Amount Helper --}}
                            <div x-data="{ openFee: false, feeVal: 'KES 5,000' }" class="relative inline-block">
                                <button type="button" @click="openFee = !openFee"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 shadow-xs transition cursor-pointer"
                                    title="Insert fee balance or amount">
                                    <flux:icon icon="banknotes" class="size-3.5 text-zinc-500" />
                                    <span>Fee Balance</span>
                                    <flux:icon icon="chevron-down" class="size-3 opacity-60" />
                                </button>
                                <div x-show="openFee" @click.outside="openFee = false" style="display: none;"
                                    class="absolute left-0 mt-1.5 z-40 w-64 p-3.5 rounded-xl shadow-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 space-y-2.5">
                                    <p class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">Fee Balance</p>
                                    <input type="text" x-model="feeVal" placeholder="e.g. KES 5,000"
                                        class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-white" />
                                    <div class="flex flex-col gap-1.5 pt-1">
                                        <button type="button" @click="insertText(feeVal, 'body'); openFee = false;"
                                            class="w-full py-1.5 px-2 text-xs font-semibold rounded-md bg-zinc-900 hover:bg-zinc-800 text-white dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100 text-center transition cursor-pointer">
                                            Insert Fixed Amount
                                        </button>
                                        <button type="button" @click="insertTag('fee_balance', 'body'); openFee = false;"
                                            class="w-full py-1 text-xs text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-200 text-center cursor-pointer">
                                            Insert Tag &#123;&#123;fee_balance&#125;&#125;
                                        </button>
                                    </div>
                                </div>
                            </div>

                            {{-- Reporting Time Pill --}}
                            <div x-data="{ openTime: false }" class="relative inline-block">
                                <button type="button" @click="openTime = !openTime"
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 shadow-xs transition cursor-pointer"
                                    title="Reporting time">
                                    <flux:icon icon="clock" class="size-3.5 text-zinc-500" />
                                    <span>Reporting Time</span>
                                    <flux:icon icon="chevron-down" class="size-3 opacity-60" />
                                </button>
                                <div x-show="openTime" @click.outside="openTime = false" style="display: none;"
                                    class="absolute left-0 mt-1.5 z-40 w-48 p-3 rounded-xl shadow-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 space-y-1">
                                    <p class="text-xs font-semibold text-zinc-700 dark:text-zinc-300 mb-1">Select Time:</p>
                                    <button type="button" @click="insertText('8:00 AM', 'body'); openTime = false;" class="w-full text-left px-2.5 py-1.5 text-xs rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 cursor-pointer">8:00 AM</button>
                                    <button type="button" @click="insertText('7:30 AM', 'body'); openTime = false;" class="w-full text-left px-2.5 py-1.5 text-xs rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 cursor-pointer">7:30 AM</button>
                                    <button type="button" @click="insertText('9:00 AM', 'body'); openTime = false;" class="w-full text-left px-2.5 py-1.5 text-xs rounded-md hover:bg-zinc-100 dark:hover:bg-zinc-700 text-zinc-800 dark:text-zinc-200 cursor-pointer">9:00 AM</button>
                                    <button type="button" @click="insertTag('reporting_time', 'body'); openTime = false;" class="w-full text-left px-2.5 py-1.5 text-xs text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white hover:bg-zinc-100 dark:hover:bg-zinc-700 rounded-md cursor-pointer">Tag &#123;&#123;reporting_time&#125;&#125;</button>
                                </div>
                            </div>

                            {{-- Custom Variable Pill with Popover --}}
                            <div x-data="{ open: false }" class="relative inline-block">
                                <button type="button" @click="open = !open" 
                                    class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-100 dark:hover:bg-zinc-700 shadow-xs transition cursor-pointer">
                                    <flux:icon icon="plus" class="size-3.5 text-zinc-500" />
                                    <span>Custom Tag</span>
                                </button>
                                <div x-show="open" @click.outside="open = false" style="display: none;"
                                    class="absolute left-0 mt-1.5 z-40 w-64 p-3.5 rounded-xl shadow-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 space-y-2.5">
                                    <div>
                                        <p class="text-xs font-semibold text-zinc-900 dark:text-zinc-100">Add Custom Tag</p>
                                        <p class="text-xs text-zinc-500 mt-0.5">e.g. bus_route, uniform_fee, agm_venue</p>
                                    </div>
                                    <input type="text" x-model="customTagName" @keydown.enter.prevent="insertCustom('body'); open = false;"
                                        placeholder="Tag name (e.g. bus_route)" 
                                        class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-900 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-zinc-500 text-zinc-900 dark:text-white" />
                                    <div class="flex justify-end gap-2">
                                        <button type="button" @click="open = false" class="px-2.5 py-1 text-xs text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 cursor-pointer">Cancel</button>
                                        <button type="button" @click="insertCustom('body'); open = false;" class="px-3 py-1 text-xs font-semibold rounded-md bg-zinc-900 hover:bg-zinc-800 text-white dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100 shadow-xs cursor-pointer">Insert</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- The Textarea -- Zero latency debounced typing --}}
                    <flux:textarea id="composer-body" wire:model.live.debounce.1200ms="body" @input="onBodyInput($event)" rows="6"
                        placeholder="Dear @{{parent}}, kindly pay the school fee balance before 30th September 2026..." />
                    @error('body') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                    {{-- Personalize Suggestion --}}
                    <div x-show="localBody && /dear\s+parents?/i.test(localBody) && !localBody.includes(String.fromCharCode(123, 123) + 'parent' + String.fromCharCode(125, 125))" style="display: none;"
                        class="p-3 rounded-lg bg-zinc-100/80 dark:bg-zinc-800/80 border border-zinc-200 dark:border-zinc-700 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 text-xs text-zinc-800 dark:text-zinc-200">
                        <div class="flex items-center gap-2">
                            <flux:icon icon="information-circle" class="size-4 text-zinc-500 shrink-0" />
                            <span>Address each parent personally instead of a generic greeting.</span>
                        </div>
                        <button type="button" @click="autoPersonalize()"
                            class="px-3 py-1.5 rounded-md bg-zinc-900 hover:bg-zinc-800 text-white dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100 font-semibold text-xs shadow-xs transition cursor-pointer shrink-0">
                            Replace with Parent's Name
                        </button>
                    </div>

                    {{-- SMS Credit & Character Live Counter (Calculated instantly in browser) --}}
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-1 text-xs text-zinc-500"
                         x-data="{ stats: getSmsStats(localBody) }" x-effect="stats = getSmsStats(localBody)">
                        <template x-if="$wire.channels.includes('sms')">
                            <div class="flex items-center flex-wrap gap-2">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">
                                    <flux:icon icon="chat-bubble-bottom-center-text" class="size-3.5 text-zinc-500" />
                                    <span x-text="stats.chars + ' chars'"></span>
                                    <span class="opacity-50">·</span>
                                    <span x-text="stats.credits + ' SMS ' + (stats.credits === 1 ? 'credit' : 'credits')"></span>
                                </span>

                                <template x-if="stats.isUnicode">
                                    <span class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1" title="Unicode characters reduce segment length to 70 chars">
                                        <flux:icon icon="information-circle" class="size-3.5" />
                                        Special characters (70 chars/credit)
                                    </span>
                                </template>

                                <template x-if="!stats.isUnicode && stats.credits > 1">
                                    <span class="text-xs text-amber-600 dark:text-amber-400 flex items-center gap-1">
                                        <flux:icon icon="exclamation-triangle" class="size-3.5" />
                                        Multi-part SMS (153 chars/segment)
                                    </span>
                                </template>

                                @if ($this->parentCount > 0)
                                    <span class="text-xs text-zinc-500 dark:text-zinc-400 hidden sm:inline">
                                        (~<span x-text="stats.credits * {{ $this->parentCount }}"></span> total credits for {{ $this->parentCount }} parents)
                                    </span>
                                @endif
                            </div>
                        </template>
                        <template x-if="!$wire.channels.includes('sms')">
                            <span class="text-zinc-500 text-xs">
                                <span x-text="(localBody || '').length"></span> / 2,000 characters
                            </span>
                        </template>

                        <span class="text-xs text-zinc-500">
                            Auto-filled: <code class="font-mono text-zinc-700 dark:text-zinc-300">@{{parent}}</code> & <code class="font-mono text-zinc-700 dark:text-zinc-300">@{{institution}}</code>
                        </span>
                    </div>
                </div>

                {{-- Notice Dates & Parameters Card --}}
                @if (count($this->customPlaceholders))
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/40 p-4 sm:p-5 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-zinc-200/80 dark:border-zinc-800 pb-3">
                            <div class="flex items-center gap-2.5">
                                <div class="p-2 rounded-lg bg-zinc-100 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-700">
                                    <flux:icon icon="calendar-days" class="size-4" />
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-white flex items-center gap-2">
                                        <span>Notice Parameters</span>
                                        <flux:badge size="sm" color="zinc">{{ count($this->customPlaceholders) }} required</flux:badge>
                                    </h4>
                                    <p class="text-xs text-zinc-500">
                                        Specify the exact dates or details for this notice before dispatching.
                                    </p>
                                </div>
                            </div>
                            <span class="text-xs text-zinc-500 hidden sm:inline">
                                Injected into recipient messages automatically
                            </span>
                        </div>

                        <div class="grid gap-3.5 sm:grid-cols-2">
                            @foreach ($this->customPlaceholders as $name)
                                @php
                                    $isDate = str_contains($name, 'date');
                                    $label = match($name) {
                                        'opening_date' => 'School Reopening Date',
                                        'closing_date' => 'School Closing / Holiday Date',
                                        'due_date' => 'Fee Payment Deadline Date',
                                        default => ucwords(str_replace('_', ' ', $name)),
                                    };
                                    $help = match($name) {
                                        'opening_date' => 'The date learners report back to school',
                                        'closing_date' => 'The last day of the school term / holiday start',
                                        'due_date' => 'Final date for school fee clearance',
                                        default => 'Custom value to include in this notice',
                                    };
                                @endphp

                                <div class="p-3.5 rounded-lg border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-800 space-y-2.5 shadow-xs"
                                     x-data="{
                                         setQuickDate(formatted) {
                                             $wire.set('placeholderValues.{{ $name }}', formatted);
                                             localPlaceholderValues['{{ $name }}'] = formatted;
                                             const el = document.getElementById('placeholder-input-{{ $name }}');
                                             const input = el?.tagName === 'INPUT' ? el : el?.querySelector('input');
                                             if (input) input.value = formatted;
                                         },
                                         pickCalendar(e) {
                                             const val = e.target.value;
                                             if (!val) return;
                                             const [y, m, d] = val.split('-');
                                             const dt = new Date(parseInt(y), parseInt(m) - 1, parseInt(d));
                                             const months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                             const dayNum = parseInt(d);
                                             const j = dayNum % 10, k = dayNum % 100;
                                             let suffix = 'th';
                                             if (j === 1 && k !== 11) suffix = 'st';
                                             else if (j === 2 && k !== 12) suffix = 'nd';
                                             else if (j === 3 && k !== 13) suffix = 'rd';
                                             const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                                             const dayName = days[dt.getDay()];
                                             const formatted = `${dayName}, ${dayNum}${suffix} ${months[dt.getMonth()]} ${y}`;
                                             this.setQuickDate(formatted);
                                         }
                                     }">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <label class="text-xs font-semibold text-zinc-900 dark:text-white flex items-center gap-1.5">
                                                <flux:icon icon="{{ $isDate ? 'calendar' : 'tag' }}" class="size-3.5 text-zinc-500" />
                                                <span>{{ $label }}</span>
                                            </label>
                                            <p class="text-xs text-zinc-500">{{ $help }}</p>
                                        </div>
                                        <code class="text-xs font-mono text-zinc-500 bg-zinc-100 dark:bg-zinc-700/60 px-1.5 py-0.5 rounded">
                                            &#123;&#123;{{ $name }}&#125;&#125;
                                        </code>
                                    </div>

                                    @if ($isDate)
                                        <div class="space-y-2 pt-1">
                                            <div class="flex items-center gap-2">
                                                <div class="relative flex-1">
                                                    <flux:input id="placeholder-input-{{ $name }}"
                                                        wire:model.live.debounce.250ms="placeholderValues.{{ $name }}"
                                                        @input="localPlaceholderValues['{{ $name }}'] = $event.target.value"
                                                        placeholder="e.g. Monday, 5th January 2026"
                                                        class="w-full text-xs" />
                                                </div>
                                                <div class="relative shrink-0" x-data="{ openCalendarPop: false }">
                                                    <button type="button"
                                                        @click="openCalendarPop = !openCalendarPop"
                                                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-800 dark:text-zinc-200 text-xs font-medium cursor-pointer transition"
                                                        title="Pick date from calendar">
                                                        <flux:icon icon="calendar" class="size-3.5 text-zinc-500" />
                                                        <span>Pick Date</span>
                                                        <flux:icon icon="chevron-down" class="size-3 opacity-60" />
                                                    </button>

                                                    <div x-show="openCalendarPop" @click.outside="openCalendarPop = false" style="display: none;"
                                                        class="absolute right-0 mt-1.5 z-50 w-72 p-3.5 rounded-2xl shadow-2xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 space-y-3">
                                                        <div class="flex items-center justify-between border-b border-zinc-100 dark:border-zinc-700/60 pb-2">
                                                             <p class="text-xs font-bold text-zinc-900 dark:text-white flex items-center gap-1.5">
                                                                 <flux:icon icon="calendar" class="size-3.5 text-indigo-600" />
                                                                 Select on Calendar
                                                             </p>
                                                             <button type="button" @click="openCalendarPop = false" class="text-zinc-400 hover:text-zinc-600 text-xs cursor-pointer">✕</button>
                                                        </div>

                                                        <div>
                                                             <label class="block text-[11px] font-medium text-zinc-600 dark:text-zinc-300 mb-1">Select date on calendar:</label>
                                                             <input type="date"
                                                                 @change="pickCalendar($event); openCalendarPop = false;"
                                                                 class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-900 px-3 py-2 text-zinc-900 dark:text-white font-medium focus:ring-2 focus:ring-indigo-500 cursor-pointer" />
                                                        </div>

                                                        <div class="space-y-1 pt-1">
                                                             <p class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Quick Suggestions:</p>
                                                             @if ($name === 'opening_date')
                                                                 <button type="button" @click="setQuickDate('{{ now()->next(\Carbon\Carbon::MONDAY)->format('l, jS F Y') }}'); openCalendarPop = false;"
                                                                     class="w-full py-1.5 px-2.5 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200/60 flex items-center justify-between cursor-pointer">
                                                                     <span>Next Monday</span>
                                                                     <strong>{{ now()->next(\Carbon\Carbon::MONDAY)->format('j M') }}</strong>
                                                                 </button>
                                                                 <button type="button" @click="setQuickDate('{{ now()->addWeeks(2)->next(\Carbon\Carbon::MONDAY)->format('l, jS F Y') }}'); openCalendarPop = false;"
                                                                     class="w-full py-1.5 px-2.5 text-xs font-semibold rounded-lg bg-zinc-50 hover:bg-zinc-100 text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 border border-zinc-200 flex items-center justify-between cursor-pointer">
                                                                     <span>In 2 Weeks</span>
                                                                     <strong>{{ now()->addWeeks(2)->next(\Carbon\Carbon::MONDAY)->format('j M') }}</strong>
                                                                 </button>
                                                             @elseif ($name === 'closing_date')
                                                                 <button type="button" @click="setQuickDate('{{ now()->next(\Carbon\Carbon::FRIDAY)->format('l, jS F Y') }}'); openCalendarPop = false;"
                                                                     class="w-full py-1.5 px-2.5 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200/60 flex items-center justify-between cursor-pointer">
                                                                     <span>This Friday</span>
                                                                     <strong>{{ now()->next(\Carbon\Carbon::FRIDAY)->format('j M') }}</strong>
                                                                 </button>
                                                                 <button type="button" @click="setQuickDate('{{ now()->addWeek()->next(\Carbon\Carbon::FRIDAY)->format('l, jS F Y') }}'); openCalendarPop = false;"
                                                                     class="w-full py-1.5 px-2.5 text-xs font-semibold rounded-lg bg-zinc-50 hover:bg-zinc-100 text-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 border border-zinc-200 flex items-center justify-between cursor-pointer">
                                                                     <span>Next Friday</span>
                                                                     <strong>{{ now()->addWeek()->next(\Carbon\Carbon::FRIDAY)->format('j M') }}</strong>
                                                                 </button>
                                                             @else
                                                                 <button type="button" @click="setQuickDate('{{ now()->addDays(7)->format('jS F Y') }}'); openCalendarPop = false;"
                                                                     class="w-full py-1.5 px-2.5 text-xs font-semibold rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200/60 flex items-center justify-between cursor-pointer">
                                                                     <span>In 7 Days</span>
                                                                     <strong>{{ now()->addDays(7)->format('j M') }}</strong>
                                                                 </button>
                                                             @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="flex flex-wrap items-center gap-1.5 pt-0.5 text-[11px]">
                                                <span class="text-zinc-400 text-[10px] uppercase font-bold">Quick:</span>
                                                @if ($name === 'opening_date')
                                                    <button type="button" @click="setQuickDate('{{ now()->next(\Carbon\Carbon::MONDAY)->format('l, jS F Y') }}')"
                                                        class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-700 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/60 text-zinc-700 dark:text-zinc-300 transition cursor-pointer">
                                                        Next Mon ({{ now()->next(\Carbon\Carbon::MONDAY)->format('j M') }})
                                                    </button>
                                                    <button type="button" @click="setQuickDate('{{ now()->addWeeks(2)->next(\Carbon\Carbon::MONDAY)->format('l, jS F Y') }}')"
                                                        class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-700 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/60 text-zinc-700 dark:text-zinc-300 transition cursor-pointer">
                                                        In 2 Weeks
                                                    </button>
                                                @elseif ($name === 'closing_date')
                                                    <button type="button" @click="setQuickDate('{{ now()->next(\Carbon\Carbon::FRIDAY)->format('l, jS F Y') }}')"
                                                        class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-700 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/60 text-zinc-700 dark:text-zinc-300 transition cursor-pointer">
                                                        This Fri ({{ now()->next(\Carbon\Carbon::FRIDAY)->format('j M') }})
                                                    </button>
                                                    <button type="button" @click="setQuickDate('{{ now()->addWeek()->next(\Carbon\Carbon::FRIDAY)->format('l, jS F Y') }}')"
                                                        class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-700 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/60 text-zinc-700 dark:text-zinc-300 transition cursor-pointer">
                                                        Next Fri ({{ now()->addWeek()->next(\Carbon\Carbon::FRIDAY)->format('j M') }})
                                                    </button>
                                                @else
                                                    <button type="button" @click="setQuickDate('{{ now()->addDays(7)->format('jS F Y') }}')"
                                                        class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-700 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/60 text-zinc-700 dark:text-zinc-300 transition cursor-pointer">
                                                        In 7 Days ({{ now()->addDays(7)->format('j M') }})
                                                    </button>
                                                    <button type="button" @click="setQuickDate('{{ now()->endOfMonth()->format('jS F Y') }}')"
                                                        class="px-2 py-0.5 rounded-md bg-zinc-100 dark:bg-zinc-700 hover:bg-indigo-50 hover:text-indigo-600 dark:hover:bg-indigo-950/60 text-zinc-700 dark:text-zinc-300 transition cursor-pointer">
                                                        End of Month
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <flux:input wire:model.live.debounce.250ms="placeholderValues.{{ $name }}"
                                            @input="localPlaceholderValues['{{ $name }}'] = $event.target.value"
                                            placeholder="Enter {{ str_replace('_', ' ', $name) }}"
                                            class="w-full text-xs" />
                                    @endif

                                    @error('placeholderValues.' . $name)
                                        <p class="text-xs text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div>
                    <flux:label>Channels</flux:label>
                    <div class="flex flex-wrap gap-4 mt-1.5">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <flux:checkbox wire:model.live="channels" value="email" />
                            <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200 flex items-center gap-1.5">
                                <flux:icon icon="envelope" class="size-4 text-zinc-500" />
                                E-mail
                            </span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <flux:checkbox wire:model.live="channels" value="sms" />
                            <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200 flex items-center gap-1.5">
                                <flux:icon icon="device-phone-mobile" class="size-4 text-zinc-500" />
                                SMS
                            </span>
                        </label>
                    </div>
                    @error('channels') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    <p class="text-[11px] text-zinc-400 mt-1.5">Parents chosen for SMS with no phone on file automatically fall back to e-mail.</p>
                    @unless (featureEnabled('sms'))
                        <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-1 flex items-center gap-1">
                            <flux:icon icon="exclamation-triangle" class="size-3.5" />
                            SMS is currently switched off in Site Settings.
                        </p>
                    @endunless
                </div>

                <div>
                    <flux:label>When to deliver</flux:label>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-1.5">
                        <flux:radio.group wire:model.live="mode" variant="segmented">
                            <flux:radio value="now" label="Send immediately" />
                            <flux:radio value="schedule" label="Schedule for later" />
                        </flux:radio.group>
                        @if ($mode === 'schedule')
                            <flux:input type="datetime-local" wire:model="scheduledFor" size="sm" class="w-full sm:w-auto" />
                        @endif
                    </div>
                    @error('scheduledFor') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-2">
                    @if ($mode === 'now')
                        <flux:modal.trigger name="confirm-send-modal">
                            <flux:button variant="primary" icon="paper-airplane" class="w-full sm:w-auto justify-center">
                                {{ $editingScheduledId ? 'Update & Send Now' : 'Send to '.$this->parentCount.' Parent(s)' }}
                            </flux:button>
                        </flux:modal.trigger>
                    @else
                        <flux:button variant="primary" icon="paper-airplane" wire:click="send" wire:loading.attr="disabled" class="w-full sm:w-auto justify-center">
                            {{ $editingScheduledId ? 'Save Updated Schedule' : 'Schedule Notification' }}
                        </flux:button>
                    @endif

                    <div wire:loading wire:target="send" class="text-xs text-zinc-500 flex items-center gap-1.5">
                        <flux:icon icon="arrow-path" class="size-3.5 animate-spin" />
                        Processing delivery queue...
                    </div>
                </div>
            </div>

            {{-- Live preview --}}
            <div class="lg:col-span-2">
                <div class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-zinc-50/70 dark:bg-zinc-900/50 p-4 sm:p-5 sticky top-4 shadow-xs space-y-3">
                    <div class="flex items-center justify-between border-b border-zinc-200/60 dark:border-zinc-800 pb-2.5">
                        <p class="text-xs font-bold text-zinc-500 uppercase tracking-wider flex items-center gap-1.5">
                            <flux:icon icon="eye" class="size-3.5 text-zinc-400" />
                            Live Parent Preview
                        </p>
                        <div class="flex items-center gap-1 text-[11px] text-zinc-400">
                            <span>Personalized</span>
                        </div>
                    </div>

                    {{-- Preview Message Bubble / Card --}}
                    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700/80 bg-white dark:bg-zinc-800/90 p-4 space-y-2.5 shadow-sm">
                        <div class="flex items-center gap-2 pb-1.5 border-b border-zinc-100 dark:border-zinc-700/50">
                            <div class="size-7 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 font-bold text-xs flex items-center justify-center">
                                P
                            </div>
                            <div class="text-xs leading-tight">
                                <p class="font-bold text-zinc-900 dark:text-white">Jane Doe <span class="text-[10px] font-normal text-zinc-400">(Parent)</span></p>
                                <p class="text-[10px] text-zinc-400">via {{ implode(' & ', array_map('ucfirst', $channels)) ?: 'None' }}</p>
                            </div>
                        </div>

                        <p class="text-sm font-bold text-zinc-900 dark:text-white break-words"
                           x-text="getPreview(localSubject) || '{{ addslashes($this->preview['subject']) ?: 'Subject / Title' }}'">
                            {{ $this->preview['subject'] ?: 'Subject / Title' }}
                        </p>

                        <p class="text-xs sm:text-sm text-zinc-700 dark:text-zinc-300 whitespace-pre-line leading-relaxed break-words"
                           x-text="getPreview(localBody) || '{{ addslashes($this->preview['body']) ?: 'Type your message on the left or click a 1-click starter to see the personalized preview here.' }}'">
                            {{ $this->preview['body'] ?: 'Type your message on the left or click variable chips to see the personalized preview here.' }}
                        </p>
                    </div>

                    <p class="text-[11px] text-zinc-400 dark:text-zinc-500 leading-normal">
                        Each parent sees their own name, current school details, and custom dates in place of placeholders.
                    </p>
                </div>
            </div>
        </div>
    @endif

    {{-- ========================== TEMPLATES ========================== --}}
    @if ($tab === 'templates')
        <div class="space-y-6">
            {{-- Pre-made Standard System Templates --}}
            <div class="space-y-3">
                <div>
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200/60">
                            Built-in & Ready to Use
                        </span>
                    </div>
                    <h3 class="text-base font-bold text-zinc-900 dark:text-white">Pre-made System Templates</h3>
                    <p class="text-xs text-zinc-500">Standard notices for Kenyan schools. Click "Use in Notice" to send immediately or "Customize" to save a school-specific copy.</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach (self::PREMADE_TEMPLATES as $key => $p)
                        <div class="p-3.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-800/80 shadow-xs flex flex-col justify-between gap-3 hover:border-indigo-300 dark:hover:border-indigo-700 transition">
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider bg-zinc-100 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-300">
                                        {{ $p['category'] }}
                                    </span>
                                    <span class="text-[10px] text-zinc-400 font-mono">
                                        {{ count($p['placeholders']) }} variables
                                    </span>
                                </div>
                                <h4 class="text-sm font-bold text-zinc-900 dark:text-white line-clamp-1">{{ $p['name'] }}</h4>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 line-clamp-2 leading-relaxed">{{ $p['body'] }}</p>
                            </div>

                            <div class="flex items-center gap-2 pt-2 border-t border-zinc-100 dark:border-zinc-700/60">
                                <flux:button size="xs" variant="primary" icon="paper-airplane" wire:click="usePremadeTemplate('{{ $key }}')" class="flex-1 justify-center">
                                    Use in Notice
                                </flux:button>
                                <flux:button size="xs" variant="subtle" icon="document-duplicate" wire:click="copyPremadeToCustom('{{ $key }}')" title="Customize a copy for your school">
                                    Customize
                                </flux:button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Custom School Templates --}}
            <div class="space-y-3 pt-2">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                        <h3 class="text-base font-bold text-zinc-900 dark:text-white">{{ currentInstitution()->name }} Custom Templates</h3>
                        <p class="text-xs text-zinc-500">Reusable templates created and managed specifically by your school.</p>
                    </div>
                    <flux:button variant="primary" icon="plus" wire:click="newTemplate" class="w-full sm:w-auto">New template</flux:button>
                </div>

                <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-left text-sm min-w-[550px]">
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
                                        <div class="flex flex-wrap gap-1">
                                            @forelse ($template->placeholders() as $pl)
                                                <span class="inline-block px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-[10px] text-zinc-600 dark:text-zinc-300">&#123;&#123;{{ $pl }}&#125;&#125;</span>
                                            @empty
                                                <span class="text-zinc-400">—</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 text-right whitespace-nowrap">
                                        <flux:button size="sm" variant="subtle" icon="pencil-square"
                                            wire:click="editTemplate({{ $template->id }})" title="Edit template" />
                                        <flux:modal.trigger name="confirm-delete-template">
                                            <flux:button size="sm" variant="subtle" icon="trash" class="text-rose-600"
                                                wire:click="$set('confirmingDeleteTemplateId', {{ $template->id }})" title="Delete template" />
                                        </flux:modal.trigger>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-8 text-center text-zinc-400">
                                        <flux:icon icon="document-text" class="size-8 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                                        <p class="font-medium">No custom templates created yet.</p>
                                        <p class="text-xs text-zinc-400 mt-0.5">Click "New template" or click "Customize" on any pre-made template above.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- =========================== HISTORY =========================== --}}
    @if ($tab === 'history')
        <div class="overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
            <table class="w-full text-left text-sm min-w-[620px]">
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
                                <span class="block text-xs text-zinc-400 truncate max-w-xs">{{ $item->subject }}</span>
                            </td>
                            <td class="py-3 px-4 text-xs uppercase text-zinc-500 font-semibold">{{ implode(' + ', $item->channels ?? []) }}</td>
                            <td class="py-3 px-4 text-xs text-zinc-500 whitespace-nowrap">
                                @if ($item->status === 'pending' && $item->scheduled_for)
                                    Scheduled {{ $item->scheduled_for->format('j M Y, H:i') }}
                                @elseif ($item->sent_at)
                                    Sent {{ $item->sent_at->format('j M Y, H:i') }}
                                @else
                                    {{ $item->created_at->diffForHumans() }}
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
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
                                    <span class="block text-xs text-zinc-400 mt-0.5">
                                        {{ $item->delivered_count }} delivered · {{ $item->failed_count }} failed
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button size="sm" variant="subtle" icon="eye"
                                        wire:click="$set('viewingId', {{ $item->id }})" title="View delivery report" />

                                    {{-- Use as Draft / Send Correction --}}
                                    <flux:button size="sm" variant="subtle" icon="arrow-path"
                                        wire:click="reuseNotification({{ $item->id }})" title="Use as Draft / Send Correction" />

                                    @if ($item->isCancellable())
                                        {{-- Edit Scheduled Notice --}}
                                        <flux:button size="sm" variant="subtle" icon="pencil-square"
                                            wire:click="editScheduled({{ $item->id }})" title="Edit scheduled notice" />

                                        {{-- Stop / Cancel Notice --}}
                                        <flux:modal.trigger name="confirm-cancel-notification">
                                            <flux:button size="sm" variant="subtle" icon="x-mark" class="text-rose-600"
                                                wire:click="$set('confirmingCancelId', {{ $item->id }})" title="Stop / Cancel notice" />
                                        </flux:modal.trigger>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-zinc-400">
                                <flux:icon icon="paper-airplane" class="size-8 mx-auto text-zinc-300 dark:text-zinc-600 mb-2" />
                                <p class="font-medium">Nothing sent yet.</p>
                                <p class="text-xs text-zinc-400 mt-0.5">Sent and scheduled notifications will appear here with delivery details.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endif

    {{-- ============================ MODALS ============================ --}}

    {{-- Pre-Send Broadcast Confirmation Modal --}}
    <flux:modal name="confirm-send-modal" class="max-w-md w-full">
        <div class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="size-10 rounded-full bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0">
                    <flux:icon icon="paper-airplane" class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">Confirm Parent Broadcast</flux:heading>
                    <flux:subheading>Please review delivery details before sending.</flux:subheading>
                </div>
            </div>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900/60 p-3.5 space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-zinc-500">Recipients:</span>
                    <strong class="text-zinc-900 dark:text-white">{{ $this->parentCount }} Parent(s)</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500">Channels:</span>
                    <span class="font-semibold text-zinc-900 dark:text-white uppercase">{{ implode(' & ', $channels) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-zinc-500">Subject:</span>
                    <span class="font-medium text-zinc-900 dark:text-white truncate max-w-[200px]">{{ $subject ?: '—' }}</span>
                </div>
            </div>

            <p class="text-xs text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 p-3 rounded-lg border border-amber-200/60 dark:border-amber-800/40 leading-relaxed">
                <flux:icon icon="information-circle" class="size-3.5 inline mr-1 text-amber-600" />
                Once dispatched, messages are immediately transmitted to mobile carrier and email networks. Delivery cannot be recalled from parents' mobile phones.
            </p>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
                <flux:modal.close><flux:button variant="filled" class="w-full sm:w-auto">Review Notice</flux:button></flux:modal.close>
                <flux:button variant="primary" icon="paper-airplane" wire:click="send" wire:loading.attr="disabled" class="w-full sm:w-auto">
                    Confirm & Send Now
                </flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Template Editor Modal --}}
    <flux:modal name="template-form" class="max-w-xl w-full">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">{{ $editingTemplateId ? 'Edit Notification Template' : 'New Notification Template' }}</flux:heading>
                <flux:subheading>Save reusable templates with variable placeholders for fast broadcasting.</flux:subheading>
            </div>

            <flux:input wire:model="tplName" label="Template name" placeholder="e.g. Term Opening Notice" />
            @error('tplName') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <flux:label>Subject / title</flux:label>
                    <div class="flex items-center gap-1 text-[11px] text-zinc-500">
                        <span class="text-[10px] text-zinc-400 hidden sm:inline">Insert:</span>
                        <button type="button" @click="insertTag('institution', 'tplSubject')" class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 hover:bg-indigo-50 hover:text-indigo-600 text-[10px] transition cursor-pointer">+ School</button>
                        <button type="button" @click="insertTag('opening_date', 'tplSubject')" class="px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 hover:bg-indigo-50 hover:text-indigo-600 text-[10px] transition cursor-pointer">+ Opening Date</button>
                    </div>
                </div>
                <flux:input id="tpl-subject" wire:model.live.debounce.250ms="tplSubject" placeholder="e.g. @{{institution}} - Term 1 Opening Dates" />
                @error('tplSubject') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <flux:label>Message template</flux:label>
                    <span class="text-xs text-zinc-500">Click a variable below to insert</span>
                </div>

                {{-- Template Modal Variable Chips --}}
                <div class="p-2.5 rounded-xl border border-zinc-200 dark:border-zinc-800 bg-zinc-50/50 dark:bg-zinc-900/40">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wide mr-1">
                            Insert:
                        </span>

                        <button type="button" @click="insertTag('parent', 'tplBody')" draggable="true" @dragstart="dragTag($event, 'parent')"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700 shadow-xs transition active:scale-95 cursor-pointer">
                            <flux:icon icon="user" class="size-3 text-zinc-500" />
                            <span>Parent Name</span>
                        </button>

                        <button type="button" @click="insertTag('institution', 'tplBody')" draggable="true" @dragstart="dragTag($event, 'institution')"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700 shadow-xs transition active:scale-95 cursor-pointer">
                            <flux:icon icon="building-library" class="size-3 text-zinc-500" />
                            <span>School Name</span>
                        </button>

                        <button type="button" @click="insertTag('date', 'tplBody')" draggable="true" @dragstart="dragTag($event, 'date')"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700 shadow-xs transition active:scale-95 cursor-pointer">
                            <flux:icon icon="calendar" class="size-3 text-zinc-500" />
                            <span>Date</span>
                        </button>

                        <button type="button" @click="insertTag('opening_date', 'tplBody')" draggable="true" @dragstart="dragTag($event, 'opening_date')"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700 shadow-xs transition active:scale-95 cursor-pointer">
                            <flux:icon icon="calendar" class="size-3 text-zinc-500" />
                            <span>Opening Date</span>
                        </button>

                        <button type="button" @click="insertTag('closing_date', 'tplBody')" draggable="true" @dragstart="dragTag($event, 'closing_date')"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700 shadow-xs transition active:scale-95 cursor-pointer">
                            <flux:icon icon="calendar" class="size-3 text-zinc-500" />
                            <span>Closing Date</span>
                        </button>

                        <button type="button" @click="insertTag('fee_balance', 'tplBody')" draggable="true" @dragstart="dragTag($event, 'fee_balance')"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-white dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-50 dark:hover:bg-zinc-700 shadow-xs transition active:scale-95 cursor-pointer">
                            <flux:icon icon="banknotes" class="size-3 text-zinc-500" />
                            <span>Fee Balance</span>
                        </button>

                        {{-- Custom tag inside modal --}}
                        <div x-data="{ open: false }" class="relative inline-block">
                            <button type="button" @click="open = !open" 
                                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border border-zinc-200 dark:border-zinc-700 hover:bg-zinc-200 dark:hover:bg-zinc-700 shadow-xs transition active:scale-95 cursor-pointer">
                                <flux:icon icon="plus" class="size-3 text-zinc-500" />
                                <span>Custom Tag</span>
                            </button>                            <div x-show="open" @click.outside="open = false" style="display: none;"
                                class="absolute right-0 mt-1.5 z-40 w-60 p-3 rounded-xl shadow-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 space-y-2">
                                <p class="text-xs font-bold text-zinc-800 dark:text-zinc-100">Custom Tag</p>
                                <input type="text" x-model="customTagName" @keydown.enter.prevent="insertCustom('tplBody'); open = false;"
                                    placeholder="e.g. reporting_time" class="w-full text-xs rounded-lg border border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-900 px-2.5 py-1.5 dark:text-white" />
                                <div class="flex justify-end gap-1.5">
                                    <button type="button" @click="open = false" class="px-2 py-1 text-xs text-zinc-500">Cancel</button>
                                    <button type="button" @click="insertCustom('tplBody'); open = false;" class="px-2.5 py-1 text-xs font-semibold rounded-md bg-indigo-600 text-white">Insert</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <flux:textarea id="tpl-body" wire:model.live.debounce.250ms="tplBody" rows="6"
                    placeholder="Dear @{{parent}}, @{{institution}} will reopen on @{{opening_date}}..." />
                @error('tplBody') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                {{-- SMS estimate inside modal --}}
                <div class="flex items-center justify-between text-xs text-zinc-400 dark:text-zinc-500 pt-0.5"
                     x-data="{ stats: getSmsStats($wire.tplBody) }" x-effect="stats = getSmsStats($wire.tplBody)">
                    <span class="inline-flex items-center gap-1.5">
                        <span x-text="stats.chars + ' chars'"></span>
                        <span class="opacity-50">·</span>
                        <span x-text="stats.credits + ' SMS ' + (stats.credits === 1 ? 'credit' : 'credits')"></span>
                    </span>
                    <span class="text-xs text-zinc-400">Max 2,000 chars</span>
                </div>
            </div>

            <div class="p-3 rounded-xl bg-zinc-50 dark:bg-zinc-900 border border-zinc-200 dark:border-zinc-800 text-xs text-zinc-700 dark:text-zinc-300 flex items-start gap-2.5">
                <flux:icon icon="information-circle" class="size-4 text-zinc-500 shrink-0 mt-0.5" />
                <div class="space-y-0.5">
                    <p class="font-semibold text-zinc-900 dark:text-white">How dynamic dates work in templates</p>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                        Tags like <code class="font-mono px-1 py-0.5 rounded bg-zinc-200/60 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">@{{opening_date}}</code> or <code class="font-mono px-1 py-0.5 rounded bg-zinc-200/60 dark:bg-zinc-800 text-zinc-800 dark:text-zinc-200">@{{closing_date}}</code> don't have fixed dates yet. When creating a notice, the system prompts you to pick the exact dates on a calendar before sending.
                    </p>
                </div>
            </div>

            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-3 border-t border-zinc-100 dark:border-zinc-800">
                <flux:modal.close><flux:button variant="filled" class="w-full sm:w-auto">Cancel</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="saveTemplate" class="w-full sm:w-auto">Save template</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Confirm Delete Template Modal --}}
    <flux:modal name="confirm-delete-template" class="max-w-md w-full" wire:close="$set('confirmingDeleteTemplateId', null)">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Delete this template?</flux:heading>
                <flux:subheading>Notifications already sent from it are not affected.</flux:subheading>
            </div>
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <flux:modal.close><flux:button variant="filled" class="w-full sm:w-auto">Cancel</flux:button></flux:modal.close>
                <flux:button variant="danger" icon="trash" wire:click="deleteTemplate" class="w-full sm:w-auto">Delete template</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Confirm Stop / Cancel Notification Modal --}}
    <flux:modal name="confirm-cancel-notification" class="max-w-md w-full" wire:close="$set('confirmingCancelId', null)">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">Stop / Cancel this notification?</flux:heading>
                <flux:subheading>If scheduled or currently in progress, remaining deliveries will be stopped immediately.</flux:subheading>
            </div>
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <flux:modal.close><flux:button variant="filled" class="w-full sm:w-auto">Keep It</flux:button></flux:modal.close>
                <flux:button variant="danger" icon="x-mark" wire:click="cancel" class="w-full sm:w-auto">Stop Delivery</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Delivery Details Modal --}}
    <flux:modal :open="$viewingId !== null" wire:close="$set('viewingId', null)" class="max-w-2xl w-full">
        @if ($this->viewing)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">{{ $this->viewing->name }}</flux:heading>
                    <flux:subheading>{{ $this->viewing->subject }}</flux:subheading>
                </div>
                <p class="text-sm text-zinc-600 dark:text-zinc-300 whitespace-pre-line rounded-xl bg-zinc-50 dark:bg-zinc-900/50 p-4 border border-zinc-200/60 dark:border-zinc-800">{{ $this->viewing->body }}</p>

                <div class="text-xs text-zinc-500 font-medium">
                    {{ $this->viewing->recipients_count }} recipient attempt(s) —
                    <span class="text-emerald-600 font-semibold">{{ $this->viewing->delivered_count }} delivered</span>,
                    <span class="text-rose-600 font-semibold">{{ $this->viewing->failed_count }} failed</span>.
                </div>

                <div class="max-h-72 overflow-y-auto overflow-x-auto rounded-xl border border-zinc-200 dark:border-zinc-800">
                    <table class="w-full text-left text-xs min-w-[480px]">
                        <thead class="bg-zinc-50 dark:bg-zinc-800/50 text-zinc-500 uppercase tracking-wider">
                            <tr>
                                <th class="py-2.5 px-3">Parent</th>
                                <th class="py-2.5 px-3">Channel</th>
                                <th class="py-2.5 px-3">Destination</th>
                                <th class="py-2.5 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            @foreach ($this->viewing->recipients as $r)
                                <tr wire:key="rcpt-{{ $r->id }}">
                                    <td class="py-2 px-3 font-medium text-zinc-900 dark:text-white">{{ $r->user?->name ?? '—' }}</td>
                                    <td class="py-2 px-3 uppercase text-xs font-semibold text-zinc-500">{{ $r->channel }}</td>
                                    <td class="py-2 px-3 text-zinc-500 font-mono text-xs">{{ $r->destination ?? '—' }}</td>
                                    <td class="py-2 px-3">
                                        <flux:badge size="sm" :color="$r->status === 'sent' ? 'emerald' : ($r->status === 'failed' ? 'red' : 'zinc')">
                                            {{ ucfirst($r->status) }}
                                        </flux:badge>
                                        @if ($r->error)
                                            <span class="block text-xs text-red-500 mt-0.5">{{ $r->error }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex justify-end pt-2">
                    <flux:modal.close><flux:button variant="filled" class="w-full sm:w-auto">Close</flux:button></flux:modal.close>
                </div>
            </div>
        @endif
    </flux:modal>
</div>
