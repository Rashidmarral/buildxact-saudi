<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

trait HandlesUploads
{
    /**
     * Store an uploaded file (if present on the request) and return its
     * storage path, deleting the previous file if one is replaced.
     */
    protected function storeUpload(Request $request, string $field, string $directory, ?string $previousPath = null): ?string
    {
        if (! $request->hasFile($field)) {
            return $previousPath;
        }

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return $request->file($field)->store($directory, 'public');
    }
}
