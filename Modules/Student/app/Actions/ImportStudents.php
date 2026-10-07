<?php

namespace Modules\Student\Actions;

use App\Models\User;
use App\Services\ParentMatchingService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Classes\Models\SchoolClass;
use Modules\Student\Exceptions\StudentLimitException;
use Modules\Student\Models\StudentDetails;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Bulk student enrolment from a spreadsheet.
 *
 * Reads the same layout the directory export produces (Student Name,
 * Admission No, Student No, Class, Gender, Guardian, Guardian Phone,
 * Status), so a school can export, fill in new rows, and upload the file
 * back. Each row is enrolled through SaveStudent so the plan's student
 * ceiling, role assignment, and institution stamping all still apply.
 */
class ImportStudents
{
    /** @var string[] */
    public const COLUMNS = ['name', 'admission_number', 'student_number', 'class_name', 'gender', 'guardian_name', 'guardian_phone', 'enrollment_status'];

    /** @var array<string, string> Header text (normalised) => row key. */
    private const HEADER_MAP = [
        'studentname' => 'name',
        'name' => 'name',
        'fullname' => 'name',
        'admissionno' => 'admission_number',
        'admissionnumber' => 'admission_number',
        'studentno' => 'student_number',
        'studentnumber' => 'student_number',
        'class' => 'class_name',
        'classgrade' => 'class_name',
        'gender' => 'gender',
        'guardian' => 'guardian_name',
        'guardianname' => 'guardian_name',
        'guardianphone' => 'guardian_phone',
        'status' => 'enrollment_status',
        'enrollmentstatus' => 'enrollment_status',
    ];

    /** @var string[] Placeholder values the export writes for blanks. */
    private const BLANKS = ['', 'n/a', 'na', 'not listed', 'unassigned', 'unspecified', 'unknown', 'none'];

    public function __construct(
        private readonly SaveStudent $saveStudent,
        private readonly ParentMatchingService $parentMatcher,
    ) {}

    /**
     * Read an uploaded xlsx/csv into grid rows keyed by COLUMNS.
     *
     * @return array<int, array<string, string>>
     */
    public function parse(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $data = $sheet->toArray(null, true, true, false);

        if ($data === []) {
            return [];
        }

        $columns = $this->mapHeaderRow(array_shift($data));

        $rows = [];

        foreach ($data as $line) {
            $row = array_fill_keys(self::COLUMNS, '');

            foreach ($columns as $index => $key) {
                $value = trim((string) ($line[$index] ?? ''));
                $row[$key] = $this->isBlankPlaceholder($value) ? '' : $value;
            }

            $row['gender'] = strtolower($row['gender']);
            $row['enrollment_status'] = strtolower($row['enrollment_status']);

            if (implode('', $row) !== '') {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Enrol every filled row, reporting per-row failures without letting
     * one bad line abort the rest of the file.
     *
     * @param  array<int, array<string, string>>  $rows
     * @return array{created: int, created_ids: array<int, int>, errors: array<int, string>}
     */
    /**
     * Enrol every filled row, reporting per-row failures without letting
     * one bad line abort the rest of the file.
     *
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, string|int>  $parentDecisions
     * @return array{created: int, created_ids: array<int, int>, errors: array<int, string>}
     */
    public function import(array $rows, int $institutionId, array $parentDecisions = []): array
    {
        $classes = SchoolClass::where('institution_id', $institutionId)->get();

        $created = 0;
        $createdIds = [];
        $errors = [];
        $seenAdmissionNumbers = [];
        $seenStudentNumbers = [];
        $intraBatchParents = [];

        foreach ($rows as $index => $row) {
            $row = array_map(fn ($value) => trim((string) $value), $row);

            // Skip rows that have no name, admission number, or student ID (even if class or status is pre-selected)
            if ($row['name'] === '' && $row['admission_number'] === '' && $row['student_number'] === '') {
                continue;
            }

            if ($row['name'] === '') {
                $errors[$index] = 'Student name is required.';

                continue;
            }

            $classId = null;

            if ($row['class_name'] !== '') {
                $class = $classes->first(fn ($c) => strcasecmp($c->name, $row['class_name']) === 0);

                if (! $class) {
                    $errors[$index] = "Class \"{$row['class_name']}\" was not found in your institution.";

                    continue;
                }

                $classId = $class->id;
            }

            // Check admission number duplicates before saving
            if ($row['admission_number'] !== '') {
                if (isset($seenAdmissionNumbers[$row['admission_number']])) {
                    $firstRowNumber = $seenAdmissionNumbers[$row['admission_number']] + 1;
                    $errors[$index] = "Admission number \"{$row['admission_number']}\" is duplicated in row {$firstRowNumber}. Each student must have a unique admission number.";

                    continue;
                }

                if (StudentDetails::where('admission_number', $row['admission_number'])->exists()) {
                    $errors[$index] = "Admission number \"{$row['admission_number']}\" is already registered to an existing student in the system.";

                    continue;
                }

                $seenAdmissionNumbers[$row['admission_number']] = $index;
            }

            // Check student number duplicates if provided
            if ($row['student_number'] !== '') {
                if (isset($seenStudentNumbers[$row['student_number']])) {
                    $firstRowNumber = $seenStudentNumbers[$row['student_number']] + 1;
                    $errors[$index] = "Student number \"{$row['student_number']}\" is duplicated in row {$firstRowNumber}.";

                    continue;
                }

                if (StudentDetails::where('student_number', $row['student_number'])->exists()) {
                    $errors[$index] = "Student number \"{$row['student_number']}\" is already registered to an existing student in the system.";

                    continue;
                }

                $seenStudentNumbers[$row['student_number']] = $index;
            }

            $status = strtolower($row['enrollment_status']) ?: 'active';
            $admissionNumber = $row['admission_number'] !== '' ? $row['admission_number'] : null;

            $hasGuardian = ! empty($row['guardian_name']) || ! empty($row['guardian_phone']);
            $parent = null;
            if ($hasGuardian) {
                $parent = $this->parentMatcher->resolveParentForBatch(
                    $institutionId,
                    [
                        'guardian_name' => $row['guardian_name'],
                        'guardian_phone' => $row['guardian_phone'],
                        'parent_name' => $row['guardian_name'],
                        'parent_phone' => $row['guardian_phone'],
                    ],
                    $intraBatchParents,
                    $parentDecisions
                );
            }

            try {
                $student = $this->saveStudent->create([
                    'name' => $row['name'],
                    'email' => $this->uniqueEmail($row['name'], $admissionNumber),
                    'password' => Str::random(16),
                    'admission_number' => $admissionNumber,
                    'student_number' => $row['student_number'] ?: null,
                    'class_id' => $classId,
                    'gender' => in_array(strtolower($row['gender']), ['male', 'female', 'other'], true) ? strtolower($row['gender']) : null,
                    'parent_id' => $parent?->id,
                    'guardian_name' => $row['guardian_name'] ?: null,
                    'guardian_phone' => $row['guardian_phone'] ?: null,
                    'parent_name' => $row['guardian_name'] ?: null,
                    'parent_phone' => $row['guardian_phone'] ?: null,
                    'enrollment_status' => $status,
                    'is_active' => $status === 'active',
                ], $institutionId);

                $created++;
                $createdIds[] = $student->id;
            } catch (StudentLimitException $e) {
                $errors[$index] = $e->getMessage();
            } catch (QueryException $e) {
                Log::warning('Student bulk import database exception: '.$e->getMessage());

                if ($e->getCode() === '23000' || (isset($e->errorInfo[1]) && $e->errorInfo[1] === 1062)) {
                    if (str_contains($e->getMessage(), 'admission_number')) {
                        $errors[$index] = "Admission number \"{$admissionNumber}\" is already in use by another student.";
                    } elseif (str_contains($e->getMessage(), 'student_number')) {
                        $errors[$index] = "Student number \"{$row['student_number']}\" is already in use by another student.";
                    } elseif (str_contains($e->getMessage(), 'email')) {
                        $errors[$index] = 'A student account with this email address already exists.';
                    } else {
                        $errors[$index] = 'A student record with these unique details already exists in the system.';
                    }
                } else {
                    $errors[$index] = 'Could not save this student due to a data constraint. Please check the entered details.';
                }
            } catch (Throwable $e) {
                Log::error('Student bulk import unexpected failure: '.$e->getMessage(), ['exception' => $e]);
                $errors[$index] = 'Could not save this student. Please verify the entered details and try again.';
            }
        }

        return ['created' => $created, 'created_ids' => $createdIds, 'errors' => $errors];
    }

    /**
     * Match each header cell to a known column; an unrecognisable header
     * row falls back to the export's column order.
     *
     * @param  array<int, mixed>  $header
     * @return array<int, string>
     */
    private function mapHeaderRow(array $header): array
    {
        $columns = [];

        foreach ($header as $index => $cell) {
            $key = self::HEADER_MAP[strtolower(preg_replace('/[^a-z]/i', '', (string) $cell))] ?? null;

            if ($key !== null) {
                $columns[$index] = $key;
            }
        }

        return $columns !== [] ? $columns : array_combine(range(0, count(self::COLUMNS) - 1), self::COLUMNS);
    }

    private function isBlankPlaceholder(string $value): bool
    {
        return in_array(strtolower($value), self::BLANKS, true);
    }

    /**
     * Uploaded sheets carry no email, but every student needs a login;
     * derive one from the name/admission number and keep it unique.
     */
    private function uniqueEmail(string $name, ?string $admissionNumber = null): string
    {
        $baseSeed = (! empty($admissionNumber)) ? $admissionNumber : $name;
        $base = Str::slug($baseSeed, '.') ?: 'student';
        $email = $base.'@students.generated';
        $suffix = 1;

        while (User::where('email', $email)->exists()) {
            $email = $base.'.'.$suffix++.'@students.generated';
        }

        return $email;
    }
}
