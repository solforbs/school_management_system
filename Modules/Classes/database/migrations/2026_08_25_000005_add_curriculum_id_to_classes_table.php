<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which curriculum a class sits on. Kenyan schools routinely run 8-4-4
     * and CBC side by side through the transition, so the grading scheme
     * has to be decided per class rather than per school.
     */
    public function up(): void
    {
        if (Schema::hasColumn('classes', 'curriculum_id')) {
            return;
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('curriculum_id')->nullable()->after('level')
                ->constrained('curricula')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('classes', 'curriculum_id')) {
            return;
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('curriculum_id');
        });
    }
};
