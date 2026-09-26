<?php

use App\Jobs\SendParentNotificationJob;
use App\Mail\ParentNotificationMail;
use App\Models\NotificationTemplate;
use App\Models\ParentNotification;
use App\Models\ParentNotificationRecipient;
use App\Models\User;
use App\Services\SmsService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Institution\Models\Institution;
use Modules\Parent\Models\ParentDetails;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

/**
 * A Director running an approved school.
 *
 * @return array{0: User, 1: Institution}
 */
function director(string $name = 'Head'): array
{
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole('Director');

    $institution = Institution::create([
        'user_id' => $user->id,
        'name' => $name."'s School",
        'code' => 'S-'.uniqid(),
        'type' => 'School',
        'is_active' => true,
        'status' => 'active',
        'is_approved' => true,
    ]);
    $user->update(['active_institution_id' => $institution->id]);

    return [$user->refresh(), $institution];
}

/** Enrol a student under $institution with a parent; optionally give the parent a phone. */
function parentAt(Institution $institution, ?string $phone = null, ?string $email = null): User
{
    $parent = User::factory()->create(['email' => $email ?? fake()->unique()->safeEmail()]);
    $parent->assignRole('Parent');

    if ($phone !== null) {
        ParentDetails::create(['parent_id' => $parent->id, 'parent_phone' => $phone]);
    }

    $student = User::factory()->create();
    $student->assignRole('Student');
    StudentDetails::create([
        'student_id' => $student->id,
        'parent_id' => $parent->id,
        'institution_id' => $institution->id,
        'admission_number' => 'ADM-'.uniqid(),
        'enrollment_status' => 'active',
    ]);

    return $parent;
}

test('the page is closed to anyone without the permission', function () {
    // A parent reaches the route (their child's school satisfies the
    // institution middleware) but has no notification permission.
    [, $institution] = director();
    $parent = parentAt($institution, null, 'nope@example.com');

    $this->actingAs($parent)->get(route('notifications.index'))->assertForbidden();
});

test('a director opens the page and sees the composer with variable chips', function () {
    [$head] = director();

    $this->actingAs($head)->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Parent Notifications')
        ->assertSee('Compose & send')
        ->assertSee('Parent Name')
        ->assertSee('School Name')
        ->assertSee('Opening Date')
        ->assertSee('Fee Balance');
});

test('a director saves a template scoped to their school', function () {
    [$head, $institution] = director();

    Livewire::actingAs($head)
        ->test('pages::notifications')
        ->set('tplName', 'Term opening')
        ->set('tplSubject', 'Term 1 opens {{opening_date}}')
        ->set('tplBody', 'Dear {{parent}}, {{institution}} reopens on {{opening_date}}.')
        ->call('saveTemplate')
        ->assertHasNoErrors();

    $template = NotificationTemplate::firstOrFail();

    expect($template->institution_id)->toBe($institution->id)
        ->and($template->created_by)->toBe($head->id)
        ->and($template->placeholders())->toBe(['opening_date', 'parent', 'institution']);
});

test('another school never sees the first school\'s templates', function () {
    [$one, $instOne] = director('One');
    [$two] = director('Two');

    NotificationTemplate::create([
        'institution_id' => $instOne->id, 'name' => 'Private', 'subject' => 'x', 'body' => 'y',
    ]);

    $component = Livewire::actingAs($two)->test('pages::notifications');

    expect($component->get('templates'))->toHaveCount(0);
});

test('sending now queues the fan-out job', function () {
    Queue::fake();
    [$head, $institution] = director();
    parentAt($institution, '0700000001');

    Livewire::actingAs($head)
        ->test('pages::notifications')
        ->set('subject', 'Closure')
        ->set('body', 'Dear {{parent}}, {{institution}} is closed on {{closure_date}}.')
        ->set('placeholderValues.closure_date', '12 Dec')
        ->set('channels', ['email', 'sms'])
        ->set('mode', 'now')
        ->call('send')
        ->assertHasNoErrors();

    $notification = ParentNotification::firstOrFail();

    expect($notification->institution_id)->toBe($institution->id)
        ->and($notification->status)->toBe('pending')
        ->and($notification->channels)->toBe(['email', 'sms'])
        ->and($notification->placeholder_values)->toBe(['closure_date' => '12 Dec']);

    Queue::assertPushed(SendParentNotificationJob::class);
});

test('a missing custom placeholder value blocks the send', function () {
    [$head, $institution] = director();
    parentAt($institution);

    Livewire::actingAs($head)
        ->test('pages::notifications')
        ->set('subject', 'Notice')
        ->set('body', 'Reopens on {{opening_date}}')
        ->set('channels', ['email'])
        ->call('send')
        ->assertHasErrors('placeholderValues.opening_date');

    expect(ParentNotification::count())->toBe(0);
});

test('the job delivers to every parent, rendering their name and school', function () {
    Mail::fake();
    $this->mock(SmsService::class, fn ($m) => $m->shouldReceive('send')->andReturn(['success' => true]));

    [$head, $institution] = director('Riverside');
    $withPhone = parentAt($institution, '0700123456', 'a@example.com');
    parentAt($institution, null, 'b@example.com'); // no phone

    $notification = ParentNotification::create([
        'institution_id' => $institution->id,
        'name' => 'Reopening',
        'subject' => '{{institution}} reopens',
        'body' => 'Dear {{parent}}, we reopen on {{d}}.',
        'channels' => ['sms'],
        'placeholder_values' => ['d' => '6 Jan'],
        'status' => 'pending',
    ]);

    (new SendParentNotificationJob($notification->id))->handle(app(SmsService::class));

    $notification->refresh();

    // SMS-only: the parent with a number gets an SMS; the one without
    // falls back to e-mail.
    expect($notification->status)->toBe('sent')
        ->and($notification->recipients_count)->toBe(2)
        ->and($notification->delivered_count)->toBe(2)
        ->and(ParentNotificationRecipient::where('channel', 'sms')->where('status', 'sent')->count())->toBe(1)
        ->and(ParentNotificationRecipient::where('channel', 'email')->where('status', 'sent')->count())->toBe(1);

    Mail::assertQueued(ParentNotificationMail::class, fn ($mail) => str_contains($mail->bodyText, 'we reopen on 6 Jan')
        && $mail->institutionName === "Riverside's School");
});

test('a parent with several children is still only notified once', function () {
    Mail::fake();
    [$head, $institution] = director();
    $parent = parentAt($institution, null, 'once@example.com');

    // A second child for the same parent.
    $student2 = User::factory()->create();
    $student2->assignRole('Student');
    StudentDetails::create([
        'student_id' => $student2->id, 'parent_id' => $parent->id,
        'institution_id' => $institution->id, 'admission_number' => 'ADM-'.uniqid(),
        'enrollment_status' => 'active',
    ]);

    $notification = ParentNotification::create([
        'institution_id' => $institution->id, 'name' => 'x', 'subject' => 'x',
        'body' => 'Hi {{parent}}', 'channels' => ['email'], 'status' => 'pending',
    ]);

    (new SendParentNotificationJob($notification->id))->handle(app(SmsService::class));

    expect($notification->refresh()->recipients_count)->toBe(1);
    Mail::assertQueued(ParentNotificationMail::class, 1);
});

test('scheduling does not send until the due command runs', function () {
    Queue::fake();
    [$head, $institution] = director();
    parentAt($institution, null, 'p@example.com');

    Livewire::actingAs($head)
        ->test('pages::notifications')
        ->set('subject', 'Later')
        ->set('body', 'Hello {{parent}}')
        ->set('channels', ['email'])
        ->set('mode', 'schedule')
        ->set('scheduledFor', now()->addDay()->format('Y-m-d\TH:i'))
        ->call('send')
        ->assertHasNoErrors();

    Queue::assertNothingPushed();
    $notification = ParentNotification::firstOrFail();
    expect($notification->status)->toBe('pending')->and($notification->scheduled_for)->not->toBeNull();

    // Not due yet.
    $this->artisan('notifications:send-due')->assertOk();
    Queue::assertNothingPushed();

    // Time passes.
    $notification->update(['scheduled_for' => now()->subMinute()]);
    $this->artisan('notifications:send-due')->assertOk();
    Queue::assertPushed(SendParentNotificationJob::class);
});

test('a director cancels a scheduled notification', function () {
    [$head, $institution] = director();

    $notification = ParentNotification::create([
        'institution_id' => $institution->id, 'name' => 'x', 'subject' => 'x', 'body' => 'x',
        'channels' => ['email'], 'status' => 'pending', 'scheduled_for' => now()->addDay(),
    ]);

    Livewire::actingAs($head)
        ->test('pages::notifications')
        ->set('confirmingCancelId', $notification->id)
        ->call('cancel')
        ->assertHasNoErrors();

    expect($notification->refresh()->status)->toBe('canceled');
});

test('a sent notification can no longer be cancelled', function () {
    [$head, $institution] = director();

    $notification = ParentNotification::create([
        'institution_id' => $institution->id, 'name' => 'x', 'subject' => 'x', 'body' => 'x',
        'channels' => ['email'], 'status' => 'sent', 'sent_at' => now(),
    ]);

    Livewire::actingAs($head)
        ->test('pages::notifications')
        ->set('confirmingCancelId', $notification->id)
        ->call('cancel')
        ->assertStatus(422);
});

test('a director can load and edit a scheduled notification', function () {
    [$head, $institution] = director();
    parentAt($institution, null, 'p@example.com');

    $notification = ParentNotification::create([
        'institution_id' => $institution->id,
        'name' => 'Old Notice',
        'subject' => 'Old Subject',
        'body' => 'Old Body for {{parent}}',
        'channels' => ['email'],
        'status' => 'pending',
        'scheduled_for' => now()->addDays(2),
    ]);

    Livewire::actingAs($head)
        ->test('pages::notifications')
        ->call('editScheduled', $notification->id)
        ->assertSet('editingScheduledId', $notification->id)
        ->assertSet('subject', 'Old Subject')
        ->assertSet('body', 'Old Body for {{parent}}')
        ->set('subject', 'Updated Subject')
        ->call('send')
        ->assertHasNoErrors();

    $notification->refresh();
    expect($notification->subject)->toBe('Updated Subject')
        ->and(ParentNotification::count())->toBe(1);
});

test('a director can reuse a sent notification as a correction draft', function () {
    [$head, $institution] = director();

    $notification = ParentNotification::create([
        'institution_id' => $institution->id,
        'name' => 'Fee Reminder',
        'subject' => 'Fee Reminder',
        'body' => 'Please pay fees by Friday',
        'channels' => ['email'],
        'status' => 'sent',
        'sent_at' => now()->subDay(),
    ]);

    Livewire::actingAs($head)
        ->test('pages::notifications')
        ->call('reuseNotification', $notification->id)
        ->assertSet('editingScheduledId', null)
        ->assertSet('subject', 'Correction: Fee Reminder')
        ->assertSet('body', 'Please pay fees by Friday');
});

test('in-flight delivery stops when notification is marked canceled', function () {
    Mail::fake();
    $this->mock(SmsService::class, fn ($m) => $m->shouldReceive('send')->andReturn(['success' => true]));

    [$head, $institution] = director();
    // 3 parents
    parentAt($institution, '0700111111', 'p1@example.com');
    parentAt($institution, '0700222222', 'p2@example.com');
    parentAt($institution, '0700333333', 'p3@example.com');

    $notification = ParentNotification::create([
        'institution_id' => $institution->id,
        'name' => 'Broadcast',
        'subject' => 'School Notice',
        'body' => 'Notice to {{parent}}',
        'channels' => ['email'],
        'status' => 'pending',
    ]);

    // Mark as canceled before/during job run
    $notification->update(['status' => 'canceled']);

    (new SendParentNotificationJob($notification->id))->handle(app(SmsService::class));

    $notification->refresh();
    expect($notification->status)->toBe('canceled')
        ->and($notification->delivered_count)->toBe(0);

    Mail::assertNothingQueued();
});

test('a director can select and use a pre-made template', function () {
    [$head, $institution] = director();

    Livewire::actingAs($head)
        ->test('pages::notifications')
        ->set('sourceTemplateId', 'premade:fee')
        ->assertSet('subject', 'School Fee Balance Notice - {{institution}}')
        ->assertSee('School Fee Balance Reminder');
});

test('a director can load a pre-made template and customize it into a saved school template', function () {
    [$head, $institution] = director();

    Livewire::actingAs($head)
        ->test('pages::notifications')
        ->call('copyPremadeToCustom', 'fee')
        ->assertSet('tplName', 'School Fee Balance Reminder')
        ->set('tplName', 'Term 1 Fee Balance Notice')
        ->call('saveTemplate')
        ->assertHasNoErrors();

    $saved = NotificationTemplate::where('institution_id', $institution->id)->firstOrFail();
    expect($saved->name)->toBe('Term 1 Fee Balance Notice')
        ->and($saved->created_by)->toBe($head->id);
});

test('a director can select and use the biometrics onboarding pre-made template', function () {
    [$head, $institution] = director();

    Livewire::actingAs($head)
        ->test('pages::notifications')
        ->set('sourceTemplateId', 'premade:biometrics')
        ->assertSet('subject', 'New Digital System & Morning Attendance - {{institution}}')
        ->assertSee('New Digital System & Biometric Attendance')
        ->assertSee('biometrics');
});
