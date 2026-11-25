<?php
/**
 * Atualização automática da tabela `agroindustria_existentes` a partir do site
 * oficial da SDR-RS (Programa Estadual de Agroindústria Familiar - PEAF).
 *
 * Fluxo:
 *   1. Baixa o HTML da página da SDR-RS.
 *   2. Extrai o link atual da planilha (agroindustrias-inclusas-no-peaf-*.xlsx).
 *   3. Se o link mudou (versão nova), baixa a planilha.
 *   4. Filtra apenas os registros orgânicos certificados/em transição e
 *      recarrega a tabela `agroindustria_existentes`.
 *   5. Registra o histórico em `agroindustria_atualizacoes`.
 *
 * Rode manualmente:  php dev-utils/atualizar_peaf.php
 * Agende:            mensalmente (SDR publica novo arquivo com a data no
 *                    nome do arquivo e na página oficial).
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

const PEAF_PAGINA_URL = 'https://www.sdr.rs.gov.br/agroindustria-familiar';
const PEAF_BASE_URL   = 'https://www.sdr.rs.gov.br';
// Diretorio de trabalho do sincronizador (planilhas baixadas das fontes oficiais).
// NUNCA apontar para pastas de rascunho do projeto (ex.: recicla/).
const PEAF_DIR        = __DIR__ . '/../storage/externos/';
const PEAF_KEEP       = ['ORGÂNICO CERTIFICADO', 'EM TRANSIÇÃO AGROECOLÓGICA', 'EM CONVERSÃO ORGÂNICA'];

if (!is_dir(PEAF_DIR)) {
    mkdir(PEAF_DIR, 0775, true);
}

$db = Database::getInstance()->getConnection();

function tabelaExiste(PDO $db, string $nome): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    $stmt->execute([$nome]);
    return (int)$stmt->fetchColumn() > 0;
}

function httpGet(string $url, string $destino = null): string {
    // Prioriza cURL (recomendado em produção); cai para file_get_contents se ausente.
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (SINPORG; PEAF sync)',
        ]);
        if ($destino !== null) {
            $fp = fopen($destino, 'wb');
            if ($fp === false) {
                throw new RuntimeException("Falha ao abrir destino: {$destino}");
            }
            curl_setopt($ch, CURLOPT_FILE, $fp);
        }
        $conteudo = curl_exec($ch);
        $erro = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($destino !== null) fclose($fp);
        if ($conteudo === false) {
            throw new RuntimeException("Falha ao baixar: {$url} ({$erro})");
        }
        if ($code >= 400) {
            throw new RuntimeException("HTTP {$code} ao baixar: {$url}");
        }
        return (string)$conteudo;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'Mozilla/5.0 (SINPORG; PEAF sync)']]);
    $conteudo = @file_get_contents($url, false, $ctx);
    if ($conteudo === false) {
        throw new RuntimeException("Falha ao baixar: {$url}");
    }
    return $conteudo;
}

function limparData($v): ?string {
    if (empty($v)) return null;
    if (preg_match('/^\d{4}-\d{2}-\d{2}/', (string)$v, $m)) return $m[0];
    $t = strtotime((string)$v);
    return $t ? date('Y-m-d', $t) : null;
}

echo "=== Sincronização PEAF ===\n";
echo '[' . date('d/m/Y H:i:s') . "] Iniciando...\n";

// 1) Descobrir o link atual da planilha
$html = httpGet(PEAF_PAGINA_URL);
if (!preg_match('#"(/upload/arquivos/[^"]*agroindustrias-inclusas-no-peaf-[^"]*\.xlsx)"#i', $html, $m)) {
    throw new RuntimeException('Link da planilha PEAF não encontrado na página oficial.');
}
$urlPlanilha = PEAF_BASE_URL . $m[1];
$nomeArquivo = basename(parse_url($urlPlanilha, PHP_URL_PATH));
echo "  Planilha encontrada: {$nomeArquivo}\n";

// 2) Verificar se já processamos esta versão
if (tabelaExiste($db, 'agroindustria_atualizacoes')) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM agroindustria_atualizacoes WHERE arquivo = ?");
    $stmt->execute([$nomeArquivo]);
    if ((int)$stmt->fetchColumn() > 0) {
        echo "  A versão {$nomeArquivo} já está sincronizada. Nada a fazer.\n";
        exit(0);
    }
}

// 3) Baixar a planilha
$caminhoLocal = PEAF_DIR . $nomeArquivo;
echo "  Baixando {$urlPlanilha}...\n";
httpGet($urlPlanilha, $caminhoLocal);

// 4) Ler e filtrar os registros orgânicos/em transição
$reader = new Xlsx();
$reader->setReadDataOnly(true);
$reader->setReadEmptyCells(false);
$spreadsheet = $reader->load($caminhoLocal);
$sheet = $spreadsheet->getActiveSheet();

$db->exec("DROP TABLE IF EXISTS agroindustria_existentes_nova");
$db->exec("CREATE TABLE agroindustria_existentes_nova LIKE agroindustria_existentes");

$stmt = $db->prepare(
    "INSERT INTO agroindustria_existentes_nova
        (numero, agroindustria, municipio, processamento, data_inclusao, peaf, licenciamento_sanitario, ultima_atualizacao, organicos)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$contador = 0;
$db->beginTransaction();
try {
    foreach ($sheet->toArray(null, true, false, false) as $idx => $linha) {
        if ($idx < 1) continue; // linha de cabeçalho
        $org = isset($linha[8]) ? trim((string)$linha[8]) : '';
        if (!in_array(mb_strtoupper($org), array_map('mb_strtoupper', PEAF_KEEP), true)) {
            continue;
        }
        $vals = array_map(fn($v) => ($v === null) ? null : (is_int($v) || is_float($v) ? (string)$v : $v), $linha);
        $stmt->execute([
            isset($vals[0]) && $vals[0] !== '' ? (int)$vals[0] : null,
            $vals[1] ?? null,
            $vals[2] ?? null,
            $vals[3] ?? null,
            limparData($vals[4] ?? null),
            $vals[5] ?? null,
            $vals[6] ?? null,
            limparData($vals[7] ?? null),
            $org,
        ]);
        $contador++;
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    unlink($caminhoLocal);
    throw $e;
}
$db->exec("DROP TABLE IF EXISTS agroindustria_existentes_antiga");
$db->exec("RENAME TABLE agroindustria_existentes TO agroindustria_existentes_antiga, agroindustria_existentes_nova TO agroindustria_existentes");
$db->exec("DROP TABLE IF EXISTS agroindustria_existentes_antiga");

// 5) Registrar histórico
if (!tabelaExiste($db, 'agroindustria_atualizacoes')) {
    $db->exec("CREATE TABLE agroindustria_atualizacoes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        arquivo VARCHAR(120) NOT NULL,
        total_registros INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$stmt = $db->prepare("INSERT INTO agroindustria_atualizacoes (arquivo, total_registros) VALUES (?, ?)");
$stmt->execute([$nomeArquivo, $contador]);

echo "  Tabela agroindustria_existentes atualizada com {$contador} registros orgânicos/em transição.\n";
echo "Concluído!\n";