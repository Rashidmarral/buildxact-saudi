<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-text vehicle/device compatibility notes for a parts item (e.g.
     * "Toyota Camry 2018–2022, Corolla 2015–2020") — the Auto Parts half
     * of the Repair Shop module (see roadmap). Kept as plain text rather
     * than a structured make/model/year table: far cheaper to build, and
     * still searchable/displayable, which is all a parts counter needs.
     * Not gated behind the repair_shop module — it's just an optional
     * field on the existing Item form, usable by any company.
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->text('compatibility_notes')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('compatibility_notes');
        });
    }
};
