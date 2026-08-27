<?php

namespace Modules\Staff\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Staff\Models\StaffDetails;

/**
 * Hiring and editing a staff member, in one place. A staff record may or may
 * not come with a login - the Livewire screen and the controller endpoint
 * both come through here so that decision is made the same way either side.
 */
class SaveStaff
{
    /**
     * Roles a staff member's login may be given. Deliberately narrow: this
     * is not the place to mint a Director or an Admin.
     *
     * @var string[]
     */
    public const SYSTEM_ROLES = ['Accountant'];

    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $wantsAccount, ?StaffDetails $staff = null): array
    {
        $needsAccount = $wantsAccount && ! $staff?->user_id;

        return [
            'name' => 'required|string|max:255',
            'email' => array_values(array_filter([
                $needsAccount ? 'required' : 'nullable',
                'email',
                'max:255',
                // Only a staff member who signs in needs an email nobody else
                // already logs in with - for the rest it's a contact detail.
                $needsAccount ? 'unique:users,email' : null,
                $staff?->user_id ? 'unique:users,email,'.$staff->user_id : null,
            ])),
            'phone' => 'nullable|string|max:20',
            'staff_number' => 'nullable|string|max:100|unique:staff_details,staff_number'.($staff ? ','.$staff->id : ''),
            'job_title' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'employment_type' => 'nullable|in:full_time,part_time,contract,volunteer',
            'hire_date' => 'nullable|date',
            'salary' => 'nullable|numeric|min:0',
            'address' => 'nullable|string',
            'status' => 'nullable|in:active,on_leave,suspended,resigned,terminated',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',

            // System access
            'create_account' => 'nullable|boolean',
            'password' => [$needsAccount ? 'required' : 'nullable', 'string', 'min:8'],
            'system_role' => [$needsAccount ? 'required' : 'nullable', 'in:'.implode(',', self::SYSTEM_ROLES)],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function create(array $data, int $institutionId, bool $wantsAccount): StaffDetails
    {
        return DB::transaction(function () use ($data, $institutionId, $wantsAccount) {
            $staffData = collect($data)->except(['password', 'system_role', 'create_account'])->toArray();
            $staffData['institution_id'] = $institutionId;
            $staffData['is_active'] = (bool) ($data['is_active'] ?? true);

            $staff = StaffDetails::create($staffData);

            if ($wantsAccount) {
                self::attachAccountTo($staff, $data['password'], $data['system_role']);
            }

            return $staff->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function update(StaffDetails $staff, array $data, int $institutionId, bool $wantsAccount): StaffDetails
    {
        DB::transaction(function () use ($staff, $data, $institutionId, $wantsAccount) {
            $staffData = collect($data)->except(['password', 'system_role', 'create_account'])->toArray();
            $staffData['institution_id'] = $institutionId;
            $staffData['is_active'] = (bool) ($data['is_active'] ?? false);

            $staff->update($staffData);

            if ($staff->user) {
                $staff->user->update([
                    'name' => $staff->name,
                    'email' => $staff->email ?? $staff->user->email,
                ]);

                if (filled($data['password'] ?? null)) {
                    $staff->user->update(['password' => Hash::make($data['password'])]);
                }

                if (filled($data['system_role'] ?? null)) {
                    $staff->user->syncRoles($data['system_role']);
                }
            } elseif ($wantsAccount) {
                self::attachAccountTo($staff, $data['password'], $data['system_role']);
            }
        });

        return $staff->refresh();
    }

    /**
     * Give a staff member a login with the requested system role.
     */
    private static function attachAccountTo(StaffDetails $staff, string $password, string $role): void
    {
        $user = User::create([
            'name' => $staff->name,
            'email' => $staff->email,
            'password' => Hash::make($password),
        ]);
        $user->syncRoles($role);

        $staff->update(['user_id' => $user->id]);
    }
}
