<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\SmsService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Mail\ReportCardMail;
use Modules\ReportCard\Models\ReportCard;
use Modules\ReportCard\Services\ReportCardPdfService;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * The director of the school a report card belongs to, so the index scopes
 * to it and the send button is theirs to press.
 */
function rmsDirector(ReportCard $reportCard): User
{
    $director = User::factory()->create();
    $director->assignRole('Director');
    $director->update(['active_institution_id' => $reportCard->institution_id]);

    return $director->refresh();
}

function rmsStubPdf(): void
{
    test()->mock(ReportCardPdfService::class)
        ->shouldReceive('generate')->andReturn('report-cards/stub.pdf');
}

test('the send button delivers a ready report card by email and sms straight away', function () {
    Mail::fake();
    rmsStubPdf();

    // Ready, but nowhere near the day-old mark the nightly run waits for -
    // the whole point of the button is not having to wait for it.
    $reportCard = makeReadyReportCard();
    $reportCard->update(['completed_at' => now()]);

    $sms = null;
    $this->mock(SmsService::class)
        ->shouldReceive('send')
        ->once()
        ->andReturnUsing(function (int $mobile, string $message) use (&$sms) {
            $sms = $message;

            return ['success' => true];
        });

    Livewire::actingAs(rmsDirector($reportCard))
        ->test('reportcard::index')
        ->call('send', $reportCard->id)
        ->assertHasNoErrors();

    $reportCard->refresh();
    $expectedUrl = route('reportcard.download', $reportCard->download_token);

    expect($reportCard->status)->toBe('sent')
        ->and($reportCard->sent_at)->not->toBeNull()
        ->and($sms)->toContain($expectedUrl)
        ->and($sms)->toContain('Dear Parent');

    Mail::assertQueued(ReportCardMail::class, fn (ReportCardMail $mail) => $mail->downloadUrl === $expectedUrl);
});

test('resending retires the previous link and issues a fresh one', function () {
    Mail::fake();
    rmsStubPdf();

    $reportCard = makeReadyReportCard();
    $reportCard->update([
        'status' => 'sent',
        'download_token' => 'the-old-token',
        // The parent already spent the first link, which is exactly when
        // they ring the school asking for another.
        'downloaded_at' => now()->subHour(),
    ]);

    $this->mock(SmsService::class)->shouldReceive('send')->once()->andReturn(['success' => true]);

    Livewire::actingAs(rmsDirector($reportCard))
        ->test('reportcard::index')
        ->call('send', $reportCard->id);

    $reportCard->refresh();

    expect($reportCard->download_token)->not->toBe('the-old-token')
        ->and($reportCard->downloaded_at)->toBeNull();

    // The retired link is dead rather than merely superseded.
    $this->get(route('reportcard.download', 'the-old-token'))->assertNotFound();
});

test('a report card with no way to reach the parent reports why instead of claiming it sent', function () {
    Mail::fake();
    rmsStubPdf();

    // A school with neither channel switched on has nowhere to send to.
    Setting::set('email_notifications', false);
    Setting::set('sms', false);

    $reportCard = makeReadyReportCard();

    $this->mock(SmsService::class)->shouldNotReceive('send');

    Livewire::actingAs(rmsDirector($reportCard))
        ->test('reportcard::index')
        ->call('send', $reportCard->id);

    expect($reportCard->fresh()->status)->not->toBe('sent');

    Mail::assertNothingQueued();
});

test('a failing mailer still gets the sms out and marks the card sent', function () {
    rmsStubPdf();

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

    Livewire::actingAs(rmsDirector($reportCard))
        ->test('reportcard::index')
        ->call('send', $reportCard->id);

    expect($reportCard->fresh()->status)->toBe('sent')
        ->and($sms)->toContain(route('reportcard.download', $reportCard->fresh()->download_token));
});

test('a user without report card rights cannot send, and cannot reach another school\'s card', function () {
    Mail::fake();

    $reportCard = makeReadyReportCard();

    $this->mock(SmsService::class)->shouldNotReceive('send');

    $student = User::factory()->create();
    $student->assignRole('Student');
    $student->update(['active_institution_id' => $reportCard->institution_id]);

    Livewire::actingAs($student)
        ->test('reportcard::index')
        ->call('send', $reportCard->id)
        ->assertForbidden();

    // A director elsewhere has the permission but not this card: the index
    // scopes to their own school, so it is a 404 rather than a send.
    $outsider = User::factory()->create();
    $outsider->assignRole('Director');
    $otherSchool = Institution::create([
        'user_id' => $outsider->id,
        'name' => 'Another School '.uniqid(),
        'code' => 'INST-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);
    $outsider->update(['active_institution_id' => $otherSchool->id]);

    expect(fn () => Livewire::actingAs($outsider)
        ->test('reportcard::index')
        ->call('send', $reportCard->id))
        ->toThrow(ModelNotFoundException::class);

    expect($reportCard->fresh()->status)->not->toBe('sent');
});

test('the send button is offered to staff and withheld from a student', function () {
    $reportCard = makeReadyReportCard();

    Livewire::actingAs(rmsDirector($reportCard))
        ->test('reportcard::index')
        ->assertSee('send');

    $student = User::factory()->create();
    $student->assignRole('Student');
    $student->update(['active_institution_id' => $reportCard->institution_id]);

    Livewire::actingAs($student)
        ->test('reportcard::index')
        ->assertDontSee('wire:click="send(');
});
