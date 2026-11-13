<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            // A VATEX-SA-* code (App\Support\Zatca\VatExemptionReason) — ZATCA
            // requires one on every Zero-rated/Exempt VAT category breakdown
            // (BR-KSA-24/69/23). Nullable because a standard-rated TaxRate
            // never carries one (BR-S-10 forbids it) and because existing
            // zero_rated/exempt rates predate this column; ZatcaSyncService
            // blocks submission of a document whose line uses a Zero-rated/
            // Exempt rate still missing one, rather than defaulting it.
            $table->string('exemption_reason_code', 20)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropColumn('exemption_reason_code');
        });
    }
};
