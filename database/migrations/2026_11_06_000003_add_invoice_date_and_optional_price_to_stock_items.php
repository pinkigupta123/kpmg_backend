<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->decimal('selling_price', 10, 2)->nullable()->default(null)->change();
            $table->date('invoice_date')->nullable()->after('slip_number');
        });
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table) {
            $table->dropColumn('invoice_date');
            $table->decimal('selling_price', 10, 2)->default(0)->change();
        });
    }
};
