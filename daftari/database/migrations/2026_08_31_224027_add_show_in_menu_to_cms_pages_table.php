<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirrors show_in_footer: lets an admin-created custom page also
 * auto-append itself to the header main_nav, the same way show_in_footer
 * auto-appends it to the "Resources" footer column (see
 * layouts/site.blade.php).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->boolean('show_in_menu')->default(false)->after('show_in_footer');
        });
    }

    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropColumn('show_in_menu');
        });
    }
};
