<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "shahid payment vouchers 124800 shows detail wise in report other
 * alhazera and ashraf payment shows as Sub Base 146.00 trip 500.00
 * unit price 73,000.00 total" — the printed Receiving Payments Report
 * needed two different shapes on the same page: Shahid's cash stays a
 * real itemized voucher list (date/reference/amount), while the other
 * cost categories (Sub Base, Asphalt, Asphalt Transportation, Machine,
 * Mubashir+Khalid) collapse into one measured summary line each —
 * quantity × unit rate = amount, like a site measurement sheet, not a
 * voucher listing.
 *
 * display_mode='itemized': amount is computed live at print time from
 * every linked SubcontractPayment whose own/voucher reference matches
 * match_terms (same mechanism as the existing ad-hoc category
 * breakdown), so it always stays current as new payments come in —
 * quantity/unit/rate stay null.
 *
 * display_mode='measured': amount is this row's own authoritative
 * figure, independent of any linked voucher total — set directly, or
 * computed as quantity × rate when both are given. "if i need to
 * update quantities of item it will applied to whole... recalculate...
 * maybe quantity increase or decrease at the end of project" — editing
 * quantity on a measured row recalculates its own amount (quantity ×
 * rate), the same way editing a Subcontract's own BOQ item already
 * recalculates contract_value and, through it, every milestone's
 * amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontract_report_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcontract_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('label');
            $table->string('display_mode', 20)->default('measured');
            $table->string('match_terms')->nullable();
            $table->decimal('quantity', 14, 3)->nullable();
            $table->string('unit', 30)->nullable();
            $table->decimal('rate', 14, 2)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontract_report_categories');
    }
};
