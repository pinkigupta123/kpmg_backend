<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Offers can carry a discount (a flat rupee amount or a percentage); a job remembers the discount given at delivery and why. */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('discount_type', 10)->nullable()->after('discount_text'); // amount | percent
            $table->decimal('discount_value', 10, 2)->nullable()->after('discount_type');
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->unsignedBigInteger('offer_id')->nullable()->after('final_amount_note');
            $table->decimal('discount_amount', 10, 2)->default(0)->after('offer_id');
            $table->string('discount_note')->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropColumn(['offer_id', 'discount_amount', 'discount_note']);
        });
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value']);
        });
    }
};
