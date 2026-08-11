<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    protected $fillable = [
        'content_item_id',
        'job_title',
        'name',
        'email',
        'phone',
        'cover_message',
        'resume_path',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function jobOpening(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }
}
