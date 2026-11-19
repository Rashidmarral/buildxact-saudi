<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Input VAT on general purchases (bills/expenses not directly tied to one
 * specific sale) was always treated as 100% recoverable — correct for the
 * vast majority of companies, but wrong for one that also makes VAT-exempt
 * supplies (e.g. certain financial services, residential leasing): the
 * portion of general input VAT attributable to exempt output isn't
 * recoverable at all. Saudi VAT law follows the standard proportional-
 * recovery method — a recoverable ratio (taxable supplies / total
 * supplies) applied to non-directly-attributable input VAT.
 *
 * vat_makes_exempt_supplies opts a company into this adjustment (default
 * off — no behavior change for everyone else). vat_recovery_percentage
 * is an optional manual override; left blank, the VAT report computes the
 * ratio itself each period from the company's own exempt vs taxable
 * invoice lines (via TaxRate::TYPE_EXEMPT), which is the standard method
 * and avoids the company having to track and update a ratio by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('vat_makes_exempt_supplies')->default(false)->after('primary_customer_type');
            $table->decimal('vat_recovery_percentage', 5, 2)->nullable()->after('vat_makes_exempt_supplies');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['vat_makes_exempt_supplies', 'vat_recovery_percentage']);
        });
    }
};
