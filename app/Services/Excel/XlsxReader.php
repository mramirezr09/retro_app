<?php

namespace App\Services\Excel;

class XlsxReader
{
    public function read(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('La extension zip de PHP es requerida para leer archivos .xlsx');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo .xlsx');
        }

        try {
            $shared = $this->readSharedStrings($zip);
            $sheetXml = $this->readFirstSheet($zip);
        } finally {
            $zip->close();
        }

        $matrix = $this->parseSheet($sheetXml, $shared);
        return $this->toAssoc($matrix);
    }

    private function readSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false || $xml === '') {
            return [];
        }
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($xml)) {
            return [];
        }
        $strings = [];
        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('//*[local-name()="si"]') as $si) {
            $strings[] = $this->nodeText($si);
        }
        return $strings;
    }

    private function nodeText(\DOMNode $node): string
    {
        $buffer = '';
        $xpath = new \DOMXPath($node->ownerDocument);
        foreach ($xpath->query('.//*[local-name()="t"]', $node) as $t) {
            $buffer .= $t->textContent;
        }
        if ($buffer === '') {
            $buffer = trim($node->textContent);
        }
        return $buffer;
    }

    private function readFirstSheet(\ZipArchive $zip): string
    {
        $target = 'xl/worksheets/sheet1.xml';
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook !== false && $rels !== false) {
            $doc = new \DOMDocument();
            if (@$doc->loadXML($workbook)) {
                $xpath = new \DOMXPath($doc);
                $sheet = $xpath->query('//*[local-name()="sheet"]')->item(0);
                if ($sheet instanceof \DOMElement) {
                    $rid = null;
                    foreach ($sheet->attributes as $attr) {
                        if ($attr->localName === 'id') {
                            $rid = $attr->value;
                        }
                    }
                    if ($rid !== null) {
                        $relDoc = new \DOMDocument();
                        if (@$relDoc->loadXML($rels)) {
                            $relXpath = new \DOMXPath($relDoc);
                            foreach ($relXpath->query('//*[local-name()="Relationship"]') as $rel) {
                                if ($rel->getAttribute('Id') === $rid) {
                                    $target = 'xl/' . ltrim($rel->getAttribute('Target'), '/');
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        $sheetXml = $zip->getFromName($target);
        if ($sheetXml === false && $target !== 'xl/worksheets/sheet1.xml') {
            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        }
        if ($sheetXml === false) {
            throw new \RuntimeException('El archivo .xlsx no contiene hojas de calculo');
        }
        return $sheetXml;
    }

    private function parseSheet(string $xml, array $shared): array
    {
        $doc = new \DOMDocument();
        if (!@$doc->loadXML($xml)) {
            throw new \RuntimeException('No se pudo interpretar la hoja del .xlsx');
        }
        $xpath = new \DOMXPath($doc);
        $matrix = [];
        foreach ($xpath->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $rowNode) {
            $row = [];
            foreach ($xpath->query('.//*[local-name()="c"]', $rowNode) as $cell) {
                /** @var \DOMElement $cell */
                $ref = $cell->getAttribute('r');
                $index = $this->columnIndex($ref);
                $row[$index] = $this->cellValue($cell, $shared);
            }
            if (!empty($row)) {
                $matrix[] = $row;
            }
        }
        return $matrix;
    }

    private function cellValue(\DOMElement $cell, array $shared): string
    {
        $type = $cell->getAttribute('t');

        $valueNode = null;
        foreach ($cell->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'v') {
                $valueNode = $child;
                break;
            }
        }
        $raw = $valueNode ? $valueNode->textContent : '';

        if ($type === 's') {
            return $shared[(int) $raw] ?? '';
        }
        if ($type === 'inlineStr') {
            $buffer = '';
            foreach ($cell->childNodes as $child) {
                if ($child instanceof \DOMElement && $child->localName === 'is') {
                    $buffer .= $this->nodeText($child);
                }
            }
            return $buffer;
        }
        if ($type === 'b') {
            return $raw === '1' ? 'TRUE' : 'FALSE';
        }
        return $raw;
    }

    private function columnIndex(string $ref): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
        if ($letters === '') {
            return 0;
        }
        $index = 0;
        $length = strlen($letters);
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index - 1;
    }

    private function toAssoc(array $matrix): array
    {
        if (empty($matrix)) {
            return ['headers' => [], 'rows' => []];
        }

        $headerRow = array_shift($matrix);
        $maxIndex = max(array_keys($headerRow));
        $headers = [];
        for ($i = 0; $i <= $maxIndex; $i++) {
            $label = trim((string) ($headerRow[$i] ?? ''));
            $headers[$i] = $this->uniqueLabel($headers, $label !== '' ? $label : 'Columna ' . ($i + 1));
        }

        $rows = [];
        foreach ($matrix as $row) {
            $assoc = [];
            $hasValue = false;
            for ($i = 0; $i <= $maxIndex; $i++) {
                $value = trim((string) ($row[$i] ?? ''));
                $assoc[$headers[$i]] = $value;
                if ($value !== '') {
                    $hasValue = true;
                }
            }
            if ($hasValue) {
                $rows[] = $assoc;
            }
        }

        return ['headers' => array_values($headers), 'rows' => $rows];
    }

    private function uniqueLabel(array $existing, string $label): string
    {
        $candidate = $label;
        $counter = 2;
        while (in_array($candidate, $existing, true)) {
            $candidate = $label . ' (' . $counter . ')';
            $counter++;
        }
        return $candidate;
    }
}
