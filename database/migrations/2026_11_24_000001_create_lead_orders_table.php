<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The "Order Confirmation" form filled when a lead turns into a sale (status Purchased).
     * One order per lead; editing it later updates the same record.
     * Also seeds the admin-managed dropdowns used by the payment section of the form.
     */
    public function up(): void
    {
        Schema::create('lead_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained('lead_customers')->cascadeOnDelete();
            $table->string('order_no', 30)->nullable()->unique();   // the paper form's "Sr. No."
            $table->date('order_date');
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('mobile', 20);
            $table->string('email')->nullable();
            $table->string('gst_no', 20)->nullable();
            $table->json('items');                                   // [{particulars, unit, amount}]
            $table->decimal('total', 12, 2)->default(0);
            $table->json('payments');                                // [{method, provider, amount, cashback, reference}]
            $table->json('offers')->nullable();                      // ["3 Year Software Support", ...]
            $table->string('sales_person')->nullable();
            $table->string('store')->nullable();                     // "Our from store"
            $table->string('cash_counter')->nullable();
            $table->string('verified_by')->nullable();               // "Material verified & handed over by"
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            'card_provider'    => ['Paytm', 'Pine Labs', 'Other'],
            'upi_provider'     => ['Amazon Pay', 'Paytm', 'PhonePe', 'Pine Labs', 'Other'],
            'finance_provider' => ['Bajaj', 'IDFC', 'HDB', 'Other'],
            'order_offer'      => ['3 Year Software Support', '3 Year ADP'],
        ];
        foreach ($defaults as $type => $labels) {
            foreach ($labels as $i => $label) {
                DB::table('lead_options')->insertOrIgnore([
                    'type' => $type, 'label' => $label, 'is_active' => true,
                    'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('lead_options')->whereIn('type', ['card_provider', 'upi_provider', 'finance_provider', 'order_offer'])->delete();
        Schema::dropIfExists('lead_orders');
    }
};
