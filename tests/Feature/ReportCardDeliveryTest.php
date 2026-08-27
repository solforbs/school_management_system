<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Mail\ReportCardMail;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\ReportCardCompletionService;
use Modules\ReportCard\Services\ReportCardPdfService;
use Modules\Student\Models\StudentDetails;

/**
 * @param  array{email?: string|null, phone?: string|null}  $contact
 */
function makeReadyReportCard(array $contact = []): ReportCard
{
    $contact = array_merge(['email' => 'parent@example.com', 'phone' => '0712345678'], $contact);

    $owner = User::factory()->create();
    $institution = Institution::create([
        'user_id' => $owner->id,
        'name' => 'Test Institution '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1 '.uniqid()]);

    $parent = User::factory()->create(['email' => $contact['email']]);
    // student_details.parent_id is foreign-keyed to parent_details.id rather
    // than users.id - see the standing bug; lining the ids up sidesteps it
    // without depending on that fix.
    DB::table('parent_details')->insert([
        'id' => $parent->id,
        'parent_id' => $parent->id,
        'parent_phone' => $contact['phone'],
    ]);

    $student = User::factory()->create(['name' => 'Jane Wanjiru']);
    StudentDetails::create([
        'student_id' => $student->id,
        'admission_number' => 'ADM-'.uniqid(),
        'parent_id' => $parent->id,
        'institution_id' => $institution->id,
    ]);

    return ReportCard::create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'class_id' => $class->id,
        'term' => 'Second Term',
        'academic_year' => 2026,
        'status' => 'ready',
        'completed_at' => now()->subDays(2),
    ]);
}

/**
 * The real services need a full results/examinations tree and DomPDF; both
 * are exercised elsewhere and irrelevant to how delivery is wired up.
 */
function stubReportCardServices(): void
{
    test()->mock(ReportCardCompletionService::class)
        ->shouldReceive('isStillComplete')->andReturn(true);

    test()->mock(ReportCardPdfService::class)
        ->shouldReceive('generate')->andReturn('report-cards/stub.pdf');
}

test('a ready report card is sent to the parent by both email and sms with a one-time link', function () {
    Mail::fake();
    stubReportCardServices();

    $reportCard = makeReadyReportCard();

    $sms = null;
    $this->mock(SmsService::class)
        ->shouldReceive('send')
        ->once()
        ->andReturnUsing(function (int $mobile, string $message) use (&$sms) {
            $sms = ['mobile' => $mobile, 'message' => $message];

            return ['success' => true];
        });

    $this->artisan('reportcards:send-ready')->assertSuccessful();

    $reportCard->refresh();
    expect($reportCard->status)->toBe('sent')
        ->and($reportCard->download_token)->not->toBeNull()
        ->and($reportCard->downloaded_at)->toBeNull();

    $expectedUrl = route('reportcard.download', $reportCard->download_token);

    expect($sms['mobile'])->toBe(712345678)
        // The text stands on its own for a parent the email never reaches:
        // a warm greeting, whose report card it is, and the link itself.
        ->and($sms['message'])->toContain($expectedUrl)
        ->and($sms['message'])->toContain('Dear Parent')
        ->and($sms['message'])->toContain('Jane')
        ->and($sms['message'])->toContain($reportCard->institution->name)
        ->and($sms['message'])->toContain('Thank you for walking this journey with us')
        ->and($sms['message'])->toContain('opens once');

    Mail::assertQueued(ReportCardMail::class, fn (ReportCardMail $mail) => $mail->downloadUrl === $expectedUrl);
});

test('a failing mailer still lets the sms through, since that is the fallback', function () {
    stubReportCardServices();

    $reportCard = makeReadyReportCard();

    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('mailer offline'));

    $sms = null;
    $this->mock(SmsService::class)
        ->shouldReceive('send')
        ->once()
        ->andReturnUsing(function (int $mobile, string $message) use (&$sms) {
            $sms = $message;

            return ['success' => true];
        });

    $this->artisan('reportcards:send-ready')->assertSuccessful();

    expect($sms)->toContain(route('reportcard.download', $reportCard->fresh()->download_token))
        ->and($reportCard->fresh()->status)->toBe('sent');
});

test('the download link serves the pdf once and then expires', function () {
    Storage::fake('public');
    Storage::disk('public')->put('report-cards/7.pdf', '%PDF-1.4 fake');

    $reportCard = makeReadyReportCard();
    $reportCard->update([
        'status' => 'sent',
        'pdf_path' => 'report-cards/7.pdf',
        'download_token' => 'test-token-abc',
    ]);

    $first = $this->get(route('reportcard.download', 'test-token-abc'));

    $first->assertOk();
    $first->assertDownload('jane-wanjiru-report-card.pdf');
    expect($reportCard->fresh()->downloaded_at)->not->toBeNull();

    $second = $this->get(route('reportcard.download', 'test-token-abc'));

    $second->assertStatus(410);
    $second->assertSee('already been used', false);
});

test('an unknown download token is a 404', function () {
    $this->get(route('reportcard.download', 'nope'))->assertNotFound();
});

test('no sms is sent when the sms feature is disabled', function () {
    Mail::fake();
    Setting::set('sms', false);
    stubReportCardServices();

    makeReadyReportCard();

    $this->mock(SmsService::class)->shouldNotReceive('send');

    $this->artisan('reportcards:send-ready')->assertSuccessful();

    Mail::assertQueued(ReportCardMail::class);
});

test('no email is sent when email notifications are disabled', function () {
    Mail::fake();
    Setting::set('email_notifications', false);
    stubReportCardServices();

    makeReadyReportCard();

    $this->mock(SmsService::class)->shouldReceive('send')->once()->andReturn(['success' => true]);

    $this->artisan('reportcards:send-ready')->assertSuccessful();

    Mail::assertNothingQueued();
});

test('a report card is skipped when the student has no parent on record', function () {
    Mail::fake();
    stubReportCardServices();

    $reportCard = makeReadyReportCard();
    // Hard delete - the model soft deletes, which would leave the row (and
    // so the parent link) in place.
    DB::table('student_details')->where('student_id', $reportCard->student_id)->delete();

    $this->mock(SmsService::class)->shouldNotReceive('send');

    $this->artisan('reportcards:send-ready')->assertSuccessful();

    expect($reportCard->fresh()->status)->toBe('ready');
    Mail::assertNothingQueued();
});
