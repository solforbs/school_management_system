<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('timetable_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('curriculum_type')->nullable(); // '844', 'cbc', or null for all
            $table->json('days_of_week'); // e.g. ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"]
            $table->json('periods_configuration'); // array of periods with period_number, name, start_time, end_time, is_break, type
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['institution_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_structures');
    }
};
