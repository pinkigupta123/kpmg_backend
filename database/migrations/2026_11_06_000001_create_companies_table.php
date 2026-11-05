<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique(); // company initials used in job sheet number, e.g. HTC
            $table->text('address');
            $table->string('city');
            $table->string('state');
            $table->string('state_code', 2)->nullable(); // GST state code, e.g. 07
            $table->string('pincode', 10);
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->string('pan', 12)->nullable();
            $table->unsignedBigInteger('job_sequence')->default(10000); // last issued job sheet id
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
        Schema::table('branches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
        Schema::dropIfExists('companies');
    }
};
