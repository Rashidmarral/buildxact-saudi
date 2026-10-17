<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            // Every completed sale gets a mirrored Invoice so it can go
            // through the already-ZATCA-tested Invoice submission pipeline
            // (Phase 2 clearance/reporting, real cryptographic QR) instead
            // of only ever carrying the Phase 1 QR.
            $table->foreignId('invoice_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pos_sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
