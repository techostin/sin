<?php
/**
 * API: Pré-cadastro de novo usuário (primeiro acesso)
 * Salva o associado com status='pendente' e retorna JSON.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';

header('Content-Type: application/json; charset=utf-8');

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.']);
    exit;
}

$nome       = trim($_POST['nome']       ?? '');
$sobrenome  = trim($_POST['sobrenome']  ?? '');
$email      = trim($_POST['email']      ?? '');
$senha      = $_POST['senha']           ?? '';
$confirma   = $_POST['confirma_senha']  ?? '';
$perfil     = trim($_POST['perfil'] ?? '');
$tipoUser   = trim($_POST['tipo_usuario'] ?? '');

// Perfil de acesso (nova lógica: aba "Perfil de Acesso")
// vegetal / animal -> agricultor
// agroindustria    -> produtor_agroindustria
// mista            -> agricultor_agroindustria
// gestor           -> gestor (tabela usuarios)
$perfisValidos = ['vegetal', 'animal', 'agroindustria', 'mista', 'gestor'];
$perfil = in_array($perfil, $perfisValidos, true) ? $perfil : '';
if ($perfil !== '') {
    $mapaPerfilTipo = [
        'vegetal'       => 'agricultor',
        'animal'        => 'agricultor',
        'agroindustria' => 'produtor_agroindustria',
        'mista'         => 'agricultor_agroindustria',
        'gestor'        => 'gestor',
    ];
    $tipoUser = $mapaPerfilTipo[$perfil];
    $mapaPerfilArea = [
        'vegetal'       => 'vegetal',
        'animal'        => 'animal',
        'agroindustria' => 'agroindustria',
        'mista'         => 'animal,vegetal,agroindustria',
        'gestor'        => '',
    ];
    $areas = $mapaPerfilArea[$perfil] !== '' ? explode(',', $mapaPerfilArea[$perfil]) : [];
} else {
    // Legado: aceita tipo_usuario direto + area_producao (array)
    // Área de produção (múltipla): animal, vegetal, agroindustria
    $areasValidas = ['animal', 'vegetal', 'agroindustria'];
    $areasRecebidas = $_POST['area_producao'] ?? [];
    if (!is_array($areasRecebidas)) $areasRecebidas = [];
    $areas = array_values(array_intersect(
        $areasValidas,
        array_map('trim', $areasRecebidas)
    ));
}

// Validar o tipo de usuário
if ($tipoUser === 'usuario') $tipoUser = 'agricultor';
$tipoUser = in_array($tipoUser, ['agricultor', 'produtor_agroindustria', 'agricultor_agroindustria', 'gestor'], true) ? $tipoUser : 'agricultor';

// Associados com agroindústria (só ou combinado com agricultor)
$temAgroindustria = in_array($tipoUser, ['produtor_agroindustria', 'agricultor_agroindustria'], true) ? 1 : 0;

// Validações
$errors = [];
if (empty($nome))                          $errors[] = 'O nome é obrigatório.';
if (empty($sobrenome))                     $errors[] = 'O sobrenome é obrigatório.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
                                           $errors[] = 'Informe um e-mail válido.';
if (strlen($senha) < 6)                   $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
if ($senha !== $confirma)                  $errors[] = 'As senhas não coincidem.';
if ($tipoUser !== 'gestor' && empty($areas)) {
    $errors[] = 'Selecione um perfil de acesso (Vegetal, Animal, Agroindústria ou Mista).';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'error' => implode(' ', $errors)]);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();

    // Verificar e-mail duplicado em associados e usuarios
    $stmtChk = $db->prepare("SELECT COUNT(*) FROM associados WHERE email = :e");
    $stmtChk->execute([':e' => $email]);
    if ($stmtChk->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Este e-mail já está cadastrado. Tente fazer login.']);
        exit;
    }
    $stmtChk2 = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :e");
    $stmtChk2->execute([':e' => $email]);
    if ($stmtChk2->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'error' => 'Este e-mail já está cadastrado. Tente fazer login.']);
        exit;
    }

$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

    // Perfil/nível SOLICITADOS ficam registrados para o Admin conferir antes de
    // conceder os privilégios (ver classes/AcessoPendente.php).
    $perfilSolicitado = $perfil !== '' ? $perfil : ($tipoUser === 'gestor' ? 'gestor' : $tipoUser);

    // Gestor/Fiscal -> tabela usuarios (nivel gestor). Tratado como Gestor/Técnico.
    // O acesso NÃO é liberado automaticamente: o Admin precisa confirmar (ou
    // ajustar) os privilégios solicitados em /associados/pendentes.
    if ($tipoUser === 'gestor') {
        $stmt = $db->prepare("
            INSERT INTO usuarios
                (nome, sobrenome, email, senha, nivel, nivel_solicitado, perfil_solicitado, tipo, tipo_usuario, status, created_at)
            VALUES
                (:nome, :sobrenome, :email, :senha, :nivel, :nivel_solicitado, :perfil_solicitado, 'gestor', 'gestor', 'pendente', NOW())
        ");
        $stmt->execute([
            ':nome'              => $nome,
            ':sobrenome'         => $sobrenome,
            ':email'             => $email,
            ':senha'             => $senha_hash,
            ':nivel'             => NIVEL_GESTOR,
            ':nivel_solicitado'  => NIVEL_GESTOR,
            ':perfil_solicitado' => $perfilSolicitado,
        ]);
    } else {
        // Agricultor / Produtor de Agroindústria -> CONTA em "usuarios" (nível usuário).
        //
        // O cadastro em "associados" NÃO é criado aqui: ele nasce na aprovação da
        // Ficha de Adesão, a partir dos dados informados nela, e é vinculado a
        // esta conta (usuarios.associado_id <-> associados.user_id). Assim a
        // mesma pessoa nunca aparece duplicada nas duas tabelas.
        $areaProducao = implode(',', $areas);
        $stmt = $db->prepare("
            INSERT INTO usuarios
                (nome, sobrenome, email, senha, nivel, nivel_solicitado, perfil_solicitado, tipo, tipo_usuario, tem_agroindustria, area_producao, status, created_at)
            VALUES
                (:nome, :sobrenome, :email, :senha, :nivel, :nivel_solicitado, :perfil_solicitado, 'usuario', :tipo_usuario, :tem_agroindustria, :area_producao, 'pendente', NOW())
        ");
        $stmt->execute([
            ':nome'              => $nome,
            ':sobrenome'         => $sobrenome,
            ':email'             => $email,
            ':senha'             => $senha_hash,
            ':nivel'             => NIVEL_USUARIO,
            ':nivel_solicitado'  => NIVEL_USUARIO,
            ':perfil_solicitado' => $perfilSolicitado,
            ':tipo_usuario'      => $tipoUser,
            ':tem_agroindustria' => $temAgroindustria,
            ':area_producao'     => $areaProducao,
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => $tipoUser === 'gestor'
            ? 'Solicitação enviada! O administrador vai confirmar os privilégios de Gestor/Fiscal antes de liberar o seu acesso.'
            : 'Cadastro realizado! Aguarde a liberação pelo administrador para acessar o sistema.',
    ]);

} catch (Exception $e) {
    error_log("Erro pré-cadastro: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno. Tente novamente em instantes.']);
}
