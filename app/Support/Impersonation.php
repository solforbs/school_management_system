<?php

namespace App\Support;

use App\Models\User;
use Modules\Staff\Models\StaffDetails;
use Modules\Student\Models\StudentDetails;
use Modules\Teacher\Models\TeacherDetails;

/**
 * Who may sign in as whom, and how to tell an impersonated session apart.
 *
 * One direction only: an Admin steps into a Director, a Director steps into
 * one of their own school's people. Never sideways (Director -> Director),
 * never upward (-> Admin), never the protected owner account, and never
 * nested.
 */
class Impersonation
{
    /** Roles a Director is allowed to step into, within their own school. */
    private const DIRECTOR_TARGET_ROLES = ['Teacher', 'Student', 'Parent', 'Accountant'];

    /**
     * The session key holding the real user's id while impersonating.
     */
    public const SESSION_KEY = 'impersonator_id';

    public static function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    /**
     * The real user behind the current impersonated session, if any.
     */
    public static function impersonator(): ?User
    {
        $id = session(self::SESSION_KEY);

        return $id ? User::find((int) $id) : null;
    }

    /**
     * Whether $actor may sign in as $target right now.
     */
    public static function canImpersonate(User $actor, User $target): bool
    {
        if (self::isImpersonating()) {
            return false;
        }

        if ($actor->is($target) || $target->isProtectedAdmin() || $target->status === 'suspended') {
            return false;
        }

        if ($actor->hasRole('Admin')) {
            return $target->hasRole('Director');
        }

        if ($actor->hasRole('Director') && $target->hasAnyRole(self::DIRECTOR_TARGET_ROLES)) {
            $actorInstitutionId = $actor->active_institution_id;

            return $actorInstitutionId !== null
                && self::targetInstitutionId($target) === $actorInstitutionId;
        }

        return false;
    }

    /**
     * The school a would-be target belongs to, resolved the same way
     * {@see currentInstitution()} resolves it per role: a Student/Parent
     * through student_details, a Teacher through teacher_details, an
     * Accountant through their staff record, a Director from their active
     * institution.
     */
    public static function targetInstitutionId(User $target): ?int
    {
        $institutionId = match (true) {
            $target->hasRole('Student') => StudentDetails::where('student_id', $target->id)->value('institution_id'),
            $target->hasRole('Teacher') => TeacherDetails::where('teacher_id', $target->id)->value('institution_id'),
            $target->hasRole('Parent') => StudentDetails::where('parent_id', $target->id)->value('institution_id'),
            $target->hasRole('Accountant') => StaffDetails::where('user_id', $target->id)->value('institution_id'),
            default => $target->active_institution_id,
        };

        return $institutionId === null ? null : (int) $institutionId;
    }

    /**
     * A short label for the banner: "Teacher", "Director", ...
     */
    public static function roleLabel(User $user): string
    {
        return $user->getRoleNames()->first() ?? 'User';
    }
}
