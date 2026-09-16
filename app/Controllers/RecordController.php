<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ExcelFile;
use App\Models\ExcelRow;
use App\Models\Prompt;
use App\Services\AI\AiManager;

class RecordController extends Controller
{
    public function send(): void
    {
        $this->requireCsrf();

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

        $fileId = (int) $this->request->input('archivo_id', 0);
        $promptId = (int) $this->request->input('prompt_id', 0);
        $service = (string) $this->request->string('servicio');
        $modelOverride = $this->request->string('modelo');
        $rowIds = array_map('intval', $this->request->arrayInput('registros'));

        $fileModel = new ExcelFile();
        $file = $fileModel->findActive($fileId);
        if (!$file) {
            $this->json(['ok' => false, 'error' => 'Archivo no encontrado.'], 404);
        }

        $prompt = (new Prompt())->findActive($promptId);
        if (!$prompt) {
            $this->json(['ok' => false, 'error' => 'Debe seleccionar un prompt valido.'], 422);
        }

        if (!array_key_exists($service, AiManager::SERVICES)) {
            $this->json(['ok' => false, 'error' => 'Servicio de IA no valido.'], 422);
        }

        if (empty($rowIds)) {
            $this->json(['ok' => false, 'error' => 'Debe seleccionar al menos un registro.'], 422);
        }

        $config = (new AiManager())->configFor($service);
        if ($modelOverride !== '') {
            $config['modelo'] = $modelOverride;
        }
        if ($service === 'openrouter') {
            if ((string) $config['modelo'] === '') {
                $this->json(['ok' => false, 'error' => 'Configure un modelo en Ajustes.'], 422);
            }
            if ((string) $config['api_key'] === '') {
                $this->json(['ok' => false, 'error' => 'Configure la API key de OpenRouter en Ajustes.'], 422);
            }
        }

        $ai = new AiManager();
        $rowModel = new ExcelRow();
        $results = [];

        foreach ($rowIds as $rowId) {
            $row = $rowModel->findInFile($rowId, $fileId);
            if (!$row) {
                $results[] = ['id' => $rowId, 'ok' => false, 'error' => 'Registro no encontrado'];
                continue;
            }

            $answer = (string) $row['respuesta_texto'];
            if (trim($answer) === '') {
                $rowModel->markError($rowId, 'El registro no tiene texto de respuesta.');
                $results[] = ['id' => $rowId, 'ok' => false, 'error' => 'Sin texto de respuesta'];
                continue;
            }

            $rowModel->markSending($rowId, $promptId, $service, (string) $config['modelo']);

            $response = $ai->generate($service, (string) $prompt['contenido'], $answer);

            if ($response['ok']) {
                $rowModel->markSent($rowId, (string) $response['text'], $response['tokens']);
                $results[] = [
                    'id'         => $rowId,
                    'ok'         => true,
                    'feedback'   => $response['text'],
                    'tokens'     => $response['tokens'],
                    'estado'     => 'enviado',
                ];
            } else {
                $rowModel->markError($rowId, (string) $response['error']);
                $results[] = [
                    'id'     => $rowId,
                    'ok'     => false,
                    'error'  => $response['error'],
                    'estado' => 'error',
                ];
            }
        }

        $this->json([
            'ok'      => true,
            'results' => $results,
            'servicio' => $service,
            'modelo'   => (string) $config['modelo'],
        ]);
    }

    public function reset(): void
    {
        $this->requireCsrf();

        $fileId = (int) $this->request->input('archivo_id', 0);
        $rowIds = array_map('intval', $this->request->arrayInput('registros'));

        $file = (new ExcelFile())->findActive($fileId);
        if (!$file || empty($rowIds)) {
            $this->json(['ok' => false, 'error' => 'Solicitud invalida.'], 422);
        }

        $rowModel = new ExcelRow();
        $updated = [];
        foreach ($rowIds as $rowId) {
            if ($rowModel->findInFile($rowId, $fileId)) {
                $rowModel->update($rowId, [
                    'retroalimentacion' => null,
                    'estado'            => 'pendiente',
                    'error'             => null,
                    'tokens'            => null,
                    'procesado_en'      => null,
                ]);
                $updated[] = $rowId;
            }
        }

        $this->json(['ok' => true, 'updated' => $updated]);
    }
}
