<?php

namespace App\Models\Concerns;

trait HasBilingualName
{
    /**
     * The name to show in the UI for the current viewer: name_ar when
     * Arabic is active and one is set, otherwise the primary name. Kept
     * separate from the raw `name` attribute (which stays whatever was
     * entered — used verbatim in ZATCA XML, historical line-item
     * snapshots, and the record's own bilingual edit form) so switching
     * the interface language only changes what's displayed, never what's
     * stored, submitted, or already printed on an existing document.
     */
    public function getDisplayNameAttribute(): ?string
    {
        if (app()->getLocale() === 'ar' && ! empty($this->name_ar)) {
            return $this->name_ar;
        }

        return $this->name;
    }
}
