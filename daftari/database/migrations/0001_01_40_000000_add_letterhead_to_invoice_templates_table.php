<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            // Used only by the "custom_letterhead" layout, which replaces the
            // logo+company-text header with a single uploaded banner image —
            // matching letterhead-image-based quotation/invoice formats some
            // companies already use.
            $table->string('letterhead_path')->nullable()->after('show_logo');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->dropColumn('letterhead_path');
        });
    }
};
