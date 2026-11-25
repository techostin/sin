#Requires -Version 5.1
<#
.SYNOPSIS
    Atualiza o PHP do XAMPP (ex.: 8.2.12) para 8.4.27 no Windows x64, mantendo Apache,
    MariaDB, phpMyAdmin e as configuracoes do XAMPP.

.DESCRIPTION
    O XAMPP NAO TEM comando de atualizacao de PHP. Este script faz a troca manual da pasta
    C:\xampp\php de forma segura:

      1. confere pre-requisitos (PowerShell elevado, pasta atual, Apache);
      2. baixa o build oficial Thread Safe / x64 / VS17 do windows.php.net e confere o SHA-256;
      3. clona a pasta atual do PHP (preserva php.ini, extras\, pear\ e arquivos do XAMPP);
      4. sobrepoe os binarios do 8.4.27 na copia;
      5. neutraliza no php.ini copiado as diretivas obsoletas/removidas no PHP 8.4;
      6. para o Apache, troca as pastas e valida (php -v + extensoes do php.ini);
      7. sobe o Apache e testa http://localhost/;
      8. em qualquer erro depois da troca, faz rollback automatico.

.PARAMETER XamppRoot
    Raiz do XAMPP. Padrao: C:\xampp

.PARAMETER ServiceName
    Nome do servico do Apache no Windows. Padrao: Apache2.4

.PARAMETER DryRun
    Apenas mostra o que seria feito (nao baixa, nao para servico, nao altera pastas).

.EXAMPLE
    # Abra o PowerShell COMO ADMINISTRADOR e rode:
    Set-ExecutionPolicy -Scope Process Bypass -Force
    .\tools\atualizar-php-8.4.27.ps1 -DryRun      # simulacao
    .\tools\atualizar-php-8.4.27.ps1              # executa de verdade

.NOTES
    - O arquivo correto para XAMPP/Apache (mod_php) e o Thread Safe:
      php-8.4.27-1-Win32-vs17-x64.zip   (NAO use as variantes "-nts").
    - Exige o runtime "Microsoft Visual C++ 2015-2022 Redistributable (x64)" (VS17).
    - Tambem alinha o libssh2.dll de C:\xampp\apache\bin com o do PHP 8.4 (guardando
      .bak-<versao>). Sem isso o php_curl.dll nao carrega no Apache e o log mostra
      "Unable to load dynamic library 'curl'". O Apache e reiniciado pelo script.
    - O phpMyAdmin 5.2.1 do XAMPP 8.2 nao e homologado para PHP 8.4; se der erro,
      troque C:\xampp\phpMyAdmin pela versao 5.2.3 (a que o XAMPP 8.4.27 traz).
    - Rollback manual: pare o Apache, apague C:\xampp\php e renomeie a pasta
      "php-backup-*" criada pelo script de volta para "php".
#>
[CmdletBinding()]
param(
    [string] $XamppRoot   = 'C:\xampp',
    [string] $Version     = '8.4.27',
    [string] $ServiceName = 'Apache2.4',
    [string] $ZipUrl      = 'https://windows.php.net/downloads/releases/php-8.4.27-1-Win32-vs17-x64.zip',
    [string] $Sha256      = '53db2047139bdfb8e6c7c7d5860a0a6a2a4f1dcac49fcf18ae610a14d8ccdf5b',
    [switch] $DryRun
)

$ErrorActionPreference = 'Stop'
$ProgressPreference    = 'SilentlyContinue'

function Write-Step ([string] $Text) { Write-Host "`n==> $Text" -ForegroundColor Cyan }
function Write-Ok   ([string] $Text) { Write-Host "    OK  $Text" -ForegroundColor Green }
function Write-Info ([string] $Text) { Write-Host "    ..  $Text" -ForegroundColor Gray }
function Write-Note ([string] $Text) { Write-Host "    !!  $Text" -ForegroundColor Yellow }

function Wait-ServiceStatus {
    param([string] $Name, [string] $Status, [int] $TimeoutSeconds = 60)
    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)
    while ((Get-Date) -lt $deadline) {
        $service = Get-Service -Name $Name -ErrorAction SilentlyContinue
        if (-not $service) { return $false }
        if ($service.Status -eq $Status) { return $true }
        Start-Sleep -Milliseconds 400
    }
    return $false
}

function Invoke-PhpCapture {
    param([string] $Exe, [string[]] $Arguments)
    $previous = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $output   = (& $Exe @Arguments 2>&1 | Out-String)
        $exitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previous
    }
    return [pscustomobject]@{ Output = $output; ExitCode = $exitCode }
}

$phpDir  = Join-Path $XamppRoot 'php'
$stamp   = Get-Date -Format 'yyyyMMdd-HHmmss'
$stage   = Join-Path $XamppRoot "php-staging-$stamp"
$zipPath = Join-Path $env:TEMP "php-$Version-Win32-vs17-x64.zip"
$wanted  = @('bz2', 'curl', 'fileinfo', 'gd', 'gettext', 'mbstring', 'exif',
             'mysqli', 'pdo_mysql', 'pdo_sqlite', 'zip', 'openssl', 'ftp', 'session')

$swapped = $false
$backup  = $null
$oldVersion = $null
$svc = $null

try {
    # ------------------------------------------------------------------ pre-requisitos
    Write-Step 'Verificando pre-requisitos'

    $isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole(
        [Security.Principal.WindowsBuiltInRole]::Administrator)
    if (-not $isAdmin) { throw 'Abra o PowerShell como Administrador e rode de novo.' }
    Write-Ok 'PowerShell elevado'

    $currentExe = Join-Path $phpDir 'php.exe'
    if (-not (Test-Path -LiteralPath $currentExe)) {
        throw "Nao encontrei $currentExe. Confira o parametro -XamppRoot."
    }
    $oldVersion = (& $currentExe -r 'echo PHP_VERSION;' 2>$null)
    Write-Ok "PHP atual: $oldVersion  ($phpDir)"

    if ($oldVersion -eq $Version) {
        Write-Ok "Ja esta na versao $Version. Nada a fazer."
        return
    }

    $svc = Get-Service -Name $ServiceName -ErrorAction SilentlyContinue
    if ($svc) { Write-Ok "Servico do Apache: $ServiceName (status: $($svc.Status))" }
    else      { Write-Note "Servico '$ServiceName' nao encontrado; pare/ inicie o Apache pelo painel do XAMPP." }

    if ((Get-Process -Name httpd -ErrorAction SilentlyContinue) -and -not $svc) {
        throw "O Apache esta rodando fora do servico '$ServiceName'. Pare pelo painel do XAMPP e rode de novo."
    }

    if (Test-Path -LiteralPath $stage) { Remove-Item -LiteralPath $stage -Recurse -Force }

    if ($DryRun) {
        Write-Step 'Simulacao (-DryRun): nada sera alterado'
        Write-Info "Baixaria          : $ZipUrl"
        Write-Info "Salvaria em       : $zipPath"
        Write-Info "SHA-256 esperado  : $Sha256"
        Write-Info "Clonaria          : $phpDir  ->  $stage"
        Write-Info "Sobreporia        : arquivos do PHP $Version na copia"
        Write-Info "Ajustaria         : php.ini copiado (session.* obsoletas, E_STRICT)"
        Write-Info "Pararia           : servico '$ServiceName'"
        Write-Info "Renomearia        : $phpDir  ->  (php-backup-$oldVersion-$stamp)"
        Write-Info "Renomearia        : $stage  ->  $phpDir"
        Write-Info "Validaria         : php -v + extensoes do php.ini"
        Write-Info "Subiria           : servico '$ServiceName' e testaria http://localhost/"
        return
    }

    # ------------------------------------------------------------------ download
    Write-Step "Baixando o PHP $Version (Thread Safe, x64, VS17)"
    $needDownload = $true
    if (Test-Path -LiteralPath $zipPath) {
        $existing = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLower()
        if ($existing -eq $Sha256.ToLower()) {
            Write-Ok "Zip ja existe em $zipPath e o SHA-256 confere"
            $needDownload = $false
        } else {
            Remove-Item -LiteralPath $zipPath -Force
        }
    }
    if ($needDownload) {
        Write-Info "Baixando de $ZipUrl"
        Invoke-WebRequest -Uri $ZipUrl -OutFile $zipPath -UseBasicParsing
        $hash = (Get-FileHash -LiteralPath $zipPath -Algorithm SHA256).Hash.ToLower()
        if ($hash -ne $Sha256.ToLower()) {
            Remove-Item -LiteralPath $zipPath -Force
            throw "SHA-256 nao confere. Esperado $Sha256, obtido $hash. Download incompleto ou alterado."
        }
        Write-Ok "SHA-256 confere ($hash)"
    }

    # ------------------------------------------------------------------ area de preparo
    Write-Step "Clonando $phpDir para $stage"
    Copy-Item -LiteralPath $phpDir -Destination $stage -Recurse -Force
    Write-Ok 'Copia criada (php.ini, extras, pear e arquivos do XAMPP preservados)'

    Write-Step "Sobrepondo os binarios do PHP $Version na copia"
    Expand-Archive -LiteralPath $zipPath -DestinationPath $stage -Force
    foreach ($required in @('php.exe', 'php8ts.dll', 'php8apache2_4.dll', 'php-cgi.exe')) {
        if (-not (Test-Path -LiteralPath (Join-Path $stage $required))) {
            throw "Arquivo esperado nao encontrado no zip sobreposto: $required"
        }
    }
    Write-Ok 'php.exe, php8ts.dll e php8apache2_4.dll do 8.4.27 no lugar'

    # ------------------------------------------------------------------ php.ini
    Write-Step 'Ajustando o php.ini copiado para o PHP 8.4'
    $iniPath = Join-Path $stage 'php.ini'
    if (Test-Path -LiteralPath $iniPath) {
        $raw     = [IO.File]::ReadAllText($iniPath)
        $newLine = "`n"
        if ($raw.Contains("`r`n")) { $newLine = "`r`n" }

        $sessionPattern = '^[ \t]*session\.(sid_length|sid_bits_per_character|use_only_cookies|use_trans_sid|trans_sid_tags|trans_sid_hosts|referer_check)[ \t]*='
        $strictPattern  = '^[ \t]*error_reporting[ \t]*=.*E_STRICT'

        $lines    = $raw -split "`r?`n"
        $newLines = New-Object System.Collections.Generic.List[string]
        $changed  = $false

        foreach ($line in $lines) {
            if ($line -match $sessionPattern) {
                $trimmed = $line.Trim()
                $newLines.Add('; [PHP 8.4] diretiva depreciada no PHP 8.4 - desativada pelo atualizador (era: ' + $trimmed + ')')
                $changed = $true
            } elseif ($line -match $strictPattern) {
                $trimmed = $line.Trim()
                $newLines.Add('; [PHP 8.4] E_STRICT removido no PHP 8.4 - desativada pelo atualizador (era: ' + $trimmed + ')')
                $newLines.Add('error_reporting=E_ALL & ~E_DEPRECATED')
                $changed = $true
            } else {
                $newLines.Add($line)
            }
        }

        if ($changed) {
            Copy-Item -LiteralPath $iniPath -Destination ("$iniPath.bak-$oldVersion") -Force
            [IO.File]::WriteAllText($iniPath, ($newLines -join $newLine), (New-Object System.Text.UTF8Encoding($false)))
            Write-Ok "php.ini ajustado (original salvo em php.ini.bak-$oldVersion)"
        } else {
            Write-Info 'php.ini nao precisou de ajustes'
        }
    } else {
        Write-Note 'Nao havia php.ini na pasta atual; o PHP usara os padroes (o XAMPP normalmente tem um).'
    }

    # ------------------------------------------------------------------ teste na area de preparo
    Write-Step 'Testando o PHP 8.4.27 na area de preparo (antes de mexer no XAMPP)'
    $stagePhp   = Join-Path $stage 'php.exe'
    $stageIni   = Join-Path $stage 'php.ini'
    $stageExt   = Join-Path $stage 'ext'
    $stageArgs  = @('-c', $stageIni, '-d', ("extension_dir=$stageExt"))

    $pre = Invoke-PhpCapture -Exe $stagePhp -Arguments ($stageArgs + '-v')
    if ($pre.Output -notmatch [regex]::Escape("PHP $Version")) {
        throw "O php.exe do 8.4.27 nao respondeu como esperado na area de preparo. Saida:`n$($pre.Output)"
    }
    if ($pre.Output -match '(?i)syntax error|Error in .*\.ini|Failed loading|Unable to load') {
        throw "O php.ini ajustado continua invalido para o PHP 8.4. Saida:`n$($pre.Output)"
    }
    Write-Ok 'Binario e php.ini validos para o PHP 8.4'

    $preMods    = Invoke-PhpCapture -Exe $stagePhp -Arguments ($stageArgs + '-m')
    $preMissing = @($wanted | Where-Object { $preMods.Output -notmatch ('(?im)^\s*' + [regex]::Escape($_) + '\s*$') })
    if ($preMissing.Count -gt 0) {
        throw ("Extensoes nao carregam no PHP 8.4: {0}. Saida do php -m:`n{1}" -f ($preMissing -join ', '), $preMods.Output)
    }
    Write-Ok ('Extensoes ok na area de preparo: ' + ($wanted -join ', '))

    # ------------------------------------------------------------------ parar o Apache
    Write-Step 'Parando o Apache'
    if ($svc) {
        if ($svc.Status -ne 'Stopped') {
            Stop-Service -Name $ServiceName -Force
            if (-not (Wait-ServiceStatus -Name $ServiceName -Status 'Stopped' -TimeoutSeconds 60)) {
                throw "Nao consegui parar o servico '$ServiceName'."
            }
        }
        Write-Ok "Servico '$ServiceName' parado"
    } else {
        Write-Note 'Pare o Apache no painel do XAMPP e pressione ENTER para continuar...'
        Read-Host | Out-Null
    }
    for ($i = 0; $i -lt 30; $i++) {
        if (-not (Get-Process -Name httpd -ErrorAction SilentlyContinue)) { break }
        Start-Sleep -Milliseconds 400
    }
    if (Get-Process -Name httpd -ErrorAction SilentlyContinue) {
        Write-Note 'Ainda ha processos httpd ativos; prossiga por sua conta e risco se o Apache nao estiver servindo nada.'
    }

    # ------------------------------------------------------------------ troca das pastas
    $backup = Join-Path $XamppRoot "php-backup-$oldVersion-$stamp"
    Write-Step "Trocando as pastas (backup em $backup)"
    Move-Item -LiteralPath $phpDir -Destination $backup
    Move-Item -LiteralPath $stage  -Destination $phpDir
    $swapped = $true
    Write-Ok 'Pasta php trocada para o PHP 8.4.27'

    # ------------------------------------------------------------------ validacao
    Write-Step 'Validando a nova instalacao'
    $phpExe = Join-Path $phpDir 'php.exe'
    $versionOut = (Invoke-PhpCapture -Exe $phpExe -Arguments @('-v')).Output
    if ($versionOut -notmatch [regex]::Escape("PHP $Version")) {
        throw "php -v nao reportou PHP $Version. Saida:`n$versionOut"
    }
    if ($versionOut -match '(?i)syntax error|Error in .*\.ini|Failed loading|Unable to load') {
        throw "O php.ini da instalacao nova tem erro. Saida:`n$versionOut"
    }
    Write-Ok (($versionOut -split "`r?`n")[0].Trim())

    $iniOut = (Invoke-PhpCapture -Exe $phpExe -Arguments @('--ini')).Output
    $loadedIni = (($iniOut -split "`r?`n") | Where-Object { $_ -match 'Loaded Configuration File' } | Select-Object -First 1)
    if ($loadedIni) { Write-Info $loadedIni.Trim() }

    $loaded  = (Invoke-PhpCapture -Exe $phpExe -Arguments @('-m')).Output
    $missing = @($wanted | Where-Object { $loaded -notmatch ('(?im)^\s*' + [regex]::Escape($_) + '\s*$') })
    if ($missing.Count -gt 0) {
        Write-Note ("Extensoes nao carregadas: {0} - confira $phpDir\php.ini" -f ($missing -join ', '))
    } else {
        Write-Ok ('Extensoes carregadas: ' + ($wanted -join ', '))
    }

    # ------------------------------------------------------------------ libssh2 do Apache
    # O Apache (bin) carrega o libssh2.dll antes do PHP; se ele for mais antigo que o
    # exigido pelo php_curl.dll novo, a extensao curl falha com "procedimento nao encontrado".
    $apacheLibssh2 = Join-Path $XamppRoot 'apache\bin\libssh2.dll'
    $phpLibssh2    = Join-Path $phpDir 'libssh2.dll'
    if ((Test-Path -LiteralPath $apacheLibssh2) -and (Test-Path -LiteralPath $phpLibssh2)) {
        try {
            $apacheVer = [version] (Get-Item -LiteralPath $apacheLibssh2).VersionInfo.FileVersion
            $phpVer    = [version] (Get-Item -LiteralPath $phpLibssh2).VersionInfo.FileVersion
            if ($phpVer -gt $apacheVer) {
                Copy-Item -LiteralPath $apacheLibssh2 -Destination "$apacheLibssh2.bak-$apacheVer" -Force
                Copy-Item -LiteralPath $phpLibssh2    -Destination $apacheLibssh2 -Force
                Write-Ok "libssh2.dll do Apache alinhada ($apacheVer -> $phpVer) - necessario para o curl"
            } else {
                Write-Info "libssh2.dll do Apache ja compativel ($apacheVer)"
            }
        } catch {
            Write-Note "Nao consegui alinhar o libssh2.dll do Apache: $($_.Exception.Message). O curl pode nao carregar."
        }
    }

    # ------------------------------------------------------------------ subir o Apache
    Write-Step 'Subindo o Apache'
    if ($svc) {
        Start-Service -Name $ServiceName
        if (-not (Wait-ServiceStatus -Name $ServiceName -Status 'Running' -TimeoutSeconds 60)) {
            throw "Servico '$ServiceName' nao voltou a 'Running'. Veja C:\xampp\apache\logs\error.log"
        }
        Write-Ok "Servico '$ServiceName' em execucao"
    } else {
        Write-Note 'Inicie o Apache pelo painel do XAMPP.'
    }

    try {
        $resp = Invoke-WebRequest -Uri 'http://localhost/' -UseBasicParsing -TimeoutSec 20
        Write-Ok "http://localhost/ respondeu HTTP $([int] $resp.StatusCode)"
    } catch {
        Write-Note "Nao consegui acessar http://localhost/: $($_.Exception.Message) - veja C:\xampp\apache\logs\error.log"
    }

    # ------------------------------------------------------------------ resumo
    Write-Step 'Concluido'
    Write-Host "    PHP ......... $Version (antes: $oldVersion)"
    Write-Host "    Backup ...... $backup"
    Write-Host "    php.ini ..... $phpDir\php.ini (original: php.ini.bak-$oldVersion)"
    Write-Host ''
    Write-Host '    Rollback manual, se precisar:' -ForegroundColor DarkGray
    Write-Host "      Stop-Service $ServiceName" -ForegroundColor DarkGray
    Write-Host "      Remove-Item '$phpDir' -Recurse -Force" -ForegroundColor DarkGray
    Write-Host "      Rename-Item '$backup' php" -ForegroundColor DarkGray
    Write-Host "      Start-Service $ServiceName" -ForegroundColor DarkGray
    Write-Host ''
    Write-Note 'O phpMyAdmin 5.2.1 (que veio no XAMPP 8.2) nao e homologado para PHP 8.4. Se der erro, troque a pasta phpMyAdmin pela 5.2.3 (a que o XAMPP 8.4.27 oficial traz).'
}
catch {
    $failure = $_
    Write-Host "`nERRO: $($failure.Exception.Message)" -ForegroundColor Red

    if ($swapped -and $backup -and (Test-Path -LiteralPath $backup)) {
        Write-Step 'Revertendo para a versao anterior'
        try {
            if (Test-Path -LiteralPath $phpDir) {
                Move-Item -LiteralPath $phpDir -Destination (Join-Path $XamppRoot "php-falhou-$stamp")
            }
            Move-Item -LiteralPath $backup -Destination $phpDir
            if ($svc) { Start-Service -Name $ServiceName }
            Write-Ok "Rollback concluido: o XAMPP voltou ao PHP $oldVersion."
        } catch {
            Write-Note "Falha no rollback automatico: $($_.Exception.Message). Restaure manualmente $backup para $phpDir."
        }
    }

    throw
}
finally {
    if (Test-Path -LiteralPath $stage) {
        Remove-Item -LiteralPath $stage -Recurse -Force -ErrorAction SilentlyContinue
    }
}
