<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-editable overrides for the app's translation strings. The app calls
 * __('Some English string') everywhere (no lang/en.json — English is the
 * literal key), with lang/ar.json supplying the Arabic value; a row here for
 * (locale, group, key) takes precedence over both, via DbOverlayTranslationLoader.
 *
 * `key` is unbounded text (some translated strings are full sentences), so
 * uniqueness can't be a plain column index — key_hash (sha1 of locale|group|key)
 * carries the composite unique constraint instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();
            $table->string('locale', 10);
            $table->string('group', 60)->default('*');
            $table->text('key');
            $table->char('key_hash', 40);
            $table->text('value');
            $table->timestamps();

            $table->unique(['locale', 'group', 'key_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
