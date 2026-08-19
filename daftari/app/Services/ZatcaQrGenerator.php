<?php

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;

/**
 * Builds the base64 TLV (tag-length-value) payload used by Saudi ZATCA
 * Phase-1 "simplified" e-invoice QR codes: seller name, VAT number,
 * timestamp, invoice total, and VAT total — each tag is [id][length][value]
 * — then renders it as a scannable QR PNG (returned as base64 image data).
 */
class ZatcaQrGenerator
{
    public static function generate(
        string $sellerName,
        string $vatNumber,
        \DateTimeInterface $issuedAt,
        float $invoiceTotal,
        float $vatTotal
    ): string {
        $payload = self::buildTlvPayload($sellerName, $vatNumber, $issuedAt, $invoiceTotal, $vatTotal);

        $result = Builder::create()
            ->data($payload)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Low)
            ->size(240)
            ->margin(4)
            ->build();

        return base64_encode($result->getString());
    }

    public static function buildTlvPayload(
        string $sellerName,
        string $vatNumber,
        \DateTimeInterface $issuedAt,
        float $invoiceTotal,
        float $vatTotal
    ): string {
        $tags = [
            1 => $sellerName,
            2 => $vatNumber,
            3 => $issuedAt->format('Y-m-d\TH:i:s'),
            4 => number_format($invoiceTotal, 2, '.', ''),
            5 => number_format($vatTotal, 2, '.', ''),
        ];

        $binary = '';
        foreach ($tags as $tag => $value) {
            $binary .= chr($tag).chr(strlen($value)).$value;
        }

        return base64_encode($binary);
    }

    /**
     * The full 9-tag Phase 2 QR: the 5 basic tags plus the invoice hash,
     * an ECDSA signature over that hash, the signing public key, and
     * ZATCA's own cryptographic stamp for this document. Tags 6-8 are
     * computed for real from the company's own ZATCA key material; tag 9
     * is passed through exactly as returned by ZATCA's clearance/reporting
     * response — never fabricated. Returns null if any component is
     * missing or a field would overflow the single-byte TLV length ZATCA's
     * format uses, so callers must keep the Phase 1 (5-tag) QR until a
     * genuine upgrade is possible.
     */
    public static function generatePhase2(
        string $sellerName,
        string $vatNumber,
        \DateTimeInterface $issuedAt,
        float $invoiceTotal,
        float $vatTotal,
        string $invoiceHashBase64,
        string $signatureBase64,
        string $publicKeyBase64,
        string $certificateSignatureRaw
    ): ?string {
        $payload = self::buildTlvPayloadPhase2($sellerName, $vatNumber, $issuedAt, $invoiceTotal, $vatTotal, $invoiceHashBase64, $signatureBase64, $publicKeyBase64, $certificateSignatureRaw);

        if ($payload === null) {
            return null;
        }

        // The 9-tag Phase 2 payload is dense — roughly 350-400 raw bytes,
        // ~470-540 base64 characters once the hash/signature/public-key/
        // cert-signature tags are added — which pushes the QR to a high
        // version (many small modules) even before considering error
        // correction. At 'Low' (7% recovery) and a small render size, a
        // phone camera scanning it off a screen (rather than print — full
        // of the moire/glare/motion-blur that comes with photographing a
        // display) has real trouble with a run of that many small modules,
        // producing exactly the kind of localized decode corruption this
        // was reported with. 'Quartile' (25% recovery) plus a larger
        // render trades a bit of file size for a QR that actually survives
        // a real-world camera scan.
        $result = Builder::create()
            ->data($payload)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Quartile)
            ->size(400)
            ->margin(6)
            ->build();

        return base64_encode($result->getString());
    }

    public static function buildTlvPayloadPhase2(
        string $sellerName,
        string $vatNumber,
        \DateTimeInterface $issuedAt,
        float $invoiceTotal,
        float $vatTotal,
        string $invoiceHashBase64,
        string $signatureBase64,
        string $publicKeyBase64,
        string $certificateSignatureRaw
    ): ?string {
        // User-verified against a real ZATCA-issued certificate with the
        // QrKsaReader app (confirmed "compatible with Phase 2" twice on
        // freshly-synced invoices): tags 6, 7, and 8 all stay as their
        // base64 *text* — none of the three are decoded to raw bytes
        // before being written into the TLV. Tag 9 was already raw to
        // begin with (see ZatcaCertificateService::certificateSignature()),
        // so it's unaffected either way.
        $tags = [
            1 => $sellerName,
            2 => $vatNumber,
            3 => $issuedAt->format('Y-m-d\TH:i:s\Z'),
            4 => number_format($invoiceTotal, 2, '.', ''),
            5 => number_format($vatTotal, 2, '.', ''),
            6 => $invoiceHashBase64,
            7 => $signatureBase64,
            8 => $publicKeyBase64,
            9 => $certificateSignatureRaw,
        ];

        $binary = '';
        foreach ($tags as $tag => $value) {
            // mb_strlen(..., '8bit') is functionally identical to strlen()
            // here (PHP strings are already byte arrays — strlen() always
            // counted bytes, never characters) but kept explicit per the
            // user's verified version.
            $length = mb_strlen($value, '8bit');

            if ($length > 255) {
                return null;
            }

            $binary .= chr($tag).chr($length).$value;
        }

        return base64_encode($binary);
    }
}
