<?php

use App\Models\Setting;
use App\Models\User;
use App\Notifications\FeePaymentReminder;
use App\Services\FeeReminderService;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;

function makeFeeDefaulter(): array
{
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

    $parent = User::factory()->create(['email' => 'parent@example.com']);
    // ParentDetails has no timestamp columns, but doesn't disable Eloquent's
    // default timestamps - Model::create() would try to write created_at/
    // updated_at and fail. Insert directly to sidestep that unrelated bug.
    DB::table('parent_details')->insert(['parent_id' => $parent->id, 'parent_phone' => '0712345678']);

    $student = User::factory()->create();

    $fee = Fee::create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'parent_id' => $parent->id,
        'title' => 'Term 1 Tuition',
        'amount' => 10000,
        'amount_paid' => 4000,
    ]);

    return [$parent, $fee];
}

test('it notifies by both email and sms when both features are enabled', function () {
    Notification::fake();
    [$parent] = makeFeeDefaulter();

    $this->mock(SmsService::class)
        ->shouldReceive('send')
        ->once()
        ->andReturn(['success' => true]);

    $result = app(FeeReminderService::class)->sendForDefaulters(Fee::query());

    expect($result)->toMatchArray(['parents_queued' => 1, 'emails_queued' => 1, 'sms_queued' => 1]);
    Notification::assertSentTo($parent, FeePaymentReminder::class);
});

test('it skips sms without calling the sms service when the sms feature is disabled', function () {
    Notification::fake();
    Setting::set('sms', false);
    [$parent] = makeFeeDefaulter();

    $this->mock(SmsService::class)->shouldNotReceive('send');

    $result = app(FeeReminderService::class)->sendForDefaulters(Fee::query());

    expect($result)->toMatchArray(['parents_queued' => 1, 'emails_queued' => 1, 'sms_queued' => 0]);
    Notification::assertSentTo($parent, FeePaymentReminder::class);
});

test('it skips email without notifying when the email_notifications feature is disabled', function () {
    Notification::fake();
    Setting::set('email_notifications', false);
    [$parent] = makeFeeDefaulter();

    $this->mock(SmsService::class)
        ->shouldReceive('send')
        ->once()
        ->andReturn(['success' => true]);

    $result = app(FeeReminderService::class)->sendForDefaulters(Fee::query());

    expect($result)->toMatchArray(['parents_queued' => 1, 'emails_queued' => 0, 'sms_queued' => 1]);
    Notification::assertNotSentTo($parent, FeePaymentReminder::class);
});
