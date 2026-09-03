<?php

use App\Models\Plan;
use App\Models\Setting;
use App\Models\TeacherSubjects;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;

if (! function_exists('institutionOwner')) {
    function institutionOwner(int $institution): bool
    {
        return $institution === Auth::user()->id;
    }
}
if (! function_exists('progress')) {
    function progress(Institution $institution)
    {
        $institutionColumns = $institution->toArray();
        $columns = count($institutionColumns);
        $filled = Arr::where($institutionColumns, fn($value) => filled($value));

        return round((count($filled) / $columns) * 100, 0);
    }
}
if (! function_exists('hasInstitutions')) {
    function hasInstitutions(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // A device holds one school by definition - the one it paired with.
        if (syncClientMode()) {
            return true;
        }

        // Admins own the platform, not a school.
        if (isAdmin()) {
            return true;
        }

        // Parents, Students, Teachers and Accountants belong to a school
        // without ever owning one themselves - they always have access to
        // its data.
        if ($user->hasAnyRole(['Parent', 'Student', 'Teacher', 'Accountant'])) {
            return true;
        }

        return $user->institution()->exists();
    }
}
if (! function_exists('syncClientMode')) {
    /**
     * Whether this copy of the application is running as an offline client
     * (the NativePHP build on someone's laptop or phone) rather than as the
     * central server. See config/sync.php.
     */
    function syncClientMode(): bool
    {
        return (bool) config('sync.client_mode', false);
    }
}
if (! function_exists('isTeacher')) {
    function isTeacher(): bool
    {
        return Auth::user()->hasRole('Teacher');
    }
}
if (! function_exists('isAdmin')) {
    function isAdmin(): bool
    {
        return Auth::user()->hasRole('Admin');
    }
}
if (! function_exists('isDirector')) {
    function isDirector(): bool
    {
        return Auth::user()->hasRole('Director');
    }
}
if (! function_exists('institutionApproved')) {
    function institutionApproved(): bool
    {
        $user = Auth::user();

        if (! $user || isAdmin()) {
            return true;
        }

        // Approval and payment are decided on the server. A device that was
        // allowed to pair is by definition attached to a school in good
        // standing, and re-litigating that offline would only lock people
        // out of work they're entitled to do.
        if (syncClientMode()) {
            return true;
        }

        // Staff/dependents never own an institution and can't exist yet
        // under a still-pending Director, so nothing to gate for them.
        if ($user->hasAnyRole(['Parent', 'Student', 'Teacher', 'Accountant'])) {
            return true;
        }

        $institution = currentInstitution();

        // No active institution yet (onboarding, or not chosen among
        // several) is handled separately by HasInstitution - not an
        // "unapproved" case for this helper.
        if (! $institution) {
            return true;
        }

        return (bool) $institution->is_approved;
    }
}
if (! function_exists('currentInstitution')) {
    function currentInstitution(): ?Institution
    {
        $user = Auth::user();

        if (! $user || isAdmin()) {
            return null;
        }

        if ($user->hasRole('Student')) {
            return $user->studentInstitution;
        }

        if ($user->hasRole('Teacher')) {
            return $user->teacherUserDetails?->institution;
        }

        if ($user->hasRole('Parent')) {
            return $user->parentInstitution;
        }

        // An Accountant is a staff member of exactly one school, reached
        // through the staff record a Director created them from.
        if ($user->hasRole('Accountant')) {
            return $user->staffUserDetails?->institution;
        }

        // Director (and any other role that owns institutions directly) -
        // whichever one they've chosen as active, not just "the first
        // one" - see HasInstitution for how/when this gets set.
        return $user->activeInstitution;
    }
}
if (! function_exists('currentInstitutionId')) {
    function currentInstitutionId(): ?int
    {
        return currentInstitution()?->id;
    }
}
if (! function_exists('institutionHasModule')) {
    function institutionHasModule(string $module): bool
    {
        // Admins aren't scoped to an institution's plan, and an
        // unresolvable institution (e.g. an Accountant with no plan
        // linkage yet) shouldn't be locked out over gaps in that lookup.
        $institution = currentInstitution();

        // Plans live on the server and aren't synced, so a device's
        // institution has no plan attached and every module would read as
        // unavailable - blanking the sidebar on a device that paired
        // precisely because it's entitled to this data.
        if (isAdmin() || syncClientMode() || ! $institution) {
            return true;
        }

        return $institution->hasModule($module);
    }
}
if (! function_exists('featureEnabled')) {
    /**
     * Whether a platform-wide feature toggle (managed from the admin site
     * settings page) is switched on - e.g. 'sms', 'email_notifications'.
     * Distinct from institutionHasFeature(), which gates a plan's pro-tier
     * features rather than a global on/off switch.
     */
    function featureEnabled(string $key): bool
    {
        return Setting::isEnabled($key);
    }
}
if (! function_exists('institutionHasFeature')) {
    function institutionHasFeature(string $feature): bool
    {
        $institution = currentInstitution();

        // As with institutionHasModule() - no plan on a device.
        if (isAdmin() || syncClientMode() || ! $institution) {
            return true;
        }

        return $institution->hasFeature($feature);
    }
}
if (! function_exists('institutionHasPaid')) {
    function institutionHasPaid(): bool
    {
        $user = Auth::user();

        if (! $user || isAdmin()) {
            return true;
        }

        // See institutionApproved() - subscription state is the server's
        // business, not a disconnected laptop's.
        if (syncClientMode()) {
            return true;
        }

        // Staff/dependents never own the subscription - paying for it is
        // the Director's/institution's concern, not theirs.
        if ($user->hasAnyRole(['Parent', 'Student', 'Teacher', 'Accountant'])) {
            return true;
        }

        $institution = currentInstitution();

        // No institution yet is the onboarding case, handled by
        // HasInstitution - not an "unpaid" case for this helper.
        if (! $institution) {
            return true;
        }

        return $institution->hasActiveSubscription();
    }
}
if (! function_exists('institutionInPaymentGracePeriod')) {
    function institutionInPaymentGracePeriod(): bool
    {
        $institution = currentInstitution();

        if (! $institution) {
            return true;
        }

        // Grace period counts from whichever happened most recently: the
        // institution's last paid plan expiring, or (for one that's never
        // paid at all) its approval date.
        $anchor = $institution->subscription_expires_at ?? $institution->approved_at ?? $institution->created_at;

        return ! $anchor || now()->lessThanOrEqualTo($anchor->copy()->addDays(14));
    }
}
if (! function_exists('institutionCanUpgrade')) {
    function institutionCanUpgrade(): bool
    {
        $institution = currentInstitution();

        if (! $institution) {
            return false;
        }

        $currentPrice = $institution->hasActiveSubscription() ? (float) $institution->plan->price : -1;

        return Plan::whereIsActive(true)->where('price', '>', $currentPrice)->exists();
    }
}
if (!function_exists('institutionSubjects')) {
    function institutionSubjects(): \Illuminate\Support\Collection
    {
        $institution = currentInstitution();
        if (! $institution) {
            return collect();
        }
        return $institution->subjects()->get();
    }
}
if (!function_exists('teacherSubjects')) {
    function teacherSubjects(int $teacherId): \Illuminate\Support\Collection
    {
        return TeacherSubjects::where('teacher_id', $teacherId)->get();
    }
}
if (!function_exists('getSubjectName')) {
    function getSubjectName(int $subjectId): string
    {
        $subject = Subject::find($subjectId);
        return $subject ? $subject->name : 'Unknown Subject';
    }
}
