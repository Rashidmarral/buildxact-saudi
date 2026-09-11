<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial audit finding: Withholding Tax (WHT) on payments to
 * non-resident suppliers had no support at all — no residency flag, no
 * rate table, nothing posting to a WHT payable liability. Saudi WHT only
 * applies to non-resident suppliers, so that starts here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->boolean('is_resident')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('is_resident');
        });
    }
};
