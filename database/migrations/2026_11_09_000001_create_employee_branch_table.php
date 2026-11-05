<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An employee can work in several branches. `employees.branch_id` stays as the primary / default branch;
     * this table holds the full set (the primary branch is always included).
     */
    public function up(): void
    {
        Schema::create('employee_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['employee_id', 'branch_id']);
        });

        // existing employees: their single branch becomes their (only) assignment
        $now = now();
        DB::table('employees')->select('id', 'branch_id')->orderBy('id')->each(function ($e) use ($now) {
            DB::table('employee_branch')->insert([
                'employee_id' => $e->id, 'branch_id' => $e->branch_id, 'created_at' => $now, 'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_branch');
    }
};
