<?php

namespace App\Services\AI;

use App\Core\Env;
use App\Models\Setting;

class AiManager
{
    public const SERVICES = [
        'openrouter' => 'OpenRouter (API HTTP)',
        'opencode'   => 'opencode (CLI local)',
    ];

    public function configFor(string $service): array
    {
        $settings = (new Setting())->byService($service) ?? [];
        $apiKeyEnv = (string) ($settings['api_key_env'] ?? '');

        return [
            'servicio'    => $service,
            'modelo'      => (string) ($settings['modelo'] ?? ''),
            'base_url'    => (string) ($settings['base_url'] ?? ''),
            'binary'      => (string) ($settings['opencode_path'] ?? ''),
            'api_key_env' => $apiKeyEnv,
            'api_key'     => $apiKeyEnv !== '' ? (string) Env::get($apiKeyEnv, '') : '',
        ];
    }

    public function service(string $service): AiServiceInterface
    {
        return match ($service) {
            'openrouter' => new OpenRouterService(),
            'opencode'   => new OpencodeService(),
            default      => throw new \InvalidArgumentException('Servicio de IA no soportado: ' . $service),
        };
    }

    public function generate(string $service, string $systemPrompt, string $userMessage, array $attachments = []): array
    {
        $config = $this->configFor($service);
        return $this->service($service)->generate($systemPrompt, $userMessage, $config, $attachments);
    }
}
