<?php

namespace Modules\Teacher\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Teacher\Models\TeacherDetails;

/**
 * Hiring and editing a teacher, in one place. A teacher is a login plus a
 * staff record, and both are written together - the Livewire screen and the
 * controller endpoint come through here so neither can be created without
 * the other.
 */
class SaveTeacher
{
    /**
     * @return array<string, string>
     */
    public static function createRules(): array
    {
        return array_merge([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'employee_number' => 'nullable|string|max:100|unique:teacher_details,employee_number',
        ], self::sharedRules());
    }

    /**
     * @return array<string, string>
     */
    public static function updateRules(User $teacher, TeacherDetails $details): array
    {
        return array_merge([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$teacher->id,
            'employee_number' => 'nullable|string|max:100|unique:teacher_details,employee_number,'.$details->id,
        ], self::sharedRules());
    }

    /**
     * @return array<string, string>
     */
    private static function sharedRules(): array
    {
        return [
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:255',
            'qualification' => 'nullable|string|max:255',
            'hire_date' => 'nullable|date',
            'address' => 'nullable|string',
            'status' => 'nullable|in:active,on_leave,suspended,resigned,terminated',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function create(array $data, int $institutionId): User
    {
        return DB::transaction(function () use ($data, $institutionId) {
            $teacher = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $teacher->syncRoles('Teacher');

            $teacherData = collect($data)->except(['name', 'email', 'password'])->toArray();
            $teacherData['teacher_id'] = $teacher->id;
            $teacherData['institution_id'] = $institutionId;
            $teacherData['is_active'] = (bool) ($data['is_active'] ?? true);

            $teacher->teacherUserDetails()->create($teacherData);

            return $teacher;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function update(TeacherDetails $details, array $data, int $institutionId): TeacherDetails
    {
        DB::transaction(function () use ($details, $data, $institutionId) {
            $details->teacher?->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            $teacherData = collect($data)->except(['name', 'email', 'password'])->toArray();
            $teacherData['institution_id'] = $institutionId;
            $teacherData['is_active'] = (bool) ($data['is_active'] ?? false);

            $details->update($teacherData);
        });

        return $details->refresh();
    }
}
