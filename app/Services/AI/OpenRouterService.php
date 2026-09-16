<?php

namespace App\Services\AI;

class OpenRouterService implements AiServiceInterface
{
    public function generate(string $systemPrompt, string $userMessage, array $config): array
    {
        $apiKey = (string) ($config['api_key'] ?? '');
        if ($apiKey === '') {
            return ['ok' => false, 'text' => '', 'tokens' => null, 'error' => 'No hay API key configurada para OpenRouter.'];
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? 'https://openrouter.ai/api/v1'), '/');
        $model = (string) ($config['modelo'] ?? '');
        if ($model === '') {
            return ['ok' => false, 'text' => '', 'tokens' => null, 'error' => 'No hay modelo configurado para OpenRouter.'];
        }

        $payload = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userMessage],
            ],
        ];

        $ch = curl_init($baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
                'HTTP-Referer: ' . $this->appUrl(),
                'X-OpenRouter-Title: RetroApp',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_NOSIGNAL       => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'text' => '', 'tokens' => null, 'error' => 'Error de conexion: ' . $curlError];
        }

        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'text' => '', 'tokens' => null, 'error' => 'Respuesta invalida del servicio (HTTP ' . $httpCode . ').'];
        }

        if ($httpCode >= 400 || isset($decoded['error'])) {
            $message = $decoded['error']['message'] ?? ('HTTP ' . $httpCode);
            return ['ok' => false, 'text' => '', 'tokens' => null, 'error' => 'OpenRouter: ' . $message];
        }

        $text = $decoded['choices'][0]['message']['content'] ?? '';
        if (!is_string($text) || trim($text) === '') {
            return ['ok' => false, 'text' => '', 'tokens' => null, 'error' => 'El modelo no devolvio contenido.'];
        }

        $tokens = isset($decoded['usage']['total_tokens']) ? (int) $decoded['usage']['total_tokens'] : null;

        return ['ok' => true, 'text' => trim($text), 'tokens' => $tokens, 'error' => null];
    }

    private function appUrl(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $host;
    }
}
