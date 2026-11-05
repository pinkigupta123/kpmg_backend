<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->decimal('final_amount', 12, 2)->nullable();     // what was actually charged to the customer, entered at close
            $table->text('final_amount_note')->nullable();
        });

        Schema::table('stock_usages', function (Blueprint $table) {
            $table->decimal('unit_cost', 10, 2)->nullable();        // our purchase cost per unit when the part was used
        });
    }

    public function down(): void
    {
        Schema::table('stock_usages', fn (Blueprint $t) => $t->dropColumn('unit_cost'));
        Schema::table('devices', fn (Blueprint $t) => $t->dropColumn(['final_amount', 'final_amount_note']));
    }
};
