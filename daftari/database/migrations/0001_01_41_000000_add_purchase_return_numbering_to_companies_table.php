<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('purchase_return_prefix', 10)->default('PR')->after('credit_note_prefix');
            $table->unsignedInteger('next_purchase_return_number')->default(1)->after('next_credit_note_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['purchase_return_prefix', 'next_purchase_return_number']);
        });
    }
};
