<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A curriculum's name is free text ("CBC", "8.4.4", "Competency Based
     * Curriculum"), which is fine to read but useless to grade against.
     * `system` is the machine-readable half: it decides whether results on
     * that curriculum are graded A-E or on the CBC four-band rubric.
     */
    public function up(): void
    {
        if (Schema::hasColumn('curricula', 'system')) {
            return;
        }

        Schema::table('curricula', function (Blueprint $table) {
            $table->enum('system', ['844', 'cbc'])->default('844')->after('name');
        });

        // Existing rows only carry a name, so that's all there is to go on.
        DB::table('curricula')
            ->where('name', 'like', '%cbc%')
            ->orWhere('name', 'like', '%competency%')
            ->orWhere('name', 'like', '%cbe%')
            ->update(['system' => 'cbc']);
    }

    public function down(): void
    {
        if (! Schema::hasColumn('curricula', 'system')) {
            return;
        }

        Schema::table('curricula', function (Blueprint $table) {
            $table->dropColumn('system');
        });
    }
};
