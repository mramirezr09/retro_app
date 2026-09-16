<?php

namespace App\Models;

use App\Core\Model;

class Prompt extends Model
{
    protected string $table = 'prompts';

    public function activeAll(): array
    {
        return self::db()
            ->query('SELECT * FROM prompts WHERE deleted_at IS NULL ORDER BY id DESC')
            ->fetchAll();
    }

    public function findActive(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM prompts WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function softDelete(int $id): bool
    {
        $stmt = self::db()->prepare('UPDATE prompts SET deleted_at = datetime(\'now\') WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }
}
