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
        // This file was renamed (2026_07_29_142708 -> 2026_07_29_145241)
        // after already running under its old name in some environments -
        // their `migrations` table still has the old filename, so Laravel
        // sees no record of "2026_07_29_145241_student_details" and tries
        // to run this again. Guarding the create makes that a harmless
        // no-op there while still creating the table normally on a fresh
        // install where it doesn't exist yet.
        if (Schema::hasTable('student_details')) {
            return;
        }

        Schema::create('student_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->string('phone')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('admission_number')->unique();
            $table->string('student_number')->unique()->nullable();

            // Address
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();

            // Parent Information
            $table->foreignId('parent_id')->constrained('parent_details', 'id');

            // Guardian Information
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('guardian_email')->nullable();
            $table->string('guardian_relationship')->nullable();

            // Medical Information
            $table->text('medical_conditions')->nullable();
            $table->text('allergies')->nullable();
            $table->text('special_needs')->nullable();

            // Status
            $table->boolean('is_active')->default(true);
            $table->enum('enrollment_status', ['active', 'transferred', 'graduated', 'dropped', 'suspended', 'expelled', 'withdrawn'])->default('active');

            // Institution
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('class_id')->nullable();

            // Profile
            $table->string('profile_photo')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('institution_id');
            $table->index('admission_number');
            $table->index('enrollment_status');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('student_details');
    }
};
