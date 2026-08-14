<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'attachable_type', 'attachable_id', 'uploaded_by',
        'original_name', 'path', 'size', 'mime_type',
    ];

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        $size = $this->size;

        if ($size < 1024) {
            return $size.' B';
        }

        if ($size < 1024 * 1024) {
            return round($size / 1024, 1).' KB';
        }

        return round($size / (1024 * 1024), 1).' MB';
    }
}
