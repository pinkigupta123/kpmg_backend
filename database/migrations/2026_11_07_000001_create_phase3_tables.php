<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Centralised, never-deleted dropdown values (categories etc.)
        Schema::create('dropdown_options', function (Blueprint $table) {
            $table->id();
            $table->string('group', 60);
            $table->string('value');            // stored on records; never changes once created
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['group', 'value']);
        });

        // Purchase invoice header; many stock items hang off one invoice
        Schema::create('stock_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('supplier')->nullable();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('branch_name')->nullable();
            $table->string('slip_photo_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::table('stock_items', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('invoice_date')->constrained('stock_invoices')->nullOnDelete();
        });

        // Ledger of every stock movement (additions, edits, usage)
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('stock_items')->onDelete('cascade');
            $table->string('item_name');
            $table->string('item_code')->nullable();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('type', 20); // addition | update | usage
            $table->integer('quantity_change')->default(0);
            $table->integer('quantity_after')->default(0);
            $table->string('reference')->nullable(); // invoice no / job no
            $table->text('note')->nullable();
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->string('performed_by_name')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'created_at']);
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_sequence')->default(0);
            $table->unsignedBigInteger('nongst_invoice_sequence')->default(0);
        });

        // Customer sales invoices (GST / Non-GST)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->string('type', 10); // gst | non_gst
            $table->string('supply_type', 10)->default('intra'); // intra (CGST+SGST) | inter (IGST)
            $table->date('invoice_date');
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('job_number')->nullable();
            $table->string('buyer_name');
            $table->text('buyer_address')->nullable();
            $table->string('buyer_mobile', 20)->nullable();
            $table->string('buyer_gstin', 20)->nullable();
            $table->string('buyer_state')->nullable();
            $table->string('place_of_supply')->nullable();
            $table->decimal('taxable_total', 12, 2)->default(0);
            $table->decimal('cgst_total', 12, 2)->default(0);
            $table->decimal('sgst_total', 12, 2)->default(0);
            $table->decimal('igst_total', 12, 2)->default(0);
            $table->decimal('round_off', 8, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->index('invoice_date');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            $table->unsignedInteger('line_no')->default(1);
            $table->text('description');
            $table->string('hsn_sac', 20)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 10)->default('Pc');
            $table->decimal('rate', 12, 2)->default(0);      // tax-inclusive for GST invoices
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('taxable_value', 12, 2)->default(0);
            $table->decimal('cgst', 12, 2)->default(0);
            $table->decimal('sgst', 12, 2)->default(0);
            $table->decimal('igst', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['invoice_sequence', 'nongst_invoice_sequence']);
        });
        Schema::dropIfExists('stock_transactions');
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
        Schema::dropIfExists('stock_invoices');
        Schema::dropIfExists('dropdown_options');
    }
};
