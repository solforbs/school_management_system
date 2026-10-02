<?php

namespace Modules\Curriculum\Services;

use Illuminate\Support\Collection;
use Modules\ReportCard\Support\SubjectRow;

/**
 * Service to evaluate and recommend Senior School (Grades 10–12) career pathways
 * based on learner competencies in Junior School (Grades 7–9) under KNEC's CBA framework.
 *
 * Pathways:
 * 1. Science, Technology, Engineering & Mathematics (STEM)
 * 2. Social Sciences / Humanities
 * 3. Arts and Sports Science
 */
class CbcPathwayService
{
    public const PATHWAY_STEM = 'stem';

    public const PATHWAY_SOCIAL_SCIENCES = 'social_sciences';

    public const PATHWAY_ARTS_SPORTS = 'arts_sports';

    public const PATHWAYS = [
        self::PATHWAY_STEM => [
            'id' => 'stem',
            'name' => 'Science, Technology, Engineering & Mathematics (STEM)',
            'short_name' => 'STEM',
            'badge' => 'STEM Pathway',
            'description' => 'Focuses on pure & applied sciences, engineering, ICT, computing, technical, and agricultural careers.',
            'tracks' => ['Pure Sciences', 'Applied Sciences', 'Technical & Engineering', 'Career & Technology Studies (CTS)'],
            'keywords' => [
                'math', 'mathematics', 'science', 'integrated science', 'pre-tech', 'pre-technical',
                'computer', 'ict', 'computing', 'agriculture', 'health education', 'nutrition',
                'physics', 'chemistry', 'biology', 'technology',
            ],
        ],
        self::PATHWAY_SOCIAL_SCIENCES => [
            'id' => 'social_sciences',
            'name' => 'Social Sciences / Humanities',
            'short_name' => 'Social Sciences',
            'badge' => 'Social Sciences Pathway',
            'description' => 'Focuses on languages, literature, governance, law, diplomacy, business, entrepreneurship, and humanities.',
            'tracks' => ['Languages & Literature', 'Humanities & Social Studies', 'Business & Entrepreneurship'],
            'keywords' => [
                'english', 'kiswahili', 'kenya sign language', 'ksl', 'social studies', 'history',
                'geography', 'business', 'commerce', 'economics', 'cre', 'ire', 'hre',
                'religious education', 'citizenship', 'financial literacy',
            ],
        ],
        self::PATHWAY_ARTS_SPORTS => [
            'id' => 'arts_sports',
            'name' => 'Arts and Sports Science',
            'short_name' => 'Arts & Sports',
            'badge' => 'Arts & Sports Science Pathway',
            'description' => 'Focuses on visual arts, design, performing arts, theatre, music, athletics, and sports science.',
            'tracks' => ['Visual Arts', 'Performing Arts (Music/Drama)', 'Sports Science & Athletics'],
            'keywords' => [
                'creative art', 'creative arts', 'visual art', 'performing art', 'music', 'drama',
                'physical education', 'pe', 'sport', 'sports', 'athletics', 'art & design',
            ],
        ],
    ];

    /**
     * Determines which pathway a subject/learning area belongs to.
     */
    public function matchPathway(string $subjectName, ?string $learningArea = null, ?string $strand = null): ?string
    {
        $text = strtolower(trim("{$subjectName} {$learningArea} {$strand}"));

        // Match Creative Arts & Sports first
        foreach (self::PATHWAYS[self::PATHWAY_ARTS_SPORTS]['keywords'] as $kw) {
            if (str_contains($text, $kw)) {
                return self::PATHWAY_ARTS_SPORTS;
            }
        }

        // Match STEM
        foreach (self::PATHWAYS[self::PATHWAY_STEM]['keywords'] as $kw) {
            if (str_contains($text, $kw)) {
                return self::PATHWAY_STEM;
            }
        }

        // Match Social Sciences
        foreach (self::PATHWAYS[self::PATHWAY_SOCIAL_SCIENCES]['keywords'] as $kw) {
            if (str_contains($text, $kw)) {
                return self::PATHWAY_SOCIAL_SCIENCES;
            }
        }

        return null;
    }

    /**
     * Analyze a collection of SubjectRow items (from a report card) and compute
     * the career pathway recommendations for Senior School (Grades 10-12).
     *
     * @param  Collection<int, SubjectRow>  $rows
     * @return array<string, mixed>
     */
    public function evaluate(Collection $rows): array
    {
        $clusters = [
            self::PATHWAY_STEM => collect(),
            self::PATHWAY_SOCIAL_SCIENCES => collect(),
            self::PATHWAY_ARTS_SPORTS => collect(),
        ];

        foreach ($rows as $row) {
            if (! $row->isAssessed()) {
                continue;
            }

            $pathwayKey = $this->matchPathway($row->name, $row->learningArea, $row->strand);
            if ($pathwayKey && isset($clusters[$pathwayKey])) {
                $clusters[$pathwayKey]->push($row);
            }
        }

        $evaluatedPathways = [];

        foreach (self::PATHWAYS as $key => $meta) {
            /** @var Collection<int, SubjectRow> $subjectRows */
            $subjectRows = $clusters[$key];
            $count = $subjectRows->count();

            if ($count === 0) {
                $evaluatedPathways[$key] = [
                    'id' => $meta['id'],
                    'name' => $meta['name'],
                    'short_name' => $meta['short_name'],
                    'badge' => $meta['badge'],
                    'description' => $meta['description'],
                    'tracks' => $meta['tracks'],
                    'subjects_count' => 0,
                    'average_points' => null,
                    'readiness' => 'No Data',
                    'readiness_level' => 'neutral',
                    'strengths' => [],
                ];

                continue;
            }

            $totalPoints = $subjectRows->sum(fn (SubjectRow $r) => $r->points() ?? 0);
            $avgPoints = round($totalPoints / $count, 2);

            // Readiness criteria based on CBA points (scale 1 to 8):
            // >= 6.5 (EE1, EE2): High Aptitude / Strongly Recommended
            // >= 5.0 (ME1, ME2): Competent / Recommended
            // < 5.0 (AE, BE): Developing / Foundational
            [$readiness, $readinessLevel] = match (true) {
                $avgPoints >= 6.5 => ['Strongly Recommended (High Aptitude)', 'high'],
                $avgPoints >= 5.0 => ['Recommended (Competent)', 'medium'],
                default => ['Foundational / Developing', 'low'],
            };

            // High-achieving subjects (points >= 5)
            $strengths = $subjectRows
                ->filter(fn (SubjectRow $r) => ($r->points() ?? 0) >= 5)
                ->map(fn (SubjectRow $r) => [
                    'subject' => $r->name,
                    'level' => $r->achievementLevel() ?? $r->expectationBand() ?? $r->grade(),
                    'points' => $r->points(),
                ])
                ->values()
                ->all();

            $evaluatedPathways[$key] = [
                'id' => $meta['id'],
                'name' => $meta['name'],
                'short_name' => $meta['short_name'],
                'badge' => $meta['badge'],
                'description' => $meta['description'],
                'tracks' => $meta['tracks'],
                'subjects_count' => $count,
                'average_points' => $avgPoints,
                'readiness' => $readiness,
                'readiness_level' => $readinessLevel,
                'strengths' => $strengths,
            ];
        }

        // Rank pathways with data by average points descending
        $ranked = collect($evaluatedPathways)
            ->filter(fn ($p) => $p['average_points'] !== null)
            ->sortByDesc('average_points')
            ->values();

        $primary = $ranked->first();
        $secondary = $ranked->get(1);

        $statement = $this->buildRecommendationStatement($primary, $secondary);

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'pathways' => $evaluatedPathways,
            'statement' => $statement,
            'has_recommendations' => $ranked->isNotEmpty(),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $primary
     * @param  array<string, mixed>|null  $secondary
     */
    private function buildRecommendationStatement(?array $primary, ?array $secondary): string
    {
        if (! $primary || ($primary['average_points'] ?? 0) == 0) {
            return 'Insufficient subject assessments recorded to determine a Senior School pathway recommendation.';
        }

        $pName = $primary['short_name'];
        $pAvg = $primary['average_points'];

        if ($pAvg >= 6.5) {
            $msg = "Learner demonstrates exceptional competency and high aptitude in the {$pName} cluster (Avg: {$pAvg}/8 pts).";
        } elseif ($pAvg >= 5.0) {
            $msg = "Learner meets competencies consistently in the {$pName} cluster (Avg: {$pAvg}/8 pts).";
        } else {
            $msg = "Learner is developing foundational competencies in {$pName} (Avg: {$pAvg}/8 pts).";
        }

        if ($secondary && ($secondary['average_points'] ?? 0) >= 5.0) {
            $sName = $secondary['short_name'];
            $msg .= " Secondary career aptitude identified in {$sName} ({$secondary['average_points']}/8 pts).";
        }

        return $msg;
    }
}
