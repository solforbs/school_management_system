<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which school a parent account belongs to.
 *
 * Until now the only link was through their children, so a parent with none
 * yet was claimed by nobody - and every Director could read, edit and delete
 * them. Stamping the owning institution on the row closes that: a parent is
 * reachable by the school that created them, whether or not a student has
 * been linked yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_details', function (Blueprint $table) {
            // Nullable: existing rows are backfilled below, and any that
            // can't be resolved stay null, which reads as "no school owns
            // this" - Admin-only, rather than everyone's.
            $table->foreignId('institution_id')
                ->nullable()
                ->after('parent_id')
                ->constrained('institutions')
                ->nullOnDelete();
        });

        // Backfill from the children already linked to each parent. A parent
        // with children in more than one school keeps the earliest, which is
        // the one that enrolled them first.
        DB::table('parent_details')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $institutionId = DB::table('student_details')
                    ->where('parent_id', $row->parent_id)
                    ->whereNotNull('institution_id')
                    ->orderBy('id')
                    ->value('institution_id');

                if ($institutionId !== null) {
                    DB::table('parent_details')
                        ->where('id', $row->id)
                        ->update(['institution_id' => $institutionId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('parent_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institution_id');
        });
    }
};
