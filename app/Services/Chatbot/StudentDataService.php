<?php

namespace App\Services\Chatbot;

use App\Models\Devices;
use Athwari\LaravelZktecoAdms\Enums\AttendanceStatus;
use Athwari\LaravelZktecoAdms\Models\ZktecoAttendanceLog;
use Modules\Classes\Models\SchoolClass;
use Modules\FeeManagement\Models\Fee;
use Modules\Institution\Models\Institution;
use Modules\Result\Models\Result;
use Modules\Student\Models\StudentDetails;
use Modules\Timetable\Models\TimetableEntry;

/**
 * Answers a verified chat command about a single student. Every method
 * assumes the caller has already confirmed the requester's identity via
 * VerificationService - nothing here re-checks permissions, since there is
 * no acting "user" in this flow, only a verified admission number.
 */
class StudentDataService
{
    /**
     * @return array<int, string> lines to render as the bot's reply
     */
    public function answer(string $command, StudentDetails $student): array
    {
        return match ($command) {
            'result' => $this->results($student),
            'attendance' => $this->attendance($student),
            'fees' => $this->fees($student),
            'timetable' => $this->timetable($student),
            'profile' => $this->profile($student),
            default => ["I don't have an answer for that yet."],
        };
    }

    private function results(StudentDetails $student): array
    {
        $results = Result::where('student_id', $student->student_id)
            ->with('examination.subject')
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        if ($results->isEmpty()) {
            return ['No results have been recorded yet.'];
        }

        return $results->map(function (Result $result) {
            $exam = $result->examination;
            $subject = $exam?->subject?->name ?? $exam?->subject_name ?? 'Unknown subject';
            $title = $exam?->title ?? 'Exam';
            $total = $exam?->total_marks;
            $marksLine = $total ? "{$result->marks_obtained}/{$total}" : (string) $result->marks_obtained;
            $grade = $result->grade ? " — grade {$result->grade}" : '';

            return "{$title} ({$subject}): {$marksLine}{$grade}";
        })->all();
    }

    private function attendance(StudentDetails $student): array
    {
        $deviceIds = Devices::where('institution_id', $student->institution_id)
            ->whereNotNull('zkteco_device_id')
            ->pluck('zkteco_device_id');

        $logs = ZktecoAttendanceLog::whereIn('device_id', $deviceIds)
            ->where('pin', (string) $student->student_id)
            ->orderByDesc('occurred_at')
            ->limit(8)
            ->get();

        if ($logs->isEmpty()) {
            return ['No attendance records found yet.'];
        }

        return $logs->map(function (ZktecoAttendanceLog $log) {
            $label = match ($log->status) {
                null => 'Recorded',
                AttendanceStatus::CheckIn => 'Checked in',
                AttendanceStatus::CheckOut => 'Checked out',
                AttendanceStatus::BreakOut => 'Break out',
                AttendanceStatus::BreakIn => 'Break in',
                AttendanceStatus::OvertimeIn => 'Overtime in',
                AttendanceStatus::OvertimeOut => 'Overtime out',
            };
            $when = $log->occurred_at?->format('D, M j g:i A') ?? 'Unknown time';

            return "{$label} — {$when}";
        })->all();
    }

    private function fees(StudentDetails $student): array
    {
        $fees = Fee::where('student_id', $student->student_id)->orderByDesc('id')->get();

        if ($fees->isEmpty()) {
            return ['No fee records found.'];
        }

        $totalBalance = $fees->sum('balance');
        $lines = ['Total outstanding balance: KES '.number_format($totalBalance, 2)];

        foreach ($fees->take(8) as $fee) {
            $lines[] = "{$fee->title}: paid KES ".number_format((float) $fee->amount_paid, 2)
                .' of KES '.number_format((float) $fee->amount, 2)
                ." ({$fee->status})";
        }

        return $lines;
    }

    private function timetable(StudentDetails $student): array
    {
        $entries = TimetableEntry::where('class_id', $student->class_id)
            ->orderByRaw("FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')")
            ->orderBy('start_time')
            ->get();

        if ($entries->isEmpty()) {
            return ['No timetable has been set for this class yet.'];
        }

        $byDay = $entries->groupBy('day_of_week');
        $lines = [];

        foreach ($byDay as $day => $dayEntries) {
            $lines[] = "**{$day}**";
            foreach ($dayEntries as $entry) {
                $time = $entry->start_time?->format('H:i').'–'.$entry->end_time?->format('H:i');
                $lines[] = "  {$time} {$entry->subject}";
            }
        }

        return $lines;
    }

    private function profile(StudentDetails $student): array
    {
        $class = SchoolClass::find($student->class_id);
        $institution = Institution::find($student->institution_id);

        return array_filter([
            'Admission number: '.$student->admission_number,
            $institution ? 'School: '.$institution->name : null,
            $class ? 'Class: '.$class->name : null,
            $class?->classTeacher ? 'Class teacher: '.$class->classTeacher->name : null,
        ]);
    }
}
