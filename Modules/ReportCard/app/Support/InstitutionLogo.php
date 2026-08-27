<?php

namespace Modules\ReportCard\Support;

use Illuminate\Support\Facades\Storage;

/**
 * The school's logo inlined as a data URI.
 *
 * DomPDF fetches nothing over the network while rendering, so a logo has
 * to travel inside the HTML or it simply doesn't appear on the report.
 */
class InstitutionLogo
{
    public static function dataUri($institution): ?string
    {
        if (! $institution?->logo || ! Storage::disk('public')->exists($institution->logo)) {
            return null;
        }

        return 'data:'.Storage::disk('public')->mimeType($institution->logo)
            .';base64,'.base64_encode(Storage::disk('public')->get($institution->logo));
    }
}
