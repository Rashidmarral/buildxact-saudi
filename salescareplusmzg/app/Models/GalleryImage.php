<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class GalleryImage extends Model
{
    use LogsActivity;

    protected $fillable = [
        'title',
        'caption',
        'image_path',
        'illustration',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];
}
