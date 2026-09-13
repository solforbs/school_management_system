<?php

use App\Models\ChatbotVerification;
use App\Models\User;
use App\Notifications\ChatbotVerificationCode;
use App\Services\Chatbot\VerificationService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

beforeEach(function () {
    Notification::fake();
    $this->seed(PermissionSeeder::class);
});

function seedChatStudent(?string $guardianEmail = 'guardian@example.com'): StudentDetails
{
    $director = User::factory()->create();
    $institution = Institution::create([
        'user_id' => $director->id, 'name' => 'Chat School', 'code' => 'CH-'.uniqid(),
        'type' => 'School', 'is_active' => true, 'status' => 'active', 'is_approved' => true,
    ]);

    $student = User::factory()->create();
    $student->assignRole('Student');

    return StudentDetails::create([
        'student_id' => $student->id, 'institution_id' => $institution->id,
        'admission_number' => 'ADM-'.uniqid(), 'enrollment_status' => 'active',
        'guardian_email' => $guardianEmail,
    ]);
}

/** The latest progress checklist in the transcript, as [label => state]. */
function latestChecklist(array $messages): array
{
    $progress = array_values(array_filter($messages, fn ($m) => $m['role'] === 'progress'));
    $latest = end($progress);

    return $latest ? array_column($latest['steps'], 'state', 'label') : [];
}

test('entering an admission number looks the student up first and only sends the code on the follow-up request', function () {
    $student = seedChatStudent();

    $component = Livewire::test('chat-widget')
        ->call('toggle')
        ->call('selectCommand', 'result')
        ->set('input', $student->admission_number)
        ->call('send');

    // Lookup half: the checklist shows the sending step in flight, nothing sent yet.
    $component->assertSet('stage', 'sending_code')
        ->assertSet('pendingAdmissionNumber', $student->admission_number)
        ->assertSet('pendingEmailMasked', 'g*******@example.com');

    expect(latestChecklist($component->get('messages')))->toBe([
        'Student record found' => 'done',
        'Sending a 6-digit code to g*******@example.com' => 'active',
    ]);
    Notification::assertNothingSent();
    expect(ChatbotVerification::count())->toBe(0);

    // Delivery half, triggered from the browser after the first response renders.
    $component->call('deliverCode')
        ->assertSet('stage', 'code_sent');

    expect(latestChecklist($component->get('messages')))->toBe([
        'Student record found' => 'done',
        'Sending a 6-digit code to g*******@example.com' => 'done',
        'Code sent — check your inbox (and spam folder)' => 'done',
    ]);
    Notification::assertSentOnDemand(ChatbotVerificationCode::class);
    expect(ChatbotVerification::where('admission_number', $student->admission_number)->count())->toBe(1);
});

test('the real parent email never reaches the client, only the masked form', function () {
    $student = seedChatStudent('secret.parent@example.com');

    $component = Livewire::test('chat-widget')
        ->call('toggle')
        ->call('selectCommand', 'result')
        ->set('input', $student->admission_number)
        ->call('send');

    $component->assertDontSee('secret.parent@example.com')
        ->assertSee(app(VerificationService::class)->maskEmail('secret.parent@example.com'));
});

test('an unknown admission number marks the checklist step as failed and sends nothing', function () {
    $component = Livewire::test('chat-widget')
        ->call('toggle')
        ->call('selectCommand', 'result')
        ->set('input', 'NOPE-123')
        ->call('send');

    $component->assertSet('stage', 'awaiting_admission_number');
    expect(latestChecklist($component->get('messages')))->toBe([
        'No student found with that admission number' => 'error',
    ]);
    Notification::assertNothingSent();
});

test('a student with no parent email is reported on the checklist and returns to the menu', function () {
    $student = seedChatStudent(guardianEmail: null);

    $component = Livewire::test('chat-widget')
        ->call('toggle')
        ->call('selectCommand', 'result')
        ->set('input', $student->admission_number)
        ->call('send');

    $component->assertSet('stage', 'menu');
    expect(latestChecklist($component->get('messages')))->toBe([
        'Student record found' => 'done',
        'No parent email on file' => 'error',
    ]);
    Notification::assertNothingSent();
});

test('deliverCode does nothing unless a lookup put the widget in the sending stage', function () {
    Livewire::test('chat-widget')
        ->call('toggle')
        ->call('deliverCode')
        ->assertSet('stage', 'menu');

    Notification::assertNothingSent();
});

test('resending issues a fresh code through the same two-step flow', function () {
    $student = seedChatStudent();

    $component = Livewire::test('chat-widget')
        ->call('toggle')
        ->call('selectCommand', 'result')
        ->set('input', $student->admission_number)
        ->call('send')
        ->call('deliverCode')
        ->assertSet('stage', 'code_sent');

    $component->call('resendCode')
        ->assertSet('stage', 'sending_code')
        ->call('deliverCode')
        ->assertSet('stage', 'code_sent');

    Notification::assertSentOnDemandTimes(ChatbotVerificationCode::class, 2);
    expect(ChatbotVerification::where('admission_number', $student->admission_number)->count())->toBe(2);
});

test('resend is refused when the send limit for that student is reached', function () {
    config(['chatbot.max_sends_per_admission_number' => 1]);
    $student = seedChatStudent();

    $component = Livewire::test('chat-widget')
        ->call('toggle')
        ->call('selectCommand', 'result')
        ->set('input', $student->admission_number)
        ->call('send')
        ->call('deliverCode')
        ->call('resendCode')
        ->call('deliverCode');

    $component->assertSet('stage', 'menu')
        ->assertSet('pendingAdmissionNumber', null);
    expect(latestChecklist($component->get('messages')))->toBe([
        'Too many codes requested for this student' => 'error',
    ]);
    Notification::assertSentOnDemandTimes(ChatbotVerificationCode::class, 1);
});

test('verification state cannot be set from the browser to skip the email code', function () {
    foreach (['verifiedAdmissionNumber', 'verifiedUntil', 'stage', 'pendingAdmissionNumber', 'messages'] as $property) {
        expect(fn () => Livewire::test('chat-widget')->set($property, 'anything'))
            ->toThrow(CannotUpdateLockedPropertyException::class, $property);
    }
});
