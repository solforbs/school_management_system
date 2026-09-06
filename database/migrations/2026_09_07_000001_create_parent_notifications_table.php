<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One notice a Director sends to the school's parents: the message as
     * composed, the channels it goes out on, when it should send, and how
     * it turned out. One recipient row per parent per channel hangs off it
     * for the "what actually happened" view.
     */
    public function up(): void
    {
        Schema::create('parent_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->string('name');
            $table->string('subject');
            $table->text('body');
            // Which of sms / email the Director chose for this send.
            $table->json('channels');
            // Values the Director typed for the body's custom {{placeholders}}.
            $table->json('placeholder_values')->nullable();
            // Null (or past) means "send now"; a future time means the
            // scheduler picks it up then.
            $table->timestamp('scheduled_for')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('parent_notification_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel');
            $table->string('destination')->nullable();
            $table->string('status')->default('pending');
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['parent_notification_id', 'status'], 'pnr_notification_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_notification_recipients');
        Schema::dropIfExists('parent_notifications');
    }
};
