<?php

namespace Modules\Institution\Models;

use App\Concerns\TetherSyncable;
use App\Models\Devices;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Carbon\CarbonImmutable;
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
 * @property string $timezone
 * @property bool $is_active
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

        // Status
        'status',
        'is_active',
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
            'min_electives' => 'integer',
            'max_electives' => 'integer',
            'approved_at' => 'datetime',
            'subscription_started_at' => 'datetime',
            'subscription_expires_at' => 'datetime',
        ];
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

    public function hasModule(string $module): bool
    {
        if (! $this->plan || ! $this->subscriptionActive()) {
            return false;
        }

        return $this->plan->hasModule($module);
    }

    public function hasFeature(string $feature): bool
    {
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
     * What's left to pay to reach the given plan: its price, minus
     * whatever's already been paid this window. Only counts prior payments
     * while the current subscription is still active - a lapsed
     * subscription always owes the plan's full price to start a fresh
     * window (see BillingController::finalize()).
     */
    public function amountDueForPlan(Plan $plan): float
    {
        $alreadyPaid = $this->hasActiveSubscription() ? $this->amountPaidThisWindow() : 0.0;

        return max(0.0, (float) $plan->price - $alreadyPaid);
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
        });
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
