<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Env;
use App\Models\Setting;
use App\Services\AI\AiManager;

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
            'title'    => 'Ajustes',
            'services' => $services,
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

        try {
            Env::save();
        } catch (\Throwable $e) {
            $this->flash('error', 'No se pudieron guardar las API keys: ' . $e->getMessage());
            $this->redirect(url('/settings'));
        }

        $this->flash('success', 'Ajustes guardados correctamente.');
        $this->redirect(url('/settings'));
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
