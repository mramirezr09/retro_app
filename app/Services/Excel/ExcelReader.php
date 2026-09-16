<?php

namespace App\Services\Excel;

class ExcelReader
{
    public function read(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'xlsx') {
            return (new XlsxReader())->read($path);
        }
        if ($extension === 'csv') {
            return (new CsvReader())->read($path);
        }
        throw new \RuntimeException('Formato no soportado: ' . $extension);
    }
}
