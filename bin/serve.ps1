param(
    [int]$Port = 8000
)

$root = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $root

if (-not (Test-Path -LiteralPath "$root\database\retro.sqlite")) {
    php "$root\database\migrate.php"
}

Write-Host "RetroApp en http://localhost:$Port"
php -S "localhost:$Port" -t "$root\public" "$root\public\router.php"
