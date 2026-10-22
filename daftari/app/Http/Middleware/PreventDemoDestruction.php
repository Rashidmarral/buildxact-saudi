<?php

namespace App\Http\Middleware;

use App\Support\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses any DELETE-method request from a demo company's own user
 * (Module 23) — every hard-delete action in the company panel (clients,
 * items, invoices, team members, roles, ...) is wired through a DELETE
 * route, so gating on HTTP method here is a single, comprehensive choke
 * point rather than adding a check to dozens of individual destroy()
 * actions. Applied once at the top of the 'app.' route group; a Super
 * Admin acting from the admin panel is unaffected (their own
 * company_id is null, so this never triggers for them) — deliberately,
 * since platform staff still need to be able to manage/reset a demo
 * company from the admin side.
 *
 * Commercial audit finding (post-Module-23): void/cancel/revoke actions
 * (Bill/CreditNote/PurchaseOrder/PurchaseReturn/PaymentVoucher/
 * ReceiptVoucher::void(), Invoice::cancel(), StockAdjustment::revoke(),
 * Billing::cancel()) are just as destructive as a hard delete — they
 * reverse a posted journal entry and permanently change a document's
 * status — but are routed as POST, not DELETE, so the check above alone
 * never caught them. Matched by route-name suffix rather than by
 * enumerating each one, so a future `.void`/`.cancel`/`.revoke` action
 * is covered automatically instead of silently slipping through again.
 */
class PreventDemoDestruction
{
    private const DESTRUCTIVE_ROUTE_SUFFIXES = ['.void', '.cancel', '.revoke'];

    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->user()?->company;

        if ($company?->isDemo() && ($request->isMethod('delete') || $this->isDestructiveAction($request))) {
            return back()->withErrors(['demo' => DemoMode::deletingRecords()]);
        }

        return $next($request);
    }

    private function isDestructiveAction(Request $request): bool
    {
        $routeName = (string) $request->route()?->getName();

        foreach (self::DESTRUCTIVE_ROUTE_SUFFIXES as $suffix) {
            if (str_ends_with($routeName, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
