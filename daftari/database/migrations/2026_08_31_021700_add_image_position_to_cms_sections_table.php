<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an admin place a hero/text section's image on either side of its
 * text content (see App\Models\CmsSection::TYPES and the site/partials/cms/
 * hero + text partials) instead of always stacking the image below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_sections', function (Blueprint $table) {
            $table->string('image_position', 10)->default('right')->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('cms_sections', function (Blueprint $table) {
            $table->dropColumn('image_position');
        });
    }
};
