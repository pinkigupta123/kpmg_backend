<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One saved invoice per job per type (GST / Non-GST). Regenerating a type overwrites its saved copy. */
    public function up(): void
    {
        Schema::create('job_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('devices')->onDelete('cascade');
            $table->string('job_number')->nullable();
            $table->string('type', 10); // gst | non_gst
            $table->string('supply_type', 10)->default('intra');
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date');
            $table->string('buyer_name');
            $table->string('buyer_mobile')->nullable();
            $table->text('buyer_address')->nullable();
            $table->string('buyer_gstin')->nullable();
            $table->string('buyer_state')->nullable();
            $table->string('place_of_supply')->nullable();
            $table->json('items');
            $table->decimal('taxable_total', 12, 2)->default(0);
            $table->decimal('cgst_total', 12, 2)->default(0);
            $table->decimal('sgst_total', 12, 2)->default(0);
            $table->decimal('igst_total', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_invoices');
    }
};
