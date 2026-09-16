# RetroApp - preparacion del repositorio para GitHub (Windows)
# Uso:  powershell -ExecutionPolicy Bypass -File deploy\prepare-repo.ps1
#
# No hace commit ni push: deja todo preparado para que usted lo revise.

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $root

if (-not (Get-Command git -ErrorAction SilentlyContinue)) {
    throw "git no esta en PATH"
}

if (-not (Test-Path -LiteralPath ".git")) {
    Write-Host "Inicializando repositorio git..."
    git init | Out-Null
    git branch -M main | Out-Null
}

Write-Host "Agregando archivos..."
git add -A

Write-Host "Marcando scripts .sh como ejecutables..."
git update-index --chmod=+x "bin/serve.sh"
git update-index --chmod=+x "deploy/apache/install.sh"

Write-Host "Normalizando finales de linea (.gitattributes)..."
git add --renormalize .

Write-Host ""
Write-Host "Verificando que no haya secretos o datos de alumnos preparados..."
$staged = git diff --cached --name-only
$problems = @()

if ($staged -contains ".env") {
    $problems += ".env (API keys)"
}
if ($staged | Where-Object { $_ -like "database/*.sqlite*" }) {
    $problems += "archivos de base de datos SQLite"
}
if ($staged | Where-Object { $_ -like "storage/uploads/*" -and $_ -notlike "*/.gitkeep" }) {
    $problems += "archivos subidos en storage/uploads"
}
if ($staged | Where-Object { $_ -like "storage/exports/*" -and $_ -notlike "*/.gitkeep" }) {
    $problems += "exportaciones en storage/exports"
}

if ($problems.Count -gt 0) {
    Write-Host "ADVERTENCIA: hay elementos que NO deberian subirse:" -ForegroundColor Red
    $problems | ForEach-Object { Write-Host ("  - " + $_) -ForegroundColor Red }
    Write-Host "Revise .gitignore o ejecute: git rm --cached <archivo>" -ForegroundColor Yellow
} else {
    Write-Host "OK: no se detectaron .env, base de datos ni subidas de alumnos." -ForegroundColor Green
}

Write-Host ""
Write-Host "Archivos preparados (git status):"
git status --short

Write-Host ""
Write-Host "Siguientes comandos (revise y ejecute cuando este conforme):"
Write-Host "  git commit -m `"RetroApp: app MVC PHP + despliegue Apache`""
Write-Host "  git remote add origin https://github.com/<usuario>/<repo>.git"
Write-Host "  git push -u origin main"
