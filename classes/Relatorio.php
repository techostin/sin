<?php
/**
 * Classe base para geração de relatórios
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';

class Relatorio {
    protected $db;
    protected $user;
    protected $dataInicio;
    protected $dataFim;
    protected $filtros = [];
    
    /**
     * Construtor
     */
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->user = new User();
    }
    
    /**
     * Define o período do relatório
     */
    public function setPeriodo($dataInicio, $dataFim) {
        $this->dataInicio = $dataInicio;
        $this->dataFim = $dataFim;
        return $this;
    }
    
    /**
     * Define filtros adicionais
     */
    public function setFiltros($filtros) {
        $this->filtros = array_merge($this->filtros, $filtros);
        return $this;
    }
    
    /**
     * Gera o relatório em PDF
     */
    public function gerarPdf($tipo) {
        $dados = $this->buscarDados($tipo);
        return $this->gerarPdfComDados($tipo, $dados);
    }
    
    /**
     * Gera o relatório em Excel
     */
    public function gerarExcel($tipo) {
        $dados = $this->buscarDados($tipo);
        return $this->gerarExcelComDados($tipo, $dados);
    }
    
    /**
     * Busca os dados do relatório
     */
    protected function buscarDados($tipo) {
        $metodo = 'buscarDados' . ucfirst($tipo);
        if (method_exists($this, $metodo)) {
            return $this->$metodo();
        }
        throw new Exception("Tipo de relatório não suportado: " . $tipo);
    }
    
    /**
     * Busca dados para o relatório de atividades
     */
    protected function buscarDadosAtividades() {
        $sql = "
            SELECT 
                a.*,
                p.propriedade as propriedade_nome,
                l.nome as lavoura_nome,
                ass.nome as usuario_nome,
                ca.nome as categoria_nome
            FROM atividades_diarias a
            LEFT JOIN propriedades p ON a.propriedade_id = p.id
            LEFT JOIN lavouras l ON a.lavoura_id = l.id
            LEFT JOIN associados ass ON a.associado_id = ass.id
            LEFT JOIN categorias_atividades ca ON a.categoria_id = ca.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Aplicar filtros
        if ($this->dataInicio) {
            $sql .= " AND a.data_atividade >= :data_inicio";
            $params[':data_inicio'] = $this->dataInicio;
        }
        
        if ($this->dataFim) {
            $sql .= " AND a.data_atividade <= :data_fim";
            $params[':data_fim'] = $this->dataFim;
        }
        
        // Filtro por usuário (usando nível de sessão)
        if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] > 2) {
            $sql .= " AND a.associado_id = :usuario_id";
            $params[':usuario_id'] = $_SESSION['user_id'];
        }
        
        $sql .= " ORDER BY a.data_atividade DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca dados para o relatório de propriedades
     */
    protected function buscarDadosPropriedades() {
        $sql = "
            SELECT 
                p.*,
                a.nome as responsavel_nome,
                (SELECT COUNT(*) FROM lavouras l WHERE l.propriedade_id = p.id) as total_lavouras,
                (SELECT COUNT(*) FROM atividades_diarias ad WHERE ad.propriedade_id = p.id) as total_atividades
            FROM propriedades p
            LEFT JOIN associados a ON p.associado_id = a.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Filtro por usuário (se não for admin/gestor)
        if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] > 2) {
            $sql .= " AND p.associado_id = :usuario_id";
            $params[':usuario_id'] = $_SESSION['user_id'];
        }
        
        $sql .= " ORDER BY p.propriedade ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca dados para o relatório de insumos
     */
    protected function buscarDadosInsumos() {
        $sql = "
            SELECT 
                i.*,
                f.nome as fornecedor_nome
            FROM insumos i
            LEFT JOIN fornecedores f ON i.fornecedor_id = f.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Filtro por associado se for comum
        if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] > 2) {
            $sql .= " AND i.associado_id = :usuario_id";
            $params[':usuario_id'] = $_SESSION['user_id'];
        }
        
        $sql .= " ORDER BY i.nome ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca dados para o relatório de associados
     */
    protected function buscarDadosAssociados() {
        $sql = "
            SELECT a.*, ass.nome as associacao_nome 
            FROM associados a
            LEFT JOIN associacoes ass ON a.vinculo = ass.id
            WHERE 1=1
        ";
        
        $params = [];
        
        // Apenas admin/gestor vê este relatório completo
        if (isset($_SESSION['user_nivel']) && $_SESSION['user_nivel'] > 2) {
            $sql .= " AND a.id = :usuario_id";
            $params[':usuario_id'] = $_SESSION['user_id'];
        }
        
        $sql .= " ORDER BY a.nome ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Gera o PDF com os dados fornecidos
     */
    protected function gerarPdfComDados($tipo, $dados) {
        // Incluir o autoload
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Configurações do PDF
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 20,
            'margin_bottom' => 20
        ]);
        
        // Obter o conteúdo HTML do relatório
        $html = $this->getHtmlRelatorio($tipo, $dados);
        
        // Adicionar o conteúdo ao PDF
        $mpdf->WriteHTML($html);
        
        $filename = 'relatorio_' . $tipo . '_' . date('Ymd_His') . '.pdf';
        
        return [
            'content' => $mpdf->Output($filename, 'S'),
            'filename' => $filename
        ];
    }
    
    /**
     * Gera o Excel com os dados fornecidos
     */
    protected function gerarExcelComDados($tipo, $dados) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $titulo = $this->getTituloRelatorio($tipo);
        $sheet->setCellValue('A1', $titulo);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        
        $cabecalhos = $this->getCabecalhosRelatorio($tipo);
        
        $col = 'A';
        foreach ($cabecalhos as $label) {
            $sheet->setCellValue($col . '3', $label);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
        
        $linha = 4;
        foreach ($dados as $item) {
            $col = 'A';
            foreach (array_keys($cabecalhos) as $campo) {
                $valor = $item[$campo] ?? '';
                $sheet->setCellValue($col . $linha, $valor);
                $col++;
            }
            $linha++;
        }
        
        $filename = 'relatorio_' . $tipo . '_' . date('Ymd_His') . '.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        
        // Salvar em buffer
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();
        
        return [
            'content' => $content,
            'filename' => $filename
        ];
    }
    
    /**
     * Retorna o HTML do relatório
     */
    protected function getHtmlRelatorio($tipo, $dados) {
        $html = $this->getCabecalhoRelatorio($tipo);
        $cabecalhos = $this->getCabecalhosRelatorio($tipo);
        
        $html .= '<table class="table">';
        $html .= '<thead><tr>';
        foreach ($cabecalhos as $label) {
            $html .= '<th>' . $label . '</th>';
        }
        $html .= '</tr></thead>';
        
        $html .= '<tbody>';
        if (empty($dados)) {
            $html .= '<tr><td colspan="' . count($cabecalhos) . '" style="text-align:center">Nenhum registro encontrado.</td></tr>';
        } else {
            foreach ($dados as $linha) {
                $html .= '<tr>';
                foreach (array_keys($cabecalhos) as $campo) {
                    $html .= '<td>' . htmlspecialchars($linha[$campo] ?? '') . '</td>';
                }
                $html .= '</tr>';
            }
        }
        $html .= '</tbody></table>';
        $html .= $this->getRodapeRelatorio();
        
        return $html;
    }
    
    /**
     * Retorna o cabeçalho do relatório
     */
    protected function getCabecalhoRelatorio($tipo) {
        $titulo = $this->getTituloRelatorio($tipo);
        return '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; font-size: 10pt; }
                .header { text-align: center; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; }
                th { background-color: #f2f2f2; border: 1px solid #ddd; padding: 5px; text-align: left; }
                td { border: 1px solid #ddd; padding: 5px; }
                .footer { text-align: center; font-size: 8pt; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h2>' . $titulo . '</h2>
                <p>Gerado em: ' . date('d/m/Y H:i') . '</p>
            </div>';
    }
    
    /**
     * Retorna o rodapé do relatório
     */
    protected function getRodapeRelatorio() {
        return '
            <div class="footer">
                Sistema Integrado de Produção Orgânica - Página {PAGENO} de {nb}
            </div>
        </body>
        </html>';
    }
    
    /**
     * Retorna o título do relatório
     */
    protected function getTituloRelatorio($tipo) {
        $titulos = [
            'atividades' => 'Relatório de Atividades',
            'propriedades' => 'Relatório de Propriedades',
            'insumos' => 'Relatório de Insumos',
            'associados' => 'Relatório de Associados'
        ];
        return $titulos[$tipo] ?? 'Relatório';
    }
    
    /**
     * Retorna os cabeçalhos das colunas
     */
    protected function getCabecalhosRelatorio($tipo) {
        $cabecalhos = [
            'atividades' => [
                'data_atividade' => 'Data',
                'propriedade_nome' => 'Propriedade',
                'lavoura_nome' => 'Lavoura',
                'categoria_nome' => 'Categoria',
                'descricao' => 'Descrição',
                'status' => 'Status'
            ],
            'propriedades' => [
                'id' => 'ID',
                'propriedade' => 'Propriedade',
                'responsavel_nome' => 'Responsável',
                'area_total' => 'Área (ha)',
                'cidade' => 'Cidade',
                'total_lavouras' => 'Lavouras'
            ],
            'insumos' => [
                'nome' => 'Nome',
                'descricao' => 'Descrição',
                'unidade_medida' => 'Unidade',
                'quantidade_estoque' => 'Estoque',
                'fornecedor_nome' => 'Fornecedor'
            ],
            'associados' => [
                'nome' => 'Nome',
                'email' => 'Email',
                'telefone' => 'Telefone',
                'cidade' => 'Cidade',
                'status' => 'Status'
            ]
        ];
        return $cabecalhos[$tipo] ?? [];
    }
}
