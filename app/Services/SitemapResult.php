<?php

namespace App\Services;

/**
 * What a sitemap generation attempt did, in a form the UI can render
 * without re-deriving anything. Mirrors DeployResult's shape for the same
 * reason: an admin-facing action needs "did this work, and what happened"
 * as one value, not a status code plus a log line to go read separately.
 */
final class SitemapResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly string $message,
        public readonly int $urlCount = 0,
    ) {}

    public static function success(int $urlCount): self
    {
        return new self(
            ok: true,
            message: $urlCount === 1 ? '1 page written to the sitemap.' : "{$urlCount} pages written to the sitemap.",
            urlCount: $urlCount,
        );
    }

    public static function refused(string $message): self
    {
        return new self(ok: false, message: $message);
    }

    public static function failed(string $message): self
    {
        return new self(ok: false, message: $message);
    }

    public function toastVariant(): string
    {
        return $this->ok ? 'success' : 'danger';
    }
}
