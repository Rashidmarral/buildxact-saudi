<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('credit_note_prefix', 10)->default('CN')->after('invoice_prefix');
            $table->unsignedInteger('next_credit_note_number')->default(1)->after('next_invoice_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['credit_note_prefix', 'next_credit_note_number']);
        });
    }
};
