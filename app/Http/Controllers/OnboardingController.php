<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\Setting;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The step before a director can register their school: pick a plan, and
 * settle the one-off setup fee.
 *
 * Only the setup fee is taken here. The plan itself starts billing once
 * the school is up and its trial runs out, which is why the chosen plan is
 * remembered against the institution rather than activated as a paid
 * subscription - see the selected_plan_id migration for why writing it to
 * subscription_plan would give the plan away free.
 *
 * Kept apart from BillingController because that one assumes an
 * institution exists to bill; here, by definition, one doesn't yet.
 */
class OnboardingController extends Controller
{
    public function __construct(private readonly PaystackService $paystack) {}

    /**
     * Choose a plan and see what onboarding costs.
     */
    public function plan(Request $request)
    {
        $user = $request->user();

        // Nothing to do here for someone who already runs a school.
        if ($this->alreadyOnboarded($user)) {
            return redirect()->route('dashboard');
        }

        if ($this->setupFeeSettled($user)) {
            return redirect()->route('institution.create');
        }

        return view('onboarding.plan', [
            'plans' => Plan::query()->active()->orderBy('price')->orderBy('name')->get(),
            'setupFee' => Setting::setupFee(),
        ]);
    }

    /**
     * Start checkout for the setup fee against the chosen plan.
     */
    public function pay(Request $request)
    {
        $user = $request->user();

        if ($this->alreadyOnboarded($user)) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate(['plan_id' => 'required|exists:plans,id']);

        $plan = Plan::findOrFail($validated['plan_id']);

        if (! $plan->is_active) {
            return back()->with('error', 'That plan is no longer available.');
        }

        // A quoted plan has no price anyone can check out against - those
        // schools are onboarded by hand after a conversation.
        if (! $plan->isSelfServe()) {
            return back()->with('error', 'This plan is priced on application. Please contact us and we will set your school up.');
        }

        $setupFee = Setting::setupFee();

        // Nothing to charge: record the choice and let them straight
        // through rather than sending anyone to a checkout for KES 0.
        if ($setupFee <= 0) {
            session(['onboarding.plan_id' => $plan->id]);

            return redirect()->route('institution.create');
        }

        if ($this->setupFeeSettled($user)) {
            return redirect()->route('institution.create');
        }

        $reference = 'setup_'.Str::uuid();

        $payment = Payment::create([
            'institution_id' => null,
            'plan_id' => $plan->id,
            'purpose' => Payment::PURPOSE_SETUP,
            'initiated_by' => $user->id,
            'reference' => $reference,
            'amount' => $setupFee,
            'currency' => Plan::CURRENCY,
            'status' => 'pending',
        ]);

        $response = $this->paystack->initializeTransaction([
            'email' => $user->email,
            'amount' => (int) round($setupFee * 100), // Paystack expects cents/kobo.
            'currency' => Plan::CURRENCY,
            'reference' => $reference,
            'callback_url' => route('onboarding.callback'),
            'metadata' => [
                'purpose' => Payment::PURPOSE_SETUP,
                'plan_id' => $plan->id,
                'payment_id' => $payment->id,
                'user_id' => $user->id,
            ],
        ]);

        if (! ($response['status'] ?? false)) {
            $payment->update(['status' => 'failed', 'gateway_response' => $response]);

            return back()->with('error', 'Could not start payment. Please try again.');
        }

        return redirect()->away($response['data']['authorization_url']);
    }

    /**
     * Where Paystack sends the browser back. Verifies with Paystack rather
     * than trusting the redirect, exactly as the billing flow does.
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');

        if (! $reference) {
            return redirect()->route('onboarding.plan')->with('error', 'Missing payment reference.');
        }

        $payment = Payment::where('reference', $reference)
            ->where('purpose', Payment::PURPOSE_SETUP)
            ->first();

        if (! $payment) {
            return redirect()->route('onboarding.plan')->with('error', 'Payment not found.');
        }

        $this->finalize($payment);

        if (! $payment->fresh()->isSuccessful()) {
            return redirect()->route('onboarding.plan')->with('error', 'Payment was not completed. Nothing has been charged.');
        }

        // Carried forward so the school is created against the plan that
        // was actually paid for, without trusting a query string for it.
        session(['onboarding.plan_id' => $payment->plan_id]);

        return redirect()->route('institution.create')
            ->with('success', 'Setup fee received. Now tell us about your school.');
    }

    /**
     * Verifies with Paystack and settles the payment. Idempotent, because
     * the browser callback and the webhook can both reach it for the same
     * reference.
     */
    public function finalize(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            return;
        }

        $result = $this->paystack->verifyTransaction($payment->reference);
        $data = $result['data'] ?? [];

        if (($data['status'] ?? null) !== 'success') {
            $payment->update(['status' => 'failed', 'gateway_response' => $result]);

            return;
        }

        $payment->update([
            'status' => 'successful',
            'gateway_reference' => (string) ($data['id'] ?? ''),
            'channel' => $data['channel'] ?? null,
            'paid_at' => now(),
            'gateway_response' => $result,
        ]);
    }

    /**
     * Whether this user has already paid a setup fee that no school has
     * claimed - i.e. they may proceed to create one.
     */
    public static function setupFeeSettled($user): bool
    {
        if (Setting::setupFee() <= 0) {
            return true;
        }

        return Payment::query()
            ->unclaimedSetupFee()
            ->where('initiated_by', $user->id)
            ->exists();
    }

    private function alreadyOnboarded($user): bool
    {
        return $user->institution()->exists();
    }
}
