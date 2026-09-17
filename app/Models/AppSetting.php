<?php

namespace App\Models;

use App\Core\Model;

class AppSetting extends Model
{
    protected string $table = 'app_settings';

    public function pairs(): array
    {
        $rows = self::db()->query('SELECT clave, valor FROM app_settings')->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['clave']] = (string) $row['valor'];
        }
        return $out;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        $stmt = self::db()->prepare('SELECT valor FROM app_settings WHERE clave = :c LIMIT 1');
        $stmt->execute(['c' => $key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    public function getInt(string $key, int $default): int
    {
        $value = $this->get($key);
        if ($value === null || !is_numeric($value)) {
            return $default;
        }
        return (int) $value;
    }

    public function set(string $key, string $value): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = self::db()->prepare(
            'INSERT INTO app_settings (clave, valor, updated_at) VALUES (:c, :v, :u)
             ON CONFLICT(clave) DO UPDATE SET valor = :v, updated_at = :u'
        );
        $stmt->execute(['c' => $key, 'v' => $value, 'u' => $now]);
    }
}
