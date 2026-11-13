<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->text('notes_ar')->nullable()->after('notes');
        });

        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->text('notes_ar')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->dropColumn('notes_ar');
        });

        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->dropColumn('notes_ar');
        });
    }
};
