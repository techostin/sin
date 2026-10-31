# Hardening do php.ini do XAMPP — item 4 das ações imediatas (P0)
# Uso: .\hardening_php_ini.ps1 -Path C:\caminho\php.ini [-DryRun]
param(
    [Parameter(Mandatory=$true)][string]$Path,
    [switch]$DryRun
)

if (-not (Test-Path $Path)) { throw "php.ini nao encontrado: $Path" }

$lines = [System.IO.File]::ReadAllLines($Path)

# Chave => valor alvo. Apenas diretivas seguras para a aplicacao (auditadas).
$map = [ordered]@{
    'display_errors'                    = 'Off'
    'display_startup_errors'            = 'Off'
    'expose_php'                        = 'Off'
    'log_errors'                        = 'On'
    'zend.exception_ignore_args'        = 'On'
    'post_max_size'                     = '32M'
    'upload_max_filesize'               = '30M'
    'allow_url_include'                 = 'Off'
    'disable_functions'                 = 'exec,shell_exec,system,passthru,popen,dl'
    'session.upload_progress.enabled'   = '0'
    'session.use_strict_mode'           = '1'
    'session.use_only_cookies'          = '1'
    'session.use_trans_sid'             = '0'
    'session.cookie_httponly'           = '1'
    'session.cookie_samesite'           = 'Lax'
    'session.cookie_secure'             = '0'
    'cgi.fix_pathinfo'                  = '0'
    'phar.readonly'                     = 'On'
}

$changes = New-Object System.Collections.Generic.List[string]

foreach ($key in $map.Keys) {
    $value = [string]$map[$key]
    $pattern = '^\s*;?\s*' + [regex]::Escape($key) + '\s*='
    $found = $false
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match $pattern) {
            $found = $true
            $newLine = "$key=$value"
            if ($lines[$i] -ne $newLine) {
                $changes.Add(("linha {0}: {1}  ->  {2}" -f ($i + 1), $lines[$i].Trim(), $newLine))
                $lines[$i] = $newLine
            }
        }
    }
    if (-not $found) {
        $lines += "$key=$value"
        $changes.Add(("append: {0}" -f "$key=$value"))
    }
}

if ($changes.Count -eq 0) {
    Write-Host "Nenhuma alteracao necessaria."
    exit 0
}

if ($DryRun) {
    Write-Host "=== DRY RUN (nada gravado) ==="
    $changes | ForEach-Object { Write-Host $_ }
    exit 0
}

$enc = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllLines($Path, $lines, $enc)

Write-Host "=== ALTERACOES APLICADAS ==="
$changes | ForEach-Object { Write-Host $_ }
Write-Host ("Total de alteracoes: {0}" -f $changes.Count)
