<?php

namespace App\Services\AI;

use App\Models\AppSetting;

class ProcessingOptions
{
    public const DEFAULTS = [
        'delay'        => 10,
        'retry'        => 3,
        'intentos'     => 2,
        'min_palabras' => 50,
    ];

    public static function load(): array
    {
        $settings = new AppSetting();

        return [
            'delay'        => max(0, $settings->getInt('ai_delay_segundos', self::DEFAULTS['delay'])),
            'retry'        => max(0, $settings->getInt('ai_retry_segundos', self::DEFAULTS['retry'])),
            'intentos'     => max(1, $settings->getInt('ai_intentos', self::DEFAULTS['intentos'])),
            'min_palabras' => max(0, $settings->getInt('ai_min_palabras', self::DEFAULTS['min_palabras'])),
        ];
    }

    public static function save(array $values): void
    {
        $settings = new AppSetting();
        $settings->set('ai_delay_segundos', (string) self::clamp($values['delay'] ?? null, 'delay'));
        $settings->set('ai_retry_segundos', (string) self::clamp($values['retry'] ?? null, 'retry'));
        $settings->set('ai_intentos', (string) self::clamp($values['intentos'] ?? null, 'intentos'));
        $settings->set('ai_min_palabras', (string) self::clamp($values['min_palabras'] ?? null, 'min_palabras'));
    }

    public static function words(string $text): int
    {
        $count = preg_match_all('/\S+/u', $text);
        return $count === false ? 0 : $count;
    }

    public static function isValid($ok, string $text, int $minWords): bool
    {
        return (bool) $ok && self::words($text) >= $minWords;
    }

    private static function clamp($value, string $key): int
    {
        $default = self::DEFAULTS[$key];
        if ($value === null || !is_numeric($value)) {
            return $default;
        }
        $value = (int) $value;
        if ($key === 'intentos') {
            return max(1, $value);
        }
        return max(0, $value);
    }
}
