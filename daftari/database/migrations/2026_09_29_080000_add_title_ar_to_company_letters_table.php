<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `title` was English-only, so a letter's title bar/subject line printed
 * the same English text on both sides of the bilingual PDF even when the
 * body content was fully bilingual. `title_ar` is optional — falls back
 * to `title` when blank, matching how every other bilingual field in this
 * module (party names, content blocks) already tolerates a missing
 * Arabic side.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_letters', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('company_letters', function (Blueprint $table) {
            $table->dropColumn('title_ar');
        });
    }
};
