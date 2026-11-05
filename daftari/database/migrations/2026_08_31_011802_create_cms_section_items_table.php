<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repeatable children of a cms_sections row — a feature tile, an FAQ
 * question/answer, a testimonial, a stat number, a contact method, a social
 * link. `meta` carries type-specific extras that don't need their own
 * column (e.g. a social link's target URL, a testimonial's rating) so this
 * one table serves every section type without a schema change per type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_section_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->string('icon')->nullable();
            $table->string('image_path')->nullable();
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->string('subtitle_en')->nullable();
            $table->string('subtitle_ar')->nullable();
            $table->text('body_en')->nullable();
            $table->text('body_ar')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['cms_section_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_section_items');
    }
};
