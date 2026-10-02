<?php

namespace Modules\ReportCard\Support;

use Modules\ReportCard\Models\GradingBand;
use Modules\Result\Services\ResultGrader;

/**
 * One line of a report card's subject table.
 *
 * A learner sits a subject more than once in a term (a mid-term paper and
 * an end-term one), and the report states the subject once. `marks` and
 * `outOf` are therefore the term's totals for that subject, and
 * `percentage` is worked out from those totals rather than by averaging
 * the individual papers - so a 40-mark paper counts for twice as much as a
 * 20-mark one, which is what a school means by a subject's score.
 *
 * For CBC classes, assessments can also be recorded directly as expectation
 * bands (EE, ME, AE, BE) or 8 sub-levels (EE1-BE2) without numerical marks.
 */
class SubjectRow
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $code,
        public readonly ?float $marks,
        public readonly ?float $outOf,
        public readonly ?float $percentage,
        public readonly ?GradingBand $band,
        public readonly ?string $teacherInitials,
        public readonly ?string $directBand = null,
        public readonly ?string $learningArea = null,
        public readonly ?string $strand = null,
    ) {}

    /**
     * Whether the learner actually sat this subject this term. Drives the
     * "assessed 4 of 5" count and keeps un-sat subjects out of the mean.
     */
    public function isAssessed(): bool
    {
        return $this->percentage !== null || $this->directBand !== null || $this->band !== null;
    }

    public function grade(): ?string
    {
        return $this->band?->grade ?? $this->directBand;
    }

    public function points(): ?int
    {
        if ($this->band) {
            return $this->band->points;
        }

        if ($this->directBand) {
            return ResultGrader::pointsFor($this->directBand);
        }

        return null;
    }

    public function remark(): ?string
    {
        if ($this->band) {
            return $this->band->remark;
        }

        if ($this->directBand) {
            $band = ResultGrader::parentBand($this->directBand) ?? strtoupper($this->directBand);

            return match ($band) {
                'EE' => 'Exceeding Expectations',
                'ME' => 'Meeting Expectations',
                'AE' => 'Approaching Expectations',
                'BE' => 'Below Expectations',
                default => null,
            };
        }

        return null;
    }

    /**
     * The four-band letters behind a grade: KJSEA's EE1 and EE2 are both
     * EE. Null on 8-4-4, whose A-E letters aren't expectation bands and
     * shouldn't be painted as though they were.
     */
    public function expectationBand(): ?string
    {
        if ($this->band) {
            return GradingScaleDefaults::bandFor($this->band->grade);
        }

        if ($this->directBand) {
            return ResultGrader::parentBand($this->directBand) ?? strtoupper($this->directBand);
        }

        return null;
    }

    /**
     * The KJSEA level, shown beside its band so a CBC report states both.
     * Null when the grade is already just a band, or isn't one at all.
     */
    public function achievementLevel(): ?string
    {
        $grade = $this->grade();

        return ($grade !== null && $this->expectationBand() !== null && $grade !== $this->expectationBand())
            ? $grade
            : null;
    }

    /**
     * The abbreviation under a bar on the performance chart - a subject's
     * code where it has one, otherwise the first letters of its name.
     */
    public function shortLabel(): string
    {
        if ($this->code) {
            return strtoupper(substr($this->code, 0, 4));
        }

        return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $this->name) ?: $this->name, 0, 3));
    }
}
