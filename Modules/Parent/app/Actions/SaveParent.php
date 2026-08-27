<?php

namespace Modules\Parent\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Parent\Models\ParentDetails;
use Modules\Student\Models\StudentDetails;

/**
 * Creating and editing a parent, in one place. A parent is a login, a
 * details row, and a set of children linked to them - all three move
 * together, whether the change came from the Livewire screen or the
 * controller endpoint.
 */
class SaveParent
{
    /**
     * @return array<string, mixed>
     */
    public static function createRules(): array
    {
        return array_merge([
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
        ], self::sharedRules());
    }

    /**
     * @return array<string, mixed>
     */
    public static function updateRules(User $parent): array
    {
        return array_merge([
            'email' => 'required|email|max:255|unique:users,email,'.$parent->id,
        ], self::sharedRules());
    }

    /**
     * @return array<string, mixed>
     */
    private static function sharedRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'parent_phone' => 'nullable|string|max:20',
            'parent_occupation' => 'nullable|string|max:255',
            'children' => 'nullable|array',
            'children.*' => 'exists:student_details,student_id',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Builder  $linkableStudents  students the
     *                                     caller may link - only ones with no parent yet, so this can't steal a
     *                                     student from another parent
     */
    public static function create(array $data, $linkableStudents): User
    {
        return DB::transaction(function () use ($data, $linkableStudents) {
            $parent = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $parent->syncRoles('Parent');

            ParentDetails::create([
                'parent_id' => $parent->id,
                'parent_phone' => $data['parent_phone'] ?? null,
                'parent_occupation' => $data['parent_occupation'] ?? null,
            ]);

            if (! empty($data['children'])) {
                (clone $linkableStudents)
                    ->whereIn('student_id', $data['children'])
                    ->update(['parent_id' => $parent->id]);
            }

            return $parent;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  Builder  $linkableStudents
     */
    public static function update(User $parent, array $data, $linkableStudents): User
    {
        DB::transaction(function () use ($parent, $data, $linkableStudents) {
            $parent->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            ParentDetails::updateOrCreate(
                ['parent_id' => $parent->id],
                [
                    'parent_phone' => $data['parent_phone'] ?? null,
                    'parent_occupation' => $data['parent_occupation'] ?? null,
                ]
            );

            $selected = $data['children'] ?? [];

            // Children that were deselected are unlinked, not deleted.
            StudentDetails::where('parent_id', $parent->id)
                ->whereNotIn('student_id', $selected ?: [0])
                ->update(['parent_id' => null]);

            if (! empty($selected)) {
                (clone $linkableStudents)
                    ->whereIn('student_id', $selected)
                    ->update(['parent_id' => $parent->id]);
            }
        });

        return $parent->refresh();
    }
}
