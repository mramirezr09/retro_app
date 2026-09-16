# RetroApp

Aplicación web en **PHP vanilla (MVC)** para procesar respuestas de alumnos desde un Excel y generar **retroalimentación automática con IA**, guardando el resultado por registro y permitiendo exportarlo a Excel.

- Sin frameworks ni Composer: PHP puro + SQLite.
- Lectura nativa de `.xlsx` (ZipArchive + DOM) y `.csv`.
- Servicios de IA: **OpenRouter** (HTTP/curl) y **opencode** (CLI local).
- Las API keys se guardan en `.env` (nunca en la base de datos ni en git).

---

## Requisitos

- PHP **8.1+** con extensiones: `pdo_sqlite`, `sqlite3`, `curl`, `zip`, `dom`/`xml`, `mbstring`.
- Node.js **solo** si usarás el servicio `opencode` vía npm.
- Para producción: Apache (Debian/Ubuntu) o el servidor embebido de PHP para desarrollo.

Verificar extensiones:
```bash
php -m | grep -Ei 'pdo_sqlite|sqlite3|curl|zip|xml|mbstring'
```

---

## Estructura del proyecto

```
retro_app/
├─ app/
│  ├─ Core/            Núcleo MVC (Router, Database, Model, View, Request, Config, Env)
│  ├─ Controllers/     Dashboard, Prompt, Upload, File, Record, Settings
│  ├─ Models/          Prompt, ExcelFile, ExcelRow, Setting
│  ├─ Services/
│  │  ├─ Excel/        XlsxReader, CsvReader, XlsxWriter (nativos)
│  │  └─ AI/           OpenRouterService, OpencodeService, AiManager
│  └─ Views/           Vistas PHP (layout + vistas por módulo)
├─ config/config.php   Configuración de la app
├─ database/           schema.sql, migrate.php y retro.sqlite (ignorada por git)
├─ deploy/             Scripts de despliegue (Apache, preparación de repo)
├─ public/             ÚNICO docroot: index.php, router.php, assets/
├─ routes/web.php      Definición de rutas
├─ storage/            uploads/, exports/, opencode/ (ignorados por git)
├─ .env.example        Plantilla de variables de entorno
└─ bin/                serve.ps1 / serve.sh (servidor de desarrollo)
```

> El docroot **siempre** debe apuntar a `public/`. Todo lo demás (`app/`, `database/`, `storage/`, `.env`) queda fuera del alcance web.

---

## Base de datos (SQLite)

| Tabla | Contenido |
|---|---|
| `settings` | Configuración por servicio de IA (modelo, base_url, ruta opencode, nombre de la variable de la key) |
| `prompts` | Prompts del usuario (nombre, materia, contenido) con **borrado lógico** (`deleted_at`) |
| `excel_files` | Archivos Excel guardados (columnas conservadas, columna de respuesta) |
| `excel_rows` | Registros de cada archivo (datos, retroalimentación, estado, tokens, error) |

Inicializar / migrar:
```bash
php database/migrate.php
```

---

## Correr en desarrollo

```bash
# Windows
powershell -ExecutionPolicy Bypass -File bin\serve.ps1

# Linux/macOS
bash bin/serve.sh
```
Luego abrir: http://localhost:8000

También se puede lanzar manualmente:
```bash
php -S localhost:8000 -t public public/router.php
```

---

## Configuración

1. Copiar la plantilla:
   ```bash
   cp .env.example .env
   ```
2. Ir a **Ajustes** en la app y guardar modelo y API key de cada servicio (la vista escribe el `.env`), o editar el `.env` directamente:
   ```
   APP_DEBUG=false
   OPENROUTER_API_KEY=sk-or-v1-...
   OPENCODE_API_KEY=
   ```

`APP_DEBUG`: en local puede ser `true`; en producción debe ser `false`.

---

## Flujo de uso

1. **Prompts** → crear/editar un prompt y su materia.
2. **Subir Excel** → elegir archivo `.xlsx`/`.csv`, previsualizar, marcar las **columnas a conservar** y elegir la **columna con la respuesta del alumno**.
3. **Archivos** → ver los registros del archivo, seleccionar uno o varios.
4. Elegir **prompt**, **servicio** (`OpenRouter` u `opencode`) y opcionalmente el **modelo**, y pulsar **Enviar seleccionados**.
5. La **retroalimentación** aparece en la misma tabla; el estado cambia a `enviado` (o `error`).
6. **Descargar XLSX** exporta las columnas originales + retroalimentación + metadatos.

### Cómo se arma la petición a la IA
- **OpenRouter**: `system` = prompt del usuario; `user` = respuesta del alumno.
- **opencode**: se concatena `prompt + "\n\n" + respuesta del alumno` y se envía al CLI.

---

## Despliegue en Ubuntu con Apache (puerto 8083)

Diseñado para red interna, sin HTTPS, con `mod_php`.

### 1. Clonar en `/var/www/retro_app` (no dentro de `/var/www/html`)
```bash
cd /var/www
sudo git clone https://github.com/<usuario>/<repo>.git /var/www/retro_app
sudo chown -R "$USER":"$USER" /var/www/retro_app
```

> El vhost y el instalador asumen la ruta `/var/www/retro_app`. Si tu repo tiene otro nombre, indica el destino explícito como arriba.

### 2. Crear el `.env`
```bash
cd /var/www/retro_app
cp .env.example .env
nano .env
```
```
APP_DEBUG=false
OPENROUTER_API_KEY=sk-or-v1-...
OPENCODE_API_KEY=
```
El `.env` no viene en git a propósito.

### 3. Ejecutar el instalador
```bash
sudo bash /var/www/retro_app/deploy/apache/install.sh
```
Hace lo siguiente:
- instala Apache, `mod_php` y extensiones (`curl`, `sqlite3`, `zip`, `xml`, `mbstring`);
- habilita `rewrite` y `headers`, agrega `Listen 8083`;
- copia el vhost y `php-custom.ini`, activa el sitio;
- ajusta permisos (`storage/`, `database/`, `.env` → `www-data`, `.env` en `600`);
- migra la base de datos si no existe y recarga Apache.

Variables opcionales:
```bash
sudo APP_PATH=/var/www/retro_app PORT=8083 bash deploy/apache/install.sh
```

### 4. Verificar
```bash
sudo ss -ltnp | grep 8083
curl -I http://127.0.0.1:8083/
```
Desde tu PC: `http://<IP-del-servidor>:8083/`

Si el `curl` local funciona pero desde tu PC no:
```bash
sudo ufw status
sudo ufw allow 8083/tcp
```

### 5. Servicio opencode (opcional)
```bash
# Instalar (elige una opción)
curl -fsSL https://opencode.ai/install | bash
# o bien:
sudo npm install -g opencode-ai

# Autenticar para el usuario de Apache
sudo -u www-data HOME=/var/www opencode auth login

# Probar
sudo -u www-data HOME=/var/www opencode run "test" --format json
```
Luego, en **Ajustes**, fija la **ruta del binario** de opencode si no está en el `PATH` de Apache.

### 6. AppArmor (solo si opencode falla bajo Apache)
Si `opencode` corre por terminal pero la app devuelve error, AppArmor está bloqueando su ejecución desde Apache. Ver `deploy/apache/opencode-apparmor.conf`:
```bash
sudo aa-status
sudo dmesg | grep -i DENIED | tail -20
```

---

## Preparar el repositorio para GitHub (Windows)

```powershell
cd C:\Sitios\retro_app
powershell -ExecutionPolicy Bypass -File deploy\prepare-repo.ps1
```
El script:
- inicializa el repo si no existe;
- marca `+x` en `bin/serve.sh` y `deploy/apache/install.sh`;
- normaliza finales de línea con `.gitattributes`;
- verifica que **no** se suban `.env`, la BD SQLite ni Excel de alumnos.

Luego:
```bash
git commit -m "RetroApp: app MVC PHP + despliegue Apache"
git remote add origin https://github.com/<usuario>/<repo>.git
git push -u origin main
```

Actualizar en el servidor:
```bash
cd /var/www/retro_app
git pull
```

---

## Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| `bash: deploy/apache/install.sh: No existe...` | Ejecutado desde otra carpeta (ruta relativa) | Usar ruta absoluta: `sudo bash /var/www/retro_app/deploy/apache/install.sh` |
| No se puede conectar a `:8083` | El instalador no se ejecutó / Apache no escucha | Revisar `sudo ss -ltnp \| grep 8083` y `/etc/apache2/ports.conf` |
| Funciona local pero no desde tu PC | Firewall | `sudo ufw allow 8083/tcp` |
| `403 Forbidden` | Permisos de archivos | `sudo chown -R www-data:www-data storage database` |
| Apache descarga `index.php` | Falta `mod_php` | `sudo apt install libapache2-mod-php<VER>` o migrar a php-fpm |
| `Maximum execution time exceeded` en la IA | Límite de PHP | Ya se aplica `set_time_limit(0)`; verificar `php-custom.ini` |
| No se guardan las API keys | `.env` sin permisos de escritura | `sudo chown www-data:www-data .env && sudo chmod 600 .env` |

Logs de Apache:
```bash
sudo tail -f /var/log/apache2/retro_app_error.log
```

---

## Seguridad

- `.env`, `database/`, `storage/` están **fuera** del docroot y en `.gitignore`.
- El docroot siempre es `public/`.
- `public/.htaccess` redirige todo al front controller y bloquea `router.php` y archivos ocultos.
- `APP_DEBUG=false` en producción para no mostrar trazas.
- Rotar cualquier API key que se haya expuesto.
