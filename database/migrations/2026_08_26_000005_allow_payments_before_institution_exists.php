<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Onboarding now takes the setup fee *before* the school exists, which
     * the payments table couldn't represent: institution_id was required,
     * and there is no institution to point at yet.
     *
     * So it becomes nullable, and `purpose` records which kind of payment a
     * row is - the one-off setup fee, or a subscription period. The setup
     * payment is claimed by the institution the moment it's created, so a
     * null institution_id only ever describes a payment still in flight.
     *
     * Existing rows are all subscription payments; the column default says
     * so, and nothing needs backfilling.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'purpose')) {
                $table->string('purpose')->default('subscription')->after('plan_id');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('institution_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'purpose')) {
                $table->dropColumn('purpose');
            }
        });

        // Deliberately not narrowed back to NOT NULL: any setup payment
        // taken while this was live would have no institution to point at,
        // and the rollback would fail on it.
    }
};
