<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('student_details', 'admission_number')) {
            Schema::table('student_details', function (Blueprint $table) {
                $table->string('admission_number')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('student_details', 'admission_number')) {
            Schema::table('student_details', function (Blueprint $table) {
                $table->string('admission_number')->nullable(false)->change();
            });
        }
    }
};
