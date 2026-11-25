<#
.SYNOPSIS
  Atualiza a chave da Resend em config/secrets.php, validando antes.

.DESCRIPTION
  A rotacao em si acontece no painel da Resend. Este script valida a chave nova
  (HTTP 422 no endpoint de e-mail com payload vazio = autenticacao OK) e grava
  apenas em config/secrets.php (nao versionado). Nao grava em arquivos do Git.

.EXAMPLE
  .\scripts\atualizar-chave-resend.ps1 -NovaChave "re_xxxxxxxx"
#>
param(
    [Parameter(Mandatory = $true)][string]$NovaChave,
    [string]$ChaveAntiga = $env:RESEND_CHAVE_ANTIGA
)

$ErrorActionPreference = "Stop"
$raiz = Split-Path -Parent $PSScriptRoot
$secretsFile = Join-Path $raiz "config\secrets.php"

if ($NovaChave -notmatch '^re_[A-Za-z0-9_]+$') {
    throw "Formato de chave invalido. Esperado algo como re_xxxxxxxx."
}
if ($ChaveAntiga -and $NovaChave -eq $ChaveAntiga) {
    throw "A chave informada e a mesma que esta comprometida. Gere uma NOVA no painel."
}

Write-Host "1/3 Validando a chave nova na API da Resend..."
$status = $null
try {
    $resp = Invoke-WebRequest -Uri 'https://api.resend.com/emails' -Method Post `
        -Headers @{ Authorization = "Bearer $NovaChave" } `
        -ContentType 'application/json' -Body '{}' -UseBasicParsing -TimeoutSec 20
    $status = [int]$resp.StatusCode
} catch {
    if ($_.Exception.Response) { $status = [int]$_.Exception.Response.StatusCode.value__ }
}
Write-Host "   HTTP $status"
if ($status -ne 422 -and -not ($status -ge 200 -and $status -lt 300)) {
    throw "A Resend NAO aprovou a chave nova (HTTP $status). Nada foi gravado."
}

Write-Host "2/3 Atualizando config/secrets.php..."
$secrets = Get-Content $secretsFile -Raw
$secrets = [regex]::Replace($secrets, "RESEND_API_KEY'?\s*,\s*'[^']*'", "RESEND_API_KEY', '$NovaChave'")
Set-Content -Path $secretsFile -Value $secrets -NoNewline

Write-Host "3/3 Conferindo se a chave antiga desapareceu..."
if ($ChaveAntiga) {
    $oc = (Select-String -Path $secretsFile -SimpleMatch $ChaveAntiga -ErrorAction SilentlyContinue | Measure-Object).Count
    if ($oc -gt 0) { Write-Warning "A chave antiga ainda aparece em $oc lugar(es). Revise." }
    else { Write-Host "   OK: a chave antiga nao esta mais no secrets.php." -ForegroundColor Green }
} else {
    Write-Host "   Chave antiga nao informada; pule a conferencia."
}

Write-Host "Concluido. PENDENTE: revogar a chave antiga no painel da Resend."