<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Standard Plan
        Plan::updateOrCreate(
            ['slug' => 'standard'],
            [
                'name' => 'Standard',
                'description' => 'Manages students records, teachers records, one institution reports',
                'price' => 5000.00,
                'billing_cycle' => 'monthly',
                'is_custom_priced' => false,
                'is_featured' => false,
                'is_active' => true,
                'modules' => [
                    'Institution',
                    'Student',
                    'Parent',
                    'Teacher',
                    'Timetable',
                    'Curriculum',
                    'Report',
                    'Classes',
                    'Subject',
                    'Selections',
                    'Staff',
                ],
                'features' => [],
            ]
        );

        // 2. Premium Plan (Featured / Most Popular)
        Plan::updateOrCreate(
            ['slug' => 'premium'],
            [
                'name' => 'Premium',
                'description' => 'Up to 1000 students with SMS and email notifications for fees, attendance, and report cards.',
                'price' => 30000.00,
                'billing_cycle' => 'monthly',
                'is_custom_priced' => false,
                'is_featured' => true,
                'is_active' => true,
                'modules' => [
                    'Institution',
                    'Student',
                    'Parent',
                    'Teacher',
                    'Attendance',
                    'FeeManagement',
                    'Examinations',
                    'Timetable',
                    'Curriculum',
                    'Report',
                    'Classes',
                    'Lesson',
                    'Result',
                    'Subject',
                    'Selections',
                    'ReportCard',
                    'Staff',
                ],
                'features' => ['ai_receipt_scanning'],
            ]
        );

        // 3. Enterprise Plan
        Plan::updateOrCreate(
            ['slug' => 'enterprise'],
            [
                'name' => 'Enterprise',
                'description' => 'Call 0725996394 / 0711777992 or email support@solforbs.com',
                'price' => 0.00,
                'billing_cycle' => 'monthly',
                'is_custom_priced' => true,
                'is_featured' => false,
                'is_active' => true,
                'modules' => [
                    'Institution',
                    'Student',
                    'Parent',
                    'Teacher',
                    'Attendance',
                    'FeeManagement',
                    'Examinations',
                    'Timetable',
                    'Curriculum',
                    'Report',
                    'Classes',
                    'Lesson',
                    'Result',
                    'Subject',
                    'Selections',
                    'ReportCard',
                    'Staff',
                    'Expenditure',
                ],
                'features' => ['ai_receipt_scanning'],
            ]
        );
    }
}
