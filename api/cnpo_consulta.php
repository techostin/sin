<?php
/**
 * API de consulta ao CNPO (tabela local `cnpo`).
 *
 * Apenas Admin (NIVEL_ADMIN) e Gestor (NIVEL_GESTOR) podem consultar.
 * Os dados vêm EXCLUSIVAMENTE da tabela `cnpo` sincronizada periodicamente
 * com a planilha oficial do MAPA (dev-utils/atualizar_cnpo.php).
 *
 * Exemplo:
 *   GET /api/cnpo_consulta.php?termo=JOSE&uf=RS&tipo_entidade=OPAC
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';

if (session_status() === PHP_SESSION_NONE) session_start();

header('Content-Type: application/json; charset=utf-8');

$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Acesso não autorizado']);
    exit;
}

// Apenas Admin e Gestor
if (!isset($_SESSION['user_nivel']) || (int)$_SESSION['user_nivel'] > NIVEL_GESTOR) {
    http_response_code(403);
    echo json_encode(['error' => 'Acesso negado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido']);
    exit;
}

$termo = trim($_GET['termo'] ?? '');
$uf = trim($_GET['uf'] ?? '');
$cidade = trim($_GET['cidade'] ?? '');
$tipo = trim($_GET['tipo_entidade'] ?? '');

// O nome do produtor é OPCIONAL. Um termo muito curto só é rejeitado quando
// não há nenhum outro filtro; caso contrário ele é simplesmente ignorado.
if ($termo !== '' && mb_strlen($termo) < 2) {
    if ($uf === '' && $cidade === '' && $tipo === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Informe ao menos 2 caracteres do nome do produtor.']);
        exit;
    }
    $termo = '';
}

// Normaliza o termo: minúsculas e sem acentos, para a busca ser tolerante.
$normalizar = function (string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $acentos = [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
    ];
    return strtr($s, $acentos);
};

// Cria as versões normalizadas das colunas via REPLACE encadeado
// (a coluna nome_produtor é utf8mb4_unicode_ci, mas a normalização garante
// que "Jose" case com "JOSÉ" mesmo com acentos nos dados).
$cols = [
    'nome_produtor',
    'entidade',
    'cidade',
    'uf',
    'tipo_entidade',
];
$buildCol = function (string $col) use ($normalizar): string {
    $expr = "LOWER({$col})";
    $map = [
        'á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
    ];
    foreach ($map as $de => $para) {
        $expr = "REPLACE({$expr}, '{$de}', '{$para}')";
    }
    return $expr;
};
$nomeNorm = $buildCol('nome_produtor');
$entidadeNorm = $buildCol('entidade');
$cidadeNorm = $buildCol('cidade');
$ufNorm = $buildCol('uf');
$tipoNorm = $buildCol('tipo_entidade');

// Monta o WHERE apenas com os critérios efetivamente informados.
$conds = [];
$params = [];

if ($termo !== '') {
    $conds[] = "({$nomeNorm} LIKE :termo OR {$entidadeNorm} LIKE :termo)";
    $params['termo'] = '%' . $normalizar($termo) . '%';
}
if ($uf !== '') {
    $conds[] = "{$ufNorm} = :uf";
    $params['uf'] = $normalizar($uf);
}
if ($cidade !== '') {
    $conds[] = "{$cidadeNorm} LIKE :cidade";
    $params['cidade'] = '%' . $normalizar($cidade) . '%';
}
if ($tipo !== '') {
    $conds[] = "{$tipoNorm} = :tipo";
    $params['tipo'] = $normalizar($tipo);
}

// Uma busca sem nenhum critério varreria a tabela inteira: bloqueia.
if (!$conds) {
    http_response_code(400);
    echo json_encode(['error' => 'Informe o nome do produtor ou ao menos um filtro (UF, cidade ou tipo).']);
    exit;
}

$where = 'WHERE ' . implode(' AND ', $conds);

// Paginação: ?pagina=1&limite=50 (limite entre 1 e 500).
$limite = (int)($_GET['limite'] ?? 50);
if ($limite < 1) {
    $limite = 50;
}
if ($limite > 500) {
    $limite = 500;
}

$pagina = (int)($_GET['pagina'] ?? 1);
if ($pagina < 1) {
    $pagina = 1;
}

try {
    $db = Database::getInstance()->getConnection();

    // Total de registros que atendem aos critérios.
    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM cnpo {$where}");
    $stmtTotal->execute($params);
    $total = (int)$stmtTotal->fetchColumn();

    // Se a página pedida passou do fim (ex.: filtro mudou), cai na última.
    $totalPaginas = $total > 0 ? (int)ceil($total / $limite) : 1;
    if ($pagina > $totalPaginas) {
        $pagina = $totalPaginas;
    }
    $offset = ($pagina - 1) * $limite;

    $sql = "SELECT nome_produtor, entidade, tipo_entidade, pais, uf, cidade, situacao, escopo
            FROM cnpo
            {$where}
            ORDER BY uf ASC, cidade ASC, nome_produtor ASC
            LIMIT {$limite} OFFSET {$offset}";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $linhas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'data' => $linhas,
        'total' => $total,
        'retornados' => count($linhas),
        'pagina' => $pagina,
        'limite' => $limite,
        'total_paginas' => $totalPaginas,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao consultar CNPO.', 'detail' => $e->getMessage()]);
}