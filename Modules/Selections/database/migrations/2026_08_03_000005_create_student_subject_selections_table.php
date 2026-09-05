<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This file was renamed (2026_08_03_000000 -> 2026_08_03_000005)
        // after already running under its old name in some environments -
        // their `migrations` table still has the old filename, so Laravel
        // sees no record of "2026_08_03_000005_create_student_subject_selections_table"
        // and tries to run this again. Guarding the create makes that a
        // harmless no-op there while still creating the table normally on a
        // fresh install where it doesn't exist yet.
        if (Schema::hasTable('subject_selections')) {
            return;
        }

        Schema::create('subject_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subject_selections');
    }
};
