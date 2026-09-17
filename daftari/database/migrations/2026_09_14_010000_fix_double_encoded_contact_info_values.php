<?php

use App\Models\CmsSection;
use Illuminate\Database\Migrations\Migration;

/**
 * Data-repair migration for a real bug: CmsController::updateSection() ran
 * every item's body_en/body_ar through RichText::sanitize() (an HTML
 * round-trip sanitizer) unconditionally — including "contact_info" items,
 * whose body is a plain value (an email or phone number, edited via a
 * plain text input) rather than rich HTML. That round-trip numeric-
 * entity-encodes '+' and '@', turning "+966538069288" into
 * "&#43;966538069288" and "support@daftari.app" into
 * "support&#64;daftari.app" on save — visibly broken once escaped again
 * by {{ }} at render time. The controller now skips sanitization for
 * contact_info items (see CmsController::updateSection()); this migration
 * repairs any rows already corrupted by the old behavior on an existing
 * install. html_entity_decode() is a no-op on already-clean plain text,
 * so this is safe to run against every contact_info row unconditionally.
 */
return new class extends Migration
{
    public function up(): void
    {
        CmsSection::where('type', 'contact_info')->with('items')->get()->each(function (CmsSection $section) {
            $section->body_en = $this->decode($section->body_en);
            $section->body_ar = $this->decode($section->body_ar);
            $section->save();

            $section->items->each(function ($item) {
                $item->body_en = $this->decode($item->body_en);
                $item->body_ar = $this->decode($item->body_ar);
                $item->save();
            });
        });
    }

    public function down(): void
    {
        // Not reversible: decoding is one-directional and safe to leave
        // as-is, since the "before" state was itself a bug.
    }

    private function decode(?string $value): ?string
    {
        return $value === null ? null : html_entity_decode($value, ENT_QUOTES | ENT_HTML5);
    }
};
