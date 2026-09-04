<?php

use App\Jobs\SendFeeReminderJob;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\Classes\Models\SchoolClass;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Jobs\SendReportCardJob;
use Modules\ReportCard\Models\ReportCard;

/**
 * Guards the actual moderation, not just its intent: fee reminders and
 * report cards must go out as rate-limited queued jobs, one per parent/card,
 * rather than in a synchronous loop - see AppServiceProvider's
 * 'notifications' limiter and the schedule comment in routes/console.php.
 */
test('fee reminder jobs are rate limited on the shared notifications bucket', function () {
    $job = new SendFeeReminderJob(1, [1]);

    expect($job->middleware())->toHaveCount(1)
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);
});

test('report card jobs are rate limited on the shared notifications bucket', function () {
    $job = new SendReportCardJob(1);

    expect($job->middleware())->toHaveCount(1)
        ->and($job->middleware()[0])->toBeInstanceOf(RateLimited::class);
});

test('a queue worker is scheduled to drain queued notifications without relying on an always-on process', function () {
    $events = collect(app(Schedule::class)->events());

    $worker = $events->first(fn (Event $event) => str_contains($event->command ?? '', 'queue:work'));

    expect($worker)->not->toBeNull()
        ->and($worker->expression)->not->toBe('* * * * *');
});

function makeReadyReportCardForBulkSend(): ReportCard
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

    $class = SchoolClass::create(['institution_id' => $institution->id, 'name' => 'Form 1 '.uniqid()]);

    $parent = User::factory()->create();
    DB::table('parent_details')->insert(['id' => $parent->id, 'parent_id' => $parent->id, 'parent_phone' => '0712345678']);
    $student = User::factory()->create();

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

test('the bulk send button queues one job per ready report card in the caller\'s school', function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);

    $reportCard = makeReadyReportCardForBulkSend();

    $director = User::factory()->create();
    $director->assignRole('Director');
    $director->update(['active_institution_id' => $reportCard->institution_id]);

    $this->actingAs($director->refresh())
        ->post(route('reportcard.send-ready'))
        ->assertRedirect();

    Queue::assertPushed(SendReportCardJob::class, 1);
});

test('the bulk send button is refused without edit reportcard rights', function () {
    Queue::fake();
    $this->seed(PermissionSeeder::class);

    $reportCard = makeReadyReportCardForBulkSend();

    $student = User::factory()->create();
    $student->assignRole('Student');
    $student->update(['active_institution_id' => $reportCard->institution_id]);

    $this->actingAs($student->refresh())
        ->post(route('reportcard.send-ready'))
        ->assertForbidden();

    Queue::assertNothingPushed();
});
