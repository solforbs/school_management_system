<?php

namespace Modules\Institution\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Expenditure\Models\ExpenditureCategory;
use Modules\FeeManagement\Models\Fee;
use Modules\Staff\Models\StaffDetails;
use Modules\Student\Models\StudentDetails;

/**
 * The money side of the demo school: three terms of fee invoices with a
 * realistic spread of payment, a few months of school expenses by
 * category, and payroll history for every staff member.
 */
class DemoFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $institution = DemoAccountSeeder::institution();

        if (! $institution) {
            return;
        }

        $this->fees($institution);
        $this->expenditure($institution);
        $this->payroll($institution);
    }

    private function fees($institution): void
    {
        $students = StudentDetails::where('institution_id', $institution->id)->get(['student_id', 'parent_id', 'class_id']);
        $terms = [
            ['title' => 'Term 3 '.(now()->year - 1).' Fees', 'due' => now()->subYear()->month(9)->day(30)],
            ['title' => 'Term 1 '.now()->year.' Fees', 'due' => now()->month(2)->day(15)],
        ];

        $rows = [];

        foreach ($terms as $ti => $term) {
            foreach ($students as $detail) {
                $amount = str_starts_with((string) $detail->class_id, '1') ? 32000.00 : 24000.00;
                $rand = rand(1, 100);
                $paid = $ti === 0
                    ? $amount
                    : ($rand <= 65 ? $amount : ($rand <= 88 ? round($amount * (rand(30, 70) / 100), -2) : 0.0));

                $rows[] = [
                    'institution_id' => $institution->id,
                    'student_id' => $detail->student_id,
                    'parent_id' => $detail->parent_id,
                    'title' => $term['title'],
                    'fee_type' => 'tuition',
                    'amount' => $amount,
                    'amount_paid' => $paid,
                    'due_date' => $term['due']->toDateString(),
                    'created_at' => $term['due']->copy()->subDays(20),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('fees')->insertOrIgnore($chunk);
        }

        unset($rows);
        // Keep a model-touch so any Fee observers still fire on at least one row.
        Fee::where('institution_id', $institution->id)->first()?->touch();
    }

    private function expenditure($institution): void
    {
        $categoryIds = [];
        foreach (ExpenditureCategory::DEFAULTS as $name => $description) {
            $categoryIds[] = ExpenditureCategory::firstOrCreate(
                ['institution_id' => $institution->id, 'name' => $name],
                ['description' => $description, 'is_active' => true],
            )->id;
        }

        $payees = [
            'Kenya Power', 'Nairobi Water', 'Text Book Centre', 'TotalEnergies', 'Crown Paints',
            'Superb Sports', 'TechSys Kenya', 'Farmers Choice', 'County Licensing', 'Assorted Vendors',
        ];
        $methods = ['bank_transfer', 'mobile_money', 'cheque'];
        $rows = [];

        for ($month = 4; $month >= 1; $month--) {
            $spentOn = now()->subMonths($month)->startOfMonth()->addDays(rand(3, 22));

            foreach ($categoryIds as $idx => $categoryId) {
                $rows[] = [
                    'institution_id' => $institution->id,
                    'expenditure_category_id' => $categoryId,
                    'recorded_by' => $institution->user_id,
                    'title' => $spentOn->format('F').' — '.array_keys(ExpenditureCategory::DEFAULTS)[$idx],
                    'payee' => $payees[$idx % count($payees)],
                    'amount' => (float) rand(12000, 140000),
                    'spent_on' => $spentOn->toDateString(),
                    'payment_method' => $methods[array_rand($methods)],
                    'reference' => 'EXP-'.strtoupper(Str::random(8)),
                    'status' => 'paid',
                    'paid_at' => $spentOn->copy()->addDay(),
                    'created_at' => $spentOn,
                    'updated_at' => $spentOn,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('expenditures')->insert($chunk);
        }
    }

    private function payroll($institution): void
    {
        $staff = StaffDetails::where('institution_id', $institution->id)->get(['id', 'salary']);
        $rows = [];

        foreach ($staff as $member) {
            for ($month = 4; $month >= 1; $month--) {
                $period = now()->subMonths($month)->startOfMonth();
                $gross = (float) $member->salary;
                $allowances = (float) rand(2000, 6000);
                $deductions = (float) rand(3000, 9000);

                $rows[] = [
                    'staff_details_id' => $member->id,
                    'institution_id' => $institution->id,
                    'recorded_by' => $institution->user_id,
                    'period' => $period->toDateString(),
                    'gross_amount' => $gross,
                    'allowances' => $allowances,
                    'deductions' => $deductions,
                    'net_amount' => $gross + $allowances - $deductions,
                    'payment_method' => 'bank_transfer',
                    'reference' => 'PAY-'.strtoupper(Str::random(8)),
                    'status' => 'paid',
                    'paid_at' => $period->copy()->addDays(27),
                    'created_at' => $period->copy()->addDays(27),
                    'updated_at' => $period->copy()->addDays(27),
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('staff_payments')->insertOrIgnore($chunk);
        }
    }
}
