<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reusable message templates a school's Director builds once and sends
     * to the school's parents - "term opens", "mid-term break", "closure".
     *
     * Scoped to one institution: each school keeps its own set. The body
     * may carry {{placeholders}} - {{institution}} and {{parent}} are
     * filled per recipient at send time, anything else the Director types
     * in on the send screen.
     */
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
