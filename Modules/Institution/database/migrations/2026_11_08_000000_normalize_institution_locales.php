<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The app now ships real translations for English, Swahili, French and
 * Arabic only. Schools set to any other language go back to English.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('institutions')
            ->whereNotIn('locale', ['en', 'sw', 'fr', 'ar'])
            ->update(['locale' => 'en']);
    }

    public function down(): void
    {
        //
    }
};
