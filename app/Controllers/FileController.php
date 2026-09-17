<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ExcelFile;
use App\Models\ExcelRow;
use App\Models\Prompt;
use App\Models\Setting;
use App\Services\AI\AiManager;
use App\Services\AI\ProcessingOptions;
use App\Services\Excel\XlsxWriter;

class FileController extends Controller
{
    public function index(): void
    {
        $this->render('files/index', [
            'title' => 'Archivos Excel',
            'files' => (new ExcelFile())->activeAll(),
        ]);
    }

    public function show(string $id): void
    {
        $fileModel = new ExcelFile();
        $file = $fileModel->findActive((int) $id);
        if (!$file) {
            $this->flash('error', 'Archivo no encontrado.');
            $this->redirect(url('/files'));
        }

        $rows = (new ExcelRow())->byFile((int) $file['id']);
        foreach ($rows as &$row) {
            $row['datos'] = (new ExcelRow())->data($row);
        }
        unset($row);

        $this->render('files/show', [
            'title'    => 'Registros',
            'file'     => $file,
            'columns'  => $fileModel->columns($file),
            'rows'     => $rows,
            'prompts'  => (new Prompt())->activeAll(),
            'services' => AiManager::SERVICES,
            'processing' => ProcessingOptions::load(),
            'settings' => [
                'openrouter' => (new Setting())->byService('openrouter'),
                'opencode'   => (new Setting())->byService('opencode'),
            ],
        ]);
    }

    public function destroy(string $id): void
    {
        $this->requireCsrf();
        (new ExcelFile())->softDelete((int) $id);
        $this->flash('success', 'Archivo eliminado.');
        $this->redirect(url('/files'));
    }

    public function export(string $id): void
    {
        $fileModel = new ExcelFile();
        $file = $fileModel->findActive((int) $id);
        if (!$file) {
            $this->flash('error', 'Archivo no encontrado.');
            $this->redirect(url('/files'));
        }

        $columns = $fileModel->columns($file);
        $headers = array_merge($columns, ['retroalimentacion', 'estado', 'servicio', 'modelo', 'tokens', 'procesado_en']);

        $rows = (new ExcelRow())->byFile((int) $file['id']);
        $exportRows = [];
        foreach ($rows as $row) {
            $data = (new ExcelRow())->data($row);
            $line = [];
            foreach ($columns as $column) {
                $line[] = $data[$column] ?? '';
            }
            $line[] = (string) ($row['retroalimentacion'] ?? '');
            $line[] = (string) $row['estado'];
            $line[] = (string) $row['servicio'];
            $line[] = (string) $row['modelo'];
            $line[] = $row['tokens'] !== null ? (string) $row['tokens'] : '';
            $line[] = (string) ($row['procesado_en'] ?? '');
            $exportRows[] = $line;
        }

        $binary = (new XlsxWriter())->build($headers, $exportRows);
        $base = pathinfo((string) $file['nombre_original'], PATHINFO_FILENAME);
        $filename = 'resultados_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $base) . '_' . date('Ymd_His') . '.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($binary));
        echo $binary;
        exit;
    }
}
