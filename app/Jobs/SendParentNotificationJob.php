<?php

namespace App\Jobs;

use App\Mail\ParentNotificationMail;
use App\Models\ParentNotification;
use App\Models\ParentNotificationRecipient;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Modules\Parent\Models\ParentDetails;
use Modules\Student\Models\StudentDetails;
use Throwable;

/**
 * Fans one Director's notice out to every parent of the school.
 *
 * Channel choice is the Director's, made per send. SMS to a parent with no
 * phone number on file falls back to e-mail for that parent, so "SMS only"
 * still reaches someone whose number is missing. Each delivery is its own
 * try/catch - a bad address or a full SMS gateway never stops the rest.
 */
class SendParentNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(private readonly int $notificationId) {}

    public function handle(SmsService $smsService): void
    {
        $notification = ParentNotification::with('institution')->find($this->notificationId);

        if (! $notification || ! in_array($notification->status, ['pending', 'sending'], true)) {
            return;
        }

        $notification->update(['status' => 'sending']);

        $institution = $notification->institution;
        $institutionName = $institution->name ?? 'your school';

        $wantEmail = in_array('email', $notification->channels, true);
        $wantSms = in_array('sms', $notification->channels, true);
        $values = $notification->placeholder_values ?? [];

        $delivered = 0;
        $failed = 0;
        $attempts = 0;

        // Distinct parents - a parent with several children at the school is
        // one recipient, not one per child.
        $parents = $institution
            ? $institution->parents()->select('users.id', 'users.name', 'users.email')->distinct()->get()
            : User::query()->whereRaw('1 = 0')->get();

        foreach ($parents as $parent) {
            $context = ['institution' => $institutionName, 'parent' => $parent->name];
            $subject = ParentNotification::render($notification->subject, $context, $values);
            $body = ParentNotification::render($notification->body, $context, $values);

            $phone = $this->phoneForParent((int) $parent->id);

            $doSms = $wantSms && $phone !== null;
            // "SMS only" but no number on file -> reach them by e-mail instead.
            $doEmail = $wantEmail || ($wantSms && $phone === null);

            if ($doSms) {
                $attempts++;
                $this->record($notification->id, (int) $parent->id, 'sms', $phone, function () use ($smsService, $phone, $body) {
                    return $this->sms($smsService, $phone, $body);
                }, $delivered, $failed);
            }

            if ($doEmail) {
                $attempts++;
                $address = $parent->email;
                $this->record($notification->id, (int) $parent->id, 'email', $address, function () use ($address, $subject, $body, $institutionName) {
                    return $this->email($address, $subject, $body, $institutionName);
                }, $delivered, $failed);
            }
        }

        $notification->update([
            'status' => $delivered > 0 ? 'sent' : 'failed',
            'recipients_count' => $attempts,
            'delivered_count' => $delivered,
            'failed_count' => $failed,
            'sent_at' => now(),
        ]);
    }

    /**
     * Run one delivery, write its recipient row, and tally the result.
     *
     * @param  callable():array{ok: bool, error: ?string}  $send
     */
    private function record(int $notificationId, int $userId, string $channel, ?string $destination, callable $send, int &$delivered, int &$failed): void
    {
        $result = $send();

        ParentNotificationRecipient::create([
            'parent_notification_id' => $notificationId,
            'user_id' => $userId,
            'channel' => $channel,
            'destination' => $destination,
            'status' => $result['ok'] ? 'sent' : 'failed',
            'error' => $result['error'],
            'sent_at' => $result['ok'] ? now() : null,
        ]);

        $result['ok'] ? $delivered++ : $failed++;
    }

    private function phoneForParent(int $parentId): ?string
    {
        $phone = ParentDetails::where('parent_id', $parentId)->value('parent_phone');

        if (! $phone) {
            $phone = StudentDetails::where('parent_id', $parentId)
                ->where(fn ($q) => $q->whereNotNull('parent_phone')->orWhereNotNull('guardian_phone'))
                ->value('parent_phone');
        }

        if (! $phone) {
            $phone = StudentDetails::where('parent_id', $parentId)
                ->whereNotNull('guardian_phone')
                ->value('guardian_phone');
        }

        $phone = $phone ? trim((string) $phone) : null;

        return $phone !== '' ? $phone : null;
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    private function email(?string $address, string $subject, string $body, string $institutionName): array
    {
        if (! $address) {
            return ['ok' => false, 'error' => 'No e-mail address on file.'];
        }

        try {
            Mail::to($address)->send(new ParentNotificationMail($subject, $body, $institutionName));

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            Log::warning('Parent notification e-mail failed', ['to' => $address, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => Str::limit($e->getMessage(), 240)];
        }
    }

    /**
     * @return array{ok: bool, error: ?string}
     */
    private function sms(SmsService $smsService, string $phone, string $body): array
    {
        try {
            $result = $smsService->send((int) preg_replace('/\D/', '', $phone), $body);
        } catch (Throwable $e) {
            Log::warning('Parent notification SMS failed', ['to' => $phone, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => Str::limit($e->getMessage(), 240)];
        }

        if (! ($result['success'] ?? false)) {
            return ['ok' => false, 'error' => Str::limit((string) ($result['error'] ?? $result['reason'] ?? 'SMS gateway rejected the message.'), 240)];
        }

        return ['ok' => true, 'error' => null];
    }
}
