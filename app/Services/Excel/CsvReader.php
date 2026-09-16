<?php

namespace App\Services\Excel;

class CsvReader
{
    public function read(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el archivo CSV');
        }

        $delimiter = $this->detectDelimiter($path);

        $matrix = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $matrix[] = array_map(function ($value) {
                return trim((string) $value);
            }, $row);
        }
        fclose($handle);

        if (!empty($matrix) && isset($matrix[0][0])) {
            $matrix[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $matrix[0][0]);
        }

        return $this->toAssoc($matrix);
    }

    private function detectDelimiter(string $path): string
    {
        $sample = (string) file_get_contents($path, false, null, 0, 8192);
        $candidates = [',' => 0, ';' => 0, "\t" => 0, '|' => 0];
        foreach (array_keys($candidates) as $delimiter) {
            $candidates[$delimiter] = substr_count($sample, $delimiter);
        }
        arsort($candidates);
        $best = array_key_first($candidates);
        return ($candidates[$best] ?? 0) > 0 ? $best : ',';
    }

    private function toAssoc(array $matrix): array
    {
        $matrix = array_values(array_filter($matrix, function ($row) {
            return is_array($row) && count(array_filter($row, fn ($v) => $v !== '')) > 0;
        }));

        if (empty($matrix)) {
            return ['headers' => [], 'rows' => []];
        }

        $headerRow = array_shift($matrix);
        $headers = [];
        foreach ($headerRow as $i => $label) {
            $label = trim((string) $label);
            $label = $label !== '' ? $label : 'Columna ' . ($i + 1);
            $headers[$i] = $this->uniqueLabel($headers, $label);
        }

        $rows = [];
        foreach ($matrix as $row) {
            $assoc = [];
            foreach ($headers as $i => $header) {
                $assoc[$header] = trim((string) ($row[$i] ?? ''));
            }
            $rows[] = $assoc;
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
