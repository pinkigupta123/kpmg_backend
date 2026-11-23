<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Speeds up the lead list / dashboard queries: every one of them filters by
 * executive and/or status and sorts by created_at, none of which were indexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_customers', function (Blueprint $table) {
            $table->index(['executive_id', 'created_at'], 'lead_customers_exec_created_idx');
            $table->index(['status', 'created_at'], 'lead_customers_status_created_idx');
            $table->index(['executive_id', 'status'], 'lead_customers_exec_status_idx');
            $table->index('followup_date', 'lead_customers_followup_idx');
            $table->index('created_at', 'lead_customers_created_idx');
            $table->index('mobile', 'lead_customers_mobile_idx');
        });

        Schema::table('lead_users', function (Blueprint $table) {
            $table->index('branch', 'lead_users_branch_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lead_customers', function (Blueprint $table) {
            $table->dropIndex('lead_customers_exec_created_idx');
            $table->dropIndex('lead_customers_status_created_idx');
            $table->dropIndex('lead_customers_exec_status_idx');
            $table->dropIndex('lead_customers_followup_idx');
            $table->dropIndex('lead_customers_created_idx');
            $table->dropIndex('lead_customers_mobile_idx');
        });

        Schema::table('lead_users', function (Blueprint $table) {
            $table->dropIndex('lead_users_branch_idx');
        });
    }
};
