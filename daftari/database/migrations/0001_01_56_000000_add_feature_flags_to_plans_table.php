<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('price_monthly_original', 10, 2)->nullable()->after('price_monthly');
            $table->decimal('price_yearly_original', 10, 2)->nullable()->after('price_yearly');

            $table->unsignedInteger('max_invoice_templates')->nullable()->after('max_suppliers'); // null = unlimited
            $table->unsignedInteger('max_warehouses')->nullable()->after('max_invoice_templates');
            $table->unsignedInteger('max_bank_accounts')->nullable()->after('max_warehouses');
            $table->unsignedInteger('max_branches')->nullable()->after('max_bank_accounts');

            $table->boolean('has_recurring_invoices')->default(false)->after('max_branches');
            $table->boolean('has_quotations')->default(false)->after('has_recurring_invoices');
            $table->boolean('has_stamps')->default(false)->after('has_quotations');
            $table->boolean('has_financial_statements')->default(false)->after('has_stamps');
            $table->boolean('has_vat_return_report')->default(false)->after('has_financial_statements');
            $table->boolean('has_cost_centers')->default(false)->after('has_vat_return_report');
            $table->boolean('has_purchase_orders')->default(false)->after('has_cost_centers');
            $table->boolean('has_debit_notes')->default(false)->after('has_purchase_orders');
            $table->boolean('has_roles_permissions')->default(false)->after('has_debit_notes');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'price_monthly_original', 'price_yearly_original',
                'max_invoice_templates', 'max_warehouses', 'max_bank_accounts', 'max_branches',
                'has_recurring_invoices', 'has_quotations', 'has_stamps', 'has_financial_statements',
                'has_vat_return_report', 'has_cost_centers', 'has_purchase_orders', 'has_debit_notes',
                'has_roles_permissions',
            ]);
        });
    }
};
