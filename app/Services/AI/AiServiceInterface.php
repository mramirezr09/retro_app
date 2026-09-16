<?php

namespace App\Services\AI;

interface AiServiceInterface
{
    /**
     * @param string $systemPrompt  Instruccion / prompt elegido por el usuario.
     * @param string $userMessage   Respuesta del alumno (contenido a evaluar).
     * @param array  $config        Modelo, base_url, api key, ruta del binario.
     * @return array{ok: bool, text: string, tokens: ?int, error: ?string}
     */
    public function generate(string $systemPrompt, string $userMessage, array $config): array;
}
