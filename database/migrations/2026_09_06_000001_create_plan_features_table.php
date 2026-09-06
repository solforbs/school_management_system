<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A catalogue of the pro-tier capabilities a plan can switch on -
     * "AI receipt scanning", "Bulk SMS", and whatever an admin adds later
     * from the plan screen.
     *
     * The plans table still stores only a list of feature *keys* in its
     * `features` JSON column; this table is where each key gets a printable
     * label. Keeping it a table (rather than a growing PHP constant) is
     * what lets the plan form add a new feature without a deploy.
     */
    public function up(): void
    {
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the two the codebase already knows about so existing plans'
        // stored keys keep resolving to the same wording.
        $now = now();

        DB::table('plan_features')->insert([
            [
                'key' => 'ai_receipt_scanning',
                'label' => 'AI Receipt Scanning (Fee Management)',
                'description' => 'Scan a paper receipt and have its amount, date and reference read off automatically.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'bulk_sms',
                'label' => 'Bulk SMS',
                'description' => 'Send SMS to many parents or staff at once for fees, attendance and announcements.',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_features');
    }
};
