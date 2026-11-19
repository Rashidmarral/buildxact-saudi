<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\AuditLog;
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
 * actually rendered on unauthenticated pages: the public invoice-pay link
 * and /certificates marketing page (company branding images), and the
 * platform's own marketing site, login page, CMS pages, and outbound
 * transactional emails (platform/admin branding + CMS content images —
 * 'platform', 'branding', 'cms'). Everything else uploaded to the 'public'
 * disk (invoice/bill/PO/quotation attachments, company documents, item
 * images) is business data scoped to one tenant, so it's only served to a
 * logged-in user whose company actually owns the matching Attachment/Item
 * row — BelongsToCompany's global scope makes that check tenant-safe for
 * free.
 */
class FileServeController extends Controller
{
    private const PUBLIC_PREFIXES = [
        'logos/', 'stamps/', 'letterheads/', 'footers/', 'watermarks/', 'platform-documents/',
        'platform/', 'branding/', 'cms/',
        // Audit finding UX-01: favicons are uploaded to 'favicons/'
        // (SettingsController::update()) but this list omitted that
        // prefix, so even the owning company's own logged-in users got a
        // 404 on their own favicon — the same public-branding treatment
        // already given to logos/ and stamps/ was simply missing here.
        'favicons/',
    ];

    public function show(string $filepath): Response
    {
        if (Str::contains($filepath, '..')) {
            abort(404);
        }

        abort_unless(Storage::disk('public')->exists($filepath), 404);

        if (! Str::startsWith($filepath, self::PUBLIC_PREFIXES)) {
            abort_unless(Auth::check(), 404);

            $user = Auth::user();

            // Security audit finding SEC-03: this route sits outside the
            // normal auth/company-membership/active-company middleware
            // group entirely (it's a bare top-level route — see
            // routes/web.php), so Auth::check() alone used to be the only
            // gate. A tenantless platform account (now denied by
            // BelongsToCompany's global scope — see that trait) or a
            // staff member suspended mid-session (status flips are only
            // enforced at login, not on every request) must not reach a
            // document lookup at all. Checking company_id/status
            // explicitly here, rather than only through the model scope,
            // is deliberate defense in depth for exactly this route: it
            // keeps working even if a future query on this path is ever
            // written with withoutGlobalScopes() by mistake.
            abort_unless($user->company_id && $user->status === 'active', 404);

            $attachment = Attachment::where('path', $filepath)->where('company_id', $user->company_id)->first();
            $itemMatch = Item::where('image_path', $filepath)->where('company_id', $user->company_id)->exists();
            abort_unless($attachment || $itemMatch, 404);

            // Attachment downloads are audit-logged (receipts, contracts,
            // bank statements are sensitive); Item images (product photos)
            // aren't, so only Attachment downloads are logged here.
            if ($attachment) {
                AuditLog::record('attachment.download', $attachment, __('Downloaded attachment :name', ['name' => $attachment->original_name]));
            }
        }

        return Storage::disk('public')->response($filepath);
    }
}
