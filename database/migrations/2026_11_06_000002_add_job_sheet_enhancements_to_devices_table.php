<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            $table->decimal('fixed_amount', 10, 2)->nullable();
            $table->decimal('estimated_amount', 10, 2)->nullable();
            $table->text('estimate_remarks')->nullable();
            $table->text('receiving_address')->nullable();
            $table->string('device_user_id')->nullable()->after('device_password');
            // device_password now holds an encrypted payload (Laravel `encrypted` cast)
            $table->text('device_password')->nullable()->change();
            $table->dropColumn(['customer_signature', 'employee_signature']);
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->longText('customer_signature')->nullable();
            $table->longText('employee_signature')->nullable();
            $table->dropColumn(['fixed_amount', 'estimated_amount', 'estimate_remarks', 'receiving_address', 'device_user_id']);
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
