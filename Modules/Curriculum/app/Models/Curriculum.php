<?php

namespace Modules\Curriculum\Models;

use App\Concerns\TetherSyncable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Institution\Models\Institution;
use Modules\ReportCard\Models\GradingBand;

// use Modules\Curriculum\Database\Factories\CurriculumFactory;

class Curriculum extends Model
{
    use HasFactory, TetherSyncable;

    /**
     * The systems a curriculum can be taught on: Kenya's 8-4-4, which
     * grades A-E, or CBC, which grades against expectations.
     *
     * @var array<string, string>
     */
    public const SYSTEMS = [
        '844' => '8-4-4',
        'cbc' => 'CBC',
    ];

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['institution_id', 'name', 'system', 'status'];

    // protected static function newFactory(): CurriculumFactory
    // {
    //     // return CurriculumFactory::new();
    // }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function gradingBands()
    {
        return $this->hasMany(GradingBand::class);
    }

    /**
     * Whether results on this curriculum are graded against expectations
     * rather than on 8-4-4 letter grades.
     */
    public function isCbc(): bool
    {
        return $this->system === 'cbc';
    }

    public function systemLabel(): string
    {
        return self::SYSTEMS[$this->system] ?? (string) $this->system;
    }

    /**
     * The one-line label the settings and curriculum screens show.
     *
     * It names the grades themselves rather than just the system, because
     * the mistake this label has to make visible is a curriculum named for
     * one system that is set to grade on the other. "C.B.C — 8-4-4" reads
     * like a formatting quirk; "C.B.C — 8-4-4 (A to E)" reads like the
     * error it is.
     */
    public function gradingLabel(): string
    {
        return $this->systemLabel().' ('.$this->gradesLabel().')';
    }

    /**
     * Just the grades, with no system name in front - for places that have
     * already named the curriculum and would otherwise say "CBC (CBC ...)".
     */
    public function gradesLabel(): string
    {
        return $this->isCbc()
            ? 'EE / ME / AE / BE, levels EE1 to BE2'
            : 'A to E';
    }

    /**
     * Whether the curriculum's name points at one system while `system`
     * says the other - "C.B.C" left grading A-E, for instance.
     *
     * Worth flagging rather than silently correcting: the name is free
     * text a school chose, and only they know which half is the mistake.
     * Punctuation and spaces are stripped first so "C.B.C" and "C B C" are
     * caught alongside "CBC", and "8-4-4" alongside "844" - digits have to
     * survive that strip, or the 8-4-4 half of the check never fires.
     */
    public function looksMisconfigured(): bool
    {
        $name = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $this->name) ?? '');

        if ($name === '') {
            return false;
        }

        $namedCbc = str_contains($name, 'cbc')
            || str_contains($name, 'cbe')
            || str_contains($name, 'competency');

        $named844 = str_contains($name, '844');

        return ($namedCbc && ! $this->isCbc()) || ($named844 && $this->isCbc());
    }
}
