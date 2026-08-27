<?php

namespace Modules\ReportCard\Services;

use App\Services\SmsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\ReportCard\Mail\ReportCardMail;
use Modules\ReportCard\Models\ReportCard;
use Throwable;

/**
 * Getting one report card to a parent: render it, mint a fresh one-time
 * link, and push that link down both channels the school has.
 *
 * Shared by the nightly command and the send button on the report cards
 * screen, so a manual send is the same send - same PDF, same link, same
 * wording - and not a second implementation that drifts from it.
 *
 * What it deliberately does *not* do is re-check that the results behind
 * the report are complete. The command does that itself before calling
 * here, because an unattended send should hold back a half-marked report;
 * someone pressing "send" on screen has already decided.
 */
class ReportCardSender
{
    public function __construct(
        private ReportCardPdfService $pdfService,
        private SmsService $smsService,
    ) {}

    /**
     * @return array{sent: bool, email: bool, sms: bool, reason: string|null}
     */
    public function send(ReportCard $reportCard): array
    {
        $reportCard->loadMissing(['student', 'institution']);

        if (! $reportCard->student) {
            return ['sent' => false, 'email' => false, 'sms' => false, 'reason' => 'This report card has no student attached.'];
        }

        // Resolved through the parent's own User/ParentDetails records -
        // student_details has no parent_email/parent_phone columns of its
        // own, despite the model listing them as fillable.
        $parent = $reportCard->student->studentParent;

        $email = featureEnabled('email_notifications') ? $parent?->email : null;
        $phone = featureEnabled('sms') ? $parent?->parent?->parent_phone : null;

        if (! $email && ! $phone) {
            return [
                'sent' => false,
                'email' => false,
                'sms' => false,
                'reason' => 'No parent email or phone number to send to. Check the learner\'s parent details, and that email and SMS are switched on.',
            ];
        }

        $this->pdfService->generate($reportCard);

        // A fresh token every send, which also retires the link from any
        // earlier delivery rather than leaving two live links in the wild.
        $downloadUrl = route('reportcard.download', $reportCard->issueDownloadToken());

        $emailSent = $email ? $this->email($reportCard, $email, $downloadUrl) : false;
        $smsSent = $phone ? $this->sms($reportCard, $phone, $downloadUrl) : false;

        // Still marked sent when both channels error: the PDF is rendered
        // and the link is live, and the failures are in the log. Leaving it
        // "ready" would have the nightly run try the same broken mailer
        // again every night.
        $reportCard->update(['status' => 'sent', 'sent_at' => now()]);

        return [
            'sent' => true,
            'email' => $emailSent,
            'sms' => $smsSent,
            'reason' => ($emailSent || $smsSent) ? null : 'Neither the email nor the SMS got through - see the logs.',
        ];
    }

    private function email(ReportCard $reportCard, string $address, string $downloadUrl): bool
    {
        // Caught rather than allowed to escape: the SMS is what reaches a
        // parent whose inbox doesn't, so a bounced or misconfigured mailer
        // must not take it down with it.
        try {
            Mail::to($address)->send(new ReportCardMail(
                $reportCard,
                $reportCard->student,
                $reportCard->institution,
                $downloadUrl,
            ));

            return true;
        } catch (Throwable $exception) {
            Log::warning('Report card email failed', [
                'report_card_id' => $reportCard->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function sms(ReportCard $reportCard, string $phone, string $downloadUrl): bool
    {
        try {
            $sms = $this->smsService->send(
                (int) preg_replace('/\D/', '', $phone),
                $this->smsMessage($reportCard, $downloadUrl),
            );
        } catch (Throwable $exception) {
            Log::warning('Report card SMS failed', [
                'report_card_id' => $reportCard->id,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! ($sms['success'] ?? false)) {
            Log::warning('Report card SMS failed', [
                'report_card_id' => $reportCard->id,
                'error' => $sms['error'] ?? $sms['reason'] ?? null,
            ]);

            return false;
        }

        return true;
    }

    /**
     * The text a parent gets on their phone.
     *
     * It carries the same one-time link as the email, because for a good
     * many parents the phone is the only one of the two that reaches them -
     * so this has to stand on its own rather than read as a nudge to go and
     * check an inbox.
     *
     * Written warmly but kept short: a first name rather than the full one,
     * and no praise of the results themselves, which this code has no
     * business judging. The link alone runs to about 60 characters, so
     * every word before it is one the school pays for.
     */
    public function smsMessage(ReportCard $reportCard, string $downloadUrl): string
    {
        $firstName = strtok(trim((string) $reportCard->student->name), ' ') ?: $reportCard->student->name;
        $school = $reportCard->institution?->name;

        $opening = $school
            ? "Dear Parent, {$firstName}'s {$reportCard->term} report card from {$school} is ready."
            : "Dear Parent, {$firstName}'s {$reportCard->term} report card is ready.";

        return $opening
            .' Thank you for walking this journey with us.'
            ." Download it here (the link opens once): {$downloadUrl}";
    }
}
