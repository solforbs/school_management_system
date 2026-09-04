<?php

namespace Modules\Result\Console\Commands;

use Illuminate\Console\Command;
use Modules\Institution\Models\Institution;
use Modules\Result\Models\Result;
use Modules\Result\Services\ResultGrader;

/**
 * Recompute the letter/level grade on stored results.
 *
 * A grade is worked out from the scale the result's class runs on - 8-4-4
 * letters or CBC expectation levels - at the moment the mark is saved. When
 * a class is moved onto a curriculum, or a scale is added or edited after
 * marks were already entered, the stored grades go stale; this walks them
 * and writes the grade each mark should now carry.
 */
class RegradeResults extends Command
{
    protected $signature = 'results:regrade
        {--institution= : Only results for this institution id}
        {--class= : Only results for this class id}
        {--dry-run : Show what would change without saving}';

    protected $description = 'Recompute stored result grades against each class\'s current grading scale (8-4-4 or CBC)';

    public function handle(): int
    {
        $query = Result::query()
            ->with(['examination', 'schoolClass'])
            ->when($this->option('institution'), fn ($q, $id) => $q->where('institution_id', $id))
            ->when($this->option('class'), fn ($q, $id) => $q->where('class_id', $id));

        $total = $query->count();

        if ($total === 0) {
            $this->info('No results matched.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $institutions = [];
        $changed = 0;
        $cleared = 0;

        $this->withProgressBar($query->cursor(), function (Result $result) use (&$institutions, &$changed, &$cleared, $dryRun) {
            $institution = $institutions[$result->institution_id]
                ??= Institution::find($result->institution_id);

            if (! $institution || ! $result->examination) {
                return;
            }

            $grade = ResultGrader::grade(
                $result->examination,
                (float) $result->marks_obtained,
                $institution,
                $result->schoolClass,
            );

            if ($grade === $result->grade) {
                return;
            }

            if ($grade === null) {
                $cleared++;
            } else {
                $changed++;
            }

            if (! $dryRun) {
                $result->forceFill(['grade' => $grade])->saveQuietly();
            }
        });

        $this->newLine(2);
        $this->info(sprintf(
            '%s%d of %d result(s): %d re-graded, %d left ungraded (no scale covers them).',
            $dryRun ? '[dry run] ' : '',
            $changed + $cleared,
            $total,
            $changed,
            $cleared,
        ));

        return self::SUCCESS;
    }
}
