<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Report, Section 3: a proper code, grouping and GL/VAT metadata
 * for expense categories — a category can now carry a short stable
 * code for reporting, group under a parent (Direct Project Costs,
 * General & Administrative, Payroll, Owner/Personal, Tax & VAT), link
 * to a specific GL account other than the 5100 default the way
 * Expense.account_id already does per transaction, flag whether its
 * spending is a direct project cost or needs a project selected, and
 * flag whether its input VAT is normally recoverable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name_ar');
            $table->foreignId('parent_category_id')->nullable()->after('code')->constrained('expense_categories')->nullOnDelete();
            $table->foreignId('linked_account_id')->nullable()->after('parent_category_id')->constrained('accounts')->nullOnDelete();
            $table->boolean('is_direct_cost')->default(false)->after('linked_account_id');
            $table->boolean('requires_project')->default(false)->after('is_direct_cost');
            $table->boolean('vat_eligible')->default(true)->after('requires_project');
            $table->boolean('is_active')->default(true)->after('vat_eligible');
        });
    }

    public function down(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_category_id');
            $table->dropConstrainedForeignId('linked_account_id');
            $table->dropColumn(['code', 'is_direct_cost', 'requires_project', 'vat_eligible', 'is_active']);
        });
    }
};
