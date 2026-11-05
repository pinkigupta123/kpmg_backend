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
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
        Schema::dropIfExists('stock_invoices');
        Schema::dropIfExists('dropdown_options');
    }
};
