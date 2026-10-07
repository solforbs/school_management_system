<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('student_details')) {
            Schema::table('student_details', function (Blueprint $table) {
                // Drop global unique on admission_number if present
                try {
                    $table->dropUnique('student_details_admission_number_unique');
                } catch (Throwable $e) {
                    // Index may already be dropped or named differently in some environments
                }

                // Add scoped unique index on (institution_id, admission_number)
                try {
                    $table->unique(['institution_id', 'admission_number'], 'student_details_institution_admission_unique');
                } catch (Throwable $e) {
                    // Index already exists
                }

                // Add scoped unique index on (institution_id, student_id)
                try {
                    $table->unique(['institution_id', 'student_id'], 'student_details_institution_student_unique');
                } catch (Throwable $e) {
                    // Index already exists
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('student_details')) {
            Schema::table('student_details', function (Blueprint $table) {
                try {
                    $table->dropUnique('student_details_institution_admission_unique');
                } catch (Throwable $e) {
                }

                try {
                    $table->dropUnique('student_details_institution_student_unique');
                } catch (Throwable $e) {
                }

                try {
                    $table->unique('admission_number', 'student_details_admission_number_unique');
                } catch (Throwable $e) {
                }
            });
        }
    }
};
