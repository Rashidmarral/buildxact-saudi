# Security & Access Audit

Companion to `INITIAL_AUDIT_REPORT.md` §4. Covers tenant isolation, authorization, mass assignment, audit logging, file access, 2FA, and API scoping — grounded in direct code inspection.

## Tenant isolation

Every tenant-scoped model uses `BelongsToCompany` (`app/Models/Concerns/BelongsToCompany.php`): a global Eloquent scope that filters by `Auth::user()->company_id`, active only when `Auth::check()` is true, plus an auto-fill of `company_id` on `creating()`.

**This is a single-layer defense — there is no `app/Policies/` directory at all**, so there is no independent second check beyond the global scope. Roughly 100 `withoutGlobalScopes()` call sites exist; every one spot-checked during this engagement was a legitimate cross-company platform-admin path, an unauthenticated job/command (where the scope is already inert, since there's no `Auth::user()`), or a public share-link controller using a separate token-based check. **No active, exploitable cross-company data leak was found** during this pass — but the architecture's structural risk (one mechanism, not two) remains and is not something this session's fixes address. A systematic policy-layer rollout (at minimum for the highest-value models: Invoice, Bill, JournalEntry, Client) would be the right follow-up, sized as a dedicated phase of its own given the number of controllers involved.

## What's already solid (verified)

- **Public share links** (invoice/quotation) and **attachment storage** rely on unguessable, high-entropy random tokens (`Str::random(40)`), compared via `hash_equals` — an acceptable pattern for unauthenticated access.
- **Issued-invoice deletion** is well-guarded: ZATCA-locked invoices can never be deleted; only drafts can be; draft deletion is itself audit-logged.
- **Mass assignment is safe** where it matters: sensitive fields (`status`, `approved_by`, `approved_at`) are `$fillable` but spot-checked controllers (Expense, Invoice, Bill) never pass raw request input into them — approval actions set them explicitly in dedicated methods, never via `$request->all()`.
- **API tokens are actually scope-checked** (`abilities:read`/`abilities:write` middleware on the relevant route groups), not just issued and ignored.
- **2FA is real**, with both failed and successful verification attempts logged.
- **ZATCA credentials are encrypted at rest** (Laravel `encrypted` cast on `zatca_private_key` and both CSID secret fields) — no plaintext storage found.

## File access control — correction to this finding (SEC-03)

This finding, as originally written in this audit, was **wrong**: it claimed attachment URLs carry "no authorization check once the URL is known." Re-verification while implementing the fix found that this had already been closed, in a session before this audit (commit `94c3832`, "Fix critical/high security findings from SaaS audit", finding CRIT-01) — not by this engagement, and missed during this audit's own Phase 1 inspection:

- `config/filesystems.php`'s `'links'` array is deliberately empty, so `php artisan storage:link` creates no `public/storage` symlink — there is no path by which the web server can hand out attachment files directly.
- Every file on the `public` disk resolves through `/files/{filepath}` → `FileServeController::show()`, which requires `Auth::check()` and, for anything outside a small fixed list of public branding prefixes, requires a matching `Attachment` or `Item` row to exist — and that lookup runs under `BelongsToCompany`'s global scope, so a different company's attachment path 404s even if the exact filename is known.
- This is covered by its own pre-existing regression suite, `tests/Feature/PublicStorageSymlinkRemovedTest.php`, which still passes.

**What was genuinely missing**, and is the actual fix made this session: no record existed of *who* downloaded a sensitive attachment (bank statements, contracts, personal data) or *when*. `FileServeController::show()` now calls `AuditLog::record('attachment.download', ...)` on every successful, authorized attachment download (not on `Item` image loads, which aren't sensitive in the same way, and not on the public branding prefixes, which need no login at all). Covered by `tests/Feature/AttachmentDownloadAuditTest.php`: same-company download logs an entry with the correct actor and company; a cross-company request still 404s and logs nothing; an unauthenticated request 404s.

Still open, genuinely out of scope for this session: no expiry or revocation for a once-valid download (a link never "goes stale" on its own) — would require moving to signed/expiring URLs, a larger change to how every attachment link is generated across ~13 controllers for a low-severity residual gap given the access check already in place.

## Audit log coverage — verified, not assumed

The parent report's SEC-08 finding, as originally raised, listed four suspected gaps. Direct verification in this engagement (not taken on faith) found:

| Suspected gap | Actual finding |
|---|---|
| Manual journal entry creation not logged | **False alarm — already logged** (`journal_entry.create_manual` in `ManualJournalEntryController::store()`, pre-existing) |
| Tax-rate configuration changes not logged | **Confirmed true — zero `AuditLog::record()` calls existed in `TaxRateController`.** **Fixed this session**: create/update/delete/VAT-recovery-settings changes are now all logged, with before/after values on update. |
| User role changes not logged | **Not actually a logging gap — the feature doesn't exist.** `TeamController` has no action to change an existing member's role at all; a role is only ever set once, at invite time (and that *is* logged as `team.add`). Changing someone's role today requires removing and re-inviting them, both of which are already logged (`team.remove`/`team.add`). Not fixed — there's nothing to log that isn't already logged; adding role-editing itself would be a new feature, out of scope here. |
| ZATCA credential/settings changes not logged | **False alarm — already logged extensively**: `zatca.mode_update`, `zatca.settings_update`, `zatca.csr_generate`, `zatca.compliance_csid_issue`, `zatca.production_csid_issue`, `zatca.onboarding_reset` all exist and fire at the relevant points in `ZatcaController`. |

This is a useful lesson for how this audit was conducted: the inspection agents' own findings were explicitly flagged as uncertain in two of these four cases ("I could not confirm within budget... don't take my non-finding as proof of absence"), and direct verification before implementing caught that two of the four were false alarms. Only the genuinely-confirmed gap (tax rates) was fixed.

## What a security review should still cover

- A systematic Policy-layer audit, one controller at a time, rather than this pass's spot-check.
- Signed/expiring URLs for attachment downloads, if revocation of a previously-valid link is ever required (see the SEC-03 correction above — the access-control gap itself is already closed).
- A full enumeration of every `AuditLog::record()` call site (this pass's grep hit 41KB of matches — too large to fully catalog in one sitting) to find any remaining gaps beyond the four checked above.
- Whether 2FA is enforceable per-role or always optional (not verified in this pass).
