<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether this plan's price is quoted rather than published.
     *
     * A flag is needed because the price alone can't say: an Enterprise
     * plan sits at 0.00 because nobody has fixed its rate yet, and a Basic
     * free tier sits at 0.00 because it costs nothing. Same number, opposite
     * meanings - and rendering "Free" against the first is a promise the
     * business never made.
     *
     * Kept separate from `price` rather than making that column nullable,
     * because the billing code does arithmetic on it (an institution's
     * outstanding balance, and the upgrade comparison in helpers.php). A
     * null flowing into those would quietly become 0.00 anyway.
     */
    public function up(): void
    {
        if (Schema::hasColumn('plans', 'is_custom_priced')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_custom_priced')->default(false)->after('price');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plans', 'is_custom_priced')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_custom_priced');
        });
    }
};
