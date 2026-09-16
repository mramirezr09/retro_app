<?php

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Services\Excel\ExcelReader;

class UploadController extends Controller
{
    private const SAMPLE_LIMIT = 100;

    public function index(): void
    {
        $this->render('upload/index', [
            'title'      => 'Subir Excel',
            'maxBytes'   => (int) Config::get('upload.max_bytes'),
            'extensions' => (array) Config::get('upload.extensions'),
        ]);
    }

    public function preview(): void
    {
        $this->requireCsrf();

        $file = $this->request->file('archivo');
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Debe seleccionar un archivo valido.');
            $this->redirect(url('/upload'));
        }

        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $allowed = (array) Config::get('upload.extensions');
        if (!in_array($extension, $allowed, true)) {
            $this->flash('error', 'Solo se permiten archivos: ' . implode(', ', $allowed));
            $this->redirect(url('/upload'));
        }

        if ((int) $file['size'] > (int) Config::get('upload.max_bytes')) {
            $this->flash('error', 'El archivo excede el tamano maximo permitido.');
            $this->redirect(url('/upload'));
        }

        $stored = $this->storeUploadedFile($file, $extension);
        $absolute = Config::get('paths.uploads') . DIRECTORY_SEPARATOR . $stored;

        try {
            $parsed = (new ExcelReader())->read($absolute);
        } catch (\Throwable $e) {
            @unlink($absolute);
            $this->flash('error', 'No se pudo leer el archivo: ' . $e->getMessage());
            $this->redirect(url('/upload'));
        }

        if (empty($parsed['headers'])) {
            @unlink($absolute);
            $this->flash('error', 'El archivo no contiene columnas legibles.');
            $this->redirect(url('/upload'));
        }

        $this->render('upload/preview', [
            'title'          => 'Vista previa',
            'nombreOriginal' => (string) $file['name'],
            'stored'         => $stored,
            'headers'        => $parsed['headers'],
            'rows'           => array_slice($parsed['rows'], 0, self::SAMPLE_LIMIT),
            'totalRows'      => count($parsed['rows']),
        ]);
    }

    public function save(): void
    {
        $this->requireCsrf();

        $stored = (string) $this->request->string('archivo');
        $nombreOriginal = (string) $this->request->string('nombre_original', 'archivo');
        $selected = $this->request->arrayInput('columnas');
        $respuestaColumna = (string) $this->request->string('respuesta_columna');

        if ($stored === '' || !preg_match('/^[A-Za-z0-9._-]+$/', $stored)) {
            $this->flash('error', 'Archivo temporal no valido, vuelva a subirlo.');
            $this->redirect(url('/upload'));
        }

        $absolute = Config::get('paths.storage') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $stored;
        if (!is_file($absolute)) {
            $this->flash('error', 'El archivo ya no esta disponible, vuelva a subirlo.');
            $this->redirect(url('/upload'));
        }

        $parsed = (new ExcelReader())->read($absolute);
        $headers = $parsed['headers'];

        $selected = array_values(array_intersect($headers, array_map('strval', $selected)));
        if (empty($selected)) {
            $this->flash('error', 'Debe conservar al menos una columna.');
            $this->redirect(url('/upload'));
        }

        if ($respuestaColumna === '' || !in_array($respuestaColumna, $headers, true)) {
            $this->flash('error', 'Debe elegir la columna que contiene la respuesta del alumno.');
            $this->redirect(url('/upload'));
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $fileId = (new \App\Models\ExcelFile())->create([
                'nombre_original'   => $nombreOriginal,
                'ruta'              => 'uploads/' . $stored,
                'columnas_json'     => json_encode($selected, JSON_UNESCAPED_UNICODE),
                'respuesta_columna' => $respuestaColumna,
                'total_registros'   => count($parsed['rows']),
            ]);

            $rowModel = new \App\Models\ExcelRow();
            $numero = 2;
            foreach ($parsed['rows'] as $row) {
                $filtered = [];
                foreach ($selected as $column) {
                    $filtered[$column] = $row[$column] ?? '';
                }
                $rowModel->create([
                    'excel_file_id'   => $fileId,
                    'numero_fila'     => $numero,
                    'datos_json'      => json_encode($filtered, JSON_UNESCAPED_UNICODE),
                    'respuesta_texto' => (string) ($row[$respuestaColumna] ?? ''),
                    'estado'          => 'pendiente',
                ]);
                $numero++;
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            $this->flash('error', 'Error al guardar: ' . $e->getMessage());
            $this->redirect(url('/upload'));
        }

        $this->flash('success', 'Archivo guardado con ' . count($parsed['rows']) . ' registros.');
        $this->redirect(url('/files/' . $fileId));
    }

    private function storeUploadedFile(array $file, string $extension): string
    {
        $dir = Config::get('paths.uploads');
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
        $target = $dir . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
            throw new \RuntimeException('No se pudo almacenar el archivo subido.');
        }
        return $name;
    }
}
