<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * In CBC formative / SBA assessments, teachers record expectation bands
     * directly (EE, ME, AE, BE) without numeric marks, so marks_obtained
     * must be nullable.
     */
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->decimal('marks_obtained', 6, 2)->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->decimal('marks_obtained', 6, 2)->change();
        });
    }
};
