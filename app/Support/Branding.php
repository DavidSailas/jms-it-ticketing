<?php

namespace App\Support;

/**
 * Turns the one colour a company picks into the full brand palette used across the app
 * (the --brand-* CSS variables behind Tailwind's brand-50 ... brand-900 classes).
 */
class Branding
{
    /** The standard JMS blue (the middle "700" shade of the default palette). */
    public const DEFAULT = '#164a99';

    /** White text on the main button colour must stay readable. */
    public const MIN_CONTRAST = 3.5;

    public static function isHex(?string $hex): bool
    {
        return is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1;
    }

    /** @return array{0:int,1:int,2:int} */
    public static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private static function mix(array $a, array $b, float $amountOfB): string
    {
        return implode(' ', array_map(fn ($x, $y) => (int) round($x + ($y - $x) * $amountOfB), $a, $b));
    }

    /** @return array<int,string> shade => "r g b" */
    public static function palette(string $hex): array
    {
        $base = self::rgb($hex);
        $white = [255, 255, 255];
        $black = [0, 0, 0];

        return [
            50  => self::mix($base, $white, 0.94),
            100 => self::mix($base, $white, 0.86),
            200 => self::mix($base, $white, 0.74),
            400 => self::mix($base, $white, 0.45),
            500 => self::mix($base, $white, 0.25),
            600 => self::mix($base, $white, 0.12),
            700 => self::mix($base, $white, 0),
            800 => self::mix($base, $black, 0.20),
            900 => self::mix($base, $black, 0.40),
        ];
    }

    /** The <style> rules that re-colour the app, or null when the default blue is used. */
    public static function css(?string $hex): ?string
    {
        if (! self::isHex($hex) || strtolower($hex) === self::DEFAULT) {
            return null;
        }

        $vars = '';
        foreach (self::palette($hex) as $shade => $rgb) {
            $vars .= "--brand-{$shade}:{$rgb};";
        }

        return ":root{{$vars}}";
    }

    /** WCAG contrast ratio of white text on this colour (1 = none, 21 = black). */
    public static function contrastWithWhite(string $hex): float
    {
        $lin = fn ($c) => ($c /= 255) <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        [$r, $g, $b] = self::rgb($hex);
        $luminance = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);

        return 1.05 / ($luminance + 0.05);
    }
}
