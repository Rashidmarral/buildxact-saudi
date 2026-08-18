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

        $result = Builder::create()
            ->data($payload)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Low)
            ->size(240)
            ->margin(4)
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
        $tags = [
            1 => $sellerName,
            2 => $vatNumber,
            3 => $issuedAt->format('Y-m-d\TH:i:s'),
            4 => number_format($invoiceTotal, 2, '.', ''),
            5 => number_format($vatTotal, 2, '.', ''),
            6 => base64_decode($invoiceHashBase64),
            7 => base64_decode($signatureBase64),
            8 => base64_decode($publicKeyBase64),
            9 => $certificateSignatureRaw,
        ];

        $binary = '';
        foreach ($tags as $tag => $value) {
            if (strlen($value) > 255) {
                return null;
            }
            $binary .= chr($tag).chr(strlen($value)).$value;
        }

        return base64_encode($binary);
    }
}
