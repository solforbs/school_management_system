<?php

namespace Modules\Timetable\Support;

/**
 * The result of checking a school's data before generating a timetable.
 * Blockers stop generation; warnings and info are shown but do not.
 */
final class PreflightReport
{
    /** @var array<int, PreflightIssue> */
    private array $issues = [];

    public function add(PreflightIssue $issue): void
    {
        $this->issues[] = $issue;
    }

    /**
     * @return array<int, PreflightIssue>
     */
    public function issues(): array
    {
        return $this->issues;
    }

    /**
     * @return array<int, PreflightIssue>
     */
    public function blockers(): array
    {
        return $this->bySeverity(PreflightIssue::BLOCKER);
    }

    /**
     * @return array<int, PreflightIssue>
     */
    public function warnings(): array
    {
        return $this->bySeverity(PreflightIssue::WARNING);
    }

    /**
     * @return array<int, PreflightIssue>
     */
    public function info(): array
    {
        return $this->bySeverity(PreflightIssue::INFO);
    }

    public function hasBlockers(): bool
    {
        return $this->blockers() !== [];
    }

    public function canGenerate(): bool
    {
        return ! $this->hasBlockers();
    }

    /**
     * @return array<int, PreflightIssue>
     */
    public function withCode(string $code): array
    {
        return array_values(array_filter($this->issues, fn (PreflightIssue $i) => $i->code === $code));
    }

    public function has(string $code): bool
    {
        return $this->withCode($code) !== [];
    }

    /**
     * @return array<int, string>
     */
    public function codes(): array
    {
        return array_values(array_unique(array_map(fn (PreflightIssue $i) => $i->code, $this->issues)));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'can_generate' => $this->canGenerate(),
            'blockers' => array_map(fn (PreflightIssue $i) => $i->toArray(), $this->blockers()),
            'warnings' => array_map(fn (PreflightIssue $i) => $i->toArray(), $this->warnings()),
            'info' => array_map(fn (PreflightIssue $i) => $i->toArray(), $this->info()),
        ];
    }

    /**
     * @return array<int, PreflightIssue>
     */
    private function bySeverity(string $severity): array
    {
        return array_values(array_filter($this->issues, fn (PreflightIssue $i) => $i->severity === $severity));
    }
}
