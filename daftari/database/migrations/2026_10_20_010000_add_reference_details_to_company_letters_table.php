<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Free-text overflow for the kind of project/reference info a real
 * agreement header needs but that has no structured home on this table —
 * a client's own PO reference and date, the quotation it responds to, a
 * site location, and the like. Mirrors party_b_details: optional,
 * free-typed, rendered as its own row in letter-pdf.blade.php only when
 * present.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_letters', function (Blueprint $table) {
            $table->text('reference_details')->nullable()->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('company_letters', function (Blueprint $table) {
            $table->dropColumn('reference_details');
        });
    }
};
