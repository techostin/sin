<#
.SYNOPSIS
  Sobe a API e o Metro para testar o app SINPORG no celular via Expo Go.

.DESCRIPTION
  Verifica os pre-requisitos, mostra o IP que o celular vai usar e inicia os
  dois servidores. Deixe esta janela aberta: ela e o servidor de desenvolvimento.

.EXAMPLE
  .\scripts\testar-no-celular.ps1
#>
$ErrorActionPreference = "Stop"
$raiz = Split-Path -Parent $PSScriptRoot
$api = Join-Path $raiz "sinporg\apps\api"
$mobile = Join-Path $raiz "sinporg\apps\mobile"

Write-Host "=== 1/4 Verificando o MySQL ===" -ForegroundColor Cyan
$mysql = Get-Process -Name "mysqld" -ErrorAction SilentlyContinue
if (-not $mysql) { Write-Error "O MySQL nao esta rodando. Abra o XAMPP e inicie o MySQL." }
Write-Host "   MySQL ativo" -ForegroundColor Green

Write-Host "=== 2/4 Descobrindo o IP da rede local ===" -ForegroundColor Cyan
$ip = $null
try {
    $ip = [System.Net.Dns]::GetHostAddresses([System.Net.Dns]::GetHostName()) |
          Where-Object { $_.AddressFamily -eq "InterNetwork" } |
          Select-Object -First 1 -ExpandProperty IPAddressToString
} catch { }
if (-not $ip) {
    Write-Warning "Nao consegui detectar o IP. Rode ipconfig e veja o IPv4 do adaptador Wi-Fi."
} else {
    Write-Host "   IP da maquina: $ip" -ForegroundColor Green
}

Write-Host "=== 3/4 Conferindo o build da API ===" -ForegroundColor Cyan
if (-not (Test-Path (Join-Path $api "dist\main.js"))) {
    Write-Host "   dist ausente; compilando..." -ForegroundColor Yellow
    Push-Location (Join-Path $raiz "sinporg")
    pnpm build
    Pop-Location
}
Write-Host "   build OK" -ForegroundColor Green

Write-Host "=== 4/4 Subindo os servidores ===" -ForegroundColor Cyan
Write-Host ""
Write-Host "  AVISO: o Windows pode pedir para liberar o Node no firewall." -ForegroundColor Yellow
Write-Host "  Marque as redes PRIVADAS e clique em Permitir acesso." -ForegroundColor Yellow
Write-Host ""
Write-Host "  No celular:" -ForegroundColor White
Write-Host "    1. Instale o app Expo Go (Play Store / App Store)" -ForegroundColor White
Write-Host "    2. Conecte o celular no MESMO Wi-Fi desta maquina" -ForegroundColor White
Write-Host "    3. Aponte a camera para o QR code que aparece abaixo" -ForegroundColor White
Write-Host ""
if ($ip) { Write-Host "  API que o app vai usar: http://$($ip):3001/api/v2" -ForegroundColor Green }
Write-Host ""

$apiProc = Start-Process -FilePath "node" -ArgumentList "dist/main.js" -WorkingDirectory $api -PassThru -WindowStyle Minimized
Start-Sleep -Seconds 4
Write-Host "  API iniciada (pid $($apiProc.Id))" -ForegroundColor Green

Push-Location $mobile
Write-Host ""
Write-Host "  Iniciando o Metro (o QR code aparece em seguida)..." -ForegroundColor Cyan
try {
    npx expo start
} finally {
    Pop-Location
    if ($apiProc -and -not $apiProc.HasExited) {
        Stop-Process -Id $apiProc.Id -Force -ErrorAction SilentlyContinue
        Write-Host "  API encerrada." -ForegroundColor Yellow
    }
}
