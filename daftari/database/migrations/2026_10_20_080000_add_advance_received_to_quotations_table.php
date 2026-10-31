<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Mainly meaningful for type=proforma — a client-facing proforma
            // invoice often needs to state an advance already paid (e.g.
            // "50% received, balance due before delivery") even though
            // nothing has been recorded against a real Invoice yet, since
            // a proforma isn't a posted financial document. Left null for
            // an ordinary quotation, where it has no meaning.
            $table->decimal('advance_received', 12, 2)->nullable()->after('total');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('advance_received');
        });
    }
};
