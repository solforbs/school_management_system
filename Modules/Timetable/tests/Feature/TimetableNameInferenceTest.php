<?php

namespace Modules\Timetable\Tests\Feature;

use Modules\Timetable\Models\SubjectAllocation;
use Tests\TestCase;

/**
 * Subject-name inference matches whole words from config/timetable.php,
 * so unrelated names that merely contain a short keyword are not misread.
 */
class TimetableNameInferenceTest extends TestCase
{
    public function test_core_subjects_are_high_weight(): void
    {
        foreach (['Mathematics', 'Maths', 'English Language', 'Kiswahili', 'Integrated Science', 'Physics', 'Chemistry', 'Biology'] as $name) {
            $this->assertSame('high', SubjectAllocation::inferWeight($name), $name);
        }
    }

    public function test_activity_subjects_are_low_weight(): void
    {
        foreach (['PE', 'P.E', 'Physical Education', 'Physical and Health Education', 'Music', 'Creative Arts', 'Art and Craft', 'Life Skills'] as $name) {
            $this->assertSame('low', SubjectAllocation::inferWeight($name), $name);
        }
    }

    public function test_short_keywords_do_not_match_inside_longer_words(): void
    {
        $this->assertSame('medium', SubjectAllocation::inferWeight('Special Needs Support'));
        $this->assertSame('medium', SubjectAllocation::inferWeight('Participation'));
        $this->assertSame('medium', SubjectAllocation::inferWeight('Pepper Farming'));
        $this->assertSame(3, SubjectAllocation::inferDefaultPeriods('Special Needs Support'));
    }

    public function test_unknown_subject_uses_the_configured_fallbacks(): void
    {
        config(['timetable.fallback_periods_per_week' => 7, 'timetable.fallback_cognitive_weight' => 'low']);

        $this->assertSame(7, SubjectAllocation::inferDefaultPeriods('Underwater Basket Weaving'));
        $this->assertSame('low', SubjectAllocation::inferWeight('Underwater Basket Weaving'));
    }

    public function test_default_periods_follow_the_configured_tiers(): void
    {
        $this->assertSame(5, SubjectAllocation::inferDefaultPeriods('Mathematics'));
        $this->assertSame(5, SubjectAllocation::inferDefaultPeriods('English'));
        $this->assertSame(4, SubjectAllocation::inferDefaultPeriods('Integrated Science'));
        $this->assertSame(3, SubjectAllocation::inferDefaultPeriods('Social Studies'));
        $this->assertSame(2, SubjectAllocation::inferDefaultPeriods('Music'));
    }

    public function test_changing_config_changes_inference(): void
    {
        config(['timetable.default_periods' => [9 => ['music']]]);

        $this->assertSame(9, SubjectAllocation::inferDefaultPeriods('Music'));
    }

    public function test_matching_is_case_insensitive_and_trims_whitespace(): void
    {
        $this->assertSame('high', SubjectAllocation::inferWeight('  MATHEMATICS  '));
    }

    public function test_prefix_keyword_matches_word_stems_only(): void
    {
        $this->assertTrue(SubjectAllocation::nameMatches('Mathematics', ['math*']));
        $this->assertTrue(SubjectAllocation::nameMatches('Pure Maths', ['math*']));
        $this->assertFalse(SubjectAllocation::nameMatches('Aftermath Studies', ['math*']));
    }

    public function test_blank_keywords_are_ignored(): void
    {
        $this->assertFalse(SubjectAllocation::nameMatches('Anything', ['', '  ']));
    }

    public function test_double_lessons_need_both_a_suitable_subject_and_enough_periods(): void
    {
        $this->assertTrue(SubjectAllocation::inferAllowDouble('Mathematics', 4));
        $this->assertFalse(SubjectAllocation::inferAllowDouble('Mathematics', 3));
        $this->assertFalse(SubjectAllocation::inferAllowDouble('Social Studies', 5));
        $this->assertTrue(SubjectAllocation::inferAllowDouble('Computer Studies', 4));
    }

    public function test_double_threshold_is_configurable(): void
    {
        config(['timetable.double_min_periods' => 2]);

        $this->assertTrue(SubjectAllocation::inferAllowDouble('Mathematics', 2));
    }
}
