<?php

namespace App\Services;

/**
 * What a deploy attempt did, in a form the UI can render without
 * re-deriving anything.
 *
 * "Refused" and "failed" are kept apart on purpose: refused means nothing
 * was touched, failed means something was and has been put back. Telling an
 * admin which of those happened is the difference between shrugging and
 * going to look at the server. "needs_attention" is a third case again -
 * the code moved and stayed moved, but a migration did not finish.
 */
final class DeployResult
{
    /**
     * @param  list<string>  $pendingSeeders  seeder classes a release changed, for someone to run by hand
     */
    private function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $commit = null,
        public readonly ?string $subject = null,
        public readonly int $commits = 0,
        public readonly bool $dependenciesChanged = false,
        public readonly ?string $migrations = null,
        public readonly array $pendingSeeders = [],
    ) {}

    /**
     * @param  list<string>  $pendingSeeders
     */
    public static function deployed(
        string $commit,
        string $subject,
        int $commits,
        bool $dependenciesChanged,
        ?string $migrations = null,
        array $pendingSeeders = [],
    ): self {
        return new self(
            status: 'deployed',
            message: sprintf(
                '%s pulled. Now on "%s".',
                $commits === 1 ? '1 change' : $commits.' changes',
                $subject,
            ),
            commit: $commit,
            subject: $subject,
            commits: $commits,
            dependenciesChanged: $dependenciesChanged,
            migrations: $migrations,
            pendingSeeders: $pendingSeeders,
        );
    }

    /**
     * Code was pulled and left in place, but a migration did not finish -
     * the server is in a state only a person should move it out of.
     *
     * @param  list<string>  $pendingSeeders
     */
    public static function needsAttention(
        string $commit,
        string $subject,
        int $commits,
        string $message,
        array $pendingSeeders = [],
    ): self {
        return new self(
            status: 'needs_attention',
            message: $message,
            commit: $commit,
            subject: $subject,
            commits: $commits,
            pendingSeeders: $pendingSeeders,
        );
    }

    /**
     * Seeders were run by hand from the Deployment card.
     *
     * @param  list<string>  $classes
     */
    public static function seeded(array $classes): self
    {
        $names = array_map(fn (string $class) => class_basename($class), $classes);

        return new self(
            status: 'deployed',
            message: count($classes) === 1
                ? $names[0].' seeder ran.'
                : count($classes).' seeders ran: '.implode(', ', $names).'.',
        );
    }

    public static function upToDate(string $commit, string $subject): self
    {
        return new self(
            status: 'up_to_date',
            message: 'Already up to date.',
            commit: $commit,
            subject: $subject,
        );
    }

    /** Nothing was changed. */
    public static function refused(string $message): self
    {
        return new self(status: 'refused', message: $message);
    }

    /** Something was changed and has been put back. */
    public static function failed(string $message): self
    {
        return new self(status: 'failed', message: $message);
    }

    public function ok(): bool
    {
        return in_array($this->status, ['deployed', 'up_to_date'], true);
    }

    public function toastVariant(): string
    {
        return match ($this->status) {
            'deployed' => 'success',
            'up_to_date' => 'info',
            'refused' => 'warning',
            default => 'danger',
        };
    }
}
