<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // `module` was an enum; new modules (company, invoice, option...) need a free-form value.
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('module', 40)->change();
        });
    }

    public function down(): void
    {
        // left as string: narrowing back to the old enum would fail on rows with newer modules
    }
};
