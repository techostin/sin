<?php
// Iniciar a sessão se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Incluir configuração do banco
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/User.php';

// Verificar autenticação do usuário
$user = new User();
if (!$user->isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login');
    exit();
}

// Obter o tipo de relatório da URL
$tipo = isset($_GET['tipo']) ? strtolower(trim($_GET['tipo'])) : '';

// Mapear tipos de relatório para seus respectivos arquivos
$relatorios = [
    'atividades' => 'relatorios/form_atividades.php',
    'propriedades' => 'relatorios/form_propriedades.php',
    'insumos' => 'relatorios/form_insumos.php',
    'financeiro' => 'relatorios/form_financeiro.php',
    'personalizado' => 'relatorios/form_personalizado.php',
    '' => 'relatorios/dashboard.php' // Página inicial de relatórios
];

// Verificar se o tipo de relatório é válido
if (!array_key_exists($tipo, $relatorios)) {
    // Se o tipo não for encontrado, redirecionar para a página de relatórios
    header('Location: ' . SITE_URL . '/relatorios');
    exit();
}

// Incluir o cabeçalho
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/menu.php';

// Incluir o arquivo do relatório correspondente
$arquivoRelatorio = __DIR__ . '/' . $relatorios[$tipo];
if (file_exists($arquivoRelatorio)) {
    include $arquivoRelatorio;
} else {
    echo '<div class="container mt-4">
            <div class="alert alert-danger">
                <h4 class="alert-heading">Erro ao carregar o relatório</h4>
                <p>O arquivo do relatório solicitado não foi encontrado.</p>
                <hr>
                <p class="mb-0"><a href="' . SITE_URL . '/relatorios" class="alert-link">Voltar para a página de relatórios</a></p>
            </div>
          </div>';
}

// Incluir o rodapé
require_once __DIR__ . '/includes/footer.php';
?>
