#!/usr/bin/env bash
#
# RetroApp - instalacion y despliegue en Apache (Debian/Ubuntu)
# Uso: sudo bash deploy/apache/install.sh
#
# Variables opcionales:
#   APP_PATH=/var/www/retro_app   Ruta del proyecto
#   PORT=8083                     Puerto de escucha
#
set -euo pipefail

APP_PATH="${APP_PATH:-/var/www/retro_app}"
PORT="${PORT:-8083}"
SITE_NAME="retro_app"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

log()  { printf '[retro_app] %s\n' "$*"; }
warn() { printf '[retro_app][AVISO] %s\n' "$*" >&2; }
fail() { printf '[retro_app][ERROR] %s\n' "$*" >&2; exit 1; }

if [ "${EUID:-$(id -u)}" -ne 0 ]; then
    fail "Ejecute este script con sudo: sudo bash $0"
fi

[ -d "$APP_PATH" ] || fail "No existe el directorio $APP_PATH"
[ -f "$APP_PATH/public/index.php" ] || fail "$APP_PATH no parece ser el proyecto retro_app"
command -v apt-get >/dev/null 2>&1 || fail "Este script asume Debian/Ubuntu (apt-get)"

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || true)"
[ -n "$PHP_VER" ] || fail "No se encontro 'php' en PATH"
log "PHP detectado: $PHP_VER"

log "Instalando Apache, mod_php y extensiones (best effort)..."
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq || warn "apt-get update fallo; se continua"

PACKAGES=(apache2)
for ext in curl sqlite3 zip xml mbstring; do
    if ! php -m 2>/dev/null | grep -qi "^${ext}$"; then
        PACKAGES+=("php${PHP_VER}-${ext}")
    fi
done
if [ ! -e "/usr/lib/apache2/modules/libphp${PHP_VER}.so" ] && ! ls /usr/lib/apache2/modules/libphp*.so >/dev/null 2>&1; then
    PACKAGES+=("libapache2-mod-php${PHP_VER}")
fi
apt-get install -y "${PACKAGES[@]}" || warn "Algunos paquetes no se instalaron; revise las extensiones de PHP manualmente."

if ! ls /usr/lib/apache2/modules/libphp*.so >/dev/null 2>&1; then
    fail "No se encontro mod_php (libphp*.so). Instale libapache2-mod-php${PHP_VER} o migre a php-fpm ajustando el vhost."
fi

log "Habilitando modulos de Apache (rewrite, headers)..."
a2enmod rewrite headers >/dev/null

log "Configurando escucha en el puerto ${PORT}..."
if ! grep -qE "^[[:space:]]*Listen[[:space:]]+${PORT}([[:space:]]|$)" /etc/apache2/ports.conf; then
    printf '\nListen %s\n' "$PORT" >> /etc/apache2/ports.conf
fi

log "Instalando VirtualHost..."
cp "$SCRIPT_DIR/retro_app.conf" "/etc/apache2/sites-available/${SITE_NAME}.conf"
a2ensite "$SITE_NAME" >/dev/null

PHP_CONF_DIR="/etc/php/${PHP_VER}/apache2/conf.d"
if [ -d "$PHP_CONF_DIR" ]; then
    cp "$SCRIPT_DIR/php-custom.ini" "${PHP_CONF_DIR}/99-retro_app.ini"
    log "Overrides de PHP copiados a ${PHP_CONF_DIR}/99-retro_app.ini"
else
    warn "No existe ${PHP_CONF_DIR}; los limites de subida quedan definidos en el VirtualHost."
fi

log "Ajustando permisos de datos (storage, database, .env)..."
mkdir -p "$APP_PATH/storage/uploads" "$APP_PATH/storage/exports" "$APP_PATH/storage/opencode"
chown -R www-data:www-data "$APP_PATH/storage" "$APP_PATH/database"
if [ -f "$APP_PATH/.env" ]; then
    chown www-data:www-data "$APP_PATH/.env"
    chmod 600 "$APP_PATH/.env"
fi

if [ ! -f "$APP_PATH/database/retro.sqlite" ]; then
    log "Inicializando base de datos SQLite..."
    if command -v sudo >/dev/null 2>&1; then
        (cd "$APP_PATH" && sudo -u www-data php database/migrate.php)
    else
        (cd "$APP_PATH" && runuser -u www-data -- php database/migrate.php)
    fi
fi

log "Validando configuracion de Apache..."
apache2ctl configtest

log "Recargando Apache..."
systemctl reload apache2 2>/dev/null || systemctl restart apache2

if command -v aa-status >/dev/null 2>&1; then
    log "AppArmor esta activo. Si opencode no puede ejecutarse bajo Apache, revise deploy/apache/opencode-apparmor.conf"
fi

cat <<EOF

[retro_app] Despliegue base completado.

Siguientes pasos:
  1) Cree/edite $APP_PATH/.env (APP_DEBUG=false y las API keys).
  2) Entre a http://<IP>:${PORT}/ y configure modelo y keys en "Ajustes".
  3) Para el servicio opencode:
       curl -fsSL https://opencode.ai/install | bash
       # o bien: npm install -g opencode-ai
       sudo -u www-data HOME=/var/www opencode auth login
     Luego fije la ruta absoluta del binario en "Ajustes".
  4) Revise logs: /var/log/apache2/retro_app_error.log
EOF
