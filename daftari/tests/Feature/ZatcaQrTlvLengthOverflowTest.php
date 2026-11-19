<?php

namespace Tests\Feature;

use App\Services\ZatcaQrGenerator;
use Tests\TestCase;

/**
 * ZatcaQrGenerator::buildTlvPayload() (the Phase 1 / simplified QR
 * builder) wrote each tag's length as `chr(strlen($value))`. chr()
 * silently wraps modulo 256 for a value over 255 (chr(256) === chr(0),
 * a null byte) instead of erroring — companies.name is varchar(255)
 * *characters*, not bytes, and Arabic/UTF-8 multi-byte text routinely
 * exceeds 255 bytes well under 255 characters, so a long company name
 * (entirely realistic for a Saudi legal entity name) would silently
 * write a corrupted length byte, breaking the TLV parse for that tag
 * and every tag after it — with nothing in the app ever surfacing an
 * error, since the QR still "renders", just with wrong/garbage content
 * once decoded by a real scanner. generatePhase2() already guarded the
 * same risk for its own tags; this pins the matching fix for Phase 1's
 * one genuinely variable-length tag (the seller name).
 */
class ZatcaQrTlvLengthOverflowTest extends TestCase
{
    private function parseTlv(string $base64Payload): array
    {
        $binary = base64_decode($base64Payload);
        $tags = [];
        $offset = 0;

        while ($offset < strlen($binary)) {
            $tag = ord($binary[$offset]);
            $length = ord($binary[$offset + 1]);
            $tags[$tag] = substr($binary, $offset + 2, $length);
            $offset += 2 + $length;
        }

        return $tags;
    }

    public function test_a_seller_name_over_255_bytes_is_truncated_not_silently_corrupted(): void
    {
        // 300 Arabic characters, 2 bytes each in UTF-8 — 600 bytes, well
        // past the single-byte TLV length limit, on a company name length
        // (300 characters) the database schema (varchar(255)) wouldn't
        // even permit — real long Arabic legal-entity names are shorter
        // than this but still commonly cross 255 bytes well under 255
        // characters, which this test's point doesn't depend on exactly.
        $longArabicName = str_repeat('ش', 300);

        $payload = ZatcaQrGenerator::buildTlvPayload($longArabicName, '300000000000003', new \DateTimeImmutable('2026-01-01 10:00:00'), 115.0, 15.0);
        $tags = $this->parseTlv($payload);

        $this->assertArrayHasKey(1, $tags, 'tag 1 must still parse as a real value, not be skipped or corrupted');
        $this->assertLessThanOrEqual(255, strlen($tags[1]));
        $this->assertTrue(mb_check_encoding($tags[1], 'UTF-8'), 'the truncated value must still be valid UTF-8, not cut mid-character');

        // Every later tag must still be present and intact — the entire
        // point of the fix is that one long value no longer corrupts
        // everything that follows it in the byte stream.
        $this->assertSame('300000000000003', $tags[2]);
        $this->assertSame('2026-01-01T10:00:00', $tags[3]);
        $this->assertSame('115.00', $tags[4]);
        $this->assertSame('15.00', $tags[5]);
    }

    public function test_a_normal_length_seller_name_is_unaffected(): void
    {
        $payload = ZatcaQrGenerator::buildTlvPayload('Dynamic Core Contracting', '300000000000003', new \DateTimeImmutable('2026-01-01 10:00:00'), 115.0, 15.0);
        $tags = $this->parseTlv($payload);

        $this->assertSame('Dynamic Core Contracting', $tags[1]);
    }
}
