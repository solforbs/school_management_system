<?php

namespace Modules\Timetable\Support;

use RuntimeException;

/**
 * Thrown instead of generating when the pre-flight check found blockers.
 * Nothing has been written to the database when this is thrown.
 */
class PreflightFailedException extends RuntimeException
{
    public function __construct(public readonly PreflightReport $report)
    {
        $messages = array_map(fn (PreflightIssue $i) => $i->message, $report->blockers());

        parent::__construct('The timetable cannot be generated yet: '.implode(' ', $messages));
    }
}
