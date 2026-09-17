<?php

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $path = Config::get('paths.database');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        try {
            $pdo = new PDO('sqlite:' . $path);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA journal_mode = WAL');
        } catch (PDOException $e) {
            throw new PDOException('No se pudo abrir la base de datos: ' . $e->getMessage());
        }

        self::$pdo = $pdo;
        return self::$pdo;
    }

    public static function migrate(): void
    {
        $schema = Config::get('paths.schema');
        if (!is_file($schema)) {
            throw new \RuntimeException('No se encontro el esquema en ' . $schema);
        }
        self::connection()->exec((string) file_get_contents($schema));

        self::ensureColumn('excel_files', 'imagenes_columna', "TEXT NOT NULL DEFAULT ''");
        self::ensureColumn('excel_files', 'documentos_columna', "TEXT NOT NULL DEFAULT ''");
        self::ensureColumn('excel_rows', 'intentos', 'INTEGER DEFAULT NULL');
        self::ensureColumn('excel_rows', 'usado_fallback', 'INTEGER NOT NULL DEFAULT 0');
    }

    private static function ensureColumn(string $table, string $column, string $definition): void
    {
        $pdo = self::connection();
        $columns = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
        foreach ($columns as $existing) {
            if (($existing['name'] ?? '') === $column) {
                return;
            }
        }
        $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
    }
}
