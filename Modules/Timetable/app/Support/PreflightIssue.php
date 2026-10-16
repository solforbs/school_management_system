<?php

namespace Modules\Timetable\Support;

/**
 * One finding from the pre-flight check: what is wrong, and what the school
 * needs to do about it.
 */
final class PreflightIssue
{
    public const BLOCKER = 'blocker';

    public const WARNING = 'warning';

    public const INFO = 'info';

    /**
     * @param  array<string, mixed>  $context  ids and numbers behind the message
     */
    public function __construct(
        public readonly string $severity,
        public readonly string $code,
        public readonly string $message,
        public readonly string $howToFix,
        public readonly ?string $actionRoute = null,
        public readonly ?string $actionLabel = null,
        public readonly array $context = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'severity' => $this->severity,
            'code' => $this->code,
            'message' => $this->message,
            'how_to_fix' => $this->howToFix,
            'action_route' => $this->actionRoute,
            'action_label' => $this->actionLabel,
            'context' => $this->context,
        ];
    }
}
