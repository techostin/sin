<?php
/**
 * Teste da API api/ficha_adesao.php via CLI (simula sessão + requisição).
 * Uso: php test_api_ficha.php <cenario>
 *   status    -> GET ?action=status (sessão de associado nível 3)
 *   submeter  -> POST ?action=submeter (corpo de formulário + CSRF header)
 *   aprovar   -> POST ?action=aprovar (sessão gestor nível 2)
 *   limpar    -> remove a ficha de teste
 */
error_reporting(E_ALL);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
@session_start();
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$cenario = $argv[1] ?? 'status';
$db = Database::getInstance()->getConnection();
$aid = 13; // associado de teste

if ($cenario === 'limpar') {
    $db->exec("DELETE FROM ficha_adesao WHERE associado_id = " . $aid);
    echo "limpo
";
    exit;
}

$api = __DIR__ . '/../api/ficha_adesao.php';

if ($cenario === 'status') {
    $_SESSION['user_id'] = $aid; $_SESSION['user_table'] = 'associados'; $_SESSION['user_nivel'] = 3;
    $_GET['action'] = 'status';
} elseif ($cenario === 'submeter') {
    $_SESSION['user_id'] = $aid; $_SESSION['user_table'] = 'associados'; $_SESSION['user_nivel'] = 3;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET['action'] = 'submeter';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $_SESSION['csrf_token'];
    $_POST = [
        'action' => 'submeter',
        'tipo_associado' => 'agricultor_familiar',
        'nome_completo' => 'Edmilson Teste',
        'data_nascimento' => '1985-06-15',
        'cpf' => '111.444.777-35',
        'municipio' => 'Porto Alegre',
        'estado' => 'RS',
        'telefone_whatsapp' => '(51) 99999-0000',
        'zona' => 'rural',
        'sistemas_producao' => ['horticultura'],
        'canais_comercializacao' => ['feiras_livres'],
        'produtos' => [['produto_cultura' => 'Alface', 'producao_estimada' => '500', 'unidade' => 'un.']],
        'aceite_termos' => '1',
    ];
} elseif ($cenario === 'aprovar') {
    $_SESSION['user_id'] = 1; $_SESSION['user_table'] = 'usuarios'; $_SESSION['user_nivel'] = 2;
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_GET['action'] = 'aprovar';
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $_SESSION['csrf_token'];
    $ficha = $db->query("SELECT id FROM ficha_adesao WHERE associado_id = " . $aid . " LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $_POST = ['action' => 'aprovar', 'ficha_id' => $ficha ? $ficha['id'] : 0];
} else {
    echo "cenario desconhecido
"; exit(1);
}

ob_start();
include $api;
$out = ob_get_clean();
echo $out . "
";
