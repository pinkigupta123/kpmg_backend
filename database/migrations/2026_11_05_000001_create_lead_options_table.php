<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Admin-managed dropdown values (lead source, designation, ...)
        Schema::create('lead_options', function (Blueprint $table) {
            $table->id();
            $table->string('type', 50)->index();
            $table->string('label');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['type', 'label']);
        });

        Schema::table('lead_customers', function (Blueprint $table) {
            $table->string('lead_source', 100)->nullable()->after('company');
        });

        Schema::table('lead_users', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('designation');
        });

        $now = now();
        $defaults = [
            'lead_source' => ['Walk-in', 'Just Dial', 'Google', 'Facebook', 'Instagram', 'Referral', 'Other'],
            'designation' => ['Sales Executive', 'Senior Sales Executive', 'Team Lead'],
        ];
        foreach ($defaults as $type => $labels) {
            foreach ($labels as $i => $label) {
                DB::table('lead_options')->insert([
                    'type' => $type, 'label' => $label, 'is_active' => true,
                    'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('lead_users', fn (Blueprint $t) => $t->dropColumn('permissions'));
        Schema::table('lead_customers', fn (Blueprint $t) => $t->dropColumn('lead_source'));
        Schema::dropIfExists('lead_options');
    }
};
