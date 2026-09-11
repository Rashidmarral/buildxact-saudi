<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A company-uploadable footer banner image, mirroring letterhead_path —
 * many Saudi companies print a fixed footer strip (contact details, a
 * brand pattern) below every document/voucher, and that's company-owned
 * artwork, not something the app can generate from structured fields the
 * way the plain-text footer chrome does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->string('footer_path')->nullable()->after('letterhead_path');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->dropColumn('footer_path');
        });
    }
};
