<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matching Hamly ERP's Projects module field set (per the user's "build
 * the project module same as Hamly, with complete details" request):
 * type, business location, project manager, billing type (how this
 * project gets invoiced — fixed price / time & material / milestones /
 * progress with retention / recurring AMC / per-unit-sale / no billing),
 * default VAT rate, contract value, retention terms, advance recovery,
 * cost centre, and site address. target_revenue/cost_ceiling (margin
 * goal / cost cap) stay as they are — contract_value is the actual
 * signed amount the billing phase calculates against, a distinct number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('type', 30)->default('construction_contract')->after('name_ar');
            $table->foreignId('business_location_id')->nullable()->after('client_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('project_manager_id')->nullable()->after('business_location_id')->constrained('employees')->nullOnDelete();
            $table->string('billing_type', 30)->default('milestones')->after('status');
            $table->foreignId('default_tax_rate_id')->nullable()->after('billing_type')->constrained('tax_rates')->nullOnDelete();
            $table->decimal('contract_value', 14, 2)->nullable()->after('cost_ceiling');
            $table->decimal('retention_percent', 5, 2)->nullable()->after('contract_value');
            $table->string('retention_calculated_on', 20)->default('excluding_vat')->after('retention_percent');
            $table->decimal('advance_recovery_percent', 5, 2)->nullable()->after('retention_calculated_on');
            $table->foreignId('cost_center_id')->nullable()->after('advance_recovery_percent')->constrained('cost_centers')->nullOnDelete();
            $table->string('site_address', 255)->nullable()->after('cost_center_id');
            $table->string('city', 100)->nullable()->after('site_address');
            $table->text('description')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_location_id');
            $table->dropConstrainedForeignId('project_manager_id');
            $table->dropConstrainedForeignId('default_tax_rate_id');
            $table->dropConstrainedForeignId('cost_center_id');
            $table->dropColumn([
                'type', 'billing_type', 'contract_value', 'retention_percent',
                'retention_calculated_on', 'advance_recovery_percent', 'site_address', 'city', 'description',
            ]);
        });
    }
};
