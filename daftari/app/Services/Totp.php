<?php

namespace App\Services;

/**
 * Minimal RFC 6238 (TOTP) / RFC 4226 (HOTP) implementation — built on
 * PHP's own hash_hmac() rather than a package, matching how this app's
 * ZATCA cryptography and QR payloads were built without external crypto
 * libraries. Compatible with Google Authenticator, Microsoft Authenticator,
 * Authy, and any other standard TOTP app (SHA1, 6 digits, 30s step —
 * the universal defaults every authenticator app assumes).
 */
class Totp
{
    private const PERIOD = 30;

    private const DIGITS = 6;

    public static function generateSecret(int $length = 20): string
    {
        return self::base32Encode(random_bytes($length));
    }

    public static function provisioningUri(string $secret, string $label, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer.':'.$label),
            $secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD
        );
    }

    /**
     * Verifies a submitted code, tolerating one time-step of clock drift
     * in either direction — a real device's clock is rarely perfectly in
     * sync, and without this a valid code entered right at a 30s boundary
     * would wrongly fail.
     */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp = (int) floor(time() / self::PERIOD);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals(self::codeAt($secret, $timestamp + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    private static function codeAt(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        $binaryCounter = pack('N*', 0, $counter); // 8-byte big-endian counter

        $hash = hash_hmac('sha1', $binaryCounter, $key, true);

        $offset = ord($hash[19]) & 0x0F;
        $truncated = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF)
        );

        return str_pad((string) ($truncated % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $binary): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($binary) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $output .= $alphabet[bindec($chunk)];
        }

        return $output;
    }

    private static function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper((string) preg_replace('/[^A-Z2-7]/i', '', $secret));

        $bits = '';
        foreach (str_split($secret) as $char) {
            $position = strpos($alphabet, $char);
            if ($position === false) {
                continue;
            }
            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
