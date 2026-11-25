param(
    [string]$BaseUrl = "http://localhost/app"
)

$ErrorActionPreference = "Continue"
$root = Split-Path -Parent $PSScriptRoot
Set-Location $root

Write-Host "== composer test =="
& composer test --no-interaction 2>&1 | Out-Host
if ($LASTEXITCODE -ne 0) { throw "Testes falharam." }

Write-Host "== composer analyse =="
& composer analyse --no-interaction 2>&1 | Out-Host
if ($LASTEXITCODE -ne 0) { throw "PHPStan falhou." }

Write-Host "== composer deptrac =="
& composer deptrac --no-interaction 2>&1 | Out-Host
if ($LASTEXITCODE -ne 0) { throw "Deptrac falhou." }

Write-Host "== composer lint =="
& composer lint --no-interaction 2>&1 | Out-Host
if ($LASTEXITCODE -ne 0) { throw "PHPCS falhou." }

Write-Host "== health =="
$health = Invoke-RestMethod -Uri "$BaseUrl/api/v1/health" -TimeoutSec 20 -ErrorAction Stop
if ($health.data.status -ne "ok") { throw "Health degradado: $($health.data.status)" }

Write-Host "== K2 estados =="
$estados = Invoke-RestMethod -Uri "$BaseUrl/api/v1/territorios/estados" -TimeoutSec 20 -ErrorAction Stop
if ([int]$estados.meta.count -lt 1) { throw "K2 sem estados." }

Write-Host "== legado =="
$login = Invoke-WebRequest -Uri "$BaseUrl/login.php" -UseBasicParsing -TimeoutSec 20 -ErrorAction Stop
if ($login.StatusCode -ne 200) { throw "Login legado nao respondeu 200." }

Write-Host "F1 CHECK OK: testes, qualidade, health, K2 e legado."
