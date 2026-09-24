<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teacher_details', function (Blueprint $table) {
            $table->boolean('can_enroll_students')
                ->default(false)
                ->after('status')
                ->comment('Can this teacher enroll students in their own classes?');
        });
    }

    public function down(): void
    {
        Schema::table('teacher_details', function (Blueprint $table) {
            $table->dropColumn('can_enroll_students');
        });
    }
};
