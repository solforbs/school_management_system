<?php

namespace Modules\ReportCard\Support;

use Modules\ReportCard\Models\ReportTemplate;

/**
 * The school's own opening and closing words, with the placeholders filled
 * in.
 *
 * Kept here rather than inline in the PDF service because the settings
 * preview renders the same prose: if the two worked out placeholders
 * separately, a school could tune its wording against a preview that
 * didn't match what parents received.
 */
class ReportCardProse
{
    /**
     * @param  array<string, string>  $tokens  Raw values, keyed by placeholder ("{{term}}").
     * @return array{opening: string, closing: string}
     */
    public static function render(?ReportTemplate $template, array $tokens): array
    {
        $search = array_keys($tokens);
        // Escaped before substitution, and the surrounding text escaped
        // separately below, so a school's own wording can't inject markup
        // into a report and neither can a student's name.
        $replace = array_map('e', array_values($tokens));

        $studentName = $tokens['{{student_name}}'] ?? 'your child';
        $term = $tokens['{{term}}'] ?? 'this term';

        $defaultOpening = "Dear Parent/Guardian, please find below {$studentName}'s report card for {$term}.";
        $defaultClosing = 'Thank you for your continued partnership in your child\'s education.';

        return [
            'opening' => nl2br(str_replace($search, $replace, e($template?->opening_text ?: $defaultOpening))),
            'closing' => nl2br(str_replace($search, $replace, e($template?->closing_text ?: $defaultClosing))),
        ];
    }
}
