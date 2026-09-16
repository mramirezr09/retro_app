<?php

namespace App\Models;

use App\Core\Model;

class ExcelFile extends Model
{
    protected string $table = 'excel_files';

    public function activeAll(): array
    {
        $sql = 'SELECT f.*,
                    (SELECT COUNT(*) FROM excel_rows r WHERE r.excel_file_id = f.id) AS total_filas,
                    (SELECT COUNT(*) FROM excel_rows r WHERE r.excel_file_id = f.id AND r.estado = \'enviado\') AS total_enviados
                FROM excel_files f
                WHERE f.deleted_at IS NULL
                ORDER BY f.id DESC';
        return self::db()->query($sql)->fetchAll();
    }

    public function findActive(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM excel_files WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function softDelete(int $id): bool
    {
        $stmt = self::db()->prepare('UPDATE excel_files SET deleted_at = datetime(\'now\') WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    public function columns(array $file): array
    {
        $cols = json_decode($file['columnas_json'] ?? '[]', true);
        return is_array($cols) ? $cols : [];
    }
}
