<?php

namespace App\Services\Zatca;

use App\Models\Company;
use DOMDocument;
use DOMElement;
use RuntimeException;

/**
 * Takes the plain (unsigned) invoice XML App\Services\Zatca\ZatcaXmlGenerator
 * produces — the same XML whose hash feeds the PIH chain and QR tag 6 — and
 * embeds ZATCA's required XAdES digital signature block (ext:UBLExtensions),
 * the cac:Signature marker, and the QR code, producing the final XML that
 * actually gets base64-encoded and submitted to ZATCA's compliance/
 * clearance/reporting endpoints.
 *
 * ZATCA's Phase 2 signing profile is a simplified one: rather than a full
 * enveloped-XMLDSig signature computed over a canonicalized <SignedInfo>
 * block, both the QR code's tag 7 and this block's ds:SignatureValue reuse
 * the same ECDSA signature of the plain invoice hash
 * (ZatcaCryptoService::signInvoiceHash) — confirmed against a working
 * reference ZATCA integration's implementation.
 */
class ZatcaXadesSigner
{
    private const NS_EXT = 'urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2';

    private const NS_SIG = 'urn:oasis:names:specification:ubl:schema:xsd:CommonSignatureComponents-2';

    private const NS_SAC = 'urn:oasis:names:specification:ubl:schema:xsd:SignatureAggregateComponents-2';

    private const NS_SBC = 'urn:oasis:names:specification:ubl:schema:xsd:SignatureBasicComponents-2';

    private const NS_DS = 'http://www.w3.org/2000/09/xmldsig#';

    private const NS_XADES = 'http://uri.etsi.org/01903/v1.3.2#';

    public function __construct(
        private readonly ZatcaCryptoService $crypto,
        private readonly ZatcaCertificateService $certificates,
    ) {}

    /**
     * @param  string  $unsignedXml  Output of ZatcaXmlGenerator::generate()/generateForCreditNote().
     * @param  string  $invoiceHashBase64  ZatcaCryptoService::hashInvoiceXml() of that same $unsignedXml.
     * @param  string  $certificateBase64  The company's CSID/production certificate (binarySecurityToken).
     * @param  string  $qrBase64  The Phase 2 9-tag QR payload (ZatcaQrGenerator::generatePhase2()).
     * @return string The final, signed, QR-embedded invoice XML — base64-encode this to submit to ZATCA.
     */
    public function sign(Company $company, string $unsignedXml, string $invoiceHashBase64, string $certificateBase64, string $qrBase64): string
    {
        $digitalSignature = $this->crypto->signInvoiceHash($company, $invoiceHashBase64);

        if (! $digitalSignature) {
            throw new RuntimeException('Cannot sign the invoice: company has no ZATCA private key yet.');
        }

        $certificateValue = base64_encode($this->certificates->rawCertificate($certificateBase64));
        $certificateHash = $this->certificates->certificateHash($certificateBase64);
        $issuerName = $this->certificates->issuerName($certificateBase64);
        $serialNumber = $this->certificates->serialNumber($certificateBase64);
        $signingTimestamp = gmdate('Y-m-d\TH:i:s\Z');

        $signedPropertiesXml = $this->buildSignedPropertiesXml($signingTimestamp, $certificateHash, $issuerName, $serialNumber);
        $signedPropertiesHash = base64_encode(hash('sha256', $signedPropertiesXml, false));

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->loadXML($unsignedXml);

        $root = $doc->documentElement;
        $this->declareSignatureNamespaces($root);

        $ublExtensions = $this->buildUblExtensions(
            $doc,
            $invoiceHashBase64,
            $signedPropertiesHash,
            $digitalSignature,
            $certificateValue,
            $signingTimestamp,
            $certificateHash,
            $issuerName,
            $serialNumber,
        );
        $root->insertBefore($ublExtensions, $root->firstChild);

        $insertionPoint = $this->lastAdditionalDocumentReference($root) ?? $root->firstChild;

        $qrReference = $this->buildQrReference($doc, $qrBase64);
        $this->insertAfter($qrReference, $insertionPoint);

        $signatureElement = $this->buildSignatureElement($doc);
        $this->insertAfter($signatureElement, $qrReference);

        return $doc->saveXML();
    }

    private function buildSignedPropertiesXml(string $signingTimestamp, string $certificateHash, string $issuerName, string $serialNumber): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');

        $signedProperties = $doc->createElementNS(self::NS_XADES, 'xades:SignedProperties');
        $signedProperties->setAttribute('Id', 'xadesSignedProperties');
        $doc->appendChild($signedProperties);

        $signedSignatureProperties = $doc->createElementNS(self::NS_XADES, 'xades:SignedSignatureProperties');
        $signedProperties->appendChild($signedSignatureProperties);

        $signingTime = $doc->createElementNS(self::NS_XADES, 'xades:SigningTime', $signingTimestamp);
        $signedSignatureProperties->appendChild($signingTime);

        $signingCertificate = $doc->createElementNS(self::NS_XADES, 'xades:SigningCertificate');
        $signedSignatureProperties->appendChild($signingCertificate);

        $cert = $doc->createElementNS(self::NS_XADES, 'xades:Cert');
        $signingCertificate->appendChild($cert);

        $certDigest = $doc->createElementNS(self::NS_XADES, 'xades:CertDigest');
        $cert->appendChild($certDigest);

        $digestMethod = $doc->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $certDigest->appendChild($digestMethod);

        $digestValue = $doc->createElementNS(self::NS_DS, 'ds:DigestValue', $certificateHash);
        $certDigest->appendChild($digestValue);

        $issuerSerial = $doc->createElementNS(self::NS_XADES, 'xades:IssuerSerial');
        $cert->appendChild($issuerSerial);

        $x509IssuerName = $doc->createElementNS(self::NS_DS, 'ds:X509IssuerName', htmlspecialchars($issuerName, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        $issuerSerial->appendChild($x509IssuerName);

        $x509SerialNumber = $doc->createElementNS(self::NS_DS, 'ds:X509SerialNumber', $serialNumber);
        $issuerSerial->appendChild($x509SerialNumber);

        return $doc->saveXML($signedProperties);
    }

    private function buildUblExtensions(
        DOMDocument $doc,
        string $invoiceHash,
        string $signedPropertiesHash,
        string $digitalSignature,
        string $certificateValue,
        string $signingTimestamp,
        string $certificateHash,
        string $issuerName,
        string $serialNumber,
    ): DOMElement {
        $ublExtensions = $doc->createElementNS(self::NS_EXT, 'ext:UBLExtensions');
        $ublExtension = $doc->createElementNS(self::NS_EXT, 'ext:UBLExtension');
        $ublExtensions->appendChild($ublExtension);

        $extensionUri = $doc->createElementNS(self::NS_EXT, 'ext:ExtensionURI', 'urn:oasis:names:specification:ubl:dsig:enveloped:xades');
        $ublExtension->appendChild($extensionUri);

        $extensionContent = $doc->createElementNS(self::NS_EXT, 'ext:ExtensionContent');
        $ublExtension->appendChild($extensionContent);

        $documentSignatures = $doc->createElementNS(self::NS_SIG, 'sig:UBLDocumentSignatures');
        $documentSignatures->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sac', self::NS_SAC);
        $documentSignatures->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sbc', self::NS_SBC);
        $extensionContent->appendChild($documentSignatures);

        $signatureInformation = $doc->createElementNS(self::NS_SAC, 'sac:SignatureInformation');
        $documentSignatures->appendChild($signatureInformation);

        $id = $doc->createElement('cbc:ID', 'urn:oasis:names:specification:ubl:signature:1');
        $signatureInformation->appendChild($id);

        $referencedSignatureId = $doc->createElementNS(self::NS_SBC, 'sbc:ReferencedSignatureID', 'urn:oasis:names:specification:ubl:signature:Invoice');
        $signatureInformation->appendChild($referencedSignatureId);

        $signature = $doc->createElementNS(self::NS_DS, 'ds:Signature');
        $signature->setAttribute('Id', 'signature');
        $signatureInformation->appendChild($signature);

        $signedInfo = $doc->createElementNS(self::NS_DS, 'ds:SignedInfo');
        $signature->appendChild($signedInfo);

        $canonicalizationMethod = $doc->createElementNS(self::NS_DS, 'ds:CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/2006/12/xml-c14n11');
        $signedInfo->appendChild($canonicalizationMethod);

        $signatureMethod = $doc->createElementNS(self::NS_DS, 'ds:SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256');
        $signedInfo->appendChild($signatureMethod);

        $signedInfo->appendChild($this->buildInvoiceContentReference($doc, $invoiceHash));
        $signedInfo->appendChild($this->buildSignedPropertiesReference($doc, $signedPropertiesHash));

        $signatureValue = $doc->createElementNS(self::NS_DS, 'ds:SignatureValue', $digitalSignature);
        $signature->appendChild($signatureValue);

        $keyInfo = $doc->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $signature->appendChild($keyInfo);

        $x509Data = $doc->createElementNS(self::NS_DS, 'ds:X509Data');
        $keyInfo->appendChild($x509Data);

        $x509Certificate = $doc->createElementNS(self::NS_DS, 'ds:X509Certificate', $certificateValue);
        $x509Data->appendChild($x509Certificate);

        $object = $doc->createElementNS(self::NS_DS, 'ds:Object');
        $signature->appendChild($object);

        $object->appendChild($this->buildQualifyingProperties($doc, $signingTimestamp, $certificateHash, $issuerName, $serialNumber));

        return $ublExtensions;
    }

    private function buildInvoiceContentReference(DOMDocument $doc, string $invoiceHash): DOMElement
    {
        $reference = $doc->createElementNS(self::NS_DS, 'ds:Reference');
        $reference->setAttribute('Id', 'invoiceSignedData');
        $reference->setAttribute('URI', '');

        $transforms = $doc->createElementNS(self::NS_DS, 'ds:Transforms');
        $reference->appendChild($transforms);

        foreach ([
            'not(//ancestor-or-self::ext:UBLExtensions)',
            'not(//ancestor-or-self::cac:Signature)',
            "not(//ancestor-or-self::cac:AdditionalDocumentReference[cbc:ID='QR'])",
        ] as $xpath) {
            $transform = $doc->createElementNS(self::NS_DS, 'ds:Transform');
            $transform->setAttribute('Algorithm', 'http://www.w3.org/TR/1999/REC-xpath-19991116');
            $transforms->appendChild($transform);

            $xPathEl = $doc->createElementNS(self::NS_DS, 'ds:XPath', $xpath);
            $transform->appendChild($xPathEl);
        }

        $c14nTransform = $doc->createElementNS(self::NS_DS, 'ds:Transform');
        $c14nTransform->setAttribute('Algorithm', 'http://www.w3.org/2006/12/xml-c14n11');
        $transforms->appendChild($c14nTransform);

        $digestMethod = $doc->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $reference->appendChild($digestMethod);

        $digestValue = $doc->createElementNS(self::NS_DS, 'ds:DigestValue', $invoiceHash);
        $reference->appendChild($digestValue);

        return $reference;
    }

    private function buildSignedPropertiesReference(DOMDocument $doc, string $signedPropertiesHash): DOMElement
    {
        $reference = $doc->createElementNS(self::NS_DS, 'ds:Reference');
        $reference->setAttribute('Type', 'http://www.w3.org/2000/09/xmldsig#SignatureProperties');
        $reference->setAttribute('URI', '#xadesSignedProperties');

        $digestMethod = $doc->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $reference->appendChild($digestMethod);

        $digestValue = $doc->createElementNS(self::NS_DS, 'ds:DigestValue', $signedPropertiesHash);
        $reference->appendChild($digestValue);

        return $reference;
    }

    private function buildQualifyingProperties(DOMDocument $doc, string $signingTimestamp, string $certificateHash, string $issuerName, string $serialNumber): DOMElement
    {
        $qualifyingProperties = $doc->createElementNS(self::NS_XADES, 'xades:QualifyingProperties');
        $qualifyingProperties->setAttribute('Target', 'signature');

        $signedProperties = $doc->createElementNS(self::NS_XADES, 'xades:SignedProperties');
        $signedProperties->setAttribute('Id', 'xadesSignedProperties');
        $qualifyingProperties->appendChild($signedProperties);

        $signedSignatureProperties = $doc->createElementNS(self::NS_XADES, 'xades:SignedSignatureProperties');
        $signedProperties->appendChild($signedSignatureProperties);

        $signingTime = $doc->createElementNS(self::NS_XADES, 'xades:SigningTime', $signingTimestamp);
        $signedSignatureProperties->appendChild($signingTime);

        $signingCertificate = $doc->createElementNS(self::NS_XADES, 'xades:SigningCertificate');
        $signedSignatureProperties->appendChild($signingCertificate);

        $cert = $doc->createElementNS(self::NS_XADES, 'xades:Cert');
        $signingCertificate->appendChild($cert);

        $certDigest = $doc->createElementNS(self::NS_XADES, 'xades:CertDigest');
        $cert->appendChild($certDigest);

        $digestMethod = $doc->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $certDigest->appendChild($digestMethod);

        $digestValue = $doc->createElementNS(self::NS_DS, 'ds:DigestValue', $certificateHash);
        $certDigest->appendChild($digestValue);

        $issuerSerial = $doc->createElementNS(self::NS_XADES, 'xades:IssuerSerial');
        $cert->appendChild($issuerSerial);

        $x509IssuerName = $doc->createElementNS(self::NS_DS, 'ds:X509IssuerName', htmlspecialchars($issuerName, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        $issuerSerial->appendChild($x509IssuerName);

        $x509SerialNumber = $doc->createElementNS(self::NS_DS, 'ds:X509SerialNumber', $serialNumber);
        $issuerSerial->appendChild($x509SerialNumber);

        return $qualifyingProperties;
    }

    private function buildQrReference(DOMDocument $doc, string $qrBase64): DOMElement
    {
        $reference = $doc->createElement('cac:AdditionalDocumentReference');
        $reference->appendChild($doc->createElement('cbc:ID', 'QR'));

        $attachment = $doc->createElement('cac:Attachment');
        $reference->appendChild($attachment);

        $binary = $doc->createElement('cbc:EmbeddedDocumentBinaryObject', $qrBase64);
        $binary->setAttribute('mimeCode', 'text/plain');
        $attachment->appendChild($binary);

        return $reference;
    }

    private function buildSignatureElement(DOMDocument $doc): DOMElement
    {
        $signature = $doc->createElement('cac:Signature');
        $signature->appendChild($doc->createElement('cbc:ID', 'urn:oasis:names:specification:ubl:signature:Invoice'));
        $signature->appendChild($doc->createElement('cbc:SignatureMethod', 'urn:oasis:names:specification:ubl:dsig:enveloped:xades'));

        return $signature;
    }

    private function lastAdditionalDocumentReference(DOMElement $root): ?DOMElement
    {
        $last = null;

        foreach ($root->childNodes as $node) {
            if ($node instanceof DOMElement && $node->localName === 'AdditionalDocumentReference') {
                $last = $node;
            }
        }

        return $last;
    }

    private function insertAfter(DOMElement $new, ?DOMElement $reference): void
    {
        if ($reference === null || $reference->parentNode === null) {
            throw new RuntimeException('Cannot locate an insertion point in the invoice XML.');
        }

        if ($reference->nextSibling !== null) {
            $reference->parentNode->insertBefore($new, $reference->nextSibling);
        } else {
            $reference->parentNode->appendChild($new);
        }
    }

    private function declareSignatureNamespaces(DOMElement $root): void
    {
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ext', self::NS_EXT);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:sig', self::NS_SIG);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:ds', self::NS_DS);
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xades', self::NS_XADES);
    }
}
