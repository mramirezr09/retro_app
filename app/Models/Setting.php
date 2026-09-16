<?php

namespace App\Models;

use App\Core\Model;

class Setting extends Model
{
    protected string $table = 'settings';

    public function byService(string $service): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM settings WHERE servicio = :s LIMIT 1');
        $stmt->execute(['s' => $service]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function allServices(): array
    {
        return $this->all('id ASC');
    }

    public function saveService(string $service, array $data): void
    {
        $existing = $this->byService($service);
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($existing) {
            $this->update((int) $existing['id'], $data);
            return;
        }
        $data['servicio'] = $service;
        $this->create($data);
    }
}
