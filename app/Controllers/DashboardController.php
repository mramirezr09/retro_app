<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\ExcelFile;
use App\Models\Prompt;

class DashboardController extends Controller
{
    public function index(): void
    {
        $db = Database::connection();

        $stats = [
            'archivos'   => (int) $db->query('SELECT COUNT(*) FROM excel_files WHERE deleted_at IS NULL')->fetchColumn(),
            'prompts'    => (int) $db->query('SELECT COUNT(*) FROM prompts WHERE deleted_at IS NULL')->fetchColumn(),
            'registros'  => (int) $db->query('SELECT COUNT(*) FROM excel_rows')->fetchColumn(),
            'enviados'   => (int) $db->query('SELECT COUNT(*) FROM excel_rows WHERE estado = \'enviado\'')->fetchColumn(),
            'pendientes' => (int) $db->query('SELECT COUNT(*) FROM excel_rows WHERE estado IN (\'pendiente\', \'error\')')->fetchColumn(),
        ];

        $this->render('dashboard/index', [
            'title'   => 'Inicio',
            'stats'   => $stats,
            'files'   => array_slice((new ExcelFile())->activeAll(), 0, 5),
            'prompts' => array_slice((new Prompt())->activeAll(), 0, 5),
            'servicio' => (new \App\Models\Setting())->byService('openrouter'),
        ]);
    }
}
