<?php

namespace App\Models;

use App\Concerns\TetherSyncable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;
use Modules\Parent\Models\ParentDetails;
use Modules\Staff\Models\StaffDetails;
use Modules\Student\Models\StudentDetails;
use Modules\Teacher\Models\TeacherDetails;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'zkteco_synced', 'zkteco_synced_at', 'zkteco_sync_error', 'active_institution_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TetherSyncable, TwoFactorAuthenticatable;

    /**
     * Accounts that own the platform outright. Their Admin access is not
     * something another admin can take away from the Admin Access screen -
     * see App\Livewire (pages::admin.user-roles) - so a fallout among
     * admins can never lock the real owner out.
     *
     * @var list<string>
     */
    public const PROTECTED_ADMIN_EMAILS = [
        'solforbs@gmail.com',
    ];

    /**
     * What a device is allowed to learn about a person. Credentials are
     * absent by design - see TetherServiceProvider::stripCredentials(),
     * which also blanks them on the way out.
     *
     * @var string[]
     */
    protected array $tetherSyncable = [
        'name',
        'email',
        'active_institution_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'zkteco_synced' => 'boolean',
            'zkteco_synced_at' => 'datetime',
        ];
    }

    /**
     * A platform-owner account whose Admin access no other admin may
     * revoke. Strictly configured via PROTECTED_ADMIN_EMAILS in .env (comma-separated).
     * Matched case-insensitively.
     */
    public function isProtectedAdmin(): bool
    {
        $raw = config('app.protected_admin_emails', '');
        if (empty($raw)) {
            return false;
        }

        $emails = is_array($raw) ? $raw : array_map('trim', explode(',', (string) $raw));
        $currentEmail = Str::lower((string) $this->email);

        foreach ($emails as $protected) {
            if ($protected !== '' && $currentEmail === Str::lower($protected)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function children(): HasMany
    {
        return $this->hasMany(StudentDetails::class, 'parent_id');
    }

    public function institution(): HasMany
    {
        return $this->hasMany(Institution::class);
    }

    /**
     * The institution a Director is currently "running the system as" -
     * one of possibly several they own. See HasInstitution for how/when
     * this gets set.
     */
    public function activeInstitution(): BelongsTo
    {
        return $this->belongsTo(Institution::class, 'active_institution_id');
    }

    /**
     * student_details keys a student by student_id; there is no user_id
     * column on that table and never has been. This pointed at one, so
     * every screen eager-loading it - the fee list, a student's profile,
     * attendance - failed on an unknown column.
     */
    public function studentUserDetails(): HasOne
    {
        return $this->hasOne(StudentDetails::class, 'student_id');
    }

    public function parentUserDetails(): HasOneThrough
    {
        return $this->hasOneThrough(
            ParentDetails::class,        // Final model
            StudentDetails::class,     // Intermediate model
            'student_id',              // Foreign key on intermediate model (StudentDetails.student_id)
            'id',                      // Foreign key on final model (Institution.id)
            'id',                      // Local key on current model (User.id)
            'parent_id'           // Local key on intermediate model (StudentDetails.institution_id)
        );
    }

    public function parentInstitution(): HasOneThrough
    {
        return $this->hasOneThrough(
            Institution::class,
            StudentDetails::class,
            'parent_id',        // Foreign key on student_details (parent user id)
            'id',               // Foreign key on institutions (institution id)
            'id',               // Local key on users (parent user id)
            'institution_id'    // Local key on student_details (institution id)
        );
    }

    public function studentParent(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,           // Final model (Parent User)
            StudentDetails::class, // Intermediate model
            'student_id',          // Foreign key on intermediate model (StudentDetails.student_id) - matches users.id
            'id',                  // Foreign key on final model (User.id) - matches StudentDetails.parent_id
            'id',                  // Local key on current model (User.id)
            'parent_id'            // Local key on intermediate model (StudentDetails.parent_id)
        );
    }

    /**
     * @return HasOneThrough<Institution, StudentDetails, $this>
     */
    public function studentInstitution(): HasOneThrough
    {
        return $this->hasOneThrough(
            Institution::class,        // Final model
            StudentDetails::class,     // Intermediate model
            'student_id',              // Foreign key on intermediate model (StudentDetails.student_id)
            'id',                      // Foreign key on final model (Institution.id)
            'id',                      // Local key on current model (User.id)
            'institution_id'           // Local key on intermediate model (StudentDetails.institution_id)
        );
    }

    /** @return HasOne<ParentDetails, $this> */
    public function parent(): HasOne
    {
        return $this->hasOne(ParentDetails::class, 'parent_id');
    }

    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class, 'student_id');
    }

    public function childFees(): HasMany
    {
        return $this->hasMany(Fee::class, 'parent_id');
    }

    public function teacherUserDetails(): HasOne
    {
        return $this->hasOne(TeacherDetails::class, 'teacher_id');
    }

    /**
     * The staff record this login belongs to - how an Accountant is tied to
     * the school they work for, since they never own one.
     */
    public function staffUserDetails(): HasOne
    {
        return $this->hasOne(StaffDetails::class, 'user_id');
    }
}
