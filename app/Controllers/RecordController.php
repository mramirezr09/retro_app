<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ExcelFile;
use App\Models\ExcelRow;
use App\Models\Prompt;
use App\Services\AI\AiManager;
use App\Services\AI\ProcessingOptions;

class RecordController extends Controller
{
    public function send(): void
    {
        $this->requireCsrf();

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

        $options = ProcessingOptions::load();

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

        $imagenesColumna = (string) ($file['imagenes_columna'] ?? '');
        $documentosColumna = (string) ($file['documentos_columna'] ?? '');

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

        $ai = new AiManager();

        $primaryConfig = $ai->configFor($service);
        if ($modelOverride !== '') {
            $primaryConfig['modelo'] = $modelOverride;
        }
        if ($service === 'openrouter') {
            if ((string) $primaryConfig['modelo'] === '') {
                $this->json(['ok' => false, 'error' => 'Configure un modelo en Ajustes.'], 422);
            }
            if ((string) $primaryConfig['api_key'] === '') {
                $this->json(['ok' => false, 'error' => 'Configure la API key de OpenRouter en Ajustes.'], 422);
            }
        }

        $alternateService = $service === 'openrouter' ? 'opencode' : 'openrouter';
        $alternateConfig = $ai->configFor($alternateService);

        $intentosPorServicio = $options['intentos'];
        $sequence = [];
        for ($i = 0; $i < $intentosPorServicio; $i++) {
            $sequence[] = ['servicio' => $service, 'config' => $primaryConfig];
        }
        for ($i = 0; $i < $intentosPorServicio; $i++) {
            $sequence[] = ['servicio' => $alternateService, 'config' => $alternateConfig];
        }
        $totalIntentos = count($sequence);

        $rowModel = new ExcelRow();
        $results = [];

        foreach ($rowIds as $index => $rowId) {
            if ($index > 0) {
                sleep($options['delay']);
            }

            $row = $rowModel->findInFile($rowId, $fileId);
            if (!$row) {
                $results[] = ['id' => $rowId, 'ok' => false, 'error' => 'Registro no encontrado'];
                continue;
            }

            $answer = (string) $row['respuesta_texto'];
            if (trim($answer) === '') {
                $rowModel->markError($rowId, 'El registro no tiene texto de respuesta.', 0);
                $results[] = ['id' => $rowId, 'ok' => false, 'error' => 'Sin texto de respuesta', 'estado' => 'error'];
                continue;
            }

            $rowModel->markSending($rowId, $promptId, $service, (string) $primaryConfig['modelo']);

            $data = $rowModel->data($row);
            $attachments = [
                'imagenes'   => $this->parseUrls((string) ($data[$imagenesColumna] ?? '')),
                'documentos' => $this->parseUrls((string) ($data[$documentosColumna] ?? '')),
            ];

            $winner = null;
            $attemptLog = [];
            $attemptNumber = 0;

            foreach ($sequence as $attempt) {
                $attemptNumber++;
                $esPrincipal = $attempt['servicio'] === $service;

                $response = $ai->service($attempt['servicio'])->generate(
                    (string) $prompt['contenido'],
                    $answer,
                    $attempt['config'],
                    $attachments
                );

                $text = (string) ($response['text'] ?? '');
                $ok = (bool) ($response['ok'] ?? false);
                $palabras = ProcessingOptions::words($text);
                $valid = ProcessingOptions::isValid($ok, $text, $options['min_palabras']);
                $modelUsed = (string) ($attempt['config']['modelo'] ?? '');

                $attemptLog[] = [
                    'servicio' => $attempt['servicio'],
                    'modelo'   => $modelUsed,
                    'principal' => $esPrincipal,
                    'ok'       => $valid,
                    'palabras' => $palabras,
                    'error'    => $valid
                        ? null
                        : (((string) ($response['error'] ?? '')) !== ''
                            ? (string) $response['error']
                            : 'Sin retroalimentacion suficiente (' . $palabras . ' de ' . $options['min_palabras'] . ' palabras).'),
                ];

                if ($valid) {
                    $winner = [
                        'response' => $response,
                        'servicio' => $attempt['servicio'],
                        'modelo'   => $modelUsed,
                        'principal' => $esPrincipal,
                    ];
                    break;
                }

                if ($attemptNumber < $totalIntentos) {
                    sleep($options['retry']);
                }
            }

            if ($winner) {
                $rowModel->markSent(
                    $rowId,
                    (string) $winner['response']['text'],
                    $winner['response']['tokens'],
                    (string) $winner['servicio'],
                    (string) $winner['modelo'],
                    $attemptNumber,
                    !$winner['principal']
                );
                $results[] = [
                    'id'        => $rowId,
                    'ok'        => true,
                    'feedback'  => $winner['response']['text'],
                    'tokens'    => $winner['response']['tokens'],
                    'estado'    => 'enviado',
                    'servicio'  => $winner['servicio'],
                    'modelo'    => $winner['modelo'],
                    'fallback'  => !$winner['principal'],
                    'intentos'  => $attemptNumber,
                ];
                continue;
            }

            $summary = 'Sin retroalimentacion valida tras ' . $attemptNumber . ' intento(s).';
            foreach ($attemptLog as $i => $log) {
                $modelo = $log['modelo'] !== '' ? $log['modelo'] : 'modelo por defecto';
                $summary .= "\n" . ($i + 1) . '. ' . $log['servicio'] . '/' . $modelo . ': ' . $log['error'];
            }

            $rowModel->markError($rowId, $summary, $attemptNumber);
            $results[] = [
                'id'       => $rowId,
                'ok'       => false,
                'error'    => $summary,
                'estado'   => 'error',
                'intentos' => $attemptNumber,
                'detalle'  => $attemptLog,
            ];
        }

        $this->json([
            'ok'       => true,
            'results'  => $results,
            'servicio' => $service,
            'modelo'   => (string) $primaryConfig['modelo'],
            'alterno'  => $alternateService,
        ]);
    }

    private function parseUrls(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $urls = [];
        foreach (preg_split('/[|\n\r]+/', $value) as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '' || !preg_match('#^https?://#i', $candidate)) {
                continue;
            }
            $urls[$candidate] = true;
        }

        return array_keys($urls);
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
                    'intentos'          => null,
                    'usado_fallback'    => 0,
                ]);
                $updated[] = $rowId;
            }
        }

        $this->json(['ok' => true, 'updated' => $updated]);
    }
}
