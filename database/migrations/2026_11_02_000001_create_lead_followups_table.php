<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-up tracking for the Lead module.
 *
 * lead_customers.followup_date stays as "the next pending follow-up" (so existing
 * screens keep working); this table keeps the full history: what was scheduled,
 * whether it was done, when, by whom, and the outcome.
 *
 *   status:  pending      – scheduled, not yet handled
 *            done         – completed (resolved_at / outcome filled)
 *            rescheduled  – replaced by a later date
 *            cancelled    – lead was closed/purchased or the date was cleared
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('lead_customers')->onDelete('cascade');
            $table->date('scheduled_for');
            $table->string('status', 20)->default('pending');
            $table->text('remarks')->nullable();      // note when scheduling
            $table->text('outcome')->nullable();      // note when completing
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('lead_users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('lead_users')->onDelete('cascade');
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
        });

        // Backfill: every open lead that already has a follow-up date gets one pending row.
        DB::table('lead_followups')->insertUsing(
            ['customer_id', 'scheduled_for', 'status', 'created_by', 'created_at', 'updated_at'],
            DB::table('lead_customers')
                ->whereNotNull('followup_date')
                ->whereNotIn('status', ['purchased', 'closed'])
                ->selectRaw("id, DATE(followup_date), 'pending', executive_id, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP")
        );

        // Expo push tokens, used by the daily reminder job.
        Schema::create('lead_user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_user_id')->constrained('lead_users')->onDelete('cascade');
            $table->string('expo_token')->unique();
            $table->string('platform', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_user_devices');
        Schema::dropIfExists('lead_followups');
    }
};
