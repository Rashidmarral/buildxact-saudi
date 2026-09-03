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
     * The "invoice hash" ZATCA actually validates against once this XML is
     * signed — used for the PIH chain, QR tag 6, the API's invoiceHash
     * field, and as the XAdES ds:Reference digest passed into sign().
     *
     * This is *not* simply a hash of $unsignedXml. Our signature's
     * CanonicalizationMethod is inclusive C14N (c14n11, not exclusive
     * c14n), so once ext:/sig:/ds:/xades: namespaces are declared on the
     * document root for signing, they stay "in scope" for C14N purposes
     * on every descendant even after the elements that use them
     * (UBLExtensions/Signature/the QR reference) are excluded by the
     * ds:Reference's XPath transforms — that's how ZATCA recomputes this
     * hash from the final signed document it receives. So the hash must
     * be computed the same way: after those namespaces are declared on
     * root, before the signature elements themselves are added. Confirmed
     * empirically: hashing $unsignedXml as-is (no namespace pre-
     * declaration) produces a different digest than ZATCA's own
     * recomputation, which is what was causing "invalid-invoice-hash"
     * rejections.
     */
    public function contentHash(string $unsignedXml): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($unsignedXml);
        $this->declareSignatureNamespaces($doc->documentElement);

        return base64_encode(hash('sha256', $doc->C14N(), true));
    }

    /**
     * @param  string  $unsignedXml  Output of ZatcaXmlGenerator::generate()/generateForCreditNote().
     * @param  string  $invoiceHashBase64  ZatcaXadesSigner::contentHash() of that same $unsignedXml.
     * @param  string  $certificateBase64  The company's CSID/production certificate (binarySecurityToken).
     * @param  string  $qrBase64  The Phase 2 9-tag QR payload (ZatcaQrGenerator::generatePhase2()) — built
     *                            from the *same* $digitalSignature passed here, not recomputed independently.
     * @param  string  $digitalSignature  ZatcaCryptoService::signInvoiceHash() of $invoiceHashBase64 — the
     *                                    caller's own, already-computed value, reused verbatim as this
     *                                    block's ds:SignatureValue. ECDSA signing is non-deterministic (a
     *                                    fresh random nonce every call), so calling signInvoiceHash() again
     *                                    in here instead of reusing the caller's result would embed a
     *                                    signature that's individually valid but disagrees with the one
     *                                    already baked into $qrBase64's tag 7 — exactly the
     *                                    "signature value does not match with qr" rejection ZATCA returns.
     * @return string The final, signed, QR-embedded invoice XML — base64-encode this to submit to ZATCA.
     */
    public function sign(Company $company, string $unsignedXml, string $invoiceHashBase64, string $certificateBase64, string $qrBase64, string $digitalSignature): string
    {
        $certificateValue = base64_encode($this->certificates->rawCertificate($certificateBase64));
        $certificateHash = $this->certificates->certificateHash($certificateBase64);
        $issuerName = $this->certificates->issuerName($certificateBase64);
        $serialNumber = $this->certificates->serialNumber($certificateBase64);
        $signingTimestamp = gmdate('Y-m-d\TH:i:s\Z');

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->preserveWhiteSpace = false;
        $doc->loadXML($unsignedXml);

        $root = $doc->documentElement;
        $this->declareSignatureNamespaces($root);

        // ds:Object/QualifyingProperties/SignedProperties has to be built
        // and actually attached into the real document tree — inheriting
        // the ext:/sig:/ds:/xades: namespace declarations just placed on
        // $root — *before* computing its digest below. Hashing a
        // separately-built standalone copy produces a different digest
        // than what canonicalizing the real embedded node yields, because
        // the standalone copy has no ancestor to inherit those namespaces
        // from and so serializes differently. Must use the same
        // (non-exclusive) C14N here as declared in
        // ds:CanonicalizationMethod below (xml-c14n11) — exclusive C14N
        // would drop those inherited-but-unused namespace declarations
        // from the digest input, producing a hash ZATCA's own
        // recomputation (against the declared algorithm) won't match.
        [$ublExtensions, $signature, $signedProperties, $object] = $this->buildUblExtensionsSkeleton(
            $doc, $certificateValue, $signingTimestamp, $certificateHash, $issuerName, $serialNumber,
        );
        $root->insertBefore($ublExtensions, $root->firstChild);

        // Hash the *literal* serialized bytes of this node — not a true
        // C14N canonicalization of it. Two encoding-only fixes here (the
        // hex-vs-raw digest convention, then the SignedProperties id
        // string) each independently checked out against the reference
        // implementation but neither changed ZATCA's rejection at all, so
        // the remaining suspect is the content actually being hashed, not
        // its encoding. The reference implementation never canonicalizes
        // anything for this digest despite declaring the same
        // xml-c14n11 CanonicalizationMethod we do: it builds the XML with
        // \XMLWriter (pretty-printed, insertion-order attributes) and
        // hashes \XMLReader::readInnerXml()'s literal text — i.e. exactly
        // the bytes about to be transmitted, not a canonical form of them.
        // DOMDocument::saveXML($node) is the DOM equivalent of that: the
        // literal serialization of this node as it will actually appear
        // in the final document (reconstructing only the namespace
        // declarations this fragment actually needs, the same way
        // XMLReader's extraction does), rather than DOMElement::C14N()'s
        // canonical form (which also pulls in every namespace merely
        // *in scope* — ext:, sig: — regardless of whether SignedProperties
        // uses them). ZATCA's validator apparently does the equivalent of
        // a literal re-hash of the transmitted bytes rather than a true
        // C14N-invariant recomputation, despite the declared algorithm.
        //
        // withRedundantXadesNamespace() additionally splices a literal,
        // redundant 'xmlns:xades' declaration onto this element's own
        // opening tag — matching the reference implementation's literal
        // XML exactly — which the digest below must be computed from,
        // since it has to hash the *exact* bytes that end up transmitted
        // (see that method's docblock for why this can't be done through
        // the normal DOM API).
        $signedPropertiesLiteral = $this->withRedundantXadesNamespace(trim($doc->saveXML($signedProperties)));
        $signedPropertiesHash = base64_encode(hash('sha256', $signedPropertiesLiteral, false));

        $this->attachSignatureCore($doc, $signature, $object, $invoiceHashBase64, $signedPropertiesHash, $digitalSignature, $certificateValue);

        $insertionPoint = $this->lastAdditionalDocumentReference($root) ?? $root->firstChild;

        $qrReference = $this->buildQrReference($doc, $qrBase64);
        $this->insertAfter($qrReference, $insertionPoint);

        $signatureElement = $this->buildSignatureElement($doc);
        $this->insertAfter($signatureElement, $qrReference);

        // Must apply the identical string surgery to the actually-
        // transmitted document, or the digest computed above would
        // describe bytes that were never sent — see
        // withRedundantXadesNamespace()'s docblock.
        return $this->withRedundantXadesNamespace($doc->saveXML());
    }

    /**
     * Splices a literal 'xmlns:xades' declaration onto xades:SignedProperties'
     * own opening tag — redundant with the declaration it already inherits
     * from its parent xades:QualifyingProperties, but physically present in
     * the text regardless. Matches a commercially available, independently-
     * verified-working ZATCA Phase 2 reference implementation (Ultimate
     * POS's ZATCA module): its literal XML structure (UBLExtensions.php)
     * declares 'xmlns:xades' as one of SignedProperties' own attributes,
     * not merely inherited scope.
     *
     * This can't be done through DOMElement::setAttributeNS(): PHP's DOM
     * silently drops a namespace declaration set that way whenever an
     * ancestor already declares the same prefix+URI (confirmed directly —
     * hasAttributeNS() reports it as set immediately afterward, but
     * saveXML() never emits it, and the attribute never shows up in the
     * element's own attributes list), since libxml2 treats it as
     * redundant and elides it during serialization no matter how it was
     * set. There is no DOM-level way to force a genuinely redundant
     * declaration into the output, so this does it with direct string
     * substitution on the already-serialized text instead. Used on both
     * the digest input (the isolated SignedProperties fragment) and the
     * final transmitted document, so the two stay consistent — the digest
     * must describe the exact bytes ZATCA receives.
     */
    private function withRedundantXadesNamespace(string $xml): string
    {
        $target = '<xades:SignedProperties Id="xadesSignedProperties">';
        $replacement = '<xades:SignedProperties xmlns:xades="'.self::NS_XADES.'" Id="xadesSignedProperties">';

        $count = 0;
        $result = str_replace($target, $replacement, $xml, $count);

        if ($count !== 1) {
            throw new RuntimeException(
                'Expected exactly one xades:SignedProperties opening tag to splice the redundant xmlns:xades declaration into, found '.$count.'.'
            );
        }

        return $result;
    }

    /**
     * Builds ext:UBLExtensions down through an (as yet incomplete)
     * ds:Signature carrying only its ds:Object/QualifyingProperties/
     * SignedProperties child — SignedInfo/SignatureValue/KeyInfo are
     * added afterward, once the caller has attached this into the real
     * tree and hashed the now-in-context SignedProperties node.
     *
     * @return array{0: DOMElement, 1: DOMElement, 2: DOMElement, 3: DOMElement} [ublExtensions, signature, signedProperties, object]
     */
    private function buildUblExtensionsSkeleton(
        DOMDocument $doc,
        string $certificateValue,
        string $signingTimestamp,
        string $certificateHash,
        string $issuerName,
        string $serialNumber,
    ): array {
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

        $object = $doc->createElementNS(self::NS_DS, 'ds:Object');
        $signature->appendChild($object);

        [$qualifyingProperties, $signedProperties] = $this->buildQualifyingProperties($doc, $signingTimestamp, $certificateHash, $issuerName, $serialNumber);
        $object->appendChild($qualifyingProperties);

        return [$ublExtensions, $signature, $signedProperties, $object];
    }

    /**
     * Builds ds:SignedInfo/SignatureValue/KeyInfo and inserts them as the
     * first children of $signature, ahead of the already-attached
     * ds:Object — restoring ds:Signature's required child order
     * (SignedInfo, SignatureValue, KeyInfo, Object) despite Object having
     * been built and attached first (it had to be, to hash it in place).
     */
    private function attachSignatureCore(DOMDocument $doc, DOMElement $signature, DOMElement $object, string $invoiceHash, string $signedPropertiesHash, string $digitalSignature, string $certificateValue): void
    {
        $signedInfo = $doc->createElementNS(self::NS_DS, 'ds:SignedInfo');

        $canonicalizationMethod = $doc->createElementNS(self::NS_DS, 'ds:CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/2006/12/xml-c14n11');
        $signedInfo->appendChild($canonicalizationMethod);

        $signatureMethod = $doc->createElementNS(self::NS_DS, 'ds:SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256');
        $signedInfo->appendChild($signatureMethod);

        $signedInfo->appendChild($this->buildInvoiceContentReference($doc, $invoiceHash));
        $signedInfo->appendChild($this->buildSignedPropertiesReference($doc, $signedPropertiesHash));

        $signatureValue = $doc->createElementNS(self::NS_DS, 'ds:SignatureValue', $digitalSignature);

        $keyInfo = $doc->createElementNS(self::NS_DS, 'ds:KeyInfo');
        $x509Data = $doc->createElementNS(self::NS_DS, 'ds:X509Data');
        $keyInfo->appendChild($x509Data);
        $x509Certificate = $doc->createElementNS(self::NS_DS, 'ds:X509Certificate', $certificateValue);
        $x509Data->appendChild($x509Certificate);

        $signature->insertBefore($signedInfo, $object);
        $signature->insertBefore($signatureValue, $object);
        $signature->insertBefore($keyInfo, $object);
    }

    private function buildInvoiceContentReference(DOMDocument $doc, string $invoiceHash): DOMElement
    {
        $reference = $doc->createElementNS(self::NS_DS, 'ds:Reference');
        // No hyphen — matches the exact id spelling a commercially
        // available, independently-verified-working ZATCA Phase 2
        // reference implementation (Ultimate POS's ZATCA module) uses.
        // Not dereferenced by any URI in this document (this reference's
        // own URI="" is a whole-document self-reference), so this is
        // low-risk, but ZATCA's validator has proven to do literal string
        // matching on these ids elsewhere (see buildSignedPropertiesReference
        // below), so it's safest to match the reference exactly here too.
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
        // Plain XMLDSig "SignatureProperties" Type URI, not the
        // XAdES-specific "http://uri.etsi.org/01903#SignedProperties" a
        // prior version of this method used (reasoning that XAdES/ETSI TS
        // 101 903 formally calls for it) — cross-checking against a
        // commercially available, independently-verified-working ZATCA
        // Phase 2 reference implementation (Ultimate POS's ZATCA module)
        // shows it uses the generic XMLDSig URI instead, so that's what's
        // used here now.
        $reference->setAttribute('Type', 'http://www.w3.org/2000/09/xmldsig#SignatureProperties');
        // No hyphen in the id fragment — ZATCA's own compliance-check
        // rejection ("Invalid signed properties hashing, SignedProperties
        // with id='xadesSignedProperties'") names this *exact* id, with no
        // hyphen, confirming ZATCA's validator does a literal string match
        // against this id/URI pair rather than resolving it as a generic
        // XML ID reference — a prior version of this method used
        // 'xades-SignedProperties' (with a hyphen), which the validator
        // silently failed to resolve, surfacing as an "invalid hashing"
        // error even though the digest itself may have been correct.
        $reference->setAttribute('URI', '#xadesSignedProperties');

        $digestMethod = $doc->createElementNS(self::NS_DS, 'ds:DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2001/04/xmlenc#sha256');
        $reference->appendChild($digestMethod);

        $digestValue = $doc->createElementNS(self::NS_DS, 'ds:DigestValue', $signedPropertiesHash);
        $reference->appendChild($digestValue);

        return $reference;
    }

    /**
     * @return array{0: DOMElement, 1: DOMElement} [qualifyingProperties, signedProperties]
     */
    private function buildQualifyingProperties(DOMDocument $doc, string $signingTimestamp, string $certificateHash, string $issuerName, string $serialNumber): array
    {
        $qualifyingProperties = $doc->createElementNS(self::NS_XADES, 'xades:QualifyingProperties');
        $qualifyingProperties->setAttribute('Target', 'signature');

        $signedProperties = $doc->createElementNS(self::NS_XADES, 'xades:SignedProperties');
        // NOTE: this element deliberately does NOT call setAttributeNS()
        // to redeclare xmlns:xades locally, even though the reference
        // implementation's literal XML does — PHP's DOM silently drops a
        // namespace declaration set via setAttributeNS() whenever an
        // ancestor (here, $qualifyingProperties, once attached) already
        // declares the same prefix+URI, no matter how it's set; there is
        // no DOM-level API to force a genuinely redundant declaration
        // through to saveXML() output. See withRedundantXadesNamespace()
        // in sign(), which achieves the same literal text via direct
        // string surgery on the serialized output instead.
        // Must match buildSignedPropertiesReference()'s URI exactly
        // (no hyphen) — see the comment there.
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

        return [$qualifyingProperties, $signedProperties];
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
