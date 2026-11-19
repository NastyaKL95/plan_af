<?php
declare(strict_types=1);

/**
 * Однолистовый экспорт (обёртка над премиум-книгой).
 *
 * @param list<string> $headers
 * @param list<list<string>> $rows
 */
function audit_fund_xlsx_output(string $filename, string $sheetName, array $headers, array $rows): void
{
    audit_fund_xlsx_output_workbook($filename, [
        [
            'name' => $sheetName,
            'headers' => $headers,
            'rows' => $rows,
            'autofilter' => true,
        ],
    ]);
}

/**
 * Премиум XLSX: несколько листов, стили заголовков, автоширина, автофильтр на листах с данными.
 *
 * Каждый элемент $sheets:
 * - name: string (до 31 символа, недопустимые символы заменяются)
 * - headers: list<string>
 * - rows: list<list<string>>
 * - autofilter?: bool (по умолчанию true, если есть заголовки)
 *
 * @param list<array{name:string,headers:list<string>,rows:list<list<string>>,autofilter?:bool}> $sheets
 */
function audit_fund_xlsx_output_workbook(string $filename, array $sheets): void
{
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo 'ZipArchive недоступен для генерации XLSX.';
        exit;
    }

    if ($sheets === []) {
        http_response_code(500);
        echo 'Нет листов для экспорта.';
        exit;
    }

    $sheetCount = count($sheets);
    $sheetFiles = [];
    $contentTypeOverrides = '';

    foreach ($sheets as $idx => $sheet) {
        $n = $idx + 1;
        $path = 'xl/worksheets/sheet' . $n . '.xml';
        $sheetFiles[] = [
            'path' => $path,
            'xml' => audit_fund_xlsx_build_worksheet_xml(
                $sheet['headers'] ?? [],
                $sheet['rows'] ?? [],
                isset($sheet['autofilter']) ? (bool) $sheet['autofilter'] : true
            ),
        ];
        $contentTypeOverrides .= '<Override PartName="/' . $path . '" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
    }

    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . $contentTypeOverrides
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $relsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $workbookSheets = '';
    $workbookRels = '';
    for ($i = 1; $i <= $sheetCount; $i++) {
        $name = audit_fund_xlsx_sanitize_sheet_name(isset($sheets[$i - 1]['name']) ? (string) $sheets[$i - 1]['name'] : ('Лист' . $i));
        $nameEsc = audit_fund_xlsx_xml_escape($name);
        $workbookSheets .= '<sheet name="' . $nameEsc . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
        $workbookRels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
    }
    $stylesRid = $sheetCount + 1;
    $workbookRels .= '<Relationship Id="rId' . $stylesRid . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
        . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . $workbookSheets . '</sheets>'
        . '</workbook>';

    $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . $workbookRels
        . '</Relationships>';

    $stylesXml = audit_fund_xlsx_build_styles_xml();

    $tmp = tempnam(sys_get_temp_dir(), 'audit-fund-xlsx-');
    if ($tmp === false) {
        http_response_code(500);
        echo 'Не удалось создать временный файл.';
        exit;
    }
    $zipPath = $tmp . '.xlsx';
    @unlink($zipPath);

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
        @unlink($tmp);
        http_response_code(500);
        echo 'Не удалось создать XLSX архив.';
        exit;
    }

    $zip->addFromString('[Content_Types].xml', $contentTypesXml);
    $zip->addFromString('_rels/.rels', $relsXml);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    foreach ($sheetFiles as $sf) {
        $zip->addFromString($sf['path'], $sf['xml']);
    }
    $zip->close();
    @unlink($tmp);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) filesize($zipPath));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    readfile($zipPath);
    @unlink($zipPath);
    exit;
}

function audit_fund_xlsx_sanitize_sheet_name(string $name): string
{
    $name = str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $name);
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if ($name === '') {
        $name = 'Лист';
    }
    if (mb_strlen($name, 'UTF-8') > 31) {
        $name = mb_substr($name, 0, 31, 'UTF-8');
    }

    return $name;
}

/**
 * @param list<string> $headers
 * @param list<list<string>> $dataRows
 */
function audit_fund_xlsx_build_worksheet_xml(array $headers, array $dataRows, bool $autoFilter): string
{
    $colCount = count($headers);
    if ($colCount === 0) {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData/></worksheet>';
    }

    $allTextRows = [$headers];
    foreach ($dataRows as $r) {
        $allTextRows[] = array_pad(array_values($r), $colCount, '');
    }

    $widths = audit_fund_xlsx_compute_col_widths($headers, $dataRows, $colCount);
    $lastRow = count($allTextRows);
    $lastColLetter = audit_fund_xlsx_col_ref($colCount);

    $colsXml = '<cols>';
    for ($c = 1; $c <= $colCount; $c++) {
        $w = $widths[$c - 1];
        $colsXml .= '<col min="' . $c . '" max="' . $c . '" width="' . $w . '" customWidth="1"/>';
    }
    $colsXml .= '</cols>';

    $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<dimension ref="A1:' . $lastColLetter . $lastRow . '"/>'
        . $colsXml
        . '<sheetData>';

    foreach ($allTextRows as $rowIndex => $row) {
        $r = $rowIndex + 1;
        $isHeader = ($r === 1);
        $xml .= '<row r="' . $r . '">';
        for ($colIndex = 0; $colIndex < $colCount; $colIndex++) {
            $value = isset($row[$colIndex]) ? (string) $row[$colIndex] : '';
            $colRef = audit_fund_xlsx_col_ref($colIndex + 1) . $r;
            $cellValue = audit_fund_xlsx_xml_escape($value);
            $sAttr = $isHeader ? ' s="1"' : '';
            $xml .= '<c r="' . $colRef . '" t="inlineStr"' . $sAttr . '><is><t xml:space="preserve">' . $cellValue . '</t></is></c>';
        }
        $xml .= '</row>';
    }

    $xml .= '</sheetData>';

    if ($autoFilter && $lastRow >= 1) {
        $xml .= '<autoFilter ref="A1:' . $lastColLetter . $lastRow . '"/>';
    }

    $xml .= '</worksheet>';

    return $xml;
}

/**
 * @param list<string> $headers
 * @param list<list<string>> $dataRows
 * @return list<float>
 */
function audit_fund_xlsx_compute_col_widths(array $headers, array $dataRows, int $colCount): array
{
    $widths = [];
    for ($i = 0; $i < $colCount; $i++) {
        $maxLen = 8;
        if (isset($headers[$i])) {
            $maxLen = max($maxLen, mb_strlen((string) $headers[$i], 'UTF-8'));
        }
        foreach ($dataRows as $row) {
            $cell = isset($row[$i]) ? (string) $row[$i] : '';
            $len = mb_strlen($cell, 'UTF-8');
            if ($len > $maxLen) {
                $maxLen = $len;
            }
        }
        $w = $maxLen * 1.15 + 1.5;
        if ($w < 10) {
            $w = 10;
        }
        if ($w > 55) {
            $w = 55;
        }
        $widths[] = round($w, 2);
    }

    return $widths;
}

function audit_fund_xlsx_build_styles_xml(): string
{
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="3">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9E2F3"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';
}

function audit_fund_xlsx_col_ref(int $num): string
{
    $col = '';
    while ($num > 0) {
        $num--;
        $col = chr(65 + ($num % 26)) . $col;
        $num = intdiv($num, 26);
    }

    return $col;
}

function audit_fund_xlsx_xml_escape(string $value): string
{
    return str_replace(
        ['&', '<', '>', '"', "'"],
        ['&amp;', '&lt;', '&gt;', '&quot;', '&apos;'],
        $value
    );
}
