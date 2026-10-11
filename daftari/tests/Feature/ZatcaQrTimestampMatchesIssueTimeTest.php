<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ZatcaQrGenerator;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Real-world report: a business owner asked why the generated invoice's
 * "time" never seemed right. Root cause: ZATCA's QR code carries its own
 * timestamp (tag 3), which business rule KSA-25 requires to match the
 * document's own cbc:IssueDate/cbc:IssueTime exactly — but the QR was
 * always built from $document->issue_date alone, a date-only column with
 * no time component (always midnight), while the XML's IssueTime
 * correctly used the real creation time. Every live submission (not just
 * the compliance-check flow, which already got this right) silently
 * encoded a QR timestamp that didn't match its own document.
 */
class ZatcaQrTimestampMatchesIssueTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_phase_1_only_invoices_qr_uses_the_real_creation_time_not_midnight(): void
    {
        $company = Company::create(['name' => 'Test Trading Co.', 'slug' => 'test-'.uniqid(), 'vat_number' => '300012345600003']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'TEST-'.uniqid(),
            'type' => 'standard', 'issue_date' => '2026-10-11', 'currency' => 'SAR',
        ]);
        // created_at (the real creation instant) is forced to a specific
        // time, deliberately not midnight, to prove the QR follows it.
        $invoice->forceFill(['created_at' => Carbon::parse('2026-10-11 14:37:22', 'UTC')])->save();

        InvoiceItem::create([
            'invoice_id' => $invoice->id, 'description' => 'Line', 'quantity' => 1, 'unit_price' => 100,
            'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 115, 'sort_order' => 0,
        ]);
        $invoice->refresh();
        $invoice->recalculateTotals();
        $invoice->refresh();

        $this->assertNotNull($invoice->qr_code);

        $midnightQr = ZatcaQrGenerator::generate(
            $company->name, (string) $company->vat_number, Carbon::parse('2026-10-11 00:00:00', 'UTC'),
            (float) $invoice->total, (float) $invoice->vat_total
        );
        $correctQr = ZatcaQrGenerator::generate(
            $company->name, (string) $company->vat_number, Carbon::parse('2026-10-11 14:37:22', 'UTC'),
            (float) $invoice->total, (float) $invoice->vat_total
        );

        $this->assertNotSame($midnightQr, $invoice->qr_code, 'The QR still encodes midnight instead of the real creation time.');
        $this->assertSame($correctQr, $invoice->qr_code);
    }

    public function test_the_phase_2_qrs_timestamp_exactly_matches_the_xmls_issue_date_and_time(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp256k1']);
        $this->assertNotFalse($key, 'Unable to generate a test EC key pair — is the openssl extension missing secp256k1 support?');
        openssl_pkey_export($key, $privateKeyPem);

        $dn = ['commonName' => 'Test EGS', 'organizationName' => 'Test Co', 'countryName' => 'SA'];
        $csr = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $certPem);
        $certBase64 = base64_encode(str_replace(["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"], '', $certPem));

        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_zatca_phase2' => true,
        ]);
        $company = Company::create([
            'name' => 'Riyadh Co.', 'slug' => 'riyadh-'.uniqid(), 'vat_number' => '399999999900003',
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2, 'zatca_environment' => 'simulation',
            'zatca_sync_b2b' => true, 'zatca_onboarding_status' => 'onboarded',
            'zatca_private_key' => $privateKeyPem,
            'zatca_production_csid' => $certBase64, 'zatca_production_secret' => 'secret',
        ]);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client']);

        $invoice = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'TEST-'.uniqid(),
            'type' => 'standard', 'status' => 'sent', 'issue_date' => '2026-10-11', 'currency' => 'SAR',
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115,
        ]);
        // Issue date and creation instant deliberately differ (document
        // backdated a day) to prove the QR follows the real combination of
        // both fields, not either one alone.
        $invoice->forceFill(['created_at' => Carbon::parse('2026-10-10 22:50:05', 'UTC')])->save();
        $invoice->refresh();

        $capturedXml = null;
        Http::fake(function ($request) use (&$capturedXml) {
            if ($capturedXml === null) {
                $capturedXml = base64_decode($request->data()['invoice']);
            }

            return Http::response(['clearedInvoice' => base64_encode('stamp')], 200);
        });

        $this->actingAs($owner)->post(route('app.zatca.sync.invoice', $invoice));

        $this->assertNotNull($capturedXml);

        $doc = new DOMDocument;
        $doc->loadXML($capturedXml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $issueDate = $xpath->query('//cbc:IssueDate')->item(0)->textContent;
        $issueTime = $xpath->query('//cbc:IssueTime')->item(0)->textContent;
        $this->assertSame('2026-10-11', $issueDate);
        $this->assertSame('22:50:05', $issueTime);

        $qrNode = $xpath->query("//cac:AdditionalDocumentReference[cbc:ID='QR']//cbc:EmbeddedDocumentBinaryObject")->item(0);
        $this->assertNotNull($qrNode, 'Expected a signed QR AdditionalDocumentReference — the signer likely failed.');
        $tlv = base64_decode($qrNode->textContent);
        $timestampTag = $this->tag($tlv, 3);

        // Tag 3 is Zulu/UTC ISO 8601 (trailing "Z"), per
        // ZatcaQrGenerator::buildTlvPayloadPhase2() — the comparison is
        // still against the exact same IssueDate+IssueTime, just with
        // that suffix.
        $this->assertSame($issueDate.'T'.$issueTime.'Z', $timestampTag, "The QR's own timestamp must exactly equal IssueDate+IssueTime (ZATCA rule KSA-25).");
    }

    private function tag(string $tlv, int $tag): ?string
    {
        $offset = 0;
        $len = strlen($tlv);
        while ($offset < $len) {
            $t = ord($tlv[$offset]);
            $valueLength = ord($tlv[$offset + 1]);
            $value = substr($tlv, $offset + 2, $valueLength);
            if ($t === $tag) {
                return $value;
            }
            $offset += 2 + $valueLength;
        }

        return null;
    }
}
