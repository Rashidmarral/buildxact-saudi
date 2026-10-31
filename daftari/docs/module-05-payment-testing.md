# Module 05 — Payment & Billing Management: Manual Testing Instructions

These steps exercise the admin Payments module end-to-end without needing a
live gateway account. All amounts are illustrative.

## Setup

1. Log in as a super admin and go to **Admin → Payments**.
2. In a second tab, go to **Admin → Plans** and confirm at least one active
   plan exists (e.g. "Pro").
3. Sign up (or use an existing) test company from the public site so there
   is at least one company with an active subscription to work with.

## 1. Payments list & filters

1. Open **Admin → Payments**. Confirm the table shows Date, Company,
   Customer, Plan, Amount, Gateway, Transaction ID, Status.
2. Use the **Status** filter to select "Pending" — only pending payments
   should remain.
3. Use the **Gateway** filter to select a provider (e.g. "Moyasar") —
   only payments with that method should remain.
4. Set **Amount min**/**Amount max** to a narrow range and confirm only
   payments in that range show.
5. Set **Date from**/**Date to** and confirm the list narrows accordingly.
6. Clear filters via the **Clear** link and confirm the full list returns.
7. Type a company name fragment into the search box and confirm only that
   company's payments show.

## 2. Record a manual payment

1. Click **+ Record manual payment**.
2. Pick a company with an active or past-due subscription, enter an
   amount (e.g. `100`) and an optional reference (e.g. `CASH-001`).
3. Submit. Confirm you land on the new payment's detail page with status
   **Successful** and the reference you entered.
4. If the company's subscription was `past_due`/`grace_period`/
   `suspended`, open **Admin → Companies → [company] → Subscription** and
   confirm its status is now **Active**.

## 3. Payment detail page

1. From the Payments list, click **View** on any payment.
2. Confirm every section renders: Payment information, Gateway response,
   Related subscription, Related invoice, Timeline, Audit history.
3. For a payment with no linked gateway transaction (bank transfer or
   manual), confirm "Gateway response" shows the "no gateway transaction
   linked" message instead of a broken toggle.
4. For a **Successful** payment, click **Download receipt** and confirm a
   PDF downloads.

## 4. Refund — full and partial

1. Open a **Successful** payment's detail page and click **Refund**.
2. Leave the amount field blank and submit — confirm the status becomes
   **Refunded** and the confirm dialog fired.
3. On a *different* successful payment, click **Refund**, enter an amount
   less than the total (e.g. half), and submit. Confirm the status
   becomes **Partially refunded** and "Remaining refundable" shows the
   correct leftover amount.
4. Click **Refund** again on that same payment and try to enter an amount
   larger than the remaining balance — confirm it's rejected with a
   validation error.
5. Refund the remaining balance — confirm the status flips to **Refunded**
   and the Refund button disappears (nothing left to refund).

## 5. Retry a failed payment

1. Find (or create, via **Admin → Companies → [company] → Subscription →
   Upgrade/Downgrade** while a gateway is misconfigured, or directly via
   tinker) a payment with status **Failed** and a real gateway method
   (Moyasar/HyperPay/Tap/PayTabs — not bank transfer or manual).
2. On its detail page, click **Retry failed payment**.
3. If a platform gateway for that provider is enabled and reachable,
   confirm a new **Pending** payment appears in the list for the same
   subscription, and an audit entry `payment.retry` appears in the
   original payment's Audit history.
4. Confirm the same button is **not shown** on a bank-transfer or manual
   payment's detail page.

## 6. Confirm a bank transfer

1. Go to **Admin → Settings → Payment gateways**, enable **Bank transfer**
   at the platform level with real-looking bank details.
2. As a company user, go to **Billing → Plans**, choose a paid plan, and
   pick "Pay by bank transfer" at checkout. Confirm a **Pending** payment
   is created.
3. Back in Admin → Payments, open that payment and click **Confirm
   transfer**. Confirm status becomes **Successful**, `paid_at` is set,
   and the company's subscription becomes **Active**.

## 7. Payment gateway configuration & secret masking

1. Go to **Admin → Settings → Payment gateways**.
2. Configure a provider (e.g. Moyasar) with a test **Secret key** and
   **Webhook secret**, set **Mode** to `test`, enable it, and save.
3. Reload the page — confirm the secret fields show as masked
   ("configured", not the plaintext value) and are never re-populated in
   the HTML source (view source / inspect the input value attribute).
4. Leave the secret field blank on a subsequent save — confirm the
   previously-stored secret is preserved (not cleared), by checking that
   payments still verify successfully afterward (step 8).

## 8. Webhook: signature verification, duplicate protection, event logging

Use `curl` against your local/staging URL. Replace `whsec-123` with the
webhook secret you configured for Moyasar in step 7, and `<reference>`
with a real `payment_transactions.reference` UUID (create one by starting
a real online checkout from Billing, or via `php artisan tinker`).

**Valid signature (should succeed):**
```bash
curl -i -X POST https://<your-host>/payments/webhook/moyasar \
  -H "Content-Type: application/json" \
  -d '{"secret_token":"whsec-123","data":{"id":"moy_test_1","status":"paid","metadata":{"reference":"<reference>"}}}'
```
Expect `200 OK`. Confirm the linked payment flips to **Successful** and
the subscription becomes **Active**.

**Invalid signature (should be rejected):**
```bash
curl -i -X POST https://<your-host>/payments/webhook/moyasar \
  -H "Content-Type: application/json" \
  -d '{"secret_token":"wrong-secret","data":{"id":"moy_test_1","status":"paid","metadata":{"reference":"<reference>"}}}'
```
Expect `400 Bad Request`. Nothing about the payment should change.

**Duplicate delivery (should be short-circuited):**
Re-send the exact same valid-signature request from the first curl call a
second time. Expect `200 OK` again, but confirm via
`php artisan tinker` → `App\Models\PaymentGatewayWebhookEvent::latest()->get(['status','fingerprint'])`
that a second row was logged with `status = 'duplicate'`, and that the
payment/subscription were **not** re-processed a second time (check
`updated_at` on the payment didn't change on the replay).

**Unresolvable reference (should 404):**
```bash
curl -i -X POST https://<your-host>/payments/webhook/moyasar \
  -H "Content-Type: application/json" \
  -d '{"secret_token":"whsec-123","data":{"id":"moy_test_1","status":"paid","metadata":{"reference":"00000000-0000-0000-0000-000000000000"}}}'
```
Expect `404 Not Found`, and a `payment_gateway_webhook_events` row logged
with `status = 'rejected'`.

## 9. Automated tests

For a faster, repeatable pass covering all of the above (and more edge
cases — partial-then-full refund, chargeback status display, tenant
isolation), run:

```bash
APP_ENV=testing php artisan test --filter=PaymentManagementTest
```

All 14 tests should pass.
