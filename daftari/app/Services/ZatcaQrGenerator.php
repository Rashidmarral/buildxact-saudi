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
            3 => $issuedAt->format(DATE_ATOM),
            4 => number_format($invoiceTotal, 2, '.', ''),
            5 => number_format($vatTotal, 2, '.', ''),
        ];

        $binary = '';
        foreach ($tags as $tag => $value) {
            $binary .= chr($tag).chr(strlen($value)).$value;
        }

        return base64_encode($binary);
    }
}
