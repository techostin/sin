<?php
/**
 * Atualização automática da tabela `cnpo` a partir do site oficial do MAPA.
 *
 * Fluxo:
 *   1. Baixa o HTML da página do CNPO (gov.br).
 *   2. Extrai o link atual da planilha (cnpo-mapa-DD-MM-AAAA.xlsx).
 *   3. Se o link mudou (versão nova), baixa a planilha e recarrega a tabela `cnpo`.
 *   4. Registra o histórico em `cnpo_atualizacoes`.
 *
 * Rode manualmente:  php dev-utils/atualizar_cnpo.php
 * Agende:            diariamente ou nos dias 05/15/25 de cada mês (datas oficiais
 *                    de atualização do CNPO) via cron / Agendador do Windows.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

const CNPO_PAGINA_URL = 'https://www.gov.br/agricultura/pt-br/assuntos/sustentabilidade/organicos/cadastro-nacional-de-produtores-organicos-cnpo';
// Diretorio de trabalho do sincronizador (planilhas baixadas das fontes oficiais).
// NUNCA apontar para pastas de rascunho do projeto (ex.: recicla/).
const CNPO_DIR = __DIR__ . '/../storage/externos/';

if (!is_dir(CNPO_DIR)) {
    mkdir(CNPO_DIR, 0775, true);
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
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (SINPORG; CNPO sync)',
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
    $ctx = stream_context_create(['http' => ['timeout' => 120, 'user_agent' => 'Mozilla/5.0 (SINPORG; CNPO sync)']]);
    $conteudo = @file_get_contents($url, false, $ctx);
    if ($conteudo === false) {
        throw new RuntimeException("Falha ao baixar: {$url}");
    }
    return $conteudo;
}

echo "=== Sincronização CNPO ===\n";
echo '[' . date('d/m/Y H:i:s') . "] Iniciando...\n";

// 1) Descobrir o link atual da planilha
$html = httpGet(CNPO_PAGINA_URL);
if (!preg_match('#(https://[^"\']*cnpo-mapa-\d{2}-\d{2}-\d{4}\.xlsx)#i', $html, $m)) {
    throw new RuntimeException('Link da planilha CNPO não encontrado na página oficial.');
}
$urlPlanilha = $m[1];
$nomeArquivo = basename(parse_url($urlPlanilha, PHP_URL_PATH));
$dataPlanilha = null;
if (preg_match('/cnpo-mapa-(\d{2}-\d{2}-\d{4})\.xlsx/i', $nomeArquivo, $dm)) {
    $dataPlanilha = DateTime::createFromFormat('d-m-Y', $dm[1])->format('Y-m-d');
}
echo "  Planilha encontrada: {$nomeArquivo}\n";

// 2) Verificar se já processamos esta versão
if (tabelaExiste($db, 'cnpo_atualizacoes')) {
    $stmt = $db->prepare("SELECT COUNT(*) FROM cnpo_atualizacoes WHERE arquivo = ?");
    $stmt->execute([$nomeArquivo]);
    if ((int)$stmt->fetchColumn() > 0) {
        echo "  A versão {$nomeArquivo} já está sincronizada. Nada a fazer.\n";
        exit(0);
    }
}

// 3) Baixar a planilha
$caminhoLocal = CNPO_DIR . $nomeArquivo;
echo "  Baixando {$urlPlanilha}...\n";
httpGet($urlPlanilha, $caminhoLocal);

// 4) Recarregar a tabela `cnpo`
$reader = new Xlsx();
$reader->setReadDataOnly(true);
$reader->setReadEmptyCells(false);
$spreadsheet = $reader->load($caminhoLocal);
$sheet = $spreadsheet->getActiveSheet();

$db->exec("DROP TABLE IF EXISTS cnpo_nova");
$db->exec("CREATE TABLE cnpo_nova LIKE cnpo");

$stmt = $db->prepare(
    "INSERT INTO cnpo_nova
        (tipo_entidade, entidade, pais, uf, cidade, situacao, cnpf_cnpj_nif, nome_produtor, escopo, atividades, contato)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$contador = 0;
$db->beginTransaction();
try {
    foreach ($sheet->toArray(null, true, false, false) as $idx => $linha) {
        if ($idx < 3) continue; // linhas de título/cabeçalho
        $vals = array_map(fn($v) => ($v === null) ? null : (is_int($v) || is_float($v) ? (string)$v : $v), $linha);
        $stmt->execute([
            $vals[0] ?? null, $vals[1] ?? null, $vals[2] ?? null, $vals[3] ?? null,
            $vals[4] ?? null, $vals[5] ?? null, $vals[6] ?? null, $vals[7] ?? null,
            $vals[8] ?? null, $vals[9] ?? null, $vals[10] ?? null,
        ]);
        $contador++;
    }
    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    unlink($caminhoLocal);
    throw $e;
}
$db->exec("DROP TABLE IF EXISTS cnpo_antiga");
$db->exec("RENAME TABLE cnpo TO cnpo_antiga, cnpo_nova TO cnpo");
$db->exec("DROP TABLE IF EXISTS cnpo_antiga");

// 5) Registrar histórico
if (!tabelaExiste($db, 'cnpo_atualizacoes')) {
    $db->exec("CREATE TABLE cnpo_atualizacoes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        arquivo VARCHAR(120) NOT NULL,
        data_planilha DATE NULL,
        total_registros INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
$stmt = $db->prepare("INSERT INTO cnpo_atualizacoes (arquivo, data_planilha, total_registros) VALUES (?, ?, ?)");
$stmt->execute([$nomeArquivo, $dataPlanilha, $contador]);

echo "  Tabela cnpo atualizada com {$contador} registros (planilha de {$dataPlanilha}).\n";
echo "Concluído!\n";