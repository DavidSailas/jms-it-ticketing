<?php

namespace App\Support;

/**
 * Tidies an uploaded company logo so it looks professional wherever it is shown:
 * - a plain light background (white or light grey) is made transparent,
 * - empty margins around the artwork are trimmed,
 * - very large images are scaled down,
 * and the result is saved as a transparent PNG.
 *
 * It only touches backgrounds it is sure about (all four corners the same light colour);
 * a logo on a dark or coloured background keeps it and is just trimmed. Returns null when
 * the image cannot be processed, and the caller then keeps the original upload.
 */
class LogoProcessor
{
    private const MAX_SIDE = 800;
    private const CORNER_TOLERANCE = 18;
    private const FILL_TOLERANCE = 34;
    private const LIGHT_LUMINANCE = 200; // 0-255

    public static function process(string $path): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $raw = @file_get_contents($path);
        $src = $raw === false ? false : @imagecreatefromstring($raw);
        if (! $src) {
            return null;
        }

        $img = self::scaledCopy($src);
        imagedestroy($src);

        $w = imagesx($img);
        $h = imagesy($img);

        [$mode, $bg] = self::detectBackground($img, $w, $h);

        if ($mode === 'light') {
            self::clearEdgeConnected($img, $w, $h, $bg);
            $mode = 'transparent';
        }

        $box = self::contentBox($img, $w, $h, $mode, $bg);
        if (! $box) {
            imagedestroy($img);

            return null;
        }

        $out = self::crop($img, $box, $w, $h);
        imagedestroy($img);

        ob_start();
        imagepng($out, null, 6);
        $png = ob_get_clean();
        imagedestroy($out);

        return $png ?: null;
    }

    private static function scaledCopy($src)
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $scale = min(1, self::MAX_SIDE / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $img = imagecreatetruecolor($nw, $nh);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagecopyresampled($img, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $img;
    }

    /** @return array{0:int,1:int,2:int,3:int} r, g, b, alpha(0 opaque - 127 clear) */
    private static function px($img, int $x, int $y): array
    {
        $c = imagecolorat($img, $x, $y);

        return [($c >> 16) & 255, ($c >> 8) & 255, $c & 255, ($c >> 24) & 127];
    }

    private static function near(array $a, array $b, int $tol): bool
    {
        return abs($a[0] - $b[0]) <= $tol && abs($a[1] - $b[1]) <= $tol && abs($a[2] - $b[2]) <= $tol;
    }

    /** @return array{0:string,1:array} mode: transparent | light | solid | none */
    private static function detectBackground($img, int $w, int $h): array
    {
        $corners = [self::px($img, 0, 0), self::px($img, $w - 1, 0), self::px($img, 0, $h - 1), self::px($img, $w - 1, $h - 1)];

        $clear = count(array_filter($corners, fn ($c) => $c[3] >= 100));
        if ($clear >= 3) {
            return ['transparent', [0, 0, 0, 127]];
        }

        if ($clear > 0) {
            return ['none', [0, 0, 0, 0]];
        }

        $first = $corners[0];
        foreach ($corners as $c) {
            if (! self::near($c, $first, self::CORNER_TOLERANCE)) {
                return ['none', $first];
            }
        }

        $light = ($first[0] * 0.299 + $first[1] * 0.587 + $first[2] * 0.114) >= self::LIGHT_LUMINANCE;

        return [$light ? 'light' : 'solid', $first];
    }

    /** Make the plain background transparent, starting from the image edges so white inside the artwork stays. */
    private static function clearEdgeConnected($img, int $w, int $h, array $bg): void
    {
        $clear = imagecolorallocatealpha($img, 0, 0, 0, 127);
        $seen = str_repeat("\0", $w * $h);
        $stack = [];

        $push = function (int $x, int $y) use (&$stack, &$seen, $w) {
            $i = $y * $w + $x;
            if ($seen[$i] === "\0") {
                $seen[$i] = "\1";
                $stack[] = [$x, $y];
            }
        };

        for ($x = 0; $x < $w; $x++) {
            $push($x, 0);
            $push($x, $h - 1);
        }
        for ($y = 0; $y < $h; $y++) {
            $push(0, $y);
            $push($w - 1, $y);
        }

        while ($stack) {
            [$x, $y] = array_pop($stack);
            $p = self::px($img, $x, $y);

            if ($p[3] < 100 && ! self::near($p, $bg, self::FILL_TOLERANCE)) {
                continue;
            }

            imagesetpixel($img, $x, $y, $clear);

            if ($x > 0)      { $push($x - 1, $y); }
            if ($x < $w - 1) { $push($x + 1, $y); }
            if ($y > 0)      { $push($x, $y - 1); }
            if ($y < $h - 1) { $push($x, $y + 1); }
        }
    }

    /** @return array{0:int,1:int,2:int,3:int}|null left, top, right, bottom of the artwork */
    private static function contentBox($img, int $w, int $h, string $mode, array $bg): ?array
    {
        $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $p = self::px($img, $x, $y);

                $isBackground = $p[3] >= 100 || ($mode === 'solid' && self::near($p, $bg, self::FILL_TOLERANCE));
                if ($isBackground) {
                    continue;
                }

                if ($x < $minX) { $minX = $x; }
                if ($x > $maxX) { $maxX = $x; }
                if ($y < $minY) { $minY = $y; }
                if ($y > $maxY) { $maxY = $y; }
            }
        }

        return $maxX < 0 ? null : [$minX, $minY, $maxX, $maxY];
    }

    private static function crop($img, array $box, int $w, int $h)
    {
        [$minX, $minY, $maxX, $maxY] = $box;

        $pad = max(2, (int) round(max($maxX - $minX, $maxY - $minY) * 0.03));
        $left = max(0, $minX - $pad);
        $top = max(0, $minY - $pad);
        $right = min($w - 1, $maxX + $pad);
        $bottom = min($h - 1, $maxY + $pad);

        $cw = $right - $left + 1;
        $ch = $bottom - $top + 1;

        $out = imagecreatetruecolor($cw, $ch);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopy($out, $img, 0, 0, $left, $top, $cw, $ch);

        return $out;
    }
}
