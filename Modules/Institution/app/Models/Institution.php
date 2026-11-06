<?php

namespace Modules\Institution\Models;

use App\Concerns\TetherSyncable;
use App\Models\Devices;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use App\Services\StudentCacheService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Classes\Models\SchoolClass;
use Modules\Curriculum\Models\Curriculum;
use Modules\Examinations\Models\Examination;
use Modules\FeeManagement\Models\Fee;
use Modules\Lesson\Models\Lesson;
use Modules\Result\Models\Result;
use Modules\Student\Models\StudentDetails;
use Modules\Subject\Models\Subject;
use Modules\Teacher\Models\TeacherDetails;
use Modules\Timetable\Models\TimetableEntry;

// use Modules\Institution\Database\Factories\InstitutionFactory;

/**
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $code
 * @property string $type
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $alternate_phone
 * @property string|null $website
 * @property string $country
 * @property string|null $county
 * @property string|null $city
 * @property string|null $postal_address
 * @property string|null $physical_address
 * @property string|null $logo
 * @property string|null $favicon
 * @property string|null $principal_name
 * @property string|null $principal_phone
 * @property string|null $education_level
 * @property int $min_electives
 * @property int|null $max_electives
 * @property string $locale
 * @property string $timezone
 * @property bool $is_active
 * @property bool $is_demo
 * @property string $status
 * @property bool $is_approved
 * @property Carbon|null $approved_at
 * @property int|null $approved_by_id
 * @property string|null $notes
 * @property int|null $subscription_plan
 * @property CarbonImmutable|null $subscription_expires_at
 * @property CarbonImmutable|null $subscription_started_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Institution extends Model
{
    use HasFactory, TetherSyncable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',

        // Basic Information
        'name',
        'code',
        'type',

        // Contact
        'email',
        'phone',
        'alternate_phone',
        'website',

        // Address
        'country',
        'county',
        'city',
        'postal_address',
        'physical_address',

        // Branding
        'logo',
        'favicon',

        // Administration
        'principal_name',
        'principal_phone',

        // Academic
        'education_level',
        'min_electives',
        'max_electives',

        // Attendance
        'timezone',
        'locale',

        // Status
        'status',
        'is_active',
        'is_demo',
        'is_approved',
        'approved_at',
        'approved_by_id',

        // Subscription
        'subscription_plan',
        'selected_plan_id',
        'subscription_started_at',
        'subscription_expires_at',

        // Optional
        'notes',
    ];

    // protected static function newFactory(): InstitutionFactory
    // {
    //     // return InstitutionFactory::new();
    // }
    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'is_active' => 'boolean',
            'is_demo' => 'boolean',
            'min_electives' => 'integer',
            'max_electives' => 'integer',
            'approved_at' => 'datetime',
            'subscription_started_at' => 'datetime',
            'subscription_expires_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Institution>  $query
     */
    public function scopeDemo($query): void
    {
        $query->where('is_demo', true);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class, 'subscription_plan');
    }

    /**
     * A plan with no expiry date is treated as not time-limited.
     */
    public function subscriptionActive(): bool
    {
        return ! $this->subscription_expires_at || $this->subscription_expires_at->isFuture();
    }

    /**
     * The shared demo school exists to show off the whole product, so it
     * has every module and feature regardless of which plan (if any) is
     * attached to it.
     */
    public function hasModule(string $module): bool
    {
        if ($this->is_demo) {
            return true;
        }

        if (! $this->plan || ! $this->subscriptionActive()) {
            return false;
        }

        return $this->plan->hasModule($module);
    }

    public function hasFeature(string $feature): bool
    {
        if ($this->is_demo) {
            return true;
        }

        if (! $this->plan || ! $this->subscriptionActive()) {
            return false;
        }

        return $this->plan->hasFeature($feature);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * The plan whose limits and features apply right now: the one being
     * paid for, or - during the free trial, before the first subscription
     * charge - the one picked at onboarding.
     */
    public function effectivePlan(): ?Plan
    {
        return $this->plan ?? $this->selectedPlan;
    }

    /**
     * How many students this institution has on its roster.
     */
    public function currentStudentCount(): int
    {
        return app(StudentCacheService::class)->getInstitutionStudentCount($this->id);
    }

    /**
     * The student ceiling from the effective plan, or null when nothing
     * caps it (Enterprise, or no plan resolved at all).
     */
    public function studentLimit(): ?int
    {
        return $this->effectivePlan()?->studentLimit();
    }

    /**
     * Seats left before the ceiling, or null when uncapped. Never negative.
     */
    public function remainingStudentSlots(): ?int
    {
        $limit = $this->studentLimit();

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - $this->currentStudentCount());
    }

    /**
     * Whether enrolling this many more students stays within the plan.
     * Uncapped plans always pass. This is the check SaveStudent enforces
     * on every enrolment path - there is no flag to switch it off.
     */
    public function canAddStudents(int $count = 1): bool
    {
        $limit = $this->studentLimit();

        if ($limit === null) {
            return true;
        }

        return ($this->currentStudentCount() + max(0, $count)) <= $limit;
    }

    /**
     * Everything the student-limit banner needs in one shape, so the view
     * re-derives none of it.
     *
     * @return array{unlimited: bool, limit: ?int, used: int, remaining: ?int, reached: bool, plan: ?Plan}
     */
    public function studentLimitStatus(): array
    {
        $plan = $this->effectivePlan();
        $limit = $plan?->studentLimit();
        $used = $this->currentStudentCount();

        return [
            'unlimited' => $limit === null,
            'limit' => $limit,
            'used' => $used,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
            'reached' => $limit !== null && $used >= $limit,
            'plan' => $plan,
        ];
    }

    /**
     * Whether there's a currently-usable plan - has one assigned, and it
     * hasn't expired. Distinct from subscriptionActive(), which alone
     * treats "no expiry date" as active even when no plan has ever been
     * chosen.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription_plan !== null && $this->subscriptionActive();
    }

    /**
     * The plan this school picked at onboarding but hasn't yet paid a
     * period for. Null once they're actually subscribed.
     */
    public function selectedPlan()
    {
        return $this->belongsTo(Plan::class, 'selected_plan_id');
    }

    /**
     * Where the school stands on billing, in one shape the dashboard
     * banner can render without re-deriving any of it.
     *
     * Four states, and they are mutually exclusive:
     *  - subscribed: paid, with a renewal date ahead
     *  - lifetime:   paid, with nothing to renew
     *  - trial:      never subscribed, still inside the free window
     *  - lapsed:     expired or trial over - the gate is closing/closed
     *
     * `days` is whole days from today, negative once the date is past.
     *
     * @return array{state: string, plan: ?Plan, date: ?Carbon, days: ?int}
     */
    public function billingStatus(): array
    {
        if ($this->hasActiveSubscription()) {
            $expires = $this->subscription_expires_at;

            return [
                'state' => $expires ? 'subscribed' : 'lifetime',
                'plan' => $this->plan,
                'date' => $expires,
                'days' => $expires ? (int) now()->startOfDay()->diffInDays($expires->copy()->startOfDay(), false) : null,
            ];
        }

        // Never subscribed: the free window runs from approval, falling
        // back to signup for a school still awaiting it.
        $trialEnds = ($this->subscription_expires_at ?? $this->approved_at ?? $this->created_at)?->copy()->addDays(14);
        $onTrial = $this->subscription_plan === null && $trialEnds && $trialEnds->isFuture();

        return [
            'state' => $onTrial ? 'trial' : 'lapsed',
            'plan' => $this->plan ?? $this->selectedPlan,
            'date' => $onTrial ? $trialEnds : $this->subscription_expires_at,
            'days' => $trialEnds ? (int) now()->startOfDay()->diffInDays($trialEnds->copy()->startOfDay(), false) : null,
        ];
    }

    /**
     * Total successfully paid since the current subscription window
     * started. A lapsed/never-started subscription has nothing counted
     * towards it - see amountDueForPlan().
     */
    public function amountPaidThisWindow(): float
    {
        if (! $this->subscription_started_at) {
            return 0.0;
        }

        return (float) $this->payments()
            ->where('status', 'successful')
            ->where('paid_at', '>=', $this->subscription_started_at)
            ->sum('amount');
    }

    /**
     * The full length of the current paid subscription window, in days.
     * Null when there's no window to measure (never subscribed, or a
     * lifetime plan with no expiry).
     */
    public function subscriptionWindowDays(): ?int
    {
        if (! $this->subscription_started_at || ! $this->subscription_expires_at) {
            return null;
        }

        return max(1, (int) $this->subscription_started_at->copy()->startOfDay()
            ->diffInDays($this->subscription_expires_at->copy()->startOfDay()));
    }

    /**
     * Whole days from today until the subscription expires. Zero once the
     * date is reached or passed, and for a plan with no expiry.
     */
    public function subscriptionDaysRemaining(): int
    {
        if (! $this->subscription_expires_at) {
            return 0;
        }

        return max(0, (int) now()->startOfDay()
            ->diffInDays($this->subscription_expires_at->copy()->startOfDay(), false));
    }

    /**
     * The unused value still sitting on the current plan: its price scaled
     * by how much of the paid window is still ahead. This is what an
     * upgrade credits against the new plan's price.
     *
     * Zero when there's no active paid window, no current plan, or the
     * window has effectively run out - so "the remaining days are almost
     * up" falls out of the same formula as "pay the new plan in full".
     */
    public function unusedPlanCredit(): float
    {
        if (! $this->hasActiveSubscription() || ! $this->plan) {
            return 0.0;
        }

        $windowDays = $this->subscriptionWindowDays();

        if (! $windowDays) {
            return 0.0;
        }

        $remaining = min($this->subscriptionDaysRemaining(), $windowDays);

        return round((float) $this->plan->price * $remaining / $windowDays, 2);
    }

    /**
     * What's left to pay to move onto the given plan.
     *
     *  - The plan the school is already on this window: nothing.
     *  - Otherwise: the plan's price minus the unused value of the days
     *    still left on the current plan. A cheaper plan already covered by
     *    that credit also comes to nothing.
     *
     * A school with no active subscription has no credit, so it owes the
     * full price to open a fresh window (see BillingController::finalize()).
     */
    public function amountDueForPlan(Plan $plan): float
    {
        if ($this->hasActiveSubscription() && (int) $this->subscription_plan === (int) $plan->id) {
            return 0.0;
        }

        return max(0.0, round((float) $plan->price - $this->unusedPlanCredit(), 2));
    }

    /**
     * The upgrade sum broken out for the billing screen, so the view adds
     * nothing up itself.
     *
     * @return array{plan_price: float, credit: float, due: float, days_remaining: int, is_change: bool}
     */
    public function upgradeQuoteFor(Plan $plan): array
    {
        $credit = $this->unusedPlanCredit();

        return [
            'plan_price' => round((float) $plan->price, 2),
            'credit' => $credit,
            'due' => $this->amountDueForPlan($plan),
            'days_remaining' => $this->subscriptionDaysRemaining(),
            'is_change' => ! ($this->hasActiveSubscription() && (int) $this->subscription_plan === (int) $plan->id),
        ];
    }

    public function devices()
    {
        return $this->hasMany(Devices::class);
    }

    public function students()
    {
        return $this->hasManyThrough(User::class, StudentDetails::class, 'institution_id', 'id', 'id', 'student_id');
    }

    public function studentDetails()
    {
        return $this->hasMany(StudentDetails::class);
    }

    public function teacherDetails()
    {
        return $this->hasMany(TeacherDetails::class);
    }

    public function parents()
    {
        return $this->hasManyThrough(
            User::class,            // The final model we want (Parent User)
            StudentDetails::class,  // The intermediate model
            'institution_id',       // Foreign key on student_details (matches institutions.id)
            'id',                   // Foreign key on users (matches student_details.parent_id)
            'id',                   // Local key on institutions (institutions.id)
            'parent_id'             // Local key on student_details (student_details.parent_id)
        )->whereHas('roles', function ($query) {
            $query->where('name', 'Parent'); // Only get users with Parent role
        })->distinct();
    }

    /**
     * Get the count of unique registered parents belonging to or having children in this institution.
     */
    public function registeredParentsCount(): int
    {
        return User::role('Parent')
            ->where(function ($query) {
                $query->whereHas('parent', fn ($q) => $q->where('institution_id', $this->id))
                    ->orWhereHas('children', fn ($q) => $q->where('institution_id', $this->id));
            })
            ->count();
    }

    public function fees()
    {
        return $this->hasMany(Fee::class);
    }

    public function curricula()
    {
        return $this->hasMany(Curriculum::class);
    }

    public function teachers()
    {
        return $this->hasManyThrough(User::class, TeacherDetails::class, 'institution_id', 'id', 'id', 'teacher_id');
    }

    public function timetableEntries()
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function examinations()
    {
        return $this->hasMany(Examination::class);
    }

    public function classes()
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }

    public function results()
    {
        return $this->hasMany(Result::class);
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }

    public function isApproved(): bool
    {
        return (bool) $this->is_approved;
    }
}
