CREATE TABLE IF NOT EXISTS settings (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    servicio      TEXT NOT NULL UNIQUE,
    modelo        TEXT NOT NULL DEFAULT '',
    base_url      TEXT NOT NULL DEFAULT '',
    opencode_path TEXT NOT NULL DEFAULT '',
    api_key_env   TEXT NOT NULL DEFAULT '',
    updated_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS prompts (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre      TEXT NOT NULL,
    materia     TEXT NOT NULL DEFAULT '',
    contenido   TEXT NOT NULL,
    deleted_at  TEXT DEFAULT NULL,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS excel_files (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_original   TEXT NOT NULL,
    ruta              TEXT NOT NULL,
    columnas_json     TEXT NOT NULL DEFAULT '[]',
    respuesta_columna TEXT NOT NULL DEFAULT '',
    total_registros   INTEGER NOT NULL DEFAULT 0,
    deleted_at        TEXT DEFAULT NULL,
    created_at        TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS excel_rows (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    excel_file_id      INTEGER NOT NULL,
    numero_fila        INTEGER NOT NULL DEFAULT 0,
    datos_json         TEXT NOT NULL DEFAULT '{}',
    respuesta_texto    TEXT NOT NULL DEFAULT '',
    retroalimentacion  TEXT DEFAULT NULL,
    estado             TEXT NOT NULL DEFAULT 'pendiente',
    prompt_id          INTEGER DEFAULT NULL,
    servicio           TEXT NOT NULL DEFAULT '',
    modelo             TEXT NOT NULL DEFAULT '',
    tokens             INTEGER DEFAULT NULL,
    error              TEXT DEFAULT NULL,
    procesado_en       TEXT DEFAULT NULL,
    created_at         TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (excel_file_id) REFERENCES excel_files (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_excel_rows_file ON excel_rows (excel_file_id);
CREATE INDEX IF NOT EXISTS idx_excel_rows_estado ON excel_rows (estado);
CREATE INDEX IF NOT EXISTS idx_prompts_deleted ON prompts (deleted_at);

INSERT OR IGNORE INTO settings (servicio, modelo, base_url, opencode_path, api_key_env)
VALUES ('openrouter', 'openai/gpt-4o-mini', 'https://openrouter.ai/api/v1', '', 'OPENROUTER_API_KEY');

INSERT OR IGNORE INTO settings (servicio, modelo, base_url, opencode_path, api_key_env)
VALUES ('opencode', '', '', '', 'OPENCODE_API_KEY');
