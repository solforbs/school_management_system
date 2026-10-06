<?php

namespace Modules\Institution\Database\Seeders;

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Institution\Models\Institution;

class InstitutionDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
        ]);

        $plan = Plan::where('slug', 'premium')->first() ?? Plan::first();

        // 1. Director (School Owner)
        $director = User::firstOrCreate(
            ['email' => 'director@greenvalley.ac.ke'],
            [
                'name' => 'Green Valley Director',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );

        if (! $director->hasRole('Director')) {
            $director->assignRole('Director');
        }

        // 2. Green Valley Academy Institution
        $institution = Institution::updateOrCreate(
            ['code' => 'GVA001'],
            [
                'user_id' => $director->id,
                'name' => 'Green Valley Academy',
                'type' => 'Primary & Secondary School',
                'email' => 'info@greenvalley.ac.ke',
                'phone' => '+254 700 000 000',
                'alternate_phone' => '+254 711 000 000',
                'website' => 'https://greenvalley.ac.ke',
                'country' => 'Kenya',
                'county' => 'Nairobi',
                'city' => 'Nairobi',
                'postal_address' => 'P.O. Box 12345-00100',
                'physical_address' => 'Green Valley Way, Karen, Nairobi',
                'principal_name' => 'Dr. Jane Mwangi',
                'principal_phone' => '+254 722 000 000',
                'education_level' => 'Primary & Secondary',
                'min_electives' => 1,
                'max_electives' => 4,
                'timezone' => 'Africa/Nairobi',
                'is_active' => true,
                'status' => 'active',
                'is_approved' => true,
                'approved_at' => now(),
                'subscription_plan' => $plan?->id,
                'subscription_started_at' => now(),
                'subscription_expires_at' => now()->addYear(),
            ]
        );

        $director->update(['active_institution_id' => $institution->id]);

        // 3. Scale Demo Sub-seeders (Classes, Subjects, Faculty, Staff, 1,000 Students, Fees, Exams, Expenses)
        $this->call([
            GreenValleyClassesAndSubjectsSeeder::class,
            GreenValleyFacultyAndStaffSeeder::class,
            GreenValleyStudentBodySeeder::class,
            GreenValleyFeeHistorySeeder::class,
            GreenValleyAcademicHistorySeeder::class,
            GreenValleyExpenditureHistorySeeder::class,
        ]);
    }
}
