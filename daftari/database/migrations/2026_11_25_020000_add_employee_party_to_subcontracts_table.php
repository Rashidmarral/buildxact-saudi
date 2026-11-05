<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "we have to make shahid as a project manager not a supplier... assign
 * employee a project and then select it on vouchers as well" — a
 * subcontract's budget/milestone/BOQ/category-breakdown tracking is
 * exactly what an internally-managed work package (cash advanced to an
 * employee/project manager, who then spends it against cost categories
 * like base course/asphalt) also needs, so this reuses the Subcontract
 * model rather than building a second, parallel tracker. Mirrors the
 * party_type pattern payment_vouchers/receipt_vouchers already use:
 * supplier_id becomes optional, employee_id is the alternative, and
 * party_type says which one actually applies. An existing
 * supplier-based subcontract (like Shahid's current record) keeps
 * working unchanged — converting one to an employee party is a manual
 * edit through the form, not something this migration does on its own,
 * since picking the right employee for an existing real contract isn't
 * something a migration can infer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subcontracts', function (Blueprint $table) {
            $table->string('party_type', 10)->default('supplier')->after('project_id'); // supplier, employee
            $table->foreignId('employee_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });

        Schema::table('subcontracts', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable()->change();
        });

        Schema::table('subcontracts', function (Blueprint $table) {
            $table->index(['company_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::table('subcontracts', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'employee_id']);
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn('party_type');
        });

        Schema::table('subcontracts', function (Blueprint $table) {
            $table->unsignedBigInteger('supplier_id')->nullable(false)->change();
        });
    }
};
