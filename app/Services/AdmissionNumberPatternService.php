<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AdmissionNumberPatternService
{
    public const CACHE_TTL = 1800; // 30 minutes

    /**
     * Parse an admission number string into its prefix, numeric sequence, padding, and suffix.
     *
     * Examples:
     * - "FAHAN-014-2025" -> prefix: "FAHAN-", number: 14, padding: 3, suffix: "-2025", template: "FAHAN-{NUM}-2025"
     * - "ADM/2026/001"   -> prefix: "ADM/2026/", number: 1, padding: 3, suffix: "", template: "ADM/2026/{NUM}"
     * - "GVA-10-2026"    -> prefix: "GVA-", number: 10, padding: 2, suffix: "-2026", template: "GVA-{NUM}-2026"
     * - "1042"           -> prefix: "", number: 1042, padding: 4, suffix: "", template: "{NUM}"
     * - "ADM-005"        -> prefix: "ADM-", number: 5, padding: 3, suffix: "", template: "ADM-{NUM}"
     *
     * @return array{prefix: string, number_raw: string, number: int, padding: int, suffix: string, template: string}|null
     */
    public function parseAdmissionNumber(string $adm): ?array
    {
        $adm = trim($adm);
        if ($adm === '') {
            return null;
        }

        // Pattern 1: [Prefix]-[Number]-[Year] (e.g. FAHAN-014-2025 or STD-05-2026 or GVA-1-2025)
        if (preg_match('/^([A-Za-z0-9_\/]+[\-\/])(\d+)([\-\/](?:19|20)\d{2})$/', $adm, $m)) {
            return [
                'prefix' => $m[1],
                'number_raw' => $m[2],
                'number' => (int) $m[2],
                'padding' => strlen($m[2]),
                'suffix' => $m[3],
                'template' => $m[1].'{NUM}'.$m[3],
            ];
        }

        // Pattern 2: [Prefix][Number] (e.g. ADM-001, GVA/2026/001, STD105, 1042)
        if (preg_match('/^(.*?)(\d+)$/', $adm, $m)) {
            return [
                'prefix' => $m[1],
                'number_raw' => $m[2],
                'number' => (int) $m[2],
                'padding' => strlen($m[2]),
                'suffix' => '',
                'template' => $m[1].'{NUM}',
            ];
        }

        return null;
    }

    /**
     * Format a complete admission number from constituent parts.
     */
    public function formatAdmissionNumber(string $prefix, int $number, int $padding, string $suffix = ''): string
    {
        $numStr = str_pad((string) $number, $padding, '0', STR_PAD_LEFT);

        return $prefix.$numStr.$suffix;
    }

    /**
     * Find the highest admission number from a collection/list of admission numbers.
     * Accurately determines the highest numeric sequence (e.g. Fahan-021-2025 > Fahan-017-2025).
     *
     * @param  iterable<mixed>  $admissionNumbers
     */
    public function findHighestAdmission(iterable $admissionNumbers): ?string
    {
        $valid = [];
        foreach ($admissionNumbers as $adm) {
            if ($adm !== null && trim((string) $adm) !== '') {
                $valid[] = trim((string) $adm);
            }
        }

        if (empty($valid)) {
            return null;
        }

        /** @var array<string, array{highest_number: int, highest_admission: string, count: int, year: int|null}> $groups */
        $groups = [];
        $unparsed = [];

        foreach ($valid as $adm) {
            $parsed = $this->parseAdmissionNumber($adm);
            if (! $parsed) {
                $unparsed[] = $adm;

                continue;
            }

            $tmpl = $parsed['template'];
            if (! isset($groups[$tmpl])) {
                $year = null;
                if (preg_match('/(?:19|20)\d{2}/', $tmpl, $ym)) {
                    $year = (int) $ym[0];
                }

                $groups[$tmpl] = [
                    'highest_number' => $parsed['number'],
                    'highest_admission' => $adm,
                    'count' => 0,
                    'year' => $year,
                ];
            }

            $groups[$tmpl]['count']++;
            if ($parsed['number'] >= $groups[$tmpl]['highest_number']) {
                $groups[$tmpl]['highest_number'] = $parsed['number'];
                $groups[$tmpl]['highest_admission'] = $adm;
            }
        }

        if (! empty($groups)) {
            // Sort groups: highest year first, then highest count, then highest number
            uasort($groups, function ($a, $b) {
                if ($a['year'] !== null && $b['year'] !== null && $a['year'] !== $b['year']) {
                    return $b['year'] <=> $a['year'];
                }

                if ($a['count'] !== $b['count']) {
                    return $b['count'] <=> $a['count'];
                }

                return $b['highest_number'] <=> $a['highest_number'];
            });

            $topGroup = reset($groups);

            return $topGroup['highest_admission'];
        }

        // If none could be parsed with regex, perform natural descending sort
        usort($unparsed, fn ($a, $b) => strnatcasecmp($b, $a));

        return $unparsed[0] ?? null;
    }

    /**
     * Detect the school's predominant admission number pattern, highest number used, and next suggested number.
     * Strictly isolated to the given institution (and optionally scoped to a class).
     *
     * @return array{
     *     status: 'consistent'|'inconsistent'|'none',
     *     total_records: int,
     *     predominant_pattern: array{
     *         template: string,
     *         prefix: string,
     *         suffix: string,
     *         padding: int,
     *         highest_number: int,
     *         highest_admission: string,
     *         next_number: int,
     *         next_admission: string
     *     }|null,
     *     detected_patterns: array<string, array{
     *         template: string,
     *         prefix: string,
     *         suffix: string,
     *         padding: int,
     *         count: int,
     *         highest_number: int,
     *         highest_admission: string,
     *         next_admission: string
     *     }>
     * }
     */
    public function detectPattern(int $institutionId, ?int $classId = null): array
    {
        $query = DB::table('student_details')
            ->where('institution_id', $institutionId)
            ->whereNotNull('admission_number')
            ->where('admission_number', '!=', '')
            ->whereNull('deleted_at');

        if ($classId) {
            $query->where('class_id', $classId);
        }

        $admissions = $query->pluck('admission_number');

        if ($admissions->isEmpty()) {
            $defaultYear = now()->year;
            $defaultNext = "ADM-{$defaultYear}-001";

            return [
                'status' => 'none',
                'total_records' => 0,
                'predominant_pattern' => [
                    'template' => "ADM-{$defaultYear}-{NUM}",
                    'prefix' => "ADM-{$defaultYear}-",
                    'suffix' => '',
                    'padding' => 3,
                    'highest_number' => 0,
                    'highest_admission' => 'None',
                    'next_number' => 1,
                    'next_admission' => $defaultNext,
                ],
                'detected_patterns' => [],
            ];
        }

        /** @var array<string, array{template: string, prefix: string, suffix: string, padding: int, count: int, highest_number: int, highest_admission: string, numbers: array<int, int>}> $groups */
        $groups = [];

        foreach ($admissions as $adm) {
            $parsed = $this->parseAdmissionNumber((string) $adm);
            if (! $parsed) {
                continue;
            }

            $template = $parsed['template'];
            if (! isset($groups[$template])) {
                $groups[$template] = [
                    'template' => $template,
                    'prefix' => $parsed['prefix'],
                    'suffix' => $parsed['suffix'],
                    'padding' => $parsed['padding'],
                    'count' => 0,
                    'highest_number' => 0,
                    'highest_admission' => (string) $adm,
                    'numbers' => [],
                ];
            }

            $groups[$template]['count']++;
            $groups[$template]['numbers'][] = $parsed['number'];
            $groups[$template]['padding'] = max($groups[$template]['padding'], $parsed['padding']);

            if ($parsed['number'] >= $groups[$template]['highest_number']) {
                $groups[$template]['highest_number'] = $parsed['number'];
                $groups[$template]['highest_admission'] = (string) $adm;
            }
        }

        if (empty($groups)) {
            return [
                'status' => 'none',
                'total_records' => $admissions->count(),
                'predominant_pattern' => null,
                'detected_patterns' => [],
            ];
        }

        $currentYearStr = (string) now()->year;

        // Rank templates: prioritize current academic year in suffix/prefix, then highest count
        uasort($groups, function ($a, $b) use ($currentYearStr) {
            $aHasCurrentYear = str_contains($a['template'], $currentYearStr);
            $bHasCurrentYear = str_contains($b['template'], $currentYearStr);

            if ($aHasCurrentYear && ! $bHasCurrentYear) {
                return -1;
            }
            if (! $aHasCurrentYear && $bHasCurrentYear) {
                return 1;
            }

            return $b['count'] <=> $a['count'];
        });

        // Add next admission number to each group
        $detectedPatterns = [];
        foreach ($groups as $tmpl => $info) {
            $nextNum = $this->findNextAvailableNumber(
                $institutionId,
                $info['prefix'],
                $info['highest_number'] + 1,
                $info['padding'],
                $info['suffix']
            );

            $detectedPatterns[$tmpl] = [
                'template' => $tmpl,
                'prefix' => $info['prefix'],
                'suffix' => $info['suffix'],
                'padding' => $info['padding'],
                'count' => $info['count'],
                'highest_number' => $info['highest_number'],
                'highest_admission' => $info['highest_admission'],
                'next_number' => $nextNum,
                'next_admission' => $this->formatAdmissionNumber($info['prefix'], $nextNum, $info['padding'], $info['suffix']),
            ];
        }

        $topTemplate = (string) array_key_first($detectedPatterns);
        $topPattern = $detectedPatterns[$topTemplate];

        $totalParsed = array_sum(array_column($detectedPatterns, 'count'));
        $consistencyRatio = $totalParsed > 0 ? ($topPattern['count'] / $totalParsed) : 0;
        $status = $consistencyRatio >= 0.70 ? 'consistent' : 'inconsistent';

        return [
            'status' => $status,
            'total_records' => $admissions->count(),
            'predominant_pattern' => $topPattern,
            'detected_patterns' => $detectedPatterns,
        ];
    }

    /**
     * Find next number that does NOT collide with any existing admission number in the institution.
     */
    public function findNextAvailableNumber(int $institutionId, string $prefix, int $startNumber, int $padding, string $suffix = ''): int
    {
        $num = $startNumber;

        while (true) {
            $candidate = $this->formatAdmissionNumber($prefix, $num, $padding, $suffix);

            $exists = DB::table('student_details')
                ->where('institution_id', $institutionId)
                ->where('admission_number', $candidate)
                ->exists();

            if (! $exists) {
                return $num;
            }

            $num++;
        }
    }

    /**
     * Generate a collision-free sequential list of admission numbers for a given institution.
     *
     * @param  array{prefix?: string, padding?: int, suffix?: string, start_number?: int}|null  $override
     * @return array<int, string>
     */
    public function generateNextSequence(int $institutionId, int $count, ?array $override = null): array
    {
        if ($count <= 0) {
            return [];
        }

        if ($override && isset($override['prefix'])) {
            $prefix = (string) $override['prefix'];
            $suffix = (string) ($override['suffix'] ?? '');
            $padding = (int) ($override['padding'] ?? 3);
            $startNumber = (int) ($override['start_number'] ?? 1);
        } else {
            $patternInfo = $this->detectPattern($institutionId);
            $pred = $patternInfo['predominant_pattern'];

            if ($pred) {
                $prefix = $pred['prefix'];
                $suffix = $pred['suffix'];
                $padding = $pred['padding'];
                $startNumber = $pred['next_number'];
            } else {
                $prefix = 'ADM-'.now()->year.'-';
                $suffix = '';
                $padding = 3;
                $startNumber = 1;
            }
        }

        $sequence = [];
        $currentNumber = $startNumber;

        // Fetch existing admission numbers in institution to prevent collisions
        $existing = DB::table('student_details')
            ->where('institution_id', $institutionId)
            ->whereNotNull('admission_number')
            ->where('admission_number', '!=', '')
            ->pluck('admission_number')
            ->flip()
            ->all();

        while (count($sequence) < $count) {
            $candidate = $this->formatAdmissionNumber($prefix, $currentNumber, $padding, $suffix);
            $currentNumber++;

            if (! isset($existing[$candidate])) {
                $sequence[] = $candidate;
                $existing[$candidate] = true;
            }
        }

        return $sequence;
    }

    /**
     * Pre-populate missing admission numbers in bulk rows using the detected sequence.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    public function suggestForRows(int $institutionId, array $rows, bool $fillBlanksOnly = true): array
    {
        $blankIndices = [];
        foreach ($rows as $i => $row) {
            $hasName = trim((string) ($row['name'] ?? '')) !== '';
            $adm = trim((string) ($row['admission_number'] ?? ''));

            if ($fillBlanksOnly) {
                if ($hasName && $adm === '') {
                    $blankIndices[] = $i;
                }
            } else {
                if ($hasName) {
                    $blankIndices[] = $i;
                }
            }
        }

        if (empty($blankIndices)) {
            return $rows;
        }

        $generated = $this->generateNextSequence($institutionId, count($blankIndices));

        foreach ($blankIndices as $idx => $rowIdx) {
            $rows[$rowIdx]['admission_number'] = $generated[$idx] ?? '';
        }

        return $rows;
    }
}
