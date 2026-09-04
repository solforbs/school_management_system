<?php

use App\Models\User;
use App\Notifications\FeePaymentReminder;
use App\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;

test('the scheduled command reminds defaulters by both email and sms', function () {
    Notification::fake();

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
    DB::table('parent_details')->insert(['parent_id' => $parent->id, 'parent_phone' => '0712345678']);
    $student = User::factory()->create();

    Fee::create([
        'institution_id' => $institution->id,
        'student_id' => $student->id,
        'parent_id' => $parent->id,
        'title' => 'Term 1 Tuition',
        'amount' => 10000,
        'amount_paid' => 4000,
    ]);

    $this->mock(SmsService::class)
        ->shouldReceive('send')
        ->once()
        ->andReturn(['success' => true]);

    $this->artisan('feemanagement:send-reminders')
        ->expectsOutputToContain('Queued reminders for 1 parent(s) - 1 email(s), 1 SMS.')
        ->assertSuccessful();

    Notification::assertSentTo($parent, FeePaymentReminder::class);
});
