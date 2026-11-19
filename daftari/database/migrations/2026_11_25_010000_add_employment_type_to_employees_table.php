<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "add option to add employees for project on contract or full time" —
 * an informational classification only (job type for record-keeping;
 * it does not restrict anything an employee can be assigned to or
 * selected for). Defaults every existing employee to 'full_time' since
 * that's the common case and nothing on file distinguishes the two.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('employment_type', 20)->default('full_time')->after('department'); // full_time, contract
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('employment_type');
        });
    }
};
