<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Item;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves files from the 'public' storage disk directly through PHP instead
 * of relying on the storage:link symlink + web server static serving.
 * Symlinks are the standard Laravel approach, but they're a frequent source
 * of broken 403s in practice — most commonly on Windows/XAMPP, where
 * creating a real symlink requires admin rights and Apache's
 * "Options -FollowSymLinks" then blocks the fallback junction Windows
 * creates instead. Routing through here works identically on every
 * platform and hosting setup, with no server configuration required.
 *
 * The route parameter is deliberately named $filepath, not $path — a
 * parameter literally named "path" collides with something in Symfony's
 * routing internals under PHP's built-in dev server (confirmed by direct
 * testing: identical route/controller logic 403s with {path} and works
 * fine with {filepath}).
 *
 * Only a fixed set of folders is served with no login required — the ones
 * actually rendered on unauthenticated pages (the public invoice-pay link,
 * and the public /certificates marketing page). Everything else uploaded
 * to the 'public' disk (invoice/bill/PO/quotation attachments, company
 * documents, item images) is business data scoped to one tenant, so it's
 * only served to a logged-in user whose company actually owns the
 * matching Attachment/Item row — BelongsToCompany's global scope makes
 * that check tenant-safe for free.
 */
class FileServeController extends Controller
{
    private const PUBLIC_PREFIXES = ['logos/', 'stamps/', 'letterheads/', 'footers/', 'watermarks/', 'platform-documents/'];

    public function show(string $filepath): Response
    {
        if (Str::contains($filepath, '..')) {
            abort(404);
        }

        abort_unless(Storage::disk('public')->exists($filepath), 404);

        if (! Str::startsWith($filepath, self::PUBLIC_PREFIXES)) {
            abort_unless(Auth::check(), 404);
            abort_unless(
                Attachment::where('path', $filepath)->exists() || Item::where('image_path', $filepath)->exists(),
                404
            );
        }

        return Storage::disk('public')->response($filepath);
    }
}
