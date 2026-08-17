<?php

namespace App\Services\Zatca;

use App\Models\CreditNote;
use App\Models\Invoice;
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
            $qty->setAttribute('unitCode', $item->item?->unit_code ?: 'PCE');
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
            $this->append($doc, $taxCategory, 'cbc:ID', (float) $item->vat_rate > 0 ? 'S' : 'Z');
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

        $this->append($doc, $root, 'cbc:InstructionNote', $creditNote->reason ?: 'Sales return / correction');

        $root->appendChild($this->icvReference($doc, $icv));

        if ($previousInvoiceHash) {
            $root->appendChild($this->pihReference($doc, $previousInvoiceHash));
        }

        $originalLog = $invoice->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->latest('id')->first();

        $billingReference = $doc->createElement('cac:BillingReference');
        $docRef = $doc->createElement('cac:InvoiceDocumentReference');
        $this->append($doc, $docRef, 'cbc:ID', $invoice->invoice_number);
        if ($originalLog?->request_uuid) {
            $this->append($doc, $docRef, 'cbc:UUID', (string) $originalLog->request_uuid);
        }
        $billingReference->appendChild($docRef);
        $root->appendChild($billingReference);

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

        $paymentMeans = $doc->createElement('cac:PaymentMeans');
        $this->append($doc, $paymentMeans, 'cbc:PaymentMeansCode', '1');
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
            $qty->setAttribute('unitCode', $item->invoiceItem?->item?->unit_code ?: 'PCE');
            $lineNet = $item->quantity * $item->unit_price;
            $this->appendAmount($doc, $line, 'cbc:LineExtensionAmount', $lineNet);

            $lineTax = $doc->createElement('cac:TaxTotal');
            $this->appendAmount($doc, $lineTax, 'cbc:TaxAmount', (float) $item->vat_amount);
            $this->appendAmount($doc, $lineTax, 'cbc:RoundingAmount', $lineNet + (float) $item->vat_amount);
            $line->appendChild($lineTax);

            $itemEl = $doc->createElement('cac:Item');
            $this->append($doc, $itemEl, 'cbc:Name', $item->description);
            $taxCategory = $doc->createElement('cac:ClassifiedTaxCategory');
            $this->append($doc, $taxCategory, 'cbc:ID', (float) $item->vat_rate > 0 ? 'S' : 'Z');
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
            $taxScheme = $doc->createElement('cac:PartyTaxScheme');
            $this->append($doc, $taxScheme, 'cbc:CompanyID', $data['vat_number']);
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

        $groups = [];
        foreach ($items as $item) {
            $rate = number_format((float) $item->vat_rate, 2, '.', '');
            $groups[$rate]['taxable'] = ($groups[$rate]['taxable'] ?? 0) + $netAmount($item);
            $groups[$rate]['tax'] = ($groups[$rate]['tax'] ?? 0) + (float) $item->vat_amount;
        }

        foreach ($groups as $rate => $group) {
            $subtotal = $doc->createElement('cac:TaxSubtotal');
            $this->appendAmount($doc, $subtotal, 'cbc:TaxableAmount', (float) $group['taxable']);
            $this->appendAmount($doc, $subtotal, 'cbc:TaxAmount', (float) $group['tax']);

            $category = $doc->createElement('cac:TaxCategory');
            $this->append($doc, $category, 'cbc:ID', (float) $rate > 0 ? 'S' : 'Z');
            $this->append($doc, $category, 'cbc:Percent', $rate);
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
