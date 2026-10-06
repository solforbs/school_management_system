<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Student\Actions\SaveStudent;
use Modules\Student\Models\StudentDetails;

class LinkGuardianParents extends Command
{
    protected $signature = 'students:link-guardian-parents {--institution= : Limit to one institution id}';

    protected $description = 'Create/link Parent accounts for students that have a guardian name/phone (e.g. from bulk upload) but no parent_id.';

    public function handle(SaveStudent $saveStudent): int
    {
        $linked = 0;

        StudentDetails::query()
            ->whereNull('parent_id')
            ->where(fn ($q) => $q->whereNotNull('guardian_name')->orWhereNotNull('guardian_phone'))
            ->when($this->option('institution'), fn ($q, $id) => $q->where('institution_id', $id))
            ->chunkById(100, function ($students) use ($saveStudent, &$linked) {
                foreach ($students as $student) {
                    $saveStudent->syncParentFromGuardian($student);
                    $student->refresh()->parent_id && $linked++;
                }
            });

        $this->info("Linked {$linked} student(s) to parent accounts.");

        return self::SUCCESS;
    }
}
