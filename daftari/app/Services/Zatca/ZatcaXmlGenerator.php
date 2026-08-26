<?php

namespace App\Services\Zatca;

use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\TaxRate;
use DOMDocument;
use Illuminate\Support\Str;

/**
 * Builds the UBL 2.1 XML representation of an invoice per ZATCA's
 * e-invoicing implementation standard: standard tax invoices (B2B) use
 * InvoiceTypeCode name "0100000" (cleared before delivery to the buyer),
 * simplified invoices (B2C) use "0200000" (reported after delivery).
 */
class ZatcaXmlGenerator
{
    public function generate(Invoice $invoice, string $invoiceTypeName, ?string $previousInvoiceHash, string $uuid, int $icv = 1): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $root = $doc->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $doc->appendChild($root);

        $company = $invoice->company;
        $client = $invoice->client;

        $this->append($doc, $root, 'cbc:ProfileID', 'reporting:1.0');
        $this->append($doc, $root, 'cbc:ID', $invoice->invoice_number);
        $this->append($doc, $root, 'cbc:UUID', $uuid);
        $this->append($doc, $root, 'cbc:IssueDate', $invoice->issue_date->format('Y-m-d'));
        $this->append($doc, $root, 'cbc:IssueTime', $invoice->created_at?->format('H:i:s') ?? now()->format('H:i:s'));

        $typeCode = $this->append($doc, $root, 'cbc:InvoiceTypeCode', $invoice->status === 'cancelled' ? '381' : '388');
        $typeCode->setAttribute('name', $invoiceTypeName);

        $this->append($doc, $root, 'cbc:DocumentCurrencyCode', $invoice->currency ?: 'SAR');
        $this->append($doc, $root, 'cbc:TaxCurrencyCode', 'SAR');

        $root->appendChild($this->icvReference($doc, $icv));

        if ($previousInvoiceHash) {
            $root->appendChild($this->pihReference($doc, $previousInvoiceHash));
        }

        $root->appendChild($this->party($doc, 'cac:AccountingSupplierParty', [
            'name' => $company->name,
            'vat_number' => $company->vat_number,
            'id_scheme' => 'CRN',
            'id_value' => $company->cr_number,
            'street' => $company->street_name ?: $company->address,
            'building_number' => $company->building_number,
            'district' => $company->district,
            'city' => $company->city,
            'postal_code' => $company->postal_code,
        ]));

        $root->appendChild($this->party($doc, 'cac:AccountingCustomerParty', [
            'name' => $client->name,
            'vat_number' => $client->vat_number,
            'street' => $client->street_name,
            'building_number' => $client->building_number,
            'district' => $client->district,
            'city' => $client->city,
            'postal_code' => $client->postal_code,
        ]));

        // KSA-5 "supply date" — ZATCA requires standard tax invoices to
        // state the actual delivery/supply date (BR-KSA-15).
        $delivery = $doc->createElement('cac:Delivery');
        $this->append($doc, $delivery, 'cbc:ActualDeliveryDate', $invoice->issue_date->format('Y-m-d'));
        $root->appendChild($delivery);

        $paymentMeans = $doc->createElement('cac:PaymentMeans');
        $this->append($doc, $paymentMeans, 'cbc:PaymentMeansCode', '1');
        $root->appendChild($paymentMeans);

        if ((float) $invoice->discount_total > 0) {
            $root->appendChild($this->documentAllowanceCharge($doc, (float) $invoice->discount_total, $invoice->items));
        }

        $this->appendDualTaxTotal($doc, $root, (float) $invoice->vat_total, $invoice->items, fn ($item) => $item->quantity * $item->unit_price);

        $monetaryTotal = $doc->createElement('cac:LegalMonetaryTotal');
        $this->appendAmount($doc, $monetaryTotal, 'cbc:LineExtensionAmount', $invoice->subtotal);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:TaxExclusiveAmount', $invoice->subtotal - $invoice->discount_total);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:TaxInclusiveAmount', $invoice->total);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:AllowanceTotalAmount', $invoice->discount_total);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:PayableAmount', $invoice->total);
        $root->appendChild($monetaryTotal);

        foreach ($invoice->items as $index => $item) {
            $line = $doc->createElement('cac:InvoiceLine');
            $this->append($doc, $line, 'cbc:ID', (string) ($index + 1));
            $qty = $this->append($doc, $line, 'cbc:InvoicedQuantity', number_format((float) $item->quantity, 2, '.', ''));
            $qty->setAttribute('unitCode', $item->unit?->code ?: ($item->item?->unit_code ?: 'PCE'));
            $lineNet = $item->quantity * $item->unit_price;
            $this->appendAmount($doc, $line, 'cbc:LineExtensionAmount', $lineNet);

            // KSA-11 (line VAT amount) and KSA-12 (line amount with VAT,
            // i.e. net + VAT) — BR-KSA-51 requires both on every line.
            $lineTax = $doc->createElement('cac:TaxTotal');
            $this->appendAmount($doc, $lineTax, 'cbc:TaxAmount', (float) $item->vat_amount);
            $this->appendAmount($doc, $lineTax, 'cbc:RoundingAmount', $lineNet + (float) $item->vat_amount);
            $line->appendChild($lineTax);

            $itemEl = $doc->createElement('cac:Item');
            $this->append($doc, $itemEl, 'cbc:Name', $item->description);
            $taxCategory = $doc->createElement('cac:ClassifiedTaxCategory');
            $this->append($doc, $taxCategory, 'cbc:ID', $this->taxCategoryCode($item));
            $this->append($doc, $taxCategory, 'cbc:Percent', number_format((float) $item->vat_rate, 2, '.', ''));
            $lineTaxScheme = $doc->createElement('cac:TaxScheme');
            $this->append($doc, $lineTaxScheme, 'cbc:ID', 'VAT');
            $taxCategory->appendChild($lineTaxScheme);
            $itemEl->appendChild($taxCategory);
            $line->appendChild($itemEl);

            $price = $doc->createElement('cac:Price');
            $this->appendAmount($doc, $price, 'cbc:PriceAmount', $item->unit_price);
            $line->appendChild($price);

            $root->appendChild($line);
        }

        return $doc->saveXML();
    }

    /**
     * Credit notes use the same UBL Invoice-2 schema as tax invoices — they
     * are differentiated only by InvoiceTypeCode 381 and by carrying a
     * BillingReference back to the invoice they correct, plus a KSA-10
     * InstructionNote reason. They are never issued standalone: they must
     * continue the same Previous Invoice Hash chain as regular invoices.
     */
    public function generateForCreditNote(CreditNote $creditNote, string $invoiceTypeName, ?string $previousInvoiceHash, string $uuid, int $icv = 1): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $root = $doc->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $doc->appendChild($root);

        $company = $creditNote->company;
        $client = $creditNote->client;
        $invoice = $creditNote->invoice;

        $this->append($doc, $root, 'cbc:ProfileID', 'reporting:1.0');
        $this->append($doc, $root, 'cbc:ID', $creditNote->credit_note_number);
        $this->append($doc, $root, 'cbc:UUID', $uuid);
        $this->append($doc, $root, 'cbc:IssueDate', $creditNote->issue_date->format('Y-m-d'));
        $this->append($doc, $root, 'cbc:IssueTime', $creditNote->created_at?->format('H:i:s') ?? now()->format('H:i:s'));

        $typeCode = $this->append($doc, $root, 'cbc:InvoiceTypeCode', '381');
        $typeCode->setAttribute('name', $invoiceTypeName);

        $this->append($doc, $root, 'cbc:DocumentCurrencyCode', $creditNote->currency ?: 'SAR');
        $this->append($doc, $root, 'cbc:TaxCurrencyCode', 'SAR');

        // UBL's InvoiceType sequence places cac:BillingReference before
        // cac:AdditionalDocumentReference (which ICV/PIH use) — emitting
        // it afterward, as this previously did, violates element order
        // once any AdditionalDocumentReference is present.
        $originalLog = $invoice->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->latest('id')->first();

        $billingReference = $doc->createElement('cac:BillingReference');
        $docRef = $doc->createElement('cac:InvoiceDocumentReference');
        $this->append($doc, $docRef, 'cbc:ID', $invoice->invoice_number);
        if ($originalLog?->request_uuid) {
            $this->append($doc, $docRef, 'cbc:UUID', (string) $originalLog->request_uuid);
        }
        $billingReference->appendChild($docRef);
        $root->appendChild($billingReference);

        $root->appendChild($this->icvReference($doc, $icv));

        if ($previousInvoiceHash) {
            $root->appendChild($this->pihReference($doc, $previousInvoiceHash));
        }

        $root->appendChild($this->party($doc, 'cac:AccountingSupplierParty', [
            'name' => $company->name,
            'vat_number' => $company->vat_number,
            'id_scheme' => 'CRN',
            'id_value' => $company->cr_number,
            'street' => $company->street_name ?: $company->address,
            'building_number' => $company->building_number,
            'district' => $company->district,
            'city' => $company->city,
            'postal_code' => $company->postal_code,
        ]));

        $root->appendChild($this->party($doc, 'cac:AccountingCustomerParty', [
            'name' => $client->name,
            'vat_number' => $client->vat_number,
            'street' => $client->street_name,
            'building_number' => $client->building_number,
            'district' => $client->district,
            'city' => $client->city,
            'postal_code' => $client->postal_code,
        ]));

        $delivery = $doc->createElement('cac:Delivery');
        $this->append($doc, $delivery, 'cbc:ActualDeliveryDate', $creditNote->issue_date->format('Y-m-d'));
        $root->appendChild($delivery);

        // KSA-10 "Reason for issuing a Credit/Debit Note" (BR-KSA-17) is
        // cbc:InstructionNote nested under cac:PaymentMeans — a sibling of
        // PaymentMeansCode, per UBL's PaymentMeansType — not a top-level
        // Invoice-document child. Placing it at the document root (as this
        // previously did) is itself an XSD schema violation, on top of the
        // reason not actually being recognised where ZATCA expects it.
        $paymentMeans = $doc->createElement('cac:PaymentMeans');
        $this->append($doc, $paymentMeans, 'cbc:PaymentMeansCode', '1');
        $this->append($doc, $paymentMeans, 'cbc:InstructionNote', $creditNote->reason ?: 'Sales return / correction');
        $root->appendChild($paymentMeans);

        $this->appendDualTaxTotal($doc, $root, (float) $creditNote->vat_total, $creditNote->items, fn ($item) => $item->quantity * $item->unit_price);

        $monetaryTotal = $doc->createElement('cac:LegalMonetaryTotal');
        $this->appendAmount($doc, $monetaryTotal, 'cbc:LineExtensionAmount', $creditNote->subtotal);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:TaxExclusiveAmount', $creditNote->subtotal);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:TaxInclusiveAmount', $creditNote->total);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:PayableAmount', $creditNote->total);
        $root->appendChild($monetaryTotal);

        foreach ($creditNote->items as $index => $item) {
            $line = $doc->createElement('cac:InvoiceLine');
            $this->append($doc, $line, 'cbc:ID', (string) ($index + 1));
            $qty = $this->append($doc, $line, 'cbc:InvoicedQuantity', number_format((float) $item->quantity, 2, '.', ''));
            $qty->setAttribute('unitCode', $item->unit?->code ?: ($item->invoiceItem?->item?->unit_code ?: 'PCE'));
            $lineNet = $item->quantity * $item->unit_price;
            $this->appendAmount($doc, $line, 'cbc:LineExtensionAmount', $lineNet);

            $lineTax = $doc->createElement('cac:TaxTotal');
            $this->appendAmount($doc, $lineTax, 'cbc:TaxAmount', (float) $item->vat_amount);
            $this->appendAmount($doc, $lineTax, 'cbc:RoundingAmount', $lineNet + (float) $item->vat_amount);
            $line->appendChild($lineTax);

            $itemEl = $doc->createElement('cac:Item');
            $this->append($doc, $itemEl, 'cbc:Name', $item->description);
            $taxCategory = $doc->createElement('cac:ClassifiedTaxCategory');
            $this->append($doc, $taxCategory, 'cbc:ID', $this->taxCategoryCode($item));
            $this->append($doc, $taxCategory, 'cbc:Percent', number_format((float) $item->vat_rate, 2, '.', ''));
            $lineTaxScheme = $doc->createElement('cac:TaxScheme');
            $this->append($doc, $lineTaxScheme, 'cbc:ID', 'VAT');
            $taxCategory->appendChild($lineTaxScheme);
            $itemEl->appendChild($taxCategory);
            $line->appendChild($itemEl);

            $price = $doc->createElement('cac:Price');
            $this->appendAmount($doc, $price, 'cbc:PriceAmount', $item->unit_price);
            $line->appendChild($price);

            $root->appendChild($line);
        }

        return $doc->saveXML();
    }

    /**
     * Before issuing a production CSID, ZATCA requires proof the EGS can
     * generate every one of 6 document type/profile combinations — tax
     * invoice (388), credit note (381), and debit note (383), each in
     * both standard/B2B (0100000) and simplified/B2C (0200000) profiles —
     * not just the one real invoice type a company happens to have sent
     * so far. Rejects with "Missing-ComplianceSteps" naming exactly which
     * combinations are still outstanding otherwise.
     *
     * Uses the company's real seller identity (so BR-KSA-08/39 etc. still
     * hold) with a synthetic buyer/line — there is no real "sales debit
     * note" feature in Daftari (nor any requirement for one; ZATCA's own
     * reference implementations use fixed sample data for this exact
     * step too), and reusing one real invoice for all 6 would only work
     * for whichever single combination it happens to already be.
     */
    /**
     * $issuedAt must be the exact same instant the caller later passes to
     * ZatcaSyncService::buildSignedPayload() as its $issueDate — ZATCA
     * checks the QR code's own timestamp (KSA-25) against this document's
     * IssueDate/IssueTime, and a caller using separate now() calls for
     * each risks drift (canonicalization + signing both take real time)
     * that fails that check.
     */
    public function generateComplianceSample(\App\Models\Company $company, string $documentTypeCode, string $invoiceTypeName, ?string $previousInvoiceHash, string $uuid, int $icv, \DateTimeInterface $issuedAt): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $root = $doc->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $doc->appendChild($root);

        $this->append($doc, $root, 'cbc:ProfileID', 'reporting:1.0');
        $this->append($doc, $root, 'cbc:ID', 'COMPLIANCE-'.$icv);
        $this->append($doc, $root, 'cbc:UUID', $uuid);
        $this->append($doc, $root, 'cbc:IssueDate', $issuedAt->format('Y-m-d'));
        $this->append($doc, $root, 'cbc:IssueTime', $issuedAt->format('H:i:s'));

        $typeCode = $this->append($doc, $root, 'cbc:InvoiceTypeCode', $documentTypeCode);
        $typeCode->setAttribute('name', $invoiceTypeName);

        $this->append($doc, $root, 'cbc:DocumentCurrencyCode', 'SAR');
        $this->append($doc, $root, 'cbc:TaxCurrencyCode', 'SAR');

        // UBL's InvoiceType sequence places cac:BillingReference before
        // cac:AdditionalDocumentReference (ICV/PIH use the latter) —
        // omitting BillingReference entirely is fine (it's optional), but
        // once any AdditionalDocumentReference has been emitted, the
        // sequence position for BillingReference has already passed and
        // can't be emitted after it without violating element order.
        //
        // BR-KSA-56: a billing reference is mandatory for credit (381) and
        // debit (383) notes — the compliance sample doesn't correct a real
        // document, so this just needs to be present and well-formed.
        if ($documentTypeCode !== '388') {
            $billingReference = $doc->createElement('cac:BillingReference');
            $docRef = $doc->createElement('cac:InvoiceDocumentReference');
            $this->append($doc, $docRef, 'cbc:ID', 'COMPLIANCE-SAMPLE-SOURCE');
            $billingReference->appendChild($docRef);
            $root->appendChild($billingReference);
        }

        $root->appendChild($this->icvReference($doc, $icv));

        if ($previousInvoiceHash) {
            $root->appendChild($this->pihReference($doc, $previousInvoiceHash));
        }

        $root->appendChild($this->party($doc, 'cac:AccountingSupplierParty', [
            'name' => $company->name,
            'vat_number' => $company->vat_number,
            'id_scheme' => 'CRN',
            'id_value' => $company->cr_number,
            'street' => $company->street_name ?: $company->address,
            'building_number' => $company->building_number,
            'district' => $company->district,
            'city' => $company->city,
            'postal_code' => $company->postal_code,
        ]));

        $root->appendChild($this->party($doc, 'cac:AccountingCustomerParty', [
            'name' => 'ZATCA Compliance Test Buyer',
            'vat_number' => '300000000000003',
            'id_scheme' => 'CRN',
            'id_value' => '1000000000',
            'street' => 'King Fahd Road',
            'building_number' => '1111',
            'district' => 'Al Olaya',
            'city' => 'Riyadh',
            'postal_code' => '12222',
        ]));

        $delivery = $doc->createElement('cac:Delivery');
        $this->append($doc, $delivery, 'cbc:ActualDeliveryDate', $issuedAt->format('Y-m-d'));
        $root->appendChild($delivery);

        $paymentMeans = $doc->createElement('cac:PaymentMeans');
        $this->append($doc, $paymentMeans, 'cbc:PaymentMeansCode', '1');
        if ($documentTypeCode !== '388') {
            // KSA-10 (BR-KSA-17): the reason for issuing a credit/debit
            // note — nested under cac:PaymentMeans per UBL's
            // PaymentMeansType, not a top-level Invoice-document child.
            $this->append($doc, $paymentMeans, 'cbc:InstructionNote', 'ZATCA compliance sample');
        }
        $root->appendChild($paymentMeans);

        $sampleItem = (object) ['vat_rate' => 15.0, 'vat_amount' => 0.60];
        $this->appendDualTaxTotal($doc, $root, 0.60, [$sampleItem], fn () => 4.00);

        $monetaryTotal = $doc->createElement('cac:LegalMonetaryTotal');
        $this->appendAmount($doc, $monetaryTotal, 'cbc:LineExtensionAmount', 4.00);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:TaxExclusiveAmount', 4.00);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:TaxInclusiveAmount', 4.60);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:PayableAmount', 4.60);
        $root->appendChild($monetaryTotal);

        $line = $doc->createElement('cac:InvoiceLine');
        $this->append($doc, $line, 'cbc:ID', '1');
        $qty = $this->append($doc, $line, 'cbc:InvoicedQuantity', '2.00');
        $qty->setAttribute('unitCode', 'PCE');
        $this->appendAmount($doc, $line, 'cbc:LineExtensionAmount', 4.00);

        $lineTax = $doc->createElement('cac:TaxTotal');
        $this->appendAmount($doc, $lineTax, 'cbc:TaxAmount', 0.60);
        $this->appendAmount($doc, $lineTax, 'cbc:RoundingAmount', 4.60);
        $line->appendChild($lineTax);

        $itemEl = $doc->createElement('cac:Item');
        $this->append($doc, $itemEl, 'cbc:Name', 'Compliance Test Item');
        $taxCategory = $doc->createElement('cac:ClassifiedTaxCategory');
        $this->append($doc, $taxCategory, 'cbc:ID', 'S');
        $this->append($doc, $taxCategory, 'cbc:Percent', '15.00');
        $lineTaxScheme = $doc->createElement('cac:TaxScheme');
        $this->append($doc, $lineTaxScheme, 'cbc:ID', 'VAT');
        $taxCategory->appendChild($lineTaxScheme);
        $itemEl->appendChild($taxCategory);
        $line->appendChild($itemEl);

        $price = $doc->createElement('cac:Price');
        $this->appendAmount($doc, $price, 'cbc:PriceAmount', 2.00);
        $line->appendChild($price);

        $root->appendChild($line);

        return $doc->saveXML();
    }

    public function newUuid(): string
    {
        return (string) Str::uuid();
    }

    private function party(DOMDocument $doc, string $wrapperTag, array $data): \DOMElement
    {
        $wrapper = $doc->createElement($wrapperTag);
        $party = $doc->createElement('cac:Party');

        // UBL PartyType element order matters: PartyIdentification, then
        // PostalAddress, then PartyTaxScheme, then PartyLegalEntity — ZATCA's
        // validator (BR-KSA-08) rejects the seller party without a
        // PartyIdentification carrying a recognised scheme (CRN, MOM, MLS,
        // SAG, OTH, 700) and alphanumeric-only ID.
        if (! empty($data['id_value']) && ! empty($data['id_scheme'])) {
            $identification = $doc->createElement('cac:PartyIdentification');
            $id = $this->append($doc, $identification, 'cbc:ID', preg_replace('/[^A-Za-z0-9]/', '', (string) $data['id_value']));
            $id->setAttribute('schemeID', $data['id_scheme']);
            $party->appendChild($identification);
        }

        $hasAddress = ! empty($data['street']) || ! empty($data['city']) || ! empty($data['building_number']) || ! empty($data['district']) || ! empty($data['postal_code']);

        if ($hasAddress) {
            // UBL's PostalAddressType requires StreetName before
            // BuildingName/BuildingNumber — ZATCA's XSD validation rejects
            // the reverse order outright (and, since a schema violation
            // aborts deeper validation, was also the real cause behind
            // otherwise-inexplicable "missing ICV"/"missing seller VAT"
            // business-rule errors reported alongside it).
            $address = $doc->createElement('cac:PostalAddress');
            if (! empty($data['street'])) {
                $this->append($doc, $address, 'cbc:StreetName', $data['street']);
            }
            if (! empty($data['building_number'])) {
                $this->append($doc, $address, 'cbc:BuildingNumber', $data['building_number']);
            }
            if (! empty($data['district'])) {
                $this->append($doc, $address, 'cbc:CitySubdivisionName', $data['district']);
            }
            if (! empty($data['city'])) {
                $this->append($doc, $address, 'cbc:CityName', $data['city']);
            }
            if (! empty($data['postal_code'])) {
                $this->append($doc, $address, 'cbc:PostalZone', $data['postal_code']);
            }
            $country = $doc->createElement('cac:Country');
            $this->append($doc, $country, 'cbc:IdentificationCode', 'SA');
            $address->appendChild($country);
            $party->appendChild($address);
        }

        if (! empty($data['vat_number'])) {
            // UBL's PartyTaxSchemeType ends its sequence with a mandatory
            // (not optional) cac:TaxScheme — omitting it, as we previously
            // did, is itself an XSD violation, and since it sits inside
            // the same AccountingSupplierParty subtree as the seller's
            // CompanyID, this was also the real cause of the "missing
            // seller VAT number" (BR-KSA-39) error reported alongside it.
            $taxScheme = $doc->createElement('cac:PartyTaxScheme');
            $this->append($doc, $taxScheme, 'cbc:CompanyID', $data['vat_number']);
            $scheme = $doc->createElement('cac:TaxScheme');
            $this->append($doc, $scheme, 'cbc:ID', 'VAT');
            $taxScheme->appendChild($scheme);
            $party->appendChild($taxScheme);
        }

        $legalEntity = $doc->createElement('cac:PartyLegalEntity');
        $this->append($doc, $legalEntity, 'cbc:RegistrationName', $data['name'] ?: '');
        $party->appendChild($legalEntity);

        $wrapper->appendChild($party);

        return $wrapper;
    }

    /**
     * ZATCA's dual-currency EN16931 profile requires exactly two document
     * level cac:TaxTotal (BG-22) elements even though DocumentCurrencyCode
     * and TaxCurrencyCode are both always SAR here: one bare (TaxAmount
     * only — BR-KSA-EN16931-09) and one carrying the BG-23 VAT breakdown
     * as cac:TaxSubtotal, one per distinct VAT rate/category
     * (BR-KSA-EN16931-08). Confirmed against a working reference ZATCA
     * integration — both rules fire unless both TaxTotal elements exist.
     */
    private function appendDualTaxTotal(DOMDocument $doc, \DOMElement $root, float $vatTotal, iterable $items, \Closure $netAmount): void
    {
        $bare = $doc->createElement('cac:TaxTotal');
        $this->appendAmount($doc, $bare, 'cbc:TaxAmount', $vatTotal);
        $root->appendChild($bare);

        $withBreakdown = $doc->createElement('cac:TaxTotal');
        $this->appendAmount($doc, $withBreakdown, 'cbc:TaxAmount', $vatTotal);

        // Grouped by (tax category, rate) — not rate alone — since a
        // zero-rated (Z) and an exempt (E) line can share the same 0%
        // rate but are different legal categories UBL requires as
        // separate TaxSubtotal entries.
        $groups = [];
        foreach ($items as $item) {
            $rate = number_format((float) $item->vat_rate, 2, '.', '');
            $code = $this->taxCategoryCode($item);
            $key = $code.'|'.$rate;
            $groups[$key]['code'] = $code;
            $groups[$key]['rate'] = $rate;
            $groups[$key]['taxable'] = ($groups[$key]['taxable'] ?? 0) + $netAmount($item);
            $groups[$key]['tax'] = ($groups[$key]['tax'] ?? 0) + (float) $item->vat_amount;
        }

        foreach ($groups as $group) {
            $subtotal = $doc->createElement('cac:TaxSubtotal');
            $this->appendAmount($doc, $subtotal, 'cbc:TaxableAmount', (float) $group['taxable']);
            $this->appendAmount($doc, $subtotal, 'cbc:TaxAmount', (float) $group['tax']);

            $category = $doc->createElement('cac:TaxCategory');
            $this->append($doc, $category, 'cbc:ID', $group['code']);
            $this->append($doc, $category, 'cbc:Percent', $group['rate']);
            $scheme = $doc->createElement('cac:TaxScheme');
            $this->append($doc, $scheme, 'cbc:ID', 'VAT');
            $category->appendChild($scheme);
            $subtotal->appendChild($category);

            $withBreakdown->appendChild($subtotal);
        }

        $root->appendChild($withBreakdown);
    }

    /**
     * KSA's mandatory Invoice Counter Value — a company-wide, ever
     * incrementing sequence across every document (invoice or credit note)
     * ever submitted, distinct from the invoice's own business-facing
     * number and from the PIH hash chain.
     */
    private function icvReference(DOMDocument $doc, int $icv): \DOMElement
    {
        $ref = $doc->createElement('cac:AdditionalDocumentReference');
        $this->append($doc, $ref, 'cbc:ID', 'ICV');
        $this->append($doc, $ref, 'cbc:UUID', (string) $icv);

        return $ref;
    }

    private function pihReference(DOMDocument $doc, string $previousInvoiceHash): \DOMElement
    {
        $pih = $doc->createElement('cac:AdditionalDocumentReference');
        $this->append($doc, $pih, 'cbc:ID', 'PIH');
        $attachment = $doc->createElement('cac:Attachment');
        $binary = $doc->createElement('cbc:EmbeddedDocumentBinaryObject', $previousInvoiceHash);
        $binary->setAttribute('mimeCode', 'text/plain');
        $attachment->appendChild($binary);
        $pih->appendChild($attachment);

        return $pih;
    }

    /**
     * A single document-level discount, expressed as the UBL
     * cac:AllowanceCharge ZATCA expects to back up LegalMonetaryTotal's
     * AllowanceTotalAmount (BR-CO-11: their sum must match). Our invoices
     * only track one header-level discount rather than a per-line one, so
     * this uses the dominant VAT rate among the invoice's lines as the
     * category/percentage context.
     */
    private function documentAllowanceCharge(DOMDocument $doc, float $discountTotal, iterable $items): \DOMElement
    {
        $rate = 0.0;
        foreach ($items as $item) {
            $rate = (float) $item->vat_rate;
            break;
        }

        $allowance = $doc->createElement('cac:AllowanceCharge');
        $this->append($doc, $allowance, 'cbc:ChargeIndicator', 'false');
        $this->append($doc, $allowance, 'cbc:AllowanceChargeReason', 'discount');
        $this->appendAmount($doc, $allowance, 'cbc:Amount', $discountTotal);

        $category = $doc->createElement('cac:TaxCategory');
        $this->append($doc, $category, 'cbc:ID', $rate > 0 ? 'S' : 'Z');
        $this->append($doc, $category, 'cbc:Percent', number_format($rate, 2, '.', ''));
        $scheme = $doc->createElement('cac:TaxScheme');
        $this->append($doc, $scheme, 'cbc:ID', 'VAT');
        $category->appendChild($scheme);
        $allowance->appendChild($category);

        return $allowance;
    }

    /**
     * ZATCA's ClassifiedTaxCategory/ID for a sales line: S (standard-rated),
     * Z (zero-rated — still a taxable supply, e.g. exports), or E (exempt —
     * outside VAT entirely, e.g. residential rent). Both Z and E charge 0
     * SAR of VAT, so they can't be told apart from vat_rate alone — this
     * uses the line's linked TaxRate (Settings → Tax rates) when one is
     * set, and only falls back to the old "0% ⇒ zero-rated" guess for
     * lines created before tax rate management existed (tax_rate_id null).
     */
    private function taxCategoryCode(InvoiceItem|CreditNoteItem $item): string
    {
        return match ($item->taxRate?->type) {
            TaxRate::TYPE_EXEMPT => 'E',
            TaxRate::TYPE_ZERO_RATED => 'Z',
            TaxRate::TYPE_STANDARD => 'S',
            default => (float) $item->vat_rate > 0 ? 'S' : 'Z',
        };
    }

    private function append(DOMDocument $doc, \DOMElement $parent, string $tag, string $value): \DOMElement
    {
        $el = $doc->createElement($tag, htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        $parent->appendChild($el);

        return $el;
    }

    private function appendAmount(DOMDocument $doc, \DOMElement $parent, string $tag, float $amount): \DOMElement
    {
        $el = $this->append($doc, $parent, $tag, number_format($amount, 2, '.', ''));
        $el->setAttribute('currencyID', 'SAR');

        return $el;
    }
}
