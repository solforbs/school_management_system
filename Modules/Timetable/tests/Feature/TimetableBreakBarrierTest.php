<?php

namespace Modules\Timetable\Tests\Feature;

use Modules\Timetable\Models\TimetableStructure;
use Tests\TestCase;

/**
 * A double lesson must never span a break. These tests build structures the
 * same way the wizard does (buildCustomConfiguration) and also use legacy
 * saved structures that lack any "after_period" marker on their breaks.
 */
class TimetableBreakBarrierTest extends TestCase
{
    protected function structure(array $answers): TimetableStructure
    {
        return new TimetableStructure([
            'periods_configuration' => TimetableStructure::buildCustomConfiguration($answers),
            'days_of_week' => ['Monday'],
        ]);
    }

    public function test_period_pair_around_a_break_is_not_consecutive(): void
    {
        $structure = $this->structure([
            'daily_periods' => 6,
            'breaks' => [['name' => 'Tea', 'after_period' => 2, 'duration' => 20]],
        ]);

        $this->assertFalse($structure->areConsecutiveTeachingPeriods(2, 3));
        $this->assertFalse($structure->areConsecutiveTeachingPeriods(3, 2));
        $this->assertTrue($structure->areConsecutiveTeachingPeriods(1, 2));
        $this->assertTrue($structure->areConsecutiveTeachingPeriods(3, 4));
    }

    public function test_every_break_position_blocks_only_its_own_boundary(): void
    {
        for ($breakAfter = 1; $breakAfter <= 5; $breakAfter++) {
            $structure = $this->structure([
                'daily_periods' => 6,
                'breaks' => [['name' => 'Break', 'after_period' => $breakAfter, 'duration' => 15]],
            ]);

            for ($p = 1; $p < 6; $p++) {
                $this->assertSame(
                    $p !== $breakAfter,
                    $structure->areConsecutiveTeachingPeriods($p, $p + 1),
                    "break after {$breakAfter}, pair {$p}-".($p + 1)
                );
            }
        }
    }

    public function test_multiple_breaks_block_each_boundary(): void
    {
        $structure = $this->structure([
            'daily_periods' => 8,
            'breaks' => [
                ['name' => 'Short', 'after_period' => 2, 'duration' => 10],
                ['name' => 'Tea', 'after_period' => 4, 'duration' => 30],
                ['name' => 'Lunch', 'after_period' => 6, 'duration' => 60],
            ],
        ]);

        foreach ([2, 4, 6] as $blocked) {
            $this->assertFalse($structure->areConsecutiveTeachingPeriods($blocked, $blocked + 1));
        }
        foreach ([1, 3, 5, 7] as $open) {
            $this->assertTrue($structure->areConsecutiveTeachingPeriods($open, $open + 1));
        }
    }

    public function test_legacy_structure_without_after_period_marker_is_still_protected(): void
    {
        $legacy = new TimetableStructure([
            'periods_configuration' => [
                ['period_number' => 1, 'start_time' => '08:00', 'end_time' => '08:40', 'is_break' => false],
                ['period_number' => 2, 'start_time' => '08:40', 'end_time' => '09:20', 'is_break' => false],
                ['period_number' => 0, 'name' => 'Tea', 'start_time' => '09:20', 'end_time' => '09:40', 'is_break' => true],
                ['period_number' => 3, 'start_time' => '09:40', 'end_time' => '10:20', 'is_break' => false],
            ],
        ]);

        $this->assertFalse($legacy->areConsecutiveTeachingPeriods(2, 3));
        $this->assertTrue($legacy->areConsecutiveTeachingPeriods(1, 2));
    }

    public function test_non_adjacent_or_unknown_periods_are_never_consecutive(): void
    {
        $structure = $this->structure(['daily_periods' => 4, 'breaks' => []]);

        $this->assertFalse($structure->areConsecutiveTeachingPeriods(1, 3));
        $this->assertFalse($structure->areConsecutiveTeachingPeriods(2, 2));
        $this->assertFalse($structure->areConsecutiveTeachingPeriods(4, 5));
        $this->assertFalse($structure->areConsecutiveTeachingPeriods(0, 1));
    }

    public function test_assembly_before_first_period_does_not_affect_pairs(): void
    {
        $structure = $this->structure([
            'daily_periods' => 3,
            'has_assembly' => true,
            'breaks' => [],
        ]);

        $this->assertTrue($structure->areConsecutiveTeachingPeriods(1, 2));
        $this->assertTrue($structure->areConsecutiveTeachingPeriods(2, 3));
    }

    public function test_built_configuration_records_which_period_each_break_follows(): void
    {
        $config = TimetableStructure::buildCustomConfiguration([
            'daily_periods' => 4,
            'breaks' => [['name' => 'Tea', 'after_period' => 2, 'duration' => 20]],
        ]);

        $breaks = array_values(array_filter($config, fn ($i) => ! empty($i['is_break'])));
        $this->assertCount(1, $breaks);
        $this->assertSame(2, $breaks[0]['after_period']);
    }
}
