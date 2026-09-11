<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic content-block engine for the public marketing site: one row per
 * section (a hero, a stats bar, a feature grid, an FAQ block, ...) placed on
 * one page, in `sort_order`. `type` selects which admin form and which
 * public partial render it (see App\Models\CmsSection::TYPES); the same
 * handful of types are reused across every page instead of a bespoke table
 * per page. Repeatable children (feature cards, FAQ entries, testimonials,
 * stat numbers, contact methods, social links) live in cms_section_items.
 *
 * Every free-text field is duplicated _en/_ar rather than routed through the
 * translations table — this is admin-authored content with no English
 * "source string" to translate, unlike the rest of the app's static UI text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_sections', function (Blueprint $table) {
            $table->id();
            $table->string('page', 40);
            $table->string('type', 40);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->string('badge_en')->nullable();
            $table->string('badge_ar')->nullable();
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('subtitle_en')->nullable();
            $table->text('subtitle_ar')->nullable();
            $table->longText('body_en')->nullable();
            $table->longText('body_ar')->nullable();

            $table->string('image_path')->nullable();
            $table->string('link_url')->nullable();
            $table->string('link_text_en')->nullable();
            $table->string('link_text_ar')->nullable();

            $table->timestamps();

            $table->index(['page', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_sections');
    }
};
