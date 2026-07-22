<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';

class ReportManager {
    private $db;
    private $user;
    private $user_id;
    private $user_nivel;
    private $user_tipo;
    private $user_table;

    /**
     * @var string Diretório base para templates de relatórios
     */
    private $templatesDir = __DIR__ . '/../../relatorios/templates/';
    
    /**
     * @var string URL base para assets
     */
    private $assetsUrl = '';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->user = new User();
        
        if ($this->user->isLoggedIn()) {
            $this->user_id = $_SESSION['user_id'];
            $this->user_nivel = $_SESSION['user_nivel'] ?? NIVEL_USUARIO;
            $this->user_tipo = $_SESSION['user_tipo'] ?? 'usuario';
            $this->user_table = $_SESSION['user_table'] ?? 'usuarios';
            $this->assetsUrl = SITE_URL . '/assets';
        } else {
            throw new Exception("Usuário não autenticado");
        }
    }

    /**
     * Retorna a condição WHERE para filtrar os relatórios de acordo com o nível de acesso
     */
    private function getUserFilter($table, $userField = 'associado_id') {
        // Admin e Gestor veem todos os dados
        if ($this->user_nivel <= NIVEL_GESTOR) {
            return '';
        }
        
        // Usuário comum vê apenas seus próprios dados
        return " WHERE $table.$userField = " . $this->user_id;
    }

    /**
     * Gera um relatório em PDF
     * 
     * @param string $tipo Tipo de relatório (associados, atividades, etc.)
     * @param array $filtros Filtros para o relatório
     * @param string $formato Formato de saída (I = inline, D = download, F = salvar arquivo)
     * @return string|bool Retorna o caminho do arquivo ou false em caso de erro
     */
    public function gerarRelatorioPDF($tipo, $filtros = [], $formato = 'I') {
        try {
            // Verifica se o tipo de relatório é válido
            $metodo = 'get' . ucfirst($tipo) . 'Report';
            if (!method_exists($this, $metodo)) {
                throw new Exception("Tipo de relatório inválido: " . $tipo);
            }
            
            // Obtém os dados do relatório
            $dados = $this->$metodo($filtros);
            
            // Carrega o template do relatório
            $templateFile = $this->templatesDir . 'relatorio_' . $tipo . '.php';
            if (!file_exists($templateFile)) {
                throw new Exception("Template do relatório não encontrado: " . $templateFile);
            }
            
            // Inicia o buffer de saída
            ob_start();
            
            // Extrai as variáveis para o escopo local
            extract([
                'dados' => $dados,
                'filtros' => $filtros,
                'assetsUrl' => $this->assetsUrl,
                'titulo' => ucfirst($tipo) . ' - ' . date('d/m/Y H:i:s')
            ]);
            
            // Inclui o template
            include $templateFile;
            
            // Obtém o conteúdo do buffer
            $html = ob_get_clean();
            
            // Cria uma instância do mPDF
            $mpdf = new \Mpdf\Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'margin_header' => 10,
                'margin_footer' => 10
            ]);
            
            // Configura o cabeçalho e rodapé
            $mpdf->SetHTMLHeader(''
                . '<div style="text-align: right; border-bottom: 1px solid #ddd; padding-bottom: 5px; margin-bottom: 20px;">'
                . '    <img src="' . $this->assetsUrl . '/img/logo.png" style="height: 40px;" alt="Logo">'
                . '</div>');
                
            $mpdf->SetHTMLFooter(''
                . '<div style="text-align: center; border-top: 1px solid #ddd; padding-top: 5px; font-size: 9pt; color: #666;">'
                . '    Página {PAGENO} de {nb} - Gerado em ' . date('d/m/Y H:i:s') . ''
                . '</div>');
            
            // Adiciona o conteúdo ao PDF
            $mpdf->WriteHTML($html);
            
            // Gera o PDF
            $filename = 'relatorio_' . $tipo . '_' . date('Ymd_His') . '.pdf';
            $filepath = sys_get_temp_dir() . '/' . $filename;
            
            // Salva o arquivo temporariamente
            $mpdf->Output($filepath, 'F');
            
            // Se for para exibir no navegador
            if ($formato === 'I') {
                $mpdf->Output($filename, 'I');
                exit;
            }
            
            // Se for para download
            if ($formato === 'D') {
                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                readfile($filepath);
                unlink($filepath); // Remove o arquivo temporário
                exit;
            }
            
            // Retorna o caminho do arquivo
            return $filepath;
            
        } catch (Exception $e) {
            error_log('Erro ao gerar relatório PDF: ' . $e->getMessage());
            if (ob_get_length()) ob_end_clean();
            return false;
        }
    }
    
    /**
     * Retorna os dados para o relatório de atividades
     */
    public function getAtividadesReport($filtros = []) {
        $where = " WHERE 1=1 ";
        $params = [];
        
        // Filtro de segurança por associado
        if ($this->user_nivel > NIVEL_GESTOR) {
            $where .= " AND a.associado_id = :user_id ";
            $params[':user_id'] = $this->user_id;
        }
        
        // Adiciona filtros adicionais
        if (!empty($filtros['data_inicio'])) {
            $where .= ' AND a.data_atividade >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where .= ' AND a.data_atividade <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'];
        }
        
        $sql = "SELECT a.*, p.propriedade as propriedade_nome, l.nome as lavoura_nome, 
                assoc.nome as associado_nome, c.nome as categoria_nome
                FROM atividades_diarias a
                LEFT JOIN propriedades p ON a.propriedade_id = p.id
                LEFT JOIN lavouras l ON a.lavoura_id = l.id
                LEFT JOIN associados assoc ON a.associado_id = assoc.id
                LEFT JOIN categorias_atividades c ON a.categoria_id = c.id
                $where
                ORDER BY a.data_atividade DESC, a.hora_inicio";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os dados para o relatório de insumos
     */
    public function getInsumosReport($filtros = []) {
        $where = " WHERE 1=1 ";
        $params = [];
        
        // Filtro de segurança por associado
        if ($this->user_nivel > NIVEL_GESTOR) {
            $where .= " AND i.associado_id = :user_id ";
            $params[':user_id'] = $this->user_id;
        }
        
        // Adiciona filtros adicionais
        if (!empty($filtros['fornecedor_id'])) {
            $where .= ' AND i.fornecedor_id = :fornecedor_id';
            $params[':fornecedor_id'] = $filtros['fornecedor_id'];
        }
        
        $sql = "SELECT i.*, f.nome as fornecedor_nome, assoc.nome as associado_nome
                FROM insumos i
                LEFT JOIN fornecedores f ON i.fornecedor_id = f.id
                LEFT JOIN associados assoc ON i.associado_id = assoc.id
                $where
                ORDER BY i.nome";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os dados para o relatório de lotes
     */
    public function getLotesReport($filtros = []) {
        $where = " WHERE 1=1 ";
        $params = [];
        
        // Filtro de segurança por associado (Lotes -> Produtos -> Lavouras -> Propriedades -> Associado)
        if ($this->user_nivel > NIVEL_GESTOR) {
            $where .= " AND prop.associado_id = :user_id ";
            $params[':user_id'] = $this->user_id;
        }
        
        // Adiciona filtros adicionais
        if (!empty($filtros['produto_id'])) {
            $where .= ' AND l.produto_id = :produto_id';
            $params[':produto_id'] = $filtros['produto_id'];
        }
        
        $sql = "SELECT l.*, p.nome as produto_nome,
                (SELECT COUNT(*) FROM lotes_producao WHERE id = l.id) as total_producoes
                FROM lotes_producao l
                LEFT JOIN produtos p ON l.produto_id = p.id
                LEFT JOIN lavouras lav ON p.lavoura_id = lav.id
                LEFT JOIN propriedades prop ON lav.propriedade_id = prop.id
                $where
                ORDER BY l.nome_lote";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os dados para o relatório de produção
     */
    public function getProducaoReport($filtros = []) {
        $where = " WHERE 1=1 ";
        $params = [];
        
        // Filtro de segurança por associado (Lotes -> Produtos -> Lavouras -> Propriedades -> Associado)
        if ($this->user_nivel > NIVEL_GESTOR) {
            $where .= " AND prop.associado_id = :user_id ";
            $params[':user_id'] = $this->user_id;
        }
        
        // Adiciona filtros adicionais
        if (!empty($filtros['data_inicio'])) {
            $where .= ' AND l.data_producao >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where .= ' AND l.data_producao <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'];
        }
        
        $sql = "SELECT l.*, p.nome as produto_nome, l.nome_lote as lote_codigo
                FROM lotes_producao l
                LEFT JOIN produtos p ON l.produto_id = p.id
                LEFT JOIN lavouras lav ON p.lavoura_id = lav.id
                LEFT JOIN propriedades prop ON lav.propriedade_id = prop.id
                $where
                ORDER BY l.data_producao DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os dados para o relatório de propriedades
     */
    public function getPropriedadesReport($filtros = []) {
        $where = " WHERE 1=1 ";
        $params = [];
        
        // Filtro de segurança por associado
        if ($this->user_nivel > NIVEL_GESTOR) {
            $where .= " AND p.associado_id = :user_id ";
            $params[':user_id'] = $this->user_id;
        }
        
        // Adiciona filtros adicionais
        if (!empty($filtros['cidade'])) {
            $where .= ' AND p.cidade = :cidade';
            $params[':cidade'] = $filtros['cidade'];
        }
        
        $sql = "SELECT p.*, a.nome as responsavel_nome, 
                (SELECT COUNT(*) FROM lavouras WHERE propriedade_id = p.id) as total_lavouras
                FROM propriedades p
                LEFT JOIN associados a ON p.associado_id = a.id
                $where
                ORDER BY p.propriedade";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os dados para o relatório de usuários (apenas admin/gestor)
     */
    public function getUsuariosReport($filtros = []) {
        // Apenas admin e gestor podem ver este relatório
        if ($this->user_nivel > NIVEL_GESTOR) {
            return [];
        }
        
        $where = '1=1';
        $params = [];
        
        // Adiciona filtros adicionais
        if (!empty($filtros['tipo'])) {
            $where .= ' AND tipo = :tipo';
            $params[':tipo'] = $filtros['tipo'];
        }
        
        if (!empty($filtros['status'])) {
            $where .= ' AND status = :status';
            $params[':status'] = $filtros['status'];
        }
        
        $sql = "SELECT * FROM usuarios WHERE $where ORDER BY nome";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os dados para o relatório de associados (apenas admin/gestor)
     */
    public function getAssociadosReport($filtros = []) {
        $where = '1=1';
        $params = [];
        
        // Se for usuário comum, vê apenas seus próprios dados
        if ($this->user_nivel > NIVEL_GESTOR) {
            $where .= ' AND a.id = :user_id';
            $params[':user_id'] = $this->user_id;
        }
        
        // Adiciona filtros adicionais
        if (!empty($filtros['status'])) {
            $where .= ' AND status = :status';
            $params[':status'] = $filtros['status'];
        }
        
        if (!empty($filtros['cidade'])) {
            $where .= ' AND cidade = :cidade';
            $params[':cidade'] = $filtros['cidade'];
        }
        
        $sql = "SELECT a.*, ass.nome as associacao_nome 
                FROM associados a
                LEFT JOIN associacoes ass ON a.vinculo = ass.id
                WHERE $where 
                ORDER BY a.nome";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os dados para o relatório de associações (apenas admin/gestor)
     */
    public function getAssociacoesReport() {
        // Apenas admin e gestor podem ver este relatório
        if ($this->user_nivel > NIVEL_GESTOR) {
            return [];
        }
        
        $sql = "SELECT a.*, 
                (SELECT COUNT(*) FROM associados WHERE vinculo = a.id) as total_associados,
                (SELECT COUNT(*) FROM propriedades p JOIN associados assoc ON p.associado_id = assoc.id WHERE assoc.vinculo = a.id) as total_propriedades
                FROM associacoes a
                ORDER BY a.nome";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os dados para o relatório de fornecedores (apenas admin/gestor)
     */
    public function getFornecedoresReport($filtros = []) {
        // Apenas admin e gestor podem ver este relatório
        if ($this->user_nivel > NIVEL_GESTOR) {
            return [];
        }
        
        $where = '1=1';
        $params = [];
        
        // Adiciona filtros adicionais
        if (!empty($filtros['cidade'])) {
            $where .= ' AND cidade = :cidade';
            $params[':cidade'] = $filtros['cidade'];
        }
        
        if (!empty($filtros['tipo_fornecedor'])) {
            $where .= ' AND tipo_fornecedor = :tipo_fornecedor';
            $params[':tipo_fornecedor'] = $filtros['tipo_fornecedor'];
        }
        
        $sql = "SELECT * FROM fornecedores WHERE $where ORDER BY razao_social";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os dados para o relatório de contas (apenas admin/gestor)
     */
    public function getContasReport($filtros = []) {
        // Apenas admin e gestor podem ver este relatório
        if ($this->user_nivel > NIVEL_GESTOR) {
            return [];
        }
        
        $where = '1=1';
        $params = [];
        
        // Adiciona filtros adicionais
        if (!empty($filtros['tipo'])) {
            $where .= ' AND tipo = :tipo';
            $params[':tipo'] = $filtros['tipo'];
        }
        
        if (!empty($filtros['data_inicio'])) {
            $where .= ' AND data >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'];
        }
        
        if (!empty($filtros['data_fim'])) {
            $where .= ' AND data <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'];
        }
        
        $sql = "SELECT c.id, c.nome as descricao, c.tipo as tipo_formatado, 
                c.valor, c.data as data_vencimento,
                '-' as status_formatado,
                '-' as responsavel_nome
                FROM contas c
                WHERE $where 
                ORDER BY c.data DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os dados para o relatório financeiro (apenas admin/gestor)
     * Tabela: contas (id, nome, tipo, valor, data)
     */
    public function getFinanceiroReport($filtros = []) {
        if ($this->user_nivel > NIVEL_GESTOR) {
            return [];
        }

        $where  = ['1=1'];
        $params = [];

        // filtro por tipo: 'Receita' ou 'Despesa' (inicial maiúscula)
        if (!empty($filtros['tipo'])) {
            $where[] = 'tipo = :tipo';
            $params[':tipo'] = ucfirst(strtolower($filtros['tipo']));
        }

        // filtro por data - Desativado temporariamente para garantir visibilidade total (Solicitação do Usuário)
        /*
        if (!empty($filtros['data_inicio'])) {
            $where[] = 'data >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'];
        }

        if (!empty($filtros['data_fim'])) {
            $where[] = 'data <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'];
        }
        */

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT id, nome, tipo, valor, data FROM contas $whereClause ORDER BY data DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os dados para o relatório de lavouras
     */
    public function getLavourasReport($filtros = []) {
        $where = " WHERE 1=1 ";
        $params = [];
        
        // Filtro de segurança por associado
        if ($this->user_nivel > NIVEL_GESTOR) {
            $where .= " AND p.associado_id = :user_id ";
            $params[':user_id'] = $this->user_id;
        }
        
        $sql = "SELECT l.*, p.propriedade as propriedade_nome, 
                assoc.nome as associado_nome, pr.nome as produto_nome
                FROM lavouras l
                LEFT JOIN propriedades p ON l.propriedade_id = p.id
                LEFT JOIN associados assoc ON p.associado_id = assoc.id
                LEFT JOIN produtos pr ON pr.lavoura_id = l.id
                $where
                ORDER BY l.nome";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna os dados para o relatório de produtores (alias para associados)
     */
    public function getProdutoresReport($filtros = []) {
        return $this->getAssociadosReport($filtros);
    }

    /**
     * Retorna um resumo geral para o dashboard de relatórios
     */
    public function getResumoGeral() {
        $stats = [
            'total_associados' => 0,
            'total_propriedades' => 0,
            'atividades_hoje' => 0,
            'insumos_criticos' => 0
        ];

        try {
            // Se for Gestor ou Admin (Nivel 1 ou 2), vê tudo
            if ($this->user_nivel <= NIVEL_GESTOR) {
                // Total de Associados
                $stmt = $this->db->query("SELECT COUNT(*) FROM associados");
                $stats['total_associados'] = $stmt->fetchColumn();

                // Total de Propriedades
                $stmt = $this->db->query("SELECT COUNT(*) FROM propriedades");
                $stats['total_propriedades'] = $stmt->fetchColumn();

                // Atividades Hoje
                $stmt = $this->db->query("SELECT COUNT(*) FROM atividades_diarias WHERE data_atividade = CURRENT_DATE");
                $stats['atividades_hoje'] = $stmt->fetchColumn();

                // Insumos Críticos
                $stmt = $this->db->query("SELECT COUNT(*) FROM insumos WHERE quantidade < 5");
                $stats['insumos_criticos'] = $stmt->fetchColumn();
            } else {
                // Se for Usuário Comum (Nivel 3), vê apenas seus dados
                
                // Total de Associados (Sempre 1 - ele mesmo)
                $stats['total_associados'] = 1;

                // Total de Propriedades do usuário
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM propriedades WHERE associado_id = ?");
                $stmt->execute([$this->user_id]);
                $stats['total_propriedades'] = $stmt->fetchColumn();

                // Atividades Hoje do usuário
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM atividades_diarias WHERE associado_id = ? AND data_atividade = CURRENT_DATE");
                $stmt->execute([$this->user_id]);
                $stats['atividades_hoje'] = $stmt->fetchColumn();

                // Insumos Críticos do usuário
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM insumos WHERE associado_id = ? AND quantidade < 5");
                $stmt->execute([$this->user_id]);
                $stats['insumos_criticos'] = $stmt->fetchColumn();
            }

        } catch (Exception $e) {
            error_log("Erro ao obter resumo geral: " . $e->getMessage());
        }

        return $stats;
    }
}
