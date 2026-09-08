<?php

namespace Modules\Examinations\Database\Seeders;

use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Modules\Classes\Models\SchoolClass;
use Modules\Examinations\Models\Examination;
use Modules\Institution\Models\Institution;
use Modules\Subject\Models\Subject;

/**
 * A full week of examinations for every class in the system, laid out so the
 * exam timetable screen and PDF render as a clean grid: Monday to Friday,
 * three fixed sittings a day, a different subject in every slot.
 *
 * Written to run against a live database - it discovers the real
 * institutions, classes and subjects rather than creating any, and every
 * paper is written with updateOrCreate, so re-running it just shifts the
 * same sitting onto the next week instead of piling up duplicates.
 */
class ExamTimetableSeeder extends Seeder
{
    /**
     * The term label and sitting the seeded papers belong to. The exam
     * timetable groups papers by (term, year, sitting), so keeping these
     * fixed is what makes the seeded set read as one coherent timetable.
     */
    private const TERM = 'Term 2';

    /** Must be a key of {@see Examination::EXAM_TYPES}. */
    private const EXAM_TYPE = 'end_term';

    /**
     * The three daily sittings, identical every day so the grid has three
     * tidy columns. The last one is a shorter, lower-mark paper.
     *
     * @var list<array{start: string, end: string, marks: int}>
     */
    private const SESSIONS = [
        ['start' => '08:00', 'end' => '09:30', 'marks' => 100],
        ['start' => '10:30', 'end' => '12:00', 'marks' => 100],
        ['start' => '14:00', 'end' => '15:00', 'marks' => 60],
    ];

    /** Monday to Friday. */
    private const DAYS = 5;

    /**
     * Subject names used only for an institution that has none of its own
     * yet, so its classes still get a complete timetable (with no subject
     * link, on the free-text name).
     *
     * @var list<string>
     */
    private const FALLBACK_SUBJECTS = [
        'Mathematics', 'English', 'Kiswahili', 'Biology', 'Chemistry',
        'Physics', 'Geography', 'History and Government',
        'Christian Religious Education', 'Business Studies', 'Agriculture',
        'Computer Studies', 'Home Science', 'Art and Design', 'Music',
    ];

    public function run(): void
    {
        $weekStart = $this->examWeekStart();
        $year = $weekStart->year;
        $papersPerClass = self::DAYS * count(self::SESSIONS);

        $written = 0;
        $classesSeeded = 0;

        foreach (Institution::query()->orderBy('id')->get() as $institution) {
            $classes = SchoolClass::query()
                ->where('institution_id', $institution->id)
                ->orderBy('name')
                ->get();

            if ($classes->isEmpty()) {
                continue;
            }

            $papers = $this->papersFor($institution, $papersPerClass);

            foreach ($classes as $class) {
                $written += $this->seedClass($institution, $class, $papers, $weekStart, $year);
                $classesSeeded++;
            }
        }

        $this->command?->info(sprintf(
            'Exam timetable seeded: %d paper(s) across %d class(es) for the week of %s (%s, %s).',
            $written,
            $classesSeeded,
            $weekStart->toFormattedDateString(),
            self::TERM,
            Examination::EXAM_TYPES[self::EXAM_TYPE],
        ));
    }

    /**
     * The Monday of next week. Anchoring to next week keeps the seeded
     * sitting reading as upcoming no matter which day the seeder is run.
     */
    private function examWeekStart(): CarbonInterface
    {
        return Carbon::today()->startOfWeek(Carbon::MONDAY)->addWeek();
    }

    /**
     * The ordered list of papers each class in this institution sits, one
     * per timetable slot. Real active subjects are used first (compulsory
     * subjects leading); only if the institution has none do the fallback
     * names fill in. When there are fewer subjects than slots the list wraps
     * onto "Paper 2", "Paper 3" - and since the wrap only happens after
     * every subject has been placed once, a subject's later papers always
     * land on a different day.
     *
     * @return list<array{subject_id: int|null, name: string, paper: int}>
     */
    private function papersFor(Institution $institution, int $slots): array
    {
        $subjects = Subject::query()
            ->where('institution_id', $institution->id)
            ->where('is_active', true)
            ->orderByDesc('is_compulsory')
            ->orderBy('name')
            ->get()
            ->map(fn (Subject $subject): array => [
                'subject_id' => $subject->id,
                'name' => $subject->name,
            ])
            ->all();

        if ($subjects === []) {
            $subjects = array_map(
                fn (string $name): array => ['subject_id' => null, 'name' => $name],
                self::FALLBACK_SUBJECTS,
            );
        }

        $papers = [];
        $round = 1;

        while (count($papers) < $slots) {
            foreach ($subjects as $subject) {
                if (count($papers) >= $slots) {
                    break;
                }

                $papers[] = [...$subject, 'paper' => $round];
            }

            $round++;
        }

        return $papers;
    }

    /**
     * Write one class's week of papers, slot by slot: slot 0 is Monday's
     * first sitting, slot 3 Tuesday's first, and so on.
     *
     * @param  list<array{subject_id: int|null, name: string, paper: int}>  $papers
     */
    private function seedClass(
        Institution $institution,
        SchoolClass $class,
        array $papers,
        CarbonInterface $weekStart,
        int $year,
    ): int {
        $sessionCount = count(self::SESSIONS);
        $written = 0;

        foreach ($papers as $slot => $paper) {
            $session = self::SESSIONS[$slot % $sessionCount];
            $date = $weekStart->copy()->addDays(intdiv($slot, $sessionCount));

            $title = $paper['paper'] > 1
                ? "{$paper['name']} Paper {$paper['paper']}"
                : $paper['name'];

            Examination::updateOrCreate(
                [
                    'institution_id' => $institution->id,
                    'class_id' => $class->id,
                    'term' => self::TERM,
                    'academic_year' => $year,
                    'title' => $title,
                ],
                [
                    'subject_id' => $paper['subject_id'],
                    'subject_name' => $paper['name'],
                    'class_name' => $class->name,
                    'exam_type' => self::EXAM_TYPE,
                    'exam_date' => $date->toDateString(),
                    'start_time' => $session['start'],
                    'end_time' => $session['end'],
                    'total_marks' => $session['marks'],
                    'passing_marks' => (int) round($session['marks'] * 0.4),
                ],
            );

            $written++;
        }

        return $written;
    }
}
