<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformDocument extends Model
{
    protected $fillable = ['title', 'title_ar', 'description', 'file_path', 'mime_type', 'sort_order'];

    public function titleFor(string $locale): string
    {
        if ($locale === 'ar' && $this->title_ar) {
            return $this->title_ar;
        }

        return $this->title;
    }
}
