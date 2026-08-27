<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevicesController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\SyncDeviceController;
use App\Http\Controllers\SyncStudentToDeviceController;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HasInstitution;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;

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
});

Route::middleware(['auth', 'verified',  HasInstitution::class])->prefix('dashboard')->group(function () {
    Route::get('', DashboardController::class)->name('dashboard');
    // store/show aren't implemented - device creation and viewing are
    // handled entirely by the create/edit Livewire components.
    Route::resource('/devices', DevicesController::class)->names('devices')->except(['store', 'show']);
    // Queues a clock sync; the terminal applies it on its next check-in.
    Route::post('devices/{device}/set-time', [DevicesController::class, 'setTime'])->name('devices.set-time');
    // Offline sync clients - distinct from the biometric hardware above.
    Route::resource('sync-devices', SyncDeviceController::class)
        ->names('sync-devices')
        ->only(['index', 'create', 'store', 'destroy']);
    Route::resource('admin/modules', ModuleController::class)->names('admin.modules');
    Route::resource('admin/plans', PlanController::class)->names('admin.plans')->except(['show']);
    Route::livewire('admin/settings', 'pages::admin.site-settings')->name('admin.settings.edit');
    Route::resource('messages', ContactMessageController::class)->names('messages');
    Route::get('billing', [BillingController::class, 'show'])->name('billing.show');
    Route::post('billing/pay', [BillingController::class, 'initiate'])->name('billing.initiate');
    Route::get('billing/callback', [BillingController::class, 'callback'])->name('billing.callback');
    Route::get('students/{studentId}/sync-device', [SyncStudentToDeviceController::class, 'syncStudent'])->name('students.sync-device');
});
require __DIR__.'/settings.php';
