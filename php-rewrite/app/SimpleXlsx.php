<?php

declare(strict_types=1);

/**
 * Генерация .xlsx (Office Open XML) без внешних зависимостей.
 * Поддержка: объединение ячеек, стили (границы, жирный, курсив, выравнивание).
 *
 * Ячейка: string|int|float|null или array{v: mixed, s?: int} для стиля.
 *
 * @phpstan-type Row list<string|int|float|null|array{v: mixed, s?: int}>
 */
final class SimpleXlsx
{
    /** @var list<array{name: string, rows: list<Row>, merges: list<string>}> */
    private array $sheets = [];

    /**
     * @param list<Row> $rows
     * @param list<string> $merges ссылки вида A1:D1
     */
    public function addSheet(string $name, array $rows, array $merges = []): void
    {
        $safe = $this->sanitizeSheetName($name);
        $this->sheets[] = ['name' => $safe, 'rows' => $rows, 'merges' => $merges];
    }

    /** Сохранить книгу в локальный файл .xlsx (абсолютный путь). */
    public function writeToFile(string $absolutePath): void
    {
        if ($this->sheets === []) {
            $this->addSheet('Sheet1', []);
        }
        $zip = new ZipArchive();
        if ($zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Не удалось открыть ZipArchive для записи');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('docProps/core.xml', $this->coreXml());
        $zip->addFromString('docProps/app.xml', $this->appXml());

        $workbookRels = [];
        $sheetXmls = [];
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $rid = 'rId' . ($n + 1);
            $workbookRels[] = '<Relationship Id="' . $rid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
            $sheetXmls['xl/worksheets/sheet' . $n . '.xml'] = $this->worksheetXml($sheet['rows'], $sheet['merges']);
        }
        $sheetEntries = '';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $name = htmlspecialchars(self::xmlSafe((string) $sheet['name']), ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $sheetEntries .= '<sheet name="' . $name . '" sheetId="' . $n . '" r:id="rId' . ($n + 1) . '"/>';
        }
        $zip->addFromString(
            'xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetEntries . '</sheets></workbook>'
        );

        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        foreach ($sheetXmls as $path => $xml) {
            $zip->addFromString($path, $xml);
        }

        $zip->addFromString(
            'xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . implode('', $workbookRels)
            . '</Relationships>'
        );

        $zip->close();
    }

    public function sendDownload(string $filename): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        if ($tmp === false) {
            throw new RuntimeException('Не удалось создать временный файл');
        }
        try {
            $this->writeToFile($tmp);
        } catch (Throwable $e) {
            @unlink($tmp);
            throw $e;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        readfile($tmp);
        @unlink($tmp);
    }

    private function sanitizeSheetName(string $name): string
    {
        $name = str_replace(['\\', '/', '*', '?', ':', '[', ']'], '_', $name);
        $name = trim($name) ?: 'Sheet';
        return mb_substr($name, 0, 31);
    }

    private function colLetters(int $n): string
    {
        $s = '';
        while ($n >= 0) {
            $s = chr(ord('A') + ($n % 26)) . $s;
            $n = intdiv($n, 26) - 1;
        }

        return $s;
    }

    /**
     * @param list<Row> $rows
     * @param list<string> $merges
     */
    private function worksheetXml(array $rows, array $merges): string
    {
        $out = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';
        foreach ($rows as $ri => $row) {
            $rnum = $ri + 1;
            $out .= '<row r="' . $rnum . '">';
            foreach ($row as $ci => $cell) {
                $col = $this->colLetters($ci);
                $ref = $col . $rnum;
                $out .= $this->cellXml($cell, $ref);
            }
            $out .= '</row>';
        }
        $out .= '</sheetData>';
        if ($merges !== []) {
            $out .= '<mergeCells count="' . count($merges) . '">';
            foreach ($merges as $refRange) {
                $refRange = htmlspecialchars((string) $refRange, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $out .= '<mergeCell ref="' . $refRange . '"/>';
            }
            $out .= '</mergeCells>';
        }
        $out .= '</worksheet>';

        return $out;
    }

    /** @param string|int|float|null|array{v: mixed, s?: int} $cell */
    private function cellXml(string|int|float|null|array $cell, string $ref): string
    {
        $styleAttr = '';
        $val = $cell;
        if (is_array($cell) && array_key_exists('v', $cell)) {
            $val = $cell['v'];
            if (isset($cell['s']) && is_int($cell['s'])) {
                $styleAttr = ' s="' . $cell['s'] . '"';
            }
        }

        if ($val === null || $val === '') {
            return '<c r="' . $ref . '"' . $styleAttr . '/>';
        }
        if (is_int($val) || is_float($val)) {
            return '<c r="' . $ref . '"' . $styleAttr . '><v>' . $val . '</v></c>';
        }
        $t = htmlspecialchars(self::xmlSafe((string) $val), ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<c r="' . $ref . '"' . $styleAttr . ' t="inlineStr"><is><t xml:space="preserve">' . $t . '</t></is></c>';
    }

    /** Управляющие символы (кроме таба и переводов строк) недопустимы в XML и ломают файл Excel. */
    private static function xmlSafe(string $s): string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
    }

    private function contentTypes(): string
    {
        $overrides = '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($this->sheets as $i => $_) {
            $n = $i + 1;
            $overrides .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . $overrides
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function coreXml(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" '
            . 'xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" '
            . 'xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator> PHP</dc:creator><cp:lastModifiedBy>IRO PHP</cp:lastModifiedBy>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
            . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified></cp:coreProperties>';
    }

    private function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
            . '<Application>IRO PHP</Application></Properties>';
    }

    /** Стили: 0 ячейка с рамкой; 1 жирный; 2 курсив; 3 заголовок листа; 4 подзаголовок; 5 шапка таблицы. */
    private function stylesXml(): string
    {
        $thinBorder =
            '<border>'
            . '<left style="thin"><color rgb="FF888888"/></left>'
            . '<right style="thin"><color rgb="FF888888"/></right>'
            . '<top style="thin"><color rgb="FF888888"/></top>'
            . '<bottom style="thin"><color rgb="FF888888"/></bottom>'
            . '</border>';

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4">'
            . '<font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><i/><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            . '<font><b/><sz val="14"/><color theme="1"/><name val="Calibri"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . $thinBorder
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyBorder="1" applyFont="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyBorder="1" applyFont="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="1" xfId="0" applyBorder="1" applyFont="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyBorder="1" applyFont="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyBorder="1" applyFont="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '</cellXfs>'
            . '</styleSheet>';
    }
}
