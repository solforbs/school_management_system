<?php

use App\Services\Chatbot\StudentDataService;
use App\Services\Chatbot\VerificationService;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Modules\Student\Models\StudentDetails;

new class extends Component
{
    public bool $open = false;

    // Everything below that drives verification is #[Locked]: Livewire public
    // properties are otherwise writable from the browser via $wire.$set(),
    // which would let anyone mark themselves verified and skip the email code.

    /** menu | awaiting_admission_number | sending_code | code_sent */
    #[Locked]
    public string $stage = 'menu';

    public string $input = '';

    public string $code = '';

    #[Locked]
    public ?string $activeCommand = null;

    #[Locked]
    public ?string $pendingAdmissionNumber = null;

    /** Masked destination shown while a code is on its way; never the real address. */
    #[Locked]
    public ?string $pendingEmailMasked = null;

    #[Locked]
    public ?string $verifiedAdmissionNumber = null;

    #[Locked]
    public ?string $verifiedUntil = null;

    /**
     * Chat transcript. Bot and user entries carry `lines`; a `progress`
     * entry carries `steps`, each `['label' => string, 'state' => 'done'|'active'|'error']`,
     * and is rendered as a checklist that updates in place as the
     * verification code is looked up and sent.
     *
     * @var array<int, array{role: string, lines?: array<int, string>, steps?: array<int, array{label: string, state: string}>}>
     */
    #[Locked]
    public array $messages = [];

    public function mount(): void
    {
        $this->pushBot(["Hi! I'm the school assistant. Type / to see what I can help with."]);
    }

    public function toggle(): void
    {
        $this->open = ! $this->open;
    }

    public function send(): void
    {
        $text = trim($this->input);
        $this->input = '';

        if ($text === '') {
            return;
        }

        if ($this->stage === 'awaiting_admission_number') {
            $this->pushUser($text);
            $this->handleAdmissionNumber($text);

            return;
        }

        if ($text === '/') {
            $this->pushUser($text);
            $this->showMenu();

            return;
        }

        if (str_starts_with($text, '/')) {
            $this->runCommand(substr($text, 1), announce: true);

            return;
        }

        $this->pushUser($text);
        $this->pushBot(["I didn't quite catch that — type / to see what I can help with."]);
    }

    public function selectCommand(string $command): void
    {
        $this->runCommand($command, announce: true);
    }

    public function submitCode(): void
    {
        $code = trim($this->code);
        $this->code = '';

        if ($code === '' || ! $this->pendingAdmissionNumber) {
            return;
        }

        $this->pushUser(str_repeat('•', strlen($code)));

        $result = app(VerificationService::class)->verify($this->pendingAdmissionNumber, $code);

        if ($result === 'invalid') {
            $this->pushBot(["That code doesn't look right. Please try again."]);

            return;
        }

        if ($result === 'expired') {
            $this->pushBot(['That code has expired. Type / to request a new one.']);
            $this->showMenu();

            return;
        }

        if ($result === 'too_many_attempts') {
            $this->pushBot(['Too many incorrect attempts. Type / to request a new code.']);
            $this->showMenu();

            return;
        }

        if ($result === 'no_pending_code') {
            $this->pushBot(["I don't have a pending code for that. Type / to start again."]);
            $this->showMenu();

            return;
        }

        $this->verifiedAdmissionNumber = $this->pendingAdmissionNumber;
        $this->verifiedUntil = now()->addMinutes(15)->toIso8601String();
        $this->pendingAdmissionNumber = null;
        $this->pendingEmailMasked = null;

        $this->answerVerifiedCommand();
    }

    public function startOver(): void
    {
        $this->stage = 'menu';
        $this->pendingAdmissionNumber = null;
        $this->pendingEmailMasked = null;
        $this->activeCommand = null;
        $this->code = '';
        $this->pushBot(['No problem — type / to see what I can help with.']);
    }

    private function runCommand(string $command, bool $announce): void
    {
        $command = strtolower(trim($command, '/'));

        if ($announce) {
            $this->pushUser('/'.$command);
        }

        $definition = config("chatbot.commands.{$command}");

        if (! $definition) {
            $this->pushBot(["I don't recognize that command. Type / to see the list."]);

            return;
        }

        if (! $definition['verified']) {
            $answer = $definition['answer'];
            $this->pushBot(is_array($answer) ? $answer : [$answer]);

            return;
        }

        $this->activeCommand = $command;

        if ($this->hasActiveVerification()) {
            $this->answerVerifiedCommand();

            return;
        }

        $this->stage = 'awaiting_admission_number';
        $this->pushBot(["Sure — what's your child's admission number?"]);
    }

    /**
     * First half of verification: confirm the admission number and that a
     * parent email is on file, then hand off to deliverCode() in a second
     * request so the parent watches the code being sent step by step.
     */
    private function handleAdmissionNumber(string $admissionNumber): void
    {
        $ip = request()->ip() ?? 'unknown';
        $service = app(VerificationService::class);

        if ($service->isLookupRateLimited($ip)) {
            $this->pushBot(["You've made too many attempts. Please wait a bit and try again."]);
            $this->showMenu();

            return;
        }

        $this->pushProgress([
            ['label' => 'Checking admission number', 'state' => 'active'],
        ]);

        $student = $service->findStudent($admissionNumber, $ip);

        if (! $student) {
            $this->updateProgress(0, 'error', 'No student found with that admission number');
            $this->pushBot(["I couldn't find a student with that admission number. Please double-check and try again, or type / to start over."]);

            return;
        }

        $this->updateProgress(0, 'done', 'Student record found');

        $email = $service->resolveEmail($student);

        if (! $email) {
            $this->addProgressStep('No parent email on file', 'error');
            $this->pushBot(['We don\'t have a verified email on file for this student. Please contact the school office directly.']);
            $this->showMenu();

            return;
        }

        $this->pendingAdmissionNumber = $student->admission_number;
        $this->pendingEmailMasked = $service->maskEmail($email);
        $this->stage = 'sending_code';
        $this->addProgressStep("Sending a 6-digit code to {$this->pendingEmailMasked}", 'active');

        $this->queueDelivery();
    }

    /**
     * Second half: actually email the code. Called from the browser right
     * after the lookup response renders, so the "sending" step is visible.
     */
    public function deliverCode(): void
    {
        if ($this->stage !== 'sending_code' || ! $this->pendingAdmissionNumber) {
            return;
        }

        $service = app(VerificationService::class);
        $student = StudentDetails::where('admission_number', $this->pendingAdmissionNumber)->first();
        $email = $student ? $service->resolveEmail($student) : null;

        if (! $student || ! $email) {
            $this->failProgress('Could not send the code');
            $this->pushBot(['Something went wrong sending your code — please start again.']);
            $this->startOver();

            return;
        }

        $result = $service->sendCode($student, $email, (string) $this->activeCommand);

        if ($result === 'rate_limited') {
            $this->failProgress('Too many codes requested for this student');
            $this->pushBot(['Too many codes have been requested for this student recently. Please try again later.']);
            $this->startOver();

            return;
        }

        $this->completeProgress();
        $this->addProgressStep('Code sent — check your inbox (and spam folder)', 'done');
        $this->stage = 'code_sent';
        $this->pushBot(["We've sent a 6-digit code to {$this->pendingEmailMasked}. Enter it below to continue."]);
    }

    public function resendCode(): void
    {
        if ($this->stage !== 'code_sent' || ! $this->pendingAdmissionNumber) {
            return;
        }

        $this->code = '';
        $this->stage = 'sending_code';
        $this->pushUser('Resend the code');
        $this->pushProgress([
            ['label' => "Sending a new code to {$this->pendingEmailMasked}", 'state' => 'active'],
        ]);

        $this->queueDelivery();
    }

    /**
     * Ask the browser to call deliverCode() once this response has
     * rendered. The short delay lets the "sending" step register before
     * it flips to done, which otherwise happens too fast to notice.
     */
    private function queueDelivery(): void
    {
        $this->js('setTimeout(() => $wire.deliverCode(), 700)');
    }

    private function answerVerifiedCommand(): void
    {
        $student = StudentDetails::where('admission_number', $this->verifiedAdmissionNumber)->first();

        if (! $student || ! $this->activeCommand) {
            $this->pushBot(['Something went wrong finding that student — please start again.']);
            $this->showMenu();

            return;
        }

        $lines = app(StudentDataService::class)->answer($this->activeCommand, $student);
        $this->pushBot(array_merge(["You're verified! Here's what you asked for:"], $lines));
        $this->stage = 'menu';
    }

    private function hasActiveVerification(): bool
    {
        return $this->verifiedAdmissionNumber !== null
            && $this->verifiedUntil !== null
            && now()->lessThan($this->verifiedUntil);
    }

    private function showMenu(): void
    {
        $this->stage = 'menu';
        $this->pushBot(["Here's what I can help with:"]);
    }

    private function pushUser(string $text): void
    {
        $this->messages[] = ['role' => 'user', 'lines' => [$text]];
    }

    /**
     * @param  array<int, string>  $lines
     */
    private function pushBot(array $lines): void
    {
        $this->messages[] = ['role' => 'bot', 'lines' => $lines];
    }

    /**
     * @param  array<int, array{label: string, state: string}>  $steps
     */
    private function pushProgress(array $steps): void
    {
        $this->messages[] = ['role' => 'progress', 'steps' => $steps];
    }

    private function addProgressStep(string $label, string $state): void
    {
        $index = $this->latestProgressIndex();

        if ($index !== null) {
            $this->messages[$index]['steps'][] = ['label' => $label, 'state' => $state];
        }
    }

    private function updateProgress(int $step, string $state, ?string $label = null): void
    {
        $index = $this->latestProgressIndex();

        if ($index === null || ! isset($this->messages[$index]['steps'][$step])) {
            return;
        }

        $this->messages[$index]['steps'][$step]['state'] = $state;

        if ($label !== null) {
            $this->messages[$index]['steps'][$step]['label'] = $label;
        }
    }

    /** Mark every still-active step of the latest checklist as done. */
    private function completeProgress(): void
    {
        $this->setActiveSteps('done');
    }

    /** Mark the still-active step of the latest checklist as failed, with a new label. */
    private function failProgress(string $label): void
    {
        $this->setActiveSteps('error', $label);
    }

    private function setActiveSteps(string $state, ?string $label = null): void
    {
        $index = $this->latestProgressIndex();

        if ($index === null) {
            return;
        }

        foreach ($this->messages[$index]['steps'] as $i => $step) {
            if ($step['state'] === 'active') {
                $this->updateProgress($i, $state, $label);
            }
        }
    }

    private function latestProgressIndex(): ?int
    {
        for ($i = count($this->messages) - 1; $i >= 0; $i--) {
            if ($this->messages[$i]['role'] === 'progress') {
                return $i;
            }
        }

        return null;
    }
};
?>
<div class="fixed bottom-6 right-6 z-50">
    @unless ($open)
        <span class="pointer-events-none absolute inset-0 animate-ping rounded-full bg-indigo-500/40"></span>
        <button wire:click="toggle" type="button"
            class="relative flex size-14 items-center justify-center rounded-full bg-zinc-900 text-white shadow-lg transition hover:scale-105 hover:bg-zinc-700 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
            aria-label="Open chat assistant">
            <flux:icon icon="chat-bubble-left-right" class="size-6" />
        </button>
    @else
        {{--
            `pending` holds the message the user just sent so it shows up in the
            transcript immediately, before the server has echoed it back. The
            typing dots below the transcript are driven purely by wire:loading.
        --}}
        <div x-data="{
                pending: null,
                sendMessage() {
                    const text = ($wire.input ?? '').trim();
                    if (! text) return;
                    this.pending = text;
                    $wire.send().finally(() => this.pending = null);
                },
            }"
            class="flex h-[32rem] w-80 flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-2xl sm:w-96 dark:border-white/10 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3 dark:border-white/10">
                <div class="flex items-center gap-2.5">
                    <span class="relative flex size-8 items-center justify-center rounded-full bg-gradient-to-br from-[#00A3FF] to-[#0047FF] text-white">
                        <flux:icon icon="sparkles" class="size-4" />
                        <span class="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full border-2 border-white bg-emerald-500 dark:border-zinc-900"></span>
                    </span>
                    <span class="leading-tight">
                        <span class="block text-sm font-semibold text-zinc-900 dark:text-white">
                            {{ config('app.name') }} Assistant
                        </span>
                        <span class="block text-[11px] text-zinc-400">
                            <span wire:loading.remove>Online</span>
                            <span wire:loading class="text-[#0047FF] dark:text-[#00A3FF]">Typing…</span>
                        </span>
                    </span>
                </div>
                <button wire:click="toggle" type="button"
                    class="text-zinc-400 transition hover:text-zinc-600 dark:hover:text-zinc-200" aria-label="Close">
                    <flux:icon icon="x-mark" class="size-5" />
                </button>
            </div>

            {{-- Anything that changes in here (new bubbles, the typing dots toggling) keeps the view pinned to the bottom. --}}
            <div class="flex-1 space-y-3 overflow-y-auto px-4 py-3" x-data="{
                    stick() { this.$nextTick(() => this.$el.scrollTop = this.$el.scrollHeight) }
                }"
                x-init="stick(); new MutationObserver(() => stick()).observe($el, { childList: true, subtree: true, attributes: true, attributeFilter: ['style', 'class'] })">
                @foreach ($messages as $message)
                    @if ($message['role'] === 'progress')
                        <div class="flex justify-start" wire:key="msg-{{ $loop->index }}">
                            <div class="w-[85%] rounded-2xl border border-zinc-200/80 bg-zinc-50 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5">
                                <div class="mb-1.5 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-zinc-400">
                                    <flux:icon icon="shield-check" class="size-3.5" />
                                    Parent verification
                                </div>
                                <ol class="space-y-1.5">
                                    @foreach ($message['steps'] as $step)
                                        <li class="flex items-start gap-2 leading-snug" wire:key="msg-{{ $loop->parent->index }}-step-{{ $loop->index }}">
                                            @if ($step['state'] === 'done')
                                                <span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
                                                    <flux:icon icon="check" variant="micro" class="size-3" />
                                                </span>
                                                <span class="text-zinc-700 dark:text-zinc-200">{{ $step['label'] }}</span>
                                            @elseif ($step['state'] === 'error')
                                                <span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full bg-red-500 text-white">
                                                    <flux:icon icon="x-mark" variant="micro" class="size-3" />
                                                </span>
                                                <span class="text-red-600 dark:text-red-400">{{ $step['label'] }}</span>
                                            @else
                                                <span class="mt-0.5 size-4 shrink-0 animate-spin rounded-full border-2 border-[#00A3FF]/30 border-t-[#0047FF]"></span>
                                                <span class="text-zinc-700 dark:text-zinc-200">
                                                    {{ $step['label'] }}<span class="chat-ellipsis"></span>
                                                </span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        </div>
                    @else
                        <div class="flex {{ $message['role'] === 'user' ? 'justify-end' : 'justify-start' }}" wire:key="msg-{{ $loop->index }}">
                            <div
                                class="max-w-[85%] rounded-2xl px-3 py-2 text-sm leading-relaxed {{ $message['role'] === 'user' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-800 dark:bg-white/10 dark:text-zinc-100' }}">
                                @foreach ($message['lines'] as $line)
                                    <p @if (! $loop->first) class="mt-1" @endif>{{ $line }}</p>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach

                {{-- The message just sent, shown until the server echoes it back --}}
                <template x-if="pending">
                    <div class="flex justify-end">
                        <div class="max-w-[85%] rounded-2xl bg-zinc-900 px-3 py-2 text-sm leading-relaxed text-white dark:bg-white dark:text-zinc-900"
                            x-text="pending"></div>
                    </div>
                </template>

                {{-- Typing indicator --}}
                <div wire:loading.flex class="justify-start" aria-live="polite">
                    <div class="flex items-center gap-1 rounded-2xl bg-zinc-100 px-3.5 py-2.5 dark:bg-white/10" role="status" aria-label="Assistant is typing">
                        <span class="chat-dot"></span>
                        <span class="chat-dot [animation-delay:150ms]"></span>
                        <span class="chat-dot [animation-delay:300ms]"></span>
                    </div>
                </div>
            </div>

            @if ($stage === 'sending_code')
                <div class="flex items-center justify-center gap-2 border-t border-zinc-100 px-4 py-4 text-sm text-zinc-500 dark:border-white/10 dark:text-zinc-400">
                    <flux:icon icon="envelope" class="size-4 animate-pulse text-[#0047FF] dark:text-[#00A3FF]" />
                    Sending your verification code<span class="chat-ellipsis"></span>
                </div>
            @elseif ($stage === 'code_sent')
                <form wire:submit="submitCode"
                    class="border-t border-zinc-100 px-4 py-3 dark:border-white/10">
                    <flux:otp wire:model="code" length="6" name="code" label="Enter the code we emailed you"
                        label:sr-only class="mx-auto" />
                    <flux:button type="submit" variant="primary" class="mt-3 w-full"
                        wire:loading.attr="disabled" wire:target="submitCode">
                        <span wire:loading.remove wire:target="submitCode">Verify</span>
                        <span wire:loading wire:target="submitCode">Checking code…</span>
                    </flux:button>
                    <div class="mt-2 flex items-center justify-center gap-3 text-xs text-zinc-400">
                        <button type="button" wire:click="resendCode" wire:loading.attr="disabled"
                            class="hover:text-zinc-600 disabled:opacity-50 dark:hover:text-zinc-300">
                            Didn't get it? Resend code
                        </button>
                        <span aria-hidden="true">·</span>
                        <button type="button" wire:click="startOver" wire:loading.attr="disabled"
                            class="hover:text-zinc-600 disabled:opacity-50 dark:hover:text-zinc-300">
                            Start over
                        </button>
                    </div>
                </form>
            @else
                @if ($stage === 'menu')
                    <div class="flex flex-wrap gap-1.5 border-t border-zinc-100 px-3 py-2 dark:border-white/10">
                        @foreach (config('chatbot.commands') as $key => $definition)
                            <button wire:click="selectCommand('{{ $key }}')" type="button"
                                title="{{ $definition['description'] }}"
                                wire:loading.attr="disabled"
                                class="rounded-full border border-zinc-200 px-2.5 py-1 text-xs text-zinc-600 transition hover:bg-zinc-50 disabled:opacity-50 dark:border-white/10 dark:text-zinc-300 dark:hover:bg-white/5">
                                /{{ $key }}
                            </button>
                        @endforeach
                    </div>
                @endif

                {{-- Echo the message locally first, then let the server handle it; `send` clears the input. --}}
                <form x-on:submit.prevent="sendMessage()"
                    class="flex items-center gap-2 border-t border-zinc-100 px-3 py-2 dark:border-white/10">
                    <input type="text" wire:model="input" autocomplete="off"
                        wire:loading.attr="disabled" wire:target="send"
                        placeholder="{{ $stage === 'awaiting_admission_number' ? 'Enter admission number…' : 'Type / for commands…' }}"
                        class="flex-1 rounded-full border border-zinc-200 bg-transparent px-3 py-1.5 text-sm text-zinc-900 outline-none focus:border-zinc-400 disabled:opacity-60 dark:border-white/10 dark:text-white dark:focus:border-white/30" />
                    <button type="submit" wire:loading.attr="disabled" wire:target="send"
                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-zinc-900 text-white transition hover:bg-zinc-700 disabled:opacity-60 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200"
                        aria-label="Send">
                        <flux:icon icon="paper-airplane" class="size-4" wire:loading.remove wire:target="send" />
                        <span class="size-3.5 animate-spin rounded-full border-2 border-white/30 border-t-white dark:border-zinc-900/30 dark:border-t-zinc-900" wire:loading wire:target="send"></span>
                    </button>
                </form>
            @endif
        </div>
    @endunless
</div>
