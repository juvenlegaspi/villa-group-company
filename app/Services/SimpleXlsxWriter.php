<?php

namespace App\Services;

use DateTimeInterface;

class SimpleXlsxWriter
{
    /**
     * Build a small, dependency-free XLSX workbook with one styled worksheet.
     *
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, float|int>  $widths
     */
    public function make(string $title, array $metadata, array $headers, array $rows, array $widths = []): string
    {
        $columnCount = count($headers);
        $lastColumn = $this->columnName($columnCount);
        $headerRow = count($metadata) + 3;
        $lastRow = $headerRow + count($rows);

        $sheetRows = [];
        $sheetRows[] = '<row r="1" ht="30" customHeight="1">'.$this->stringCell('A1', $title, 1).'</row>';
        foreach (array_values($metadata) as $index => $item) {
            $rowNumber = $index + 2;
            $sheetRows[] = '<row r="'.$rowNumber.'">'
                .$this->stringCell('A'.$rowNumber, (string) ($item[0] ?? ''), 2)
                .$this->stringCell('B'.$rowNumber, (string) ($item[1] ?? ''), 3)
                .'</row>';
        }

        $headerCells = '';
        foreach ($headers as $index => $header) {
            $headerCells .= $this->stringCell($this->columnName($index + 1).$headerRow, $header, 4);
        }
        $sheetRows[] = '<row r="'.$headerRow.'" ht="34" customHeight="1">'.$headerCells.'</row>';

        foreach ($rows as $index => $row) {
            $rowNumber = $headerRow + $index + 1;
            $style = $index % 2 === 0 ? 5 : 6;
            $cells = '';
            foreach (array_values($row) as $columnIndex => $value) {
                $cells .= $this->cell($this->columnName($columnIndex + 1).$rowNumber, $value, $style);
            }
            $sheetRows[] = '<row r="'.$rowNumber.'">'.$cells.'</row>';
        }

        $columnXml = '';
        foreach ($widths as $index => $width) {
            $column = $index + 1;
            $columnXml .= '<col min="'.$column.'" max="'.$column.'" width="'.(float) $width.'" customWidth="1"/>';
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<dimension ref="A1:'.$lastColumn.max(1, $lastRow).'"/>'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$headerRow.'" topLeftCell="A'.($headerRow + 1).'" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A'.($headerRow + 1).'" sqref="A'.($headerRow + 1).'"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols>'.$columnXml.'</cols><sheetData>'.implode('', $sheetRows).'</sheetData>'
            .'<autoFilter ref="A'.$headerRow.':'.$lastColumn.max($headerRow, $lastRow).'"/>'
            .'<mergeCells count="1"><mergeCell ref="A1:'.$lastColumn.'1"/></mergeCells>'
            .'<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
            .'</worksheet>';

        $files = [
            '[Content_Types].xml' => $this->contentTypes(),
            '_rels/.rels' => $this->rootRelationships(),
            'xl/workbook.xml' => $this->workbook(),
            'xl/_rels/workbook.xml.rels' => $this->workbookRelationships(),
            'xl/styles.xml' => $this->styles(),
            'xl/worksheets/sheet1.xml' => $sheetXml,
        ];

        return $this->zip($files);
    }

    private function cell(string $reference, mixed $value, int $style): string
    {
        if ($value === null || $value === '') {
            return '<c r="'.$reference.'" s="'.$style.'"/>';
        }
        if (is_int($value) || is_float($value)) {
            return '<c r="'.$reference.'" s="'.$style.'" t="n"><v>'.$value.'</v></c>';
        }
        if ($value instanceof DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:s');
        }

        return $this->stringCell($reference, (string) $value, $style);
    }

    private function stringCell(string $reference, string $value, int $style): string
    {
        return '<c r="'.$reference.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'
            .htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    /** @param array<string, string> $files */
    private function zip(array $files): string
    {
        $body = '';
        $directory = '';
        $offset = 0;
        [$dosTime, $dosDate] = $this->dosDateTime();

        foreach ($files as $name => $contents) {
            $name = str_replace('\\', '/', $name);
            $crc = crc32($contents);
            $size = strlen($contents);
            $nameLength = strlen($name);
            $local = pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0)
                .$name.$contents;
            $body .= $local;

            $directory .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset)
                .$name;
            $offset += strlen($local);
        }

        $count = count($files);
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($directory), strlen($body), 0);

        return $body.$directory.$end;
    }

    /** @return array{int, int} */
    private function dosDateTime(): array
    {
        $year = max(1980, (int) date('Y'));
        $time = ((int) date('H') << 11) | ((int) date('i') << 5) | intdiv((int) date('s'), 2);
        $date = (($year - 1980) << 9) | ((int) date('m') << 5) | (int) date('d');

        return [$time, $date];
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Technical Defects" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="3"><font><sz val="10"/><name val="Aptos"/></font><font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Aptos Display"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Aptos"/></font></fonts>'
            .'<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF173B69"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD5DEE9"/></left><right style="thin"><color rgb="FFD5DEE9"/></right><top style="thin"><color rgb="FFD5DEE9"/></top><bottom style="thin"><color rgb="FFD5DEE9"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="7"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/><xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0" applyFill="1"/><xf numFmtId="0" fontId="2" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
