<?php

namespace Modules\Student\Exceptions;

use Modules\Institution\Models\Institution;
use RuntimeException;

/**
 * Thrown when an enrolment would take an institution past the student
 * ceiling on its plan.
 *
 * Raised from SaveStudent - the single path every enrolment goes through -
 * so it fires the same way for the enrolment form, a bulk upload and the
 * API, and there is nothing a director can do to get past it short of the
 * institution moving to a bigger plan.
 */
class StudentLimitException extends RuntimeException
{
    public function __construct(
        public readonly ?int $limit = null,
        public readonly int $current = 0,
        string $message = '',
    ) {
        parent::__construct($message ?: 'This institution has reached the student limit on its plan.');
    }

    public static function forInstitution(Institution $institution): self
    {
        $limit = $institution->studentLimit();
        $plan = $institution->effectivePlan();
        $planName = $plan?->name ?? 'current';

        $message = $limit === null
            ? "This institution has reached the student limit on its {$planName} plan."
            : "Your {$planName} plan supports up to ".number_format($limit).' students, and this institution already has '
                .number_format($institution->currentStudentCount()).'. Upgrade the plan to enrol more.';

        return new self($limit, $institution->currentStudentCount(), $message);
    }
}
