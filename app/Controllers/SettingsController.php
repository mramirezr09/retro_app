<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Env;
use App\Models\Setting;
use App\Services\AI\AiManager;
use App\Services\AI\ProcessingOptions;

class SettingsController extends Controller
{
    public function index(): void
    {
        $model = new Setting();
        $services = [];
        foreach (array_keys(AiManager::SERVICES) as $key) {
            $row = $model->byService($key) ?? [];
            $envName = (string) ($row['api_key_env'] ?? '');
            $services[$key] = [
                'label'      => AiManager::SERVICES[$key],
                'data'       => $row,
                'key_env'    => $envName,
                'has_key'    => $envName !== '' && (string) Env::get($envName, '') !== '',
                'key_masked' => $this->mask((string) Env::get($envName, '')),
            ];
        }

        $this->render('settings/index', [
            'title'      => 'Ajustes',
            'services'   => $services,
            'processing' => ProcessingOptions::load(),
        ]);
    }

    public function store(): void
    {
        $this->requireCsrf();
        $model = new Setting();

        $defaults = [
            'openrouter' => ['api_key_env' => 'OPENROUTER_API_KEY', 'base_url' => 'https://openrouter.ai/api/v1'],
            'opencode'   => ['api_key_env' => 'OPENCODE_API_KEY', 'base_url' => ''],
        ];

        foreach (array_keys(AiManager::SERVICES) as $service) {
            $prefix = $service . '_';
            $apiKeyEnv = $defaults[$service]['api_key_env'];

            $data = [
                'modelo'      => $this->request->string($prefix . 'modelo'),
                'base_url'    => $this->request->string($prefix . 'base_url', $defaults[$service]['base_url']),
                'api_key_env' => $apiKeyEnv,
            ];

            if ($service === 'opencode') {
                $data['opencode_path'] = $this->request->string($prefix . 'path');
            }

            $apiKey = $this->request->string($prefix . 'api_key');
            $clear = $this->request->input($prefix . 'clear_key');

            if ($apiKey !== '') {
                Env::set($apiKeyEnv, $apiKey);
            } elseif ($clear) {
                Env::forget($apiKeyEnv);
            }

            $model->saveService($service, $data);
        }

        ProcessingOptions::save([
            'delay'        => $this->request->input('ai_delay_segundos'),
            'retry'        => $this->request->input('ai_retry_segundos'),
            'intentos'     => $this->request->input('ai_intentos'),
            'min_palabras' => $this->request->input('ai_min_palabras'),
        ]);

        try {
            Env::save();
        } catch (\Throwable $e) {
            $this->flash('error', 'No se pudieron guardar las API keys: ' . $e->getMessage());
            $this->redirect(url('/settings'));
        }

        $this->flash('success', 'Ajustes guardados correctamente.');
        $this->redirect(url('/settings'));
    }

    public function test(): void
    {
        $this->requireCsrf();

        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

        $service = (string) $this->request->string('servicio');
        $model = (string) $this->request->string('modelo');
        $message = $this->request->input('mensaje', '');
        $message = is_string($message) ? trim($message) : '';
        if ($message === '') {
            $message = 'Responde unicamente con la palabra OK.';
        }

        if (!array_key_exists($service, AiManager::SERVICES)) {
            $this->json(['ok' => false, 'error' => 'Servicio de IA no valido.'], 422);
        }

        $ai = new AiManager();
        $config = $ai->configFor($service);
        if ($model !== '') {
            $config['modelo'] = $model;
        }

        if ($service === 'openrouter') {
            if ((string) $config['modelo'] === '') {
                $this->json(['ok' => false, 'error' => 'Configure o indique un modelo para OpenRouter.'], 422);
            }
            if ((string) $config['api_key'] === '') {
                $this->json(['ok' => false, 'error' => 'Configure la API key de OpenRouter en Ajustes.'], 422);
            }
        }

        $systemPrompt = 'Eres un asistente de prueba de integracion. Responde de forma breve y clara.';

        $start = microtime(true);
        try {
            $response = $ai->service($service)->generate($systemPrompt, $message, $config, []);
        } catch (\Throwable $e) {
            $this->json(['ok' => false, 'error' => 'Excepcion: ' . $e->getMessage()]);
        }

        $elapsed = round(microtime(true) - $start, 2);

        $this->json([
            'ok'       => (bool) $response['ok'],
            'text'     => (string) ($response['text'] ?? ''),
            'error'    => $response['error'] ?? null,
            'tokens'   => $response['tokens'] ?? null,
            'servicio' => $service,
            'modelo'   => (string) $config['modelo'],
            'segundos' => $elapsed,
        ]);
    }

    private function mask(string $key): string
    {
        if ($key === '') {
            return '';
        }
        $length = strlen($key);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }
        return substr($key, 0, 4) . str_repeat('*', max(4, $length - 8)) . substr($key, -4);
    }
}
