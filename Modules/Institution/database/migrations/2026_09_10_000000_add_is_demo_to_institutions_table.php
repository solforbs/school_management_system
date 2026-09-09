<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the one shared demo school. This flag drives the periodic reset,
 * the "you're in the demo" banner, and the middleware that turns off
 * billing / hardware / messaging inside it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->boolean('is_demo')->default(false)->index()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('institutions', function (Blueprint $table) {
            $table->dropColumn('is_demo');
        });
    }
};
