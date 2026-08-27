<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which plan wears the "Most popular" badge on the pricing page.
     *
     * The page used to hardcode it onto the middle of three cards. Now that
     * the cards come from this table there is no middle to rely on, and
     * having the view pick one by position would mean the system inventing
     * a marketing claim nobody made. A school says which, or none does.
     */
    public function up(): void
    {
        if (Schema::hasColumn('plans', 'is_featured')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_featured')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('plans', 'is_featured')) {
            return;
        }

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_featured');
        });
    }
};
