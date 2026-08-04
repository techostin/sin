<?php
/**
 * API de Relatórios Administrativos - Exportação Excel e PDF
 * 
 * endpoint: /api/relatorios-admin?relatorio=usuarios&formato=excel
 * 
 * Formatos: excel, pdf
 * Relatórios: usuarios, associados, associacoes, fornecedores, contas
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/ReportManager.php';

// Verificar autenticação e permissão
$user = new User();
if (!$user->isLoggedIn() || !$user->hasPermission(NIVEL_GESTOR)) {
    http_response_code(401);
    die(json_encode(['error' => 'Acesso não autorizado']));
}

// Obter dados do usuário responsável
$responsavel_nome = $_SESSION['user_nome'] ?? 'Usuário não identificado';
$responsavel_id = $_SESSION['user_id'] ?? 0;
$responsavel_tipo = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;

// Buscar assinatura digital do usuário na tabela associados/usuarios
$db = Database::getInstance()->getConnection();
$tabela = $_SESSION['user_table'] ?? 'usuarios';
$assinatura = null;
try {
    $stmt = $db->prepare("SELECT assinatura FROM {$tabela} WHERE id = ?");
    $stmt->execute([$responsavel_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['assinatura'])) {
        $assinatura = $row['assinatura'];
    }
} catch (Exception $e) {
    // ignora erro
}

$relatorio = $_GET['relatorio'] ?? '';
$formato = $_GET['formato'] ?? 'pdf';

// Filtros
$filtros = [];
foreach (['data_inicio', 'data_fim', 'status', 'tipo', 'cidade', 'tipo_fornecedor'] as $campo) {
    if (!empty($_GET[$campo])) {
        $filtros[$campo] = $_GET[$campo];
    }
}

try {
    $reportManager = new ReportManager();
    
    // Mapear relatórios para seus respectivos métodos e colunas
    $relatorios_map = [
        'usuarios' => [
            'metodo' => 'getUsuariosReport',
            'colunas' => ['ID', 'Nome', 'E-mail', 'Tipo', 'Status', 'Último Acesso'],
            'campos' => ['id', 'nome', 'email', 'tipo', 'status', 'ultimo_acesso'],
            'titulo' => 'Relatório de Usuários',
            'descricao' => 'Listagem completa de usuários cadastrados no sistema'
        ],
        'associados' => [
            'metodo' => 'getAssociadosReport',
            'colunas' => ['ID', 'Nome', 'E-mail', 'Telefone', 'CPF/CNPJ', 'Cidade', 'UF', 'Status', 'Associação'],
            'campos' => ['id', 'nome_completo', 'email', 'telefone', 'cpf_cnpj', 'cidade_nome', 'estado_uf', 'status', 'associacao_nome'],
            'titulo' => 'Relatório de Associados',
            'descricao' => 'Listagem completa de associados e produtores'
        ],
        'associacoes' => [
            'metodo' => 'getAssociacoesReport',
            'colunas' => ['ID', 'Nome', 'CNPJ', 'Cidade', 'UF', 'Total Associados', 'Total Propriedades'],
            'campos' => ['id', 'nome', 'cnpj', 'cidade_nome', 'estado_uf', 'total_associados', 'total_propriedades'],
            'titulo' => 'Relatório de Associações',
            'descricao' => 'Listagem completa de associações e cooperativas'
        ],
        'fornecedores' => [
            'metodo' => 'getFornecedoresReport',
            'colunas' => ['ID', 'Razão Social', 'Nome Fantasia', 'CNPJ/CPF', 'Cidade', 'UF', 'Telefone', 'E-mail'],
            'campos' => ['id', 'razao_social', 'nome_fantasia', 'cnpj_cpf', 'cidade_nome', 'estado_uf', 'telefone', 'email'],
            'titulo' => 'Relatório de Fornecedores',
            'descricao' => 'Listagem completa de fornecedores cadastrados'
        ],
        'contas' => [
            'metodo' => 'getContasReport',
            'colunas' => ['ID', 'Descrição', 'Tipo', 'Valor', 'Vencimento', 'Status', 'Responsável'],
            'campos' => ['id', 'descricao', 'tipo_formatado', 'valor', 'data_vencimento', 'status_formatado', 'responsavel_nome'],
            'titulo' => 'Relatório de Contas',
            'descricao' => 'Listagem completa de contas a pagar e receber'
        ],
        'insumos' => [
            'metodo' => 'getInsumosReport',
            'colunas' => ['ID', 'Nome', 'Fornecedor', 'Qtd. Estoque', 'Unidade', 'Validade', 'Associado'],
            'campos' => ['id', 'nome', 'fornecedor_nome', 'quantidade_estoque', 'unidade_medida', 'data_validade', 'associado_nome'],
            'titulo' => 'Relatório de Insumos',
            'descricao' => 'Listagem completa de insumos cadastrados'
        ],
        'atividades' => [
            'metodo' => 'getAtividadesReport',
            'colunas' => ['ID', 'Data', 'Atividade', 'Categoria', 'Status', 'Propriedade', 'Lavoura', 'Associado'],
            'campos' => ['id', 'data_atividade', 'nome', 'categoria_nome', 'status', 'propriedade_nome', 'lavoura_nome', 'associado_nome'],
            'titulo' => 'Relatório de Atividades Diárias',
            'descricao' => 'Listagem de atividades diárias do caderno de campo'
        ],
        'lotes' => [
            'metodo' => 'getLotesReport',
            'colunas' => ['ID', 'Lote', 'Produto', 'Qtd. Produzida', 'Unidade', 'Data Produção', 'Validade'],
            'campos' => ['id', 'nome_lote', 'produto_nome', 'quantidade_produzida', 'unidade_medida', 'data_producao', 'data_validade_lote'],
            'titulo' => 'Relatório de Lotes de Produção',
            'descricao' => 'Listagem de lotes de produção registrados'
        ],
        'propriedades' => [
            'metodo' => 'getPropriedadesReport',
            'colunas' => ['ID', 'Propriedade', 'Responsável', 'Cidade', 'UF', 'Área Total (ha)', 'Total Lavouras'],
            'campos' => ['id', 'propriedade', 'responsavel_nome', 'cidade', 'uf', 'area_total', 'total_lavouras'],
            'titulo' => 'Relatório de Propriedades',
