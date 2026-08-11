<?php

namespace App\Support;

/**
 * Generates a full 50–950 Tailwind-style shade ramp from a single brand hex
 * color, so an admin can retheme the entire site by picking one color per
 * palette instead of eleven. Hue/saturation come from the input color; each
 * shade's lightness follows a fixed curve so the ramp always reads cleanly
 * regardless of how light or dark the picked color is.
 */
class ColorPalette
{
    private const LIGHTNESS_CURVE = [
        '50' => 96, '100' => 90, '200' => 80, '300' => 67, '400' => 53,
        '500' => 42, '600' => 34, '700' => 27, '800' => 21, '900' => 16, '950' => 9,
    ];

    /**
     * @return array<string, string> shade => hex, e.g. ['50' => '#eefaf6', ...]
     */
    public static function ramp(string $hex): array
    {
        [$h, $s] = self::hexToHsl($hex);
        $s = max(25, min(90, $s));

        $ramp = [];

        foreach (self::LIGHTNESS_CURVE as $shade => $lightness) {
            $ramp[$shade] = self::hslToHex($h, $s, $lightness);
        }

        return $ramp;
    }

    /**
     * @return array{0: float, 1: float, 2: float} [hue 0-360, saturation 0-100, lightness 0-100]
     */
    private static function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '2a9078';
        }

        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0, 0, round($l * 100)];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => ($g - $b) / $d + ($g < $b ? 6 : 0),
            $g => ($b - $r) / $d + 2,
            $b => ($r - $g) / $d + 4,
        };

        return [round($h * 60), round($s * 100), round($l * 100)];
    }

    private static function hslToHex(float $h, float $s, float $l): string
    {
        $s /= 100;
        $l /= 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        $toHex = fn ($v) => str_pad(dechex((int) round(($v + $m) * 255)), 2, '0', STR_PAD_LEFT);

        return '#'.$toHex($r).$toHex($g).$toHex($b);
    }
}
