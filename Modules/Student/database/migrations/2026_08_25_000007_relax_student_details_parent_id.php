<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * student_details.parent_id holds a users.id everywhere in this codebase -
 * StudentDetails::where('parent_id', $user->id), the Parent role's scoping,
 * report card delivery, the student form. The column, however, was declared
 * NOT NULL and foreign-keyed to parent_details.id.
 *
 * Two consequences, both real: a student could not be enrolled without a
 * parent at all, and enrolling one *with* a parent only worked when the new
 * parent_details row happened to be handed the same id as the parent's user
 * row. Any mismatch was a foreign key violation.
 *
 * The conservative repair is to drop the constraint and let the column be
 * null: no data is touched, existing rows keep their values, and the column
 * now means what the whole application already assumes it means. Adding a
 * users foreign key instead is the eventual tidy-up - it can't be done here
 * without first proving every existing value resolves to a user.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('student_details', 'parent_id')) {
            return;
        }

        // The constraint is declared in the create-table migration, but the
        // deployed databases were built from an imported dump and never got
        // it - the same drift that left them a stray user_id column. Asking
        // first is what keeps this migration runnable on both.
        $hasForeignKey = collect(Schema::getForeignKeys('student_details'))
            ->contains(fn (array $foreignKey) => in_array('parent_id', $foreignKey['columns'], true));

        // Both in one blueprint on purpose: SQLite has no separate "drop
        // foreign key" statement - it clears the constraint while rebuilding
        // the table for the column change.
        Schema::table('student_details', function (Blueprint $table) use ($hasForeignKey) {
            if ($hasForeignKey) {
                $table->dropForeign(['parent_id']);
            }

            $table->unsignedBigInteger('parent_id')->nullable()->change();
        });
    }

    /**
     * Deliberately not restoring the foreign key: rows written while it was
     * gone hold user ids, and pointing those back at parent_details would
     * fail or, worse, silently mean something else.
     */
    public function down(): void
    {
        //
    }
};
