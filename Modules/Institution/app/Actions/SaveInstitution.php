<?php

namespace Modules\Institution\Actions;

use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\ImageCompressionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Modules\Institution\Models\Institution;

/**
 * Registering and editing a school, in one place - shared by the Livewire
 * screens and the controller endpoints.
 */
class SaveInstitution
{
    public function __construct(
        private readonly ImageCompressionService $imageCompressor,
    ) {}

    /**
     * The onboarding form asks for far less than the settings screen: this
     * is someone signing their school up, not configuring it.
     *
     * @return array<string, string>
     */
    public static function createRules(): array
    {
        return [
            'name' => 'required|string',
            'code' => 'required',
            'type' => 'required',
            'email' => 'required',
            'phone' => 'required',
            'alternate_phone' => 'nullable',
            'website' => 'nullable',
            'country' => 'nullable',
            'county' => 'required',
            'city' => 'required',
            'postal_address' => 'required',
            'physical_address' => 'required',
            'logo' => 'nullable|image|max:2048',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function updateRules(Institution $institution): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:institutions,code,'.$institution->id,
            'type' => 'required|string|in:School,College,University,Training Centre',
            'education_level' => 'nullable|string|max:100',
            'timezone' => 'required|string|max:50',
            'logo' => 'nullable|image|max:2048',
            'min_electives' => 'required|integer|min:0',
            'max_electives' => 'nullable|integer|gte:min_electives',

            // Contact
            'email' => 'required|email|max:255|unique:institutions,email,'.$institution->id,
            'phone' => 'required|string|max:20',
            'alternate_phone' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',

            // Address
            'country' => 'nullable|string|max:100',
            'county' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'postal_address' => 'nullable|string|max:255',
            'physical_address' => 'nullable|string',

            // Additional
            'principal_name' => 'nullable|string|max:255',
            'principal_phone' => 'nullable|string|max:20',
            'subscription_plan' => 'nullable|exists:plans,id',
            'subscription_expires_at' => 'nullable|date|after:today',
            'notes' => 'nullable|string',

            // Status
            'is_active' => 'required|boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $owner, ?UploadedFile $logo = null): Institution
    {
        if ($logo) {
            $data['logo'] = $this->imageCompressor->store($logo, 'institutions/logos');
        }

        unset($data['logo_upload']);

        // New institutions start unapproved regardless of the column's
        // default (which only exists to grandfather in institutions that
        // predate the approval workflow).
        $data['is_approved'] = false;

        $institution = $owner->institution()->create($data);

        // Owning a school makes this user its Director.
        if (! $owner->hasRole('Director')) {
            $owner->assignRole('Director');
        }

        $this->claimOnboardingPayment($institution, $owner);

        return $institution;
    }

    /**
     * Hand the school the setup fee its director already paid, and record
     * the plan they picked on the way in.
     *
     * The payment was taken before this row existed, so until now it has
     * been sitting with a null institution_id. Claiming it here is what
     * stops it being spent twice: the same director registering a second
     * school finds nothing unclaimed and is asked to pay again.
     *
     * The chosen plan is remembered, not activated. They have paid to be
     * set up, not for a period of service - the trial covers the gap until
     * the first subscription charge.
     */
    private function claimOnboardingPayment(Institution $institution, User $owner): void
    {
        $payment = Payment::query()
            ->unclaimedSetupFee()
            ->where('initiated_by', $owner->id)
            ->oldest()
            ->first();

        // Falls back to the session for the no-fee case, where there was
        // no payment to carry the choice.
        $planId = $payment?->plan_id ?? session('onboarding.plan_id');

        if ($payment) {
            $payment->update(['institution_id' => $institution->id]);
        }

        if ($planId && Plan::whereKey($planId)->exists()) {
            $institution->forceFill(['selected_plan_id' => $planId])->save();
        }

        session()->forget('onboarding.plan_id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Institution $institution, array $data, ?UploadedFile $logo = null): Institution
    {
        if (filled($data['subscription_expires_at'] ?? null)) {
            $data['subscription_expires_at'] = Carbon::parse($data['subscription_expires_at'])->format('Y-m-d H:i:s');
        }

        if ($logo) {
            // Replace the file and delete the old one only when a new logo
            // was actually sent.
            if ($institution->logo && Storage::disk('public')->exists($institution->logo)) {
                Storage::disk('public')->delete($institution->logo);
            }

            $data['logo'] = $this->imageCompressor->store($logo, 'institutions/logos');
        } else {
            unset($data['logo']);
        }

        $institution->update($data);

        return $institution->refresh();
    }
}
