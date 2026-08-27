<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Plan;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function __construct(private readonly PaystackService $paystack) {}

    /**
     * Plan picker + payment history for the acting user's institution.
     * Shows every active plan with what's still owed to reach it (0 for
     * the current plan, and for any plan already fully covered by what's
     * been paid this window).
     */
    public function show(Request $request)
    {
        abort_unless(Auth::user()->can('view billing'), 403);

        $institution = currentInstitution();
        abort_unless($institution, 404);

        $plans = Plan::where('is_active', true)->orderBy('price')->get();

        return view('billing.show', [
            'institution' => $institution,
            'plans' => $plans,
            'payments' => $institution->payments()->with('plan')->latest()->get(),
        ]);
    }

    /**
     * Starts a Paystack checkout for exactly the amount still owed to
     * reach the chosen plan - the customer never sees a smaller amount to
     * pay, so a successful payment always fully qualifies them for it.
     */
    public function initiate(Request $request)
    {
        abort_unless(Auth::user()->can('create billing'), 403);

        $institution = currentInstitution();
        abort_unless($institution, 404);

        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        abort_unless($plan->is_active, 422);

        $amountDue = $institution->amountDueForPlan($plan);

        if ($amountDue <= 0) {
            return back()->with('error', 'Nothing left to pay for this plan.');
        }

        $reference = 'sms_'.Str::uuid();

        $payment = Payment::create([
            'institution_id' => $institution->id,
            'plan_id' => $plan->id,
            'initiated_by' => Auth::id(),
            'reference' => $reference,
            'amount' => $amountDue,
            'currency' => 'KES',
            'status' => 'pending',
        ]);

        $response = $this->paystack->initializeTransaction([
            'email' => Auth::user()->email,
            'amount' => (int) round($amountDue * 100), // Paystack expects the amount in cents/kobo.
            'currency' => 'KES',
            'reference' => $reference,
            'callback_url' => route('billing.callback'),
            'metadata' => [
                'institution_id' => $institution->id,
                'plan_id' => $plan->id,
                'payment_id' => $payment->id,
            ],
        ]);

        if (! ($response['status'] ?? false)) {
            $payment->update(['status' => 'failed', 'gateway_response' => $response]);

            return back()->with('error', 'Could not start payment. Please try again.');
        }

        return redirect()->away($response['data']['authorization_url']);
    }

    /**
     * Where Paystack sends the browser back after checkout. Re-verifies
     * with Paystack directly rather than trusting the redirect itself.
     */
    public function callback(Request $request)
    {
        $reference = $request->query('reference') ?? $request->query('trxref');

        if (! $reference) {
            return redirect()->route('billing.show')->with('error', 'Missing payment reference.');
        }

        $payment = Payment::where('reference', $reference)->first();

        if (! $payment) {
            return redirect()->route('billing.show')->with('error', 'Payment not found.');
        }

        $this->finalize($payment);

        return $payment->fresh()->isSuccessful()
            ? redirect()->route('billing.show')->with('success', 'Payment successful! Your plan has been updated.')
            : redirect()->route('billing.show')->with('error', 'Payment was not completed.');
    }

    /**
     * Server-to-server confirmation - the reliable path in case the
     * browser never makes it back to callback() (closed tab, network
     * drop, etc). Unauthenticated by nature; trust is established purely
     * via the signature header, never the payload alone.
     */
    public function webhook(Request $request)
    {
        $signature = $request->header('x-paystack-signature');

        if (! $this->paystack->verifyWebhookSignature($request->getContent(), $signature)) {
            abort(400);
        }

        if ($request->input('event') === 'charge.success') {
            $payment = Payment::where('reference', $request->input('data.reference'))->first();

            if ($payment) {
                // A setup fee is settled by the onboarding flow: it has no
                // institution to activate a subscription against, and
                // finalize() below would dereference that null.
                $payment->isSetupFee()
                    ? app(OnboardingController::class)->finalize($payment)
                    : $this->finalize($payment);
            }
        }

        return response()->noContent();
    }

    /**
     * Verifies the payment directly with Paystack and, on genuine
     * success, activates the plan it was initiated for. Idempotent -
     * already-finalized payments (anything not still "pending") are left
     * alone, since callback() and webhook() can both race to call this
     * for the same payment.
     */
    private function finalize(Payment $payment): void
    {
        if ($payment->status !== 'pending') {
            return;
        }

        // Belt and braces alongside the webhook's own check: this method
        // activates a subscription, and a setup fee has no institution to
        // activate one for.
        if ($payment->isSetupFee() || ! $payment->institution) {
            return;
        }

        $result = $this->paystack->verifyTransaction($payment->reference);
        $data = $result['data'] ?? [];

        if (($data['status'] ?? null) !== 'success') {
            $payment->update(['status' => 'failed', 'gateway_response' => $result]);

            return;
        }

        $now = now();

        $payment->update([
            'status' => 'successful',
            'gateway_reference' => (string) ($data['id'] ?? ''),
            'channel' => $data['channel'] ?? null,
            'paid_at' => $now,
            'gateway_response' => $result,
        ]);

        $institution = $payment->institution;
        $plan = $payment->plan;
        $hadActiveSubscription = $institution->hasActiveSubscription();

        // A fresh window - first ever subscription, or renewing after the
        // previous one lapsed - starts the "amount paid so far" clock over
        // and sets a new expiry. A same-window upgrade just swaps the
        // plan; the institution keeps the expiry date it already paid
        // for, and this payment's amount keeps accumulating toward the
        // next upgrade via amountPaidThisWindow().
        if (! $hadActiveSubscription) {
            $institution->subscription_started_at = $now;
            $institution->subscription_expires_at = match ($plan->billing_cycle) {
                'monthly' => $now->copy()->addMonth(),
                'yearly' => $now->copy()->addYear(),
                'lifetime' => null,
            };
        } elseif (! $institution->subscription_started_at) {
            // An institution whose plan was assigned manually before this
            // feature existed has no window to accumulate against yet -
            // bootstrap it now, without touching the expiry it already
            // has, so this (and future) payments start counting.
            $institution->subscription_started_at = $now;
        }

        $institution->subscription_plan = $plan->id;
        $institution->save();
    }
}
