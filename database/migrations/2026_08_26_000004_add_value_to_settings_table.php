<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `settings` could only ever answer yes or no. The onboarding setup fee
     * is an amount, so the table needs somewhere to put one.
     *
     * Kept as a string rather than a decimal because this column is the
     * general-purpose half of the table - the next setting to need a value
     * may well not be money - and callers cast on the way out.
     */
    public function up(): void
    {
        if (Schema::hasColumn('settings', 'value')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->string('value')->nullable()->after('enabled');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('settings', 'value')) {
            return;
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('value');
        });
    }
};
