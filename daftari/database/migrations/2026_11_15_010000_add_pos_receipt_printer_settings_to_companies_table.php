<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which thermal paper a company's receipt printer takes, and whether the
 * sale receipt should print itself the moment a POS/Restaurant checkout
 * completes instead of the cashier having to click Print — see
 * ReceiptTemplateController::updateSettings(), MpdfRenderer::
 * resolvePageSize(), and pos-receipt-body.blade.php/receipt.blade.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('pos_receipt_paper_width', 10)->default('80mm')->after('short_address');
            $table->boolean('pos_auto_print_receipt')->default(false)->after('pos_receipt_paper_width');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['pos_receipt_paper_width', 'pos_auto_print_receipt']);
        });
    }
};
