<?php

namespace Modules\Institution\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Institution\Models\Institution;
use Modules\Student\Models\StudentDetails;

class GreenValleyFeeHistorySeeder extends Seeder
{
    private const TERMS = [
        ['year' => 2024, 'title' => 'Term 1 2024 Fee', 'due' => '2024-01-31', 'pay_date' => '2024-01-25'],
        ['year' => 2024, 'title' => 'Term 2 2024 Fee', 'due' => '2024-05-31', 'pay_date' => '2024-05-20'],
        ['year' => 2024, 'title' => 'Term 3 2024 Fee', 'due' => '2024-09-30', 'pay_date' => '2024-09-15'],
        ['year' => 2025, 'title' => 'Term 1 2025 Fee', 'due' => '2025-01-31', 'pay_date' => '2025-01-25'],
        ['year' => 2025, 'title' => 'Term 2 2025 Fee', 'due' => '2025-05-31', 'pay_date' => '2025-05-20'],
        ['year' => 2025, 'title' => 'Term 3 2025 Fee', 'due' => '2025-09-30', 'pay_date' => '2025-09-15'],
        ['year' => 2026, 'title' => 'Term 1 2026 Fee', 'due' => '2026-01-31', 'pay_date' => '2026-01-20'],
        ['year' => 2026, 'title' => 'Term 2 2026 Fee', 'due' => '2026-05-31', 'pay_date' => '2026-05-18'],
        ['year' => 2026, 'title' => 'Term 3 2026 Fee', 'due' => '2026-09-30', 'pay_date' => '2026-09-10'],
    ];

    public function run(): void
    {
        $institution = Institution::where('code', 'GVA001')->first();

        if (! $institution) {
            return;
        }

        $students = StudentDetails::where('institution_id', $institution->id)->get(['id', 'student_id', 'parent_id', 'class_id', 'admission_number', 'created_at']);

        if ($students->isEmpty()) {
            return;
        }

        $feesToInsert = [];

        foreach (self::TERMS as $term) {
            $termDate = now()->setYear($term['year']);

            foreach ($students as $student) {
                // Skip fees for academic years prior to student admission year
                $studentAdmYear = (int) (explode('/', $student->admission_number)[1] ?? 2024);
                if ($term['year'] < $studentAdmYear) {
                    continue;
                }

                $classId = (int) $student->class_id;
                $feeAmount = ($classId > 24) ? 35000.00 : 25000.00;

                $rand = rand(1, 100);
                if ($rand <= 80) {
                    $amountPaid = $feeAmount;
                } elseif ($rand <= 95) {
                    $amountPaid = round($feeAmount * (rand(40, 80) / 100), -2);
                } else {
                    $amountPaid = 0.00;
                }

                $createdAt = now()->parse($term['pay_date']);

                $feesToInsert[] = [
                    'institution_id' => $institution->id,
                    'student_id' => $student->student_id,
                    'parent_id' => $student->parent_id,
                    'title' => $term['title'],
                    'fee_type' => 'tuition',
                    'amount' => $feeAmount,
                    'amount_paid' => $amountPaid,
                    'due_date' => $term['due'],
                    'notes' => 'Official Termly Tuition & Activity Fee',
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }
        }

        foreach (array_chunk($feesToInsert, 400) as $chunk) {
            DB::table('fees')->insert($chunk);
        }

        // Generate individual Fee Payment records for audit trails
        $fees = DB::table('fees')
            ->where('institution_id', $institution->id)
            ->where('amount_paid', '>', 0)
            ->get(['id', 'institution_id', 'student_id', 'amount_paid', 'due_date']);

        $feePaymentsToInsert = [];

        foreach ($fees as $fee) {
            $methods = ['mpesa', 'bank_transfer', 'cash'];
            $method = $methods[rand(0, 2)];
            $ref = ($method === 'mpesa')
                ? 'Q'.strtoupper(Str::random(9))
                : (($method === 'bank_transfer') ? 'BNK-'.rand(100000, 999999) : 'CSH-'.rand(1000, 9999));

            $paidAt = $fee->due_date;

            $feePaymentsToInsert[] = [
                'fee_id' => $fee->id,
                'institution_id' => $fee->institution_id,
                'student_id' => $fee->student_id,
                'amount' => $fee->amount_paid,
                'reference' => $ref,
                'payment_method' => $method,
                'paid_at' => $paidAt,
                'source' => 'manual',
                'recorded_by' => $institution->user_id,
                'created_at' => $paidAt,
                'updated_at' => $paidAt,
            ];
        }

        foreach (array_chunk($feePaymentsToInsert, 400) as $chunk) {
            DB::table('fee_payments')->insert($chunk);
        }
    }
}
