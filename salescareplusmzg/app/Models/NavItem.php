<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NavItem extends Model
{
    protected $fillable = [
        'label',
        'location',
        'route_name',
        'page_id',
        'url',
        'sort_order',
        'is_visible',
        'open_in_new_tab',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'open_in_new_tab' => 'boolean',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Resolve the href for this nav item, or null if it can't be resolved
     * (e.g. it points at a route or page that no longer exists).
     */
    public function resolveUrl(): ?string
    {
        if ($this->url) {
            return $this->url;
        }

        if ($this->page_id) {
            return $this->page && $this->page->is_published
                ? route('page.show', $this->page->slug)
                : null;
        }

        if ($this->route_name && \Illuminate\Support\Facades\Route::has($this->route_name)) {
            return route($this->route_name);
        }

        return null;
    }

    public function isActive(): bool
    {
        if ($this->route_name) {
            return request()->routeIs($this->route_name) || request()->routeIs($this->route_name.'.*');
        }

        if ($this->page_id && $this->page) {
            return request()->routeIs('page.show') && request()->route('slug') === $this->page->slug;
        }

        return false;
    }
}
