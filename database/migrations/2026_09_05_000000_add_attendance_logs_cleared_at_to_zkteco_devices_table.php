<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('zkteco-adms.table_prefix', 'zkteco_').'devices';

        Schema::table($table, function (Blueprint $blueprint) {
            // Null means "never cleared" - the retention job treats a
            // never-cleared device as due 7 days after it registered, not
            // immediately, so a brand-new device isn't wiped on its first day.
            $blueprint->timestamp('attendance_logs_cleared_at')->nullable()->after('last_activity_at');
        });
    }

    public function down(): void
    {
        $table = config('zkteco-adms.table_prefix', 'zkteco_').'devices';

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->dropColumn('attendance_logs_cleared_at');
        });
    }
};
