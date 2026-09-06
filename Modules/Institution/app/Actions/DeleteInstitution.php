<?php

namespace Modules\Institution\Actions;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Institution\Models\Institution;
use Modules\Parent\Models\ParentDetails;
use Modules\Staff\Models\StaffDetails;
use Modules\Student\Models\StudentDetails;
use Modules\Teacher\Models\TeacherDetails;

/**
 * De-registering a school and everything that belongs to it.
 *
 * Most of the work is done by the database: every `institution_id` column
 * across the modules is a `cascadeOnDelete()` foreign key, so a single
 * `$institution->delete()` clears classes, subjects, fees, results,
 * attendance, timetables, report cards, payroll, payments, devices and the
 * student/teacher/staff detail rows in one go.
 *
 * What the cascade can't reach is the `users` table - the logins for the
 * school's students, parents, teachers, accountants and its Director. This
 * action removes those too, but only the accounts that exist solely for
 * this school: anyone still tied to another institution (a parent with a
 * child elsewhere, a teacher on two staff lists, a Director who runs more
 * than one campus) keeps their account and just loses this one.
 *
 * It also deletes the uploaded files the rows point at, which the database
 * knows nothing about.
 *
 * Foreign keys: every column that points at a user we remove here is one
 * of `cascadeOnDelete` (the child row goes with it), `nullOnDelete` (an
 * audit column like `recorded_by` is blanked), or unconstrained
 * (`zkteco_users.app_user_id`, `sessions.user_id`). The `accountIsNow
 * Orphaned()` check is what guarantees there is nothing left in another
 * school that still needs the row. The whole thing runs in a transaction,
 * so a constraint that some drifted database still enforces differently
 * rolls the entire delete back rather than half-removing a school.
 */
class DeleteInstitution
{
    /**
     * @return array{institution: string, users_deleted: int} a short
     *                                                        summary for the caller to report
     */
    public function handle(Institution $institution): array
    {
        $name = $institution->name;

        return DB::transaction(function () use ($institution, $name) {
            // Everyone linked to the school, gathered before the cascade
            // removes the detail rows that link them.
            $candidateUserIds = $this->peopleTiedTo($institution);

            // Files the DB doesn't track.
            $this->deleteFiles($institution);

            // Nothing should be left pointing at a school that's gone.
            User::where('active_institution_id', $institution->id)->update(['active_institution_id' => null]);

            // The school itself - this is what fires every cascadeOnDelete.
            $institution->delete();

            // Now drop the accounts that have nothing left to belong to.
            $orphans = $candidateUserIds->filter(fn (int $id) => $this->accountIsNowOrphaned($id));

            $deleted = $this->deleteUsers($orphans);

            return ['institution' => $name, 'users_deleted' => $deleted];
        });
    }

    /**
     * Every user id with a role in this school: its students and their
     * parents, its teachers, its accountants/other login-holding staff,
     * and its owning Director.
     *
     * @return Collection<int, int>
     */
    private function peopleTiedTo(Institution $institution): Collection
    {
        $students = StudentDetails::where('institution_id', $institution->id)
            ->pluck('student_id');

        $parents = StudentDetails::where('institution_id', $institution->id)
            ->whereNotNull('parent_id')
            ->pluck('parent_id');

        $teachers = TeacherDetails::where('institution_id', $institution->id)
            ->pluck('teacher_id');

        $staff = StaffDetails::withTrashed()
            ->where('institution_id', $institution->id)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        return collect([$students, $parents, $teachers, $staff])
            ->flatten()
            ->push($institution->user_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * True once the institution is gone and this account has no remaining
     * link to any other school - no detail row, and it owns no other
     * institution.
     */
    private function accountIsNowOrphaned(int $userId): bool
    {
        return ! StudentDetails::where('student_id', $userId)->exists()
            && ! StudentDetails::where('parent_id', $userId)->exists()
            && ! TeacherDetails::where('teacher_id', $userId)->exists()
            && ! StaffDetails::withTrashed()->where('user_id', $userId)->exists()
            && ! Institution::where('user_id', $userId)->exists();
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function deleteUsers(Collection $userIds): int
    {
        if ($userIds->isEmpty()) {
            return 0;
        }

        $deleted = 0;

        User::whereIn('id', $userIds)->get()->each(function (User $user) use (&$deleted) {
            // Never delete a platform owner as a side effect, however they
            // came to be on the list.
            if ($user->isProtectedAdmin()) {
                return;
            }

            // Rows that hang off the user but aren't guaranteed a database
            // cascade on every (possibly drifted) deployment - cleared here
            // so the delete can't trip a constraint.
            ParentDetails::where('parent_id', $user->id)->delete();
            $user->syncRoles([]);
            $user->syncPermissions([]);
            $user->tokens()->delete();

            $user->delete();
            $deleted++;
        });

        return $deleted;
    }

    private function deleteFiles(Institution $institution): void
    {
        $disk = Storage::disk('public');

        $paths = collect([$institution->logo, $institution->favicon])
            ->merge(
                StudentDetails::where('institution_id', $institution->id)
                    ->whereNotNull('profile_photo')
                    ->pluck('profile_photo')
            )
            ->filter()
            ->unique();

        foreach ($paths as $path) {
            if ($disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
