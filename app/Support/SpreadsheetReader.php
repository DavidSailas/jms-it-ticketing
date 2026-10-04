<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;
use ZipArchive;

/**
 * Tiny, dependency-free reader for the first sheet of an .xlsx workbook (or a CSV file).
 * Needs only PHP's built-in zip + dom extensions.
 */
class SpreadsheetReader
{
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const MAX_XML_BYTES = 20 * 1024 * 1024;
    private const MAX_ROWS = 2000;

    /**
     * @return array<int, array<int, string>> non-empty rows, keyed by their row number in the file (the header is the first one)
     *
     * @throws RuntimeException with a message that is safe to show to the user
     */
    public static function read(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'xlsx'        => self::readXlsx($path),
            'csv', 'txt'  => self::readCsv($path),
            'xls'         => throw new RuntimeException('Old .xls files are not supported. Open it in Excel and use File > Save As > Excel Workbook (.xlsx).'),
            default       => throw new RuntimeException('Upload an Excel (.xlsx) or CSV file.'),
        };
    }

    private static function readCsv(string $path): array
    {
        $handle = @fopen($path, 'r');
        if (! $handle) {
            throw new RuntimeException('The file could not be read.');
        }

        $first = (string) fgets($handle);
        rewind($handle);
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $rows = [];
        $line = 0;
        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;
            $cells = array_map(fn ($c) => (string) $c, $cells);

            if (self::isBlank($cells)) continue;
            $rows[$line] = $cells;
            if (count($rows) > self::MAX_ROWS) break;
        }
        fclose($handle);

        return $rows;
    }

    private static function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('This server cannot read .xlsx files yet (the PHP zip extension is off). Save the sheet as CSV and upload that instead.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('That does not look like a valid .xlsx file. If it is an old .xls file, re-save it as .xlsx first.');
        }

        try {
            $sheet = self::loadXml($zip, self::firstSheetPath($zip));
            if (! $sheet) {
                throw new RuntimeException('The workbook has no readable sheet.');
            }

            $shared = self::sharedStrings($zip);
            $rows = [];
            $auto = 0;

            foreach ($sheet->getElementsByTagNameNS('*', 'row') as $rowEl) {
                $auto++;
                $number = (int) $rowEl->getAttribute('r') ?: $auto;
                $cells = [];
                $next = 0;

                foreach ($rowEl->childNodes as $c) {
                    if (! $c instanceof DOMElement || $c->localName !== 'c') continue;

                    $ref = $c->getAttribute('r');
                    $col = $ref !== '' ? self::columnIndex($ref) : $next;
                    $next = $col + 1;

                    $cells[$col] = self::cellValue($c, $shared);
                }

                if (! $cells) continue;

                $dense = array_fill(0, max(array_keys($cells)) + 1, '');
                foreach ($cells as $i => $v) $dense[$i] = $v;

                if (self::isBlank($dense)) continue;
                $rows[$number] = $dense;
                if (count($rows) > self::MAX_ROWS) break;
            }

            ksort($rows);

            return $rows;
        } finally {
            $zip->close();
        }
    }

    private static function cellValue(DOMElement $c, array $shared): string
    {
        $type = $c->getAttribute('t');

        if ($type === 'inlineStr') {
            return trim(self::textOf($c));
        }

        $raw = null;
        foreach ($c->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'v') {
                $raw = $child->textContent;
                break;
            }
        }
        if ($raw === null) return '';

        return trim(match ($type) {
            's'     => $shared[(int) $raw] ?? '',
            'b'     => $raw === '1' ? 'TRUE' : 'FALSE',
            default => $raw,           // 'str' (formula text), 'n' and numbers
        });
    }

    /** Path inside the zip of the first worksheet. */
    private static function firstSheetPath(ZipArchive $zip): string
    {
        $fallback = 'xl/worksheets/sheet1.xml';

        $workbook = self::loadXml($zip, 'xl/workbook.xml');
        $rels     = self::loadXml($zip, 'xl/_rels/workbook.xml.rels');
        if (! $workbook || ! $rels) return $fallback;

        $sheet = $workbook->getElementsByTagNameNS('*', 'sheet')->item(0);
        $rid   = $sheet instanceof DOMElement ? $sheet->getAttributeNS(self::REL_NS, 'id') : '';
        if ($rid === '') return $fallback;

        foreach ($rels->getElementsByTagNameNS('*', 'Relationship') as $rel) {
            if ($rel->getAttribute('Id') === $rid) {
                $target = $rel->getAttribute('Target');

                return str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . $target;
            }
        }

        return $fallback;
    }

    /** @return array<int, string> */
    private static function sharedStrings(ZipArchive $zip): array
    {
        $dom = self::loadXml($zip, 'xl/sharedStrings.xml');
        $out = [];
        if (! $dom) return $out;

        foreach ($dom->getElementsByTagNameNS('*', 'si') as $si) {
            $out[] = self::textOf($si);
        }

        return $out;
    }

    /** All visible text under a node (rich-text runs joined, phonetic hints ignored). */
    private static function textOf(DOMNode $node): string
    {
        $text = '';
        foreach ($node->getElementsByTagNameNS('*', 't') as $t) {
            if ($t->parentNode && $t->parentNode->localName === 'rPh') continue;
            $text .= $t->textContent;
        }

        return $text;
    }

    private static function loadXml(ZipArchive $zip, string $name): ?DOMDocument
    {
        $stat = $zip->statName($name);
        if ($stat === false) return null;
        if ($stat['size'] > self::MAX_XML_BYTES) {
            throw new RuntimeException('The spreadsheet is too large to import.');
        }

        $xml = $zip->getFromName($name);
        if ($xml === false || $xml === '') return null;

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $ok) {
            throw new RuntimeException('The spreadsheet file is damaged or not a real .xlsx workbook.');
        }

        return $dom;
    }

    /** "B7" -> 1, "AA3" -> 26 (zero-based column). */
    private static function columnIndex(string $ref): int
    {
        preg_match('/^[A-Za-z]+/', $ref, $m);
        $letters = strtoupper($m[0] ?? 'A');

        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    private static function isBlank(array $cells): bool
    {
        foreach ($cells as $c) {
            if (trim((string) $c) !== '') return false;
        }

        return true;
    }
}
