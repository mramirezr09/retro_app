<?php

namespace App\Models;

use App\Core\Model;

class ExcelRow extends Model
{
    protected string $table = 'excel_rows';

    public function byFile(int $fileId): array
    {
        $stmt = self::db()->prepare('SELECT * FROM excel_rows WHERE excel_file_id = :f ORDER BY numero_fila ASC, id ASC');
        $stmt->execute(['f' => $fileId]);
        return $stmt->fetchAll();
    }

    public function findInFile(int $id, int $fileId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM excel_rows WHERE id = :id AND excel_file_id = :f LIMIT 1');
        $stmt->execute(['id' => $id, 'f' => $fileId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function data(array $row): array
    {
        $data = json_decode($row['datos_json'] ?? '{}', true);
        return is_array($data) ? $data : [];
    }

    public function markSending(int $id, int $promptId, string $service, string $model): void
    {
        $this->update($id, [
            'estado'    => 'enviando',
            'prompt_id' => $promptId,
            'servicio'  => $service,
            'modelo'    => $model,
            'error'     => null,
        ]);
    }

    public function markSent(int $id, string $feedback, ?int $tokens, string $service = '', string $model = '', ?int $intentos = null, bool $fallback = false): void
    {
        $data = [
            'retroalimentacion' => $feedback,
            'estado'            => 'enviado',
            'tokens'            => $tokens,
            'error'             => null,
            'procesado_en'      => date('Y-m-d H:i:s'),
        ];
        if ($service !== '') {
            $data['servicio'] = $service;
        }
        if ($model !== '') {
            $data['modelo'] = $model;
        }
        $data['intentos'] = $intentos;
        $data['usado_fallback'] = $fallback ? 1 : 0;
        $this->update($id, $data);
    }

    public function markError(int $id, string $error, ?int $intentos = null): void
    {
        $this->update($id, [
            'estado'       => 'error',
            'error'        => $error,
            'intentos'     => $intentos,
            'procesado_en' => date('Y-m-d H:i:s'),
        ]);
    }
}
