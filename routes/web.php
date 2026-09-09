<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DemoAccessController;
use App\Http\Controllers\DevicesController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SyncDeviceController;
use App\Http\Controllers\SyncStudentToDeviceController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HasInstitution;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Modules\Institution\Models\Institution;
use Modules\Staff\Models\StaffDetails;
use Modules\Student\Models\StudentDetails;
use Modules\Teacher\Models\TeacherDetails;

Route::view('/', 'frontend.home')->name('home');
Route::view('/about', 'frontend.about')->name('about');
Route::view('/services', 'frontend.services')->name('services');
// Priced from the plans table rather than the template, so an admin
// editing a plan changes the public page too - the same data the /api/plans
// endpoint serves.
Route::get('/plans', fn () => view('frontend.plans', [
    'plans' => Plan::query()->active()->orderBy('price')->orderBy('name')->get(),
]))->name('plans');
Route::view('/contact', 'frontend.contact')->name('contact');
// make sure to add the EnsureAccountIsActive middleware to all routes

// Paystack calls this directly, unauthenticated - no session, no CSRF
// token. Kept outside /dashboard so the payment-gate middleware never
// touches it either.
Route::post('billing/webhook', [BillingController::class, 'webhook'])->name('billing.webhook');

// Onboarding sits outside HasInstitution on purpose: that middleware is
// what sends a director here, so gating these with it would loop.
Route::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {
    Route::get('onboarding/plan', [OnboardingController::class, 'plan'])->name('onboarding.plan');
    Route::post('onboarding/pay', [OnboardingController::class, 'pay'])->name('onboarding.pay');
    Route::get('onboarding/callback', [OnboardingController::class, 'callback'])->name('onboarding.callback');

    // Drop into the shared demo school. Outside HasInstitution because the
    // onboarding director this is for has no institution of their own.
    Route::post('demo/enter', [DemoAccessController::class, 'enter'])->name('demo.enter');
});

// Stepping back out of an impersonated session, or the demo school. Kept
// off the /dashboard prefix (and its approval/payment gates) so the way
// back is always reachable.
Route::post('impersonate/leave', [ImpersonationController::class, 'leave'])
    ->middleware('auth')->name('impersonate.leave');
Route::post('demo/leave', [DemoAccessController::class, 'leave'])
    ->middleware('auth')->name('demo.leave');

Route::middleware(['auth', 'verified',  HasInstitution::class])->prefix('dashboard')->group(function () {
    Route::get('', DashboardController::class)->name('dashboard');
    // Sign in as another user. Who may do this to whom is enforced in
    // Impersonation::canImpersonate().
    Route::post('impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');
    Route::resource('admin/modules', ModuleController::class)->names('admin.modules');
    Route::resource('admin/plans', PlanController::class)->names('admin.plans')->except(['show']);
    Route::livewire('admin/user-roles', 'pages::admin.user-roles')->name('admin.user-roles.index');
    Route::livewire('admin/payments', 'pages::admin.payments')->name('admin.payments.index');
    Route::livewire('admin/settings', 'pages::admin.site-settings')->name('admin.settings.edit');
    Route::resource('messages', ContactMessageController::class)->names('messages');

    // Turned off inside the shared demo school - real money, real hardware,
    // real messages. See DenyOnDemoInstitution.
    Route::middleware('demo.readonly')->group(function () {
        // store/show aren't implemented - device creation and viewing are
        // handled entirely by the create/edit Livewire components.
        Route::resource('/devices', DevicesController::class)->names('devices')->except(['store', 'show']);
        // Queues a clock sync; the terminal applies it on its next check-in.
        Route::post('devices/{device}/set-time', [DevicesController::class, 'setTime'])->name('devices.set-time');
        // Settings/users/time/on-device log clearing/command queue - one page,
        // never attendance data (that stays Director-only, see Attendance).
        Route::livewire('devices/{deviceId}/manage', 'pages::devices.manage')->name('devices.manage');
        // Offline sync clients - distinct from the biometric hardware above.
        Route::resource('sync-devices', SyncDeviceController::class)
            ->names('sync-devices')
            ->only(['index', 'create', 'store', 'destroy']);
        // A Director's notices to the school's parents - term dates, closures.
        Route::livewire('notifications', 'pages::notifications')->name('notifications.index');
        Route::get('billing', [BillingController::class, 'show'])->name('billing.show');
        Route::post('billing/pay', [BillingController::class, 'initiate'])->name('billing.initiate');
        Route::get('billing/callback', [BillingController::class, 'callback'])->name('billing.callback');
        Route::post('students/{studentId}/sync-device', [SyncStudentToDeviceController::class, 'syncStudent'])->name('students.sync-device');
    });
});
Route::get('subjects-available', function () {
    return institutionSubjects()->map(fn ($subject) => [
        'id' => $subject->id,
        'name' => $subject->name,
    ]);
})->name('subjects-available');

Route::get('/seed', function () {
    set_time_limit(0);
    ini_set('memory_limit', '1024M');

    Artisan::call('db:seed', [
        '--class' => 'Modules\\Institution\\Database\\Seeders\\InstitutionDatabaseSeeder',
        '--force' => true,
    ]);

    $institution = Institution::where('code', 'GVA001')->first();

    if ($institution) {
        $institution->update([
            'is_approved' => true,
            'approved_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);
    }

    $studentsCount = StudentDetails::where('institution_id', $institution?->id)->count();
    $parentsCount = User::role('Parent')->count();
    $teachersCount = TeacherDetails::where('institution_id', $institution?->id)->count();
    $staffCount = StaffDetails::where('institution_id', $institution?->id)->count();
    $examsCount = DB::table('examinations')->where('institution_id', $institution?->id)->count();
    $resultsCount = DB::table('results')->where('institution_id', $institution?->id)->count();
    $reportCardsCount = DB::table('report_cards')->where('institution_id', $institution?->id)->count();
    $feesCount = DB::table('fees')->where('institution_id', $institution?->id)->count();

    return response()->json([
        'status' => 'success',
        'message' => 'Successfully seeded Green Valley Academy with 1,000+ students, faculty, staff, parents, exams, and fees!',
        'institution' => [
            'name' => $institution ? $institution->name : 'Green Valley Academy',
            'code' => $institution ? $institution->code : 'GVA001',
            'is_approved' => (bool) ($institution ? $institution->is_approved : false),
            'status' => $institution ? $institution->status : 'active',
            'admin_approval' => 'Bypassed (Active & Approved)',
        ],
        'credentials' => [
            'director' => 'director@greenvalley.ac.ke',
            'senior_accountant' => 'staff1@greenvalley.ac.ke',
            'teacher_sample' => 'teacher1@greenvalley.ac.ke',
            'parent_sample' => 'parent1@greenvalley.ac.ke',
            'student_sample' => 'student1@greenvalley.ac.ke',
            'default_password' => 'password',
        ],
        'counts' => [
            'students' => $studentsCount,
            'parents' => $parentsCount,
            'teachers' => $teachersCount,
            'staff' => $staffCount,
            'examinations' => $examsCount,
            'exam_results' => $resultsCount,
            'report_cards' => $reportCardsCount,
            'fee_records' => $feesCount,
        ],
    ]);
})->name('seed.greenvalley');

require __DIR__.'/settings.php';
