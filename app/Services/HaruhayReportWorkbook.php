<?php

namespace App\Services;

use Phar;
use PharData;

class HaruhayReportWorkbook
{
    private const XML = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';

    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    public function create(array $report, array $filters, string $scope): string
    {
        $sheets = ['Summary' => [
            'headers' => ['Metric / Report Basis', 'Value'],
            'rows' => [
                ['Issued billing in period (PHP)', $report['billingTotal'] / 100],
                ['Collections received in period (PHP)', $report['receivedTotal'] / 100],
                ['Outstanding as of '.$report['asOf'].' (PHP)', $report['outstanding'] / 100],
                ['Collection rate (%)', $report['rate']],
                ['Recorded rooms', $report['totalUnits']],
                ['Occupied rooms', $report['unitStatus']['Occupied']],
                ['Vacant rooms', $report['unitStatus']['Vacant']],
                ['Reserved rooms', $report['unitStatus']['Reserved']],
                ['Rooms under maintenance', $report['maintenance']],
                ['Occupancy rate (%)', $report['occupancyRate']],
                ['Active tenants', $report['activeTenants']],
                ['Occupancy date', $filters['occupancy_on'] ?? today()->toDateString()],
                ['Rooms without historical records', $report['unknownUnits']],
                ['Billing basis', 'Issued invoices only. Drafts are excluded.'],
                ['Collections basis', 'Actual receipts counted once; applying advance credit is not new cash.'],
                ['Reversals', 'Financial reports exclude entries reversed on or before the financial as-of date.'],
                ['Outstanding basis', 'All issued unpaid balances through the as-of date, including earlier periods.'],
                ['Deposits', 'Security deposit transactions are separate from rent income and collections.'],
                ['Occupancy basis', 'Current rooms unless an Occupancy As Of date is selected. Missing history is unavailable.'],
                ['Lease basis', 'Contracts ending within the selected period; status is shown as of today.'],
                ['Rows', 'All filtered records are included; screen pagination does not limit this workbook.'],
            ],
        ]];
        $names = ['overview' => 'Period Summary', 'income' => 'Billing', 'collections' => 'Collections', 'outstanding' => 'Outstanding', 'ledger' => 'Tenant Ledger', 'occupancy' => 'Occupancy', 'leases' => 'Lease Contracts', 'deposits' => 'Security Deposits'];
        foreach ($names as $key => $name) {
            $sheets[$name] = $report['tables'][$key];
        }

        $path = tempnam(sys_get_temp_dir(), 'haruhay-report-');
        if ($path === false) {
            throw new \RuntimeException('Unable to prepare the report download.');
        }
        unlink($path);
        $path .= '.zip';
        try {
            $zip = new PharData($path, 0, null, Phar::ZIP);
            $types = '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
            $workbook = '';
            $relationships = '';
            $id = 0;
            foreach ($sheets as $name => $table) {
                $id++;
                $workbook .= '<sheet name="'.$this->escape($name).'" sheetId="'.$id.'" r:id="rId'.$id.'"/>';
                $relationships .= '<Relationship Id="rId'.$id.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$id.'.xml"/>';
                $types .= '<Override PartName="/xl/worksheets/sheet'.$id.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
                $zip->addFromString('xl/worksheets/sheet'.$id.'.xml', $this->sheet($name, $table, $filters, $scope, $name === 'Summary'));
            }
            $zip->addFromString('[Content_Types].xml', self::XML.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'.$types.'</Types>');
            $zip->addFromString('_rels/.rels', self::XML.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', self::XML.'<workbook xmlns="'.self::NS.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView/></bookViews><sheets>'.$workbook.'</sheets></workbook>');
            $relationships .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
            $zip->addFromString('xl/_rels/workbook.xml.rels', self::XML.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>');
            $zip->addFromString('xl/styles.xml', $this->styles());
            unset($zip);

            return $path;
        } catch (\Throwable $e) {
            if (is_file($path)) {
                unlink($path);
            }
            throw $e;
        }
    }

    private function sheet(string $name, array $table, array $filters, string $scope, bool $summary): string
    {
        $lastColumn = $this->column(count($table['headers']));
        $rows = $this->row(1, ['HARUHAY SPACE — '.$name], 1);
        $rows .= $this->row(2, ['Period: '.$filters['from'].' to '.$filters['to']]);
        $rows .= $this->row(3, [$scope]);
        $rows .= $this->row(4, ['Generated: '.now()->format('Y-m-d H:i').' '.config('app.timezone').' | Financial as of '.min($filters['to'], today()->toDateString())]);
        $rows .= $this->row(6, $table['headers'], 2);
        $numeric = [];
        $totals = [];
        foreach ($table['headers'] as $i => $header) {
            if (str_contains($header, '(PHP)') || str_contains($header, '(%)') || in_array($header, ['Total Rooms', 'Occupied', 'Vacant', 'Reserved', 'Under Maintenance', 'Days Overdue'], true)) {
                $numeric[$i] = true;
            }
            // Received, deducted and refunded deposits have different directions; do not sum their gross amounts.
            if (str_contains($header, '(PHP)') && $name !== 'Security Deposits') {
                $totals[$i] = 0.0;
            }
        }
        $line = 7;
        foreach ($table['rows'] as $values) {
            $cells = '';
            foreach (array_values($values) as $i => $value) {
                $isNumber = ($summary ? $i === 1 && (is_int($value) || is_float($value)) : isset($numeric[$i])) && is_numeric($value);
                $style = $isNumber ? 3 : ($line % 2 ? 0 : 4);
                if (isset($totals[$i]) && $isNumber) {
                    $totals[$i] += (float) $value;
                }
                $cells .= $this->cell($this->column($i + 1).$line, $value, $style, $isNumber);
            }
            $rows .= '<row r="'.$line.'">'.$cells.'</row>';
            $line++;
        }
        $dataEnd = max(6, $line - 1);
        if (! $table['rows']) {
            $rows .= $this->row($line++, ['No records match the selected filters.']);
        }
        if ($totals) {
            $cells = $this->cell('A'.$line, 'TOTAL', 2);
            foreach ($totals as $i => $total) {
                $cells .= $this->cell($this->column($i + 1).$line, number_format($total, 2, '.', ''), 5, true);
            }
            $rows .= '<row r="'.$line.'">'.$cells.'</row>';
            $line++;
        }
        $rows .= $this->row($line + 1, ['Records: '.count($table['rows']).' | See Summary for report definitions.']);
        $columns = '';
        foreach ($table['headers'] as $i => $header) {
            $width = $summary ? ($i === 0 ? 57 : 105) : (isset($numeric[$i]) ? 23 : (str_contains($header, 'Tenant') || in_array($header, ['Property', 'Reason', 'Entry'], true) ? 34 : 24));
            $columns .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
        }
        $merges = '';
        foreach ([1, 2, 3, 4] as $r) {
            $merges .= '<mergeCell ref="A'.$r.':'.$lastColumn.$r.'"/>';
        }

        return self::XML.'<worksheet xmlns="'.self::NS.'"><sheetViews><sheetView workbookViewId="0"><pane ySplit="6" topLeftCell="A7" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="24"/><cols>'.$columns.'</cols><sheetData>'.$rows.'</sheetData><autoFilter ref="A6:'.$lastColumn.$dataEnd.'"/><mergeCells count="4">'.$merges.'</mergeCells><printOptions horizontalCentered="1"/><pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup paperSize="9" orientation="landscape"/></worksheet>';
    }

    private function row(int $line, array $values, int $style = 0): string
    {
        $cells = '';
        foreach ($values as $i => $value) {
            $cells .= $this->cell($this->column($i + 1).$line, $value, $style);
        }

        return '<row r="'.$line.'">'.$cells.'</row>';
    }

    private function cell(string $address, mixed $value, int $style, bool $numeric = false): string
    {
        // Inline strings keep names, references, dates and formula-like input as literal text.
        return '<c r="'.$address.'" s="'.$style.'"'.($numeric ? '><v>'.$value.'</v>' : ' t="inlineStr"><is><t xml:space="preserve">'.$this->escape((string) $value).'</t></is>').'</c>';
    }

    private function column(int $index): string
    {
        $name = '';
        while ($index > 0) {
            $index--;
            $name = chr(65 + $index % 26).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value), ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function styles(): string
    {
        return self::XML.'<styleSheet xmlns="'.self::NS.'"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="18"/><color rgb="FF00594F"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF00594F"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF0F7F6"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="6"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0"/><xf numFmtId="4" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/><xf numFmtId="0" fontId="0" fillId="3" borderId="0" xfId="0"/><xf numFmtId="4" fontId="2" fillId="2" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }
}
