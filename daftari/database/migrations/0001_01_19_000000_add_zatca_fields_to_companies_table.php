<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // developer = ZATCA sandbox, simulation = ZATCA simulation env, production = ZATCA core (live)
            $table->string('zatca_environment', 20)->default('developer')->after('negative_number_format');
            // instant = sync on every invoice event, hourly/daily/weekly = scheduled batch, manual = user-triggered only
            $table->string('zatca_sync_frequency', 20)->default('manual')->after('zatca_environment');
            $table->boolean('zatca_sync_b2b')->default(true)->after('zatca_sync_frequency');
            $table->boolean('zatca_sync_b2c')->default(true)->after('zatca_sync_b2b');
            // not_started, csr_generated, compliance_pending, compliance_verified, onboarded, failed
            $table->string('zatca_onboarding_status', 30)->default('not_started')->after('zatca_sync_b2c');
            $table->string('zatca_egs_serial', 100)->nullable()->after('zatca_onboarding_status');
            $table->text('zatca_csr')->nullable()->after('zatca_egs_serial');
            $table->text('zatca_private_key')->nullable()->after('zatca_csr');
            $table->text('zatca_compliance_request_id')->nullable()->after('zatca_private_key');
            $table->text('zatca_compliance_csid')->nullable()->after('zatca_compliance_request_id');
            $table->text('zatca_compliance_secret')->nullable()->after('zatca_compliance_csid');
            $table->text('zatca_production_request_id')->nullable()->after('zatca_compliance_secret');
            $table->text('zatca_production_csid')->nullable()->after('zatca_production_request_id');
            $table->text('zatca_production_secret')->nullable()->after('zatca_production_csid');
            $table->string('zatca_last_invoice_hash', 128)->nullable()->after('zatca_production_secret');
            $table->timestamp('zatca_linked_at')->nullable()->after('zatca_last_invoice_hash');
            $table->timestamp('zatca_last_sync_at')->nullable()->after('zatca_linked_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'zatca_environment', 'zatca_sync_frequency', 'zatca_sync_b2b', 'zatca_sync_b2c',
                'zatca_onboarding_status', 'zatca_egs_serial', 'zatca_csr', 'zatca_private_key',
                'zatca_compliance_request_id', 'zatca_compliance_csid', 'zatca_compliance_secret',
                'zatca_production_request_id', 'zatca_production_csid', 'zatca_production_secret',
                'zatca_last_invoice_hash', 'zatca_linked_at', 'zatca_last_sync_at',
            ]);
        });
    }
};
