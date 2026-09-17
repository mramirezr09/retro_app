<?php

namespace App\Models;

use App\Core\Model;

class Script extends Model
{
    protected string $table = 'scripts';

    public function activeAll(): array
    {
        return self::db()
            ->query('SELECT * FROM scripts WHERE deleted_at IS NULL ORDER BY id DESC')
            ->fetchAll();
    }

    public function findActive(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM scripts WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function softDelete(int $id): bool
    {
        $stmt = self::db()->prepare('UPDATE scripts SET deleted_at = datetime(\'now\') WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function seedFromFile(string $path): void
    {
        $count = (int) self::db()->query('SELECT COUNT(*) FROM scripts WHERE deleted_at IS NULL')->fetchColumn();
        if ($count > 0 || !is_file($path)) {
            return;
        }

        $contenido = (string) file_get_contents($path);
        if (trim($contenido) === '') {
            return;
        }

        $this->create([
            'nombre'      => 'extraer_foro.js',
            'descripcion' => 'Extrae respuestas de alumnos desde una discusion de foro de Moodle. Pegar en la consola (F12) de la plataforma.',
            'contenido'   => $contenido,
        ]);
    }
}
