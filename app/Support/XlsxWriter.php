<?php

namespace App\Support;

/**
 * Tiny, dependency-free writer for a single-sheet .xlsx workbook.
 * Companion to SpreadsheetReader: it packs the zip itself, so it needs neither
 * PhpSpreadsheet nor PHP's zip extension.
 *
 * Numbers are written as real numbers (so Excel can sum and sort them); everything else is text.
 * Text is never treated as a formula, so cells starting with "=" or "+" are safe.
 */
class XlsxWriter
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * @param  array<int, string>  $headers  first row, bold on a dark fill, frozen, with filter buttons
     * @param  iterable<array<int, mixed>>  $rows  each row is a list of cell values (null or '' leaves the cell empty)
     * @param  array<int, float>  $widths  optional column widths, in characters
     * @return string the binary contents of the .xlsx file
     */
    public static function build(string $sheetName, array $headers, iterable $rows, array $widths = []): string
    {
        $colCount = count($headers);
        $sheetData = self::rowXml(1, $headers, 1);
        $rowNo = 1;

        foreach ($rows as $row) {
            $sheetData .= self::rowXml(++$rowNo, array_values($row), 0);
        }

        $cols = '';
        foreach (array_values($widths) as $i => $w) {
            $n = $i + 1;
            $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . $w . '" customWidth="1"/>';
        }

        $lastCol = self::columnName(max($colCount, 1));
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . ($cols !== '' ? '<cols>' . $cols . '</cols>' : '')
            . '<sheetData>' . $sheetData . '</sheetData>'
            . '<autoFilter ref="A1:' . $lastCol . $rowNo . '"/>'
            . '</worksheet>';

        $name = self::esc(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', ' ', $sheetName) ?: 'Sheet1', 0, 31));

        return self::zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets>'
                . '</workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
                . '</Relationships>',
            'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
                . '<fonts count="2">'
                . '<font><sz val="11"/><name val="Calibri"/></font>'
                . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
                . '</fonts>'
                . '<fills count="3">'
                . '<fill><patternFill patternType="none"/></fill>'
                . '<fill><patternFill patternType="gray125"/></fill>'
                . '<fill><patternFill patternType="solid"><fgColor rgb="FF1E3A5F"/><bgColor indexed="64"/></patternFill></fill>'
                . '</fills>'
                . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="2">'
                . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
                . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
                . '</cellXfs>'
                . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                . '</styleSheet>',
            'xl/worksheets/sheet1.xml' => $sheet,
        ]);
    }

    private static function rowXml(int $rowNo, array $cells, int $style): string
    {
        $xml = '';
        $s = $style ? ' s="' . $style . '"' : '';

        foreach (array_values($cells) as $i => $value) {
            if ($value === null || $value === '') {
                // Header cells keep their fill even when blank; data cells are simply skipped.
                if ($style) {
                    $xml .= '<c r="' . self::columnName($i + 1) . $rowNo . '"' . $s . '/>';
                }
                continue;
            }

            $ref = self::columnName($i + 1) . $rowNo;

            if (is_int($value) || (is_float($value) && is_finite($value))) {
                $num = is_int($value) ? (string) $value : rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
                $xml .= '<c r="' . $ref . '"' . $s . '><v>' . $num . '</v></c>';
            } else {
                $xml .= '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . self::esc((string) $value) . '</t></is></c>';
            }
        }

        return '<row r="' . $rowNo . '">' . $xml . '</row>';
    }

    /** 1 => A, 26 => Z, 27 => AA */
    private static function columnName(int $n): string
    {
        $name = '';
        while ($n > 0) {
            $n--;
            $name = chr(65 + $n % 26) . $name;
            $n = intdiv($n, 26);
        }

        return $name;
    }

    private static function esc(string $text): string
    {
        // Drop characters XML cannot hold (stray control codes, invalid UTF-8) so Excel never reports a corrupt file.
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Minimal zip container (stored, no compression), so the PHP zip extension is not required. */
    private static function zip(array $files): string
    {
        $time = time();
        $dosTime = ((int) date('H', $time) << 11) | ((int) date('i', $time) << 5) | intdiv((int) date('s', $time), 2);
        $dosDate = (max((int) date('Y', $time), 1980) - 1980) << 9 | ((int) date('n', $time) << 5) | (int) date('j', $time);

        $body = '';
        $central = '';
        $count = 0;

        foreach ($files as $name => $data) {
            $crc = crc32($data);
            $size = strlen($data);
            $offset = strlen($body);

            $body .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $size, $size, strlen($name), 0) . $name . $data;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
            $count++;
        }

        return $body . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($body), 0);
    }
}
