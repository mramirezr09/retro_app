<?php

namespace App\Services\Imports;

use App\Core\Config;

class ZipImporter
{
    public const COLUMNA_ALUMNO = 'Alumno';
    public const COLUMNA_ARCHIVO = 'Archivo';

    private const IGNORED_FILES = ['.DS_Store', 'Thumbs.db', 'desktop.ini'];

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    private const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'odt', 'rtf', 'txt'];

    /**
     * Lista el contenido del ZIP sin extraer archivos (para la vista previa).
     *
     * @return array{headers: string[], rows: array<int, array<string, string>>, alumnos: array<string, array>}
     */
    public function inspect(string $path): array
    {
        $alumnos = $this->collect($path, false);
        return $this->buildResult($alumnos, false);
    }

    /**
     * Extrae el contenido del ZIP y devuelve las referencias locales (para guardar).
     *
     * @return array{headers: string[], rows: array<int, array<string, string>>, alumnos: array<string, array>, origen: string}
     */
    public function read(string $path): array
    {
        $alumnos = $this->collect($path, true);
        $destDir = $this->destinationDirectory();

        foreach ($alumnos as $alumno => $archivos) {
            foreach ($archivos as $index => $archivo) {
                $alumnos[$alumno][$index]['ref'] = $this->storeFile($destDir, $alumno, $archivo['name'], $archivo['extension'], (string) $archivo['contents']);
                unset($alumnos[$alumno][$index]['contents']);
            }
        }

        $result = $this->buildResult($alumnos, true);
        $result['origen'] = basename($destDir);
        return $result;
    }

    /**
     * @return array<string, array<int, array{name: string, extension: string, tipo: string, contents?: string}>>
     */
    private function collect(string $path, bool $withContents): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('La extension zip de PHP es requerida para leer archivos .zip');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('No se pudo abrir el archivo .zip');
        }

        $extensions = $this->allowedExtensions();
        $alumnos = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $this->normalizeName((string) $zip->getNameIndex($i));
            if ($name === '' || str_ends_with($name, '/') || $this->isIgnored($name)) {
                continue;
            }

            $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
            if (!in_array($extension, $extensions, true)) {
                continue;
            }

            $alumno = $this->studentName($this->folderOf($name), $name);
            if ($alumno === '') {
                continue;
            }

            $entry = [
                'name'      => basename($name),
                'extension' => $extension,
                'tipo'      => in_array($extension, self::IMAGE_EXTENSIONS, true) ? 'imagen' : 'documento',
            ];

            if ($withContents) {
                $contents = $zip->getFromIndex($i);
                if (!is_string($contents) || $contents === '') {
                    continue;
                }
                $entry['contents'] = $contents;
            }

            $alumnos[$alumno][] = $entry;
        }

        $zip->close();

        if (empty($alumnos)) {
            throw new \RuntimeException('El archivo .zip no contiene archivos validos (PDF, Word o imagenes).');
        }

        return $alumnos;
    }

    /**
     * @return array{headers: string[], rows: array<int, array<string, string>>, alumnos: array<string, array>}
     */
    private function buildResult(array $alumnos, bool $withRefs): array
    {
        $rows = [];
        foreach ($alumnos as $alumno => $archivos) {
            $values = [];
            foreach ($archivos as $archivo) {
                $values[] = $withRefs ? (string) ($archivo['ref'] ?? '') : $archivo['name'];
            }
            $rows[] = [
                self::COLUMNA_ALUMNO  => $alumno,
                self::COLUMNA_ARCHIVO => implode('|', array_filter($values, static fn ($value) => $value !== '')),
            ];
        }

        return [
            'headers' => [self::COLUMNA_ALUMNO, self::COLUMNA_ARCHIVO],
            'rows'    => $rows,
            'alumnos' => $alumnos,
        ];
    }

    private function storeFile(string $destDir, string $alumno, string $name, string $extension, string $contents): string
    {
        $folderName = $this->slug($alumno) . '_' . substr(sha1($alumno), 0, 6);

        $targetDir = $destDir . DIRECTORY_SEPARATOR . $folderName;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('No se pudo crear la carpeta de extraccion.');
        }

        $base = $this->slug($this->stripExtension($name));
        if ($base === '') {
            $base = 'ARCHIVO';
        }

        $fileName = $base . '.' . $extension;
        $counter = 2;
        while (is_file($targetDir . DIRECTORY_SEPARATOR . $fileName)) {
            $fileName = $base . '_' . $counter . '.' . $extension;
            $counter++;
        }

        if (file_put_contents($targetDir . DIRECTORY_SEPARATOR . $fileName, $contents) === false) {
            throw new \RuntimeException('No se pudo extraer el archivo ' . $name . '.');
        }

        return 'local:uploads/' . basename($destDir) . '/' . $folderName . '/' . $fileName;
    }

    private function allowedExtensions(): array
    {
        $configured = (array) Config::get('upload.attachment_extensions', []);
        $extensions = $configured !== [] ? $configured : array_merge(self::DOCUMENT_EXTENSIONS, self::IMAGE_EXTENSIONS);
        return array_values(array_unique(array_map('strtolower', $extensions)));
    }

    private function destinationDirectory(): string
    {
        $dir = Config::get('paths.uploads') . DIRECTORY_SEPARATOR . 'zip_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo preparar el directorio de extraccion.');
        }
        return $dir;
    }

    private function folderOf(string $name): string
    {
        $dir = dirname($name);
        return ($dir === '.' || $dir === '') ? '' : $dir;
    }

    private function studentName(string $folder, string $name): string
    {
        $segment = trim((string) basename(str_replace('\\', '/', $folder)), " \t\n\r\0\x0B");
        if ($segment === '') {
            return trim((string) pathinfo($name, PATHINFO_FILENAME));
        }

        if (preg_match('/^(.*?)_\d+/u', $segment, $matches) === 1) {
            $candidate = trim($matches[1]);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return $segment;
    }

    private function isIgnored(string $name): bool
    {
        $normalized = str_replace('\\', '/', $name);
        if (str_starts_with($normalized, '__MACOSX/') || str_contains($normalized, '/__MACOSX/')) {
            return true;
        }

        $base = basename($normalized);
        if (in_array($base, self::IGNORED_FILES, true)) {
            return true;
        }

        return str_starts_with($base, '._');
    }

    private function normalizeName(string $name): string
    {
        $name = str_replace('\\', '/', $name);
        if ($name === '' || mb_check_encoding($name, 'UTF-8')) {
            return $name;
        }

        foreach (['CP437', 'Windows-1252', 'ISO-8859-1'] as $encoding) {
            $converted = @mb_convert_encoding($name, 'UTF-8', $encoding);
            if (is_string($converted) && $converted !== '' && mb_check_encoding($converted, 'UTF-8')) {
                return $converted;
            }
        }

        return $name;
    }

    private function stripExtension(string $name): string
    {
        return (string) pathinfo(basename(str_replace('\\', '/', $name)), PATHINFO_FILENAME);
    }

    private function slug(string $value): string
    {
        $value = $this->stripAccents($value);
        $value = (string) preg_replace('/[^A-Za-z0-9]+/', '_', $value);
        $value = trim($value, '_');
        return $value !== '' ? strtoupper($value) : 'SIN_NOMBRE';
    }

    private function stripAccents(string $value): string
    {
        $value = (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return $value;
    }
}
