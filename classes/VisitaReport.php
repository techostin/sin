<?php
/**
 * Classe para geração de relatórios de Visita Técnica em PDF
 */
require_once __DIR__ . '/Relatorio.php';
require_once __DIR__ . '/VisitaTecnica.php';

class VisitaReport extends Relatorio {
    private $visitaId;
    private $visitaManager;

    public function __construct($visitaId) {
        parent::__construct();
        $this->visitaId = $visitaId;
        $this->visitaManager = new VisitaTecnica();
    }

    /**
     * Gera o PDF da visita específica
     */
    public function gerarRelatorioVisita() {
        $dados = $this->visitaManager->getById($this->visitaId);
        if (!$dados) {
            throw new Exception("Visita técnica não encontrada.");
        }

        return $this->gerarPdfVisita($dados);
    }

    protected function gerarPdfVisita($dados) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 35,
            'margin_bottom' => 20
        ]);

        $html = $this->getHtmlVisita($dados);
        
        // Cabeçalho e Rodapé
        $mpdf->SetHTMLHeader($this->getHeaderVisita($dados));
        $mpdf->SetHTMLFooter($this->getFooterVisita());
        
        $mpdf->WriteHTML($html);
        
        $filename = 'Visita_Tecnica_' . $dados['associado_nome'] . '_' . date('Y-m-d', strtotime($dados['data_visita'])) . '.pdf';
        
        return [
            'content' => $mpdf->Output($filename, 'S'),
            'filename' => $filename
        ];
    }

    private function getHtmlVisita($dados) {
        $html = '
        <style>
            .section { margin-bottom: 20px; border: 1px solid #ddd; padding: 10px; }
            .section-title { background: #f4f4f4; padding: 5px; font-weight: bold; border-bottom: 1px solid #ddd; margin: -10px -10px 10px -10px; }
            .info-grid { display: table; width: 100%; }
            .info-item { display: table-cell; width: 50%; padding: 5px; }
            .table-checklist { width: 100%; border-collapse: collapse; margin-top: 10px; }
            .table-checklist th, .table-checklist td { border: 1px solid #ddd; padding: 8px; font-size: 10pt; }
            .table-checklist th { background: #f9f9f9; text-align: left; }
            .status-conforme { color: green; font-weight: bold; }
            .status-nao-conforme { color: red; font-weight: bold; }
            .signature-box { width: 45%; display: inline-block; text-align: center; margin-top: 50px; }
            .signature-line { border-top: 1px solid #000; margin-top: 60px; padding-top: 5px; }
            .score-badge { font-size: 14pt; font-weight: bold; float: right; padding: 10px; border: 2px solid #4CAF50; border-radius: 5px; }
        </style>

        <div class="score-badge">
            Pontuação: ' . ($dados['pontuacao'] ?? '0') . '%
        </div>

        <div class="section">
            <div class="section-title">Informações Gerais</div>
            <table width="100%">
                <tr>
                    <td width="50%"><strong>Produtor:</strong> ' . $dados['associado_nome'] . ' ' . $dados['associado_sobrenome'] . '</td>
                    <td width="50%"><strong>Data:</strong> ' . date('d/m/Y', strtotime($dados['data_visita'])) . '</td>
                </tr>
                <tr>
                    <td><strong>Propriedade:</strong> ' . $dados['propriedade_nome'] . '</td>
                    <td><strong>Tipo de Inspeção:</strong> ' . $dados['roteiro_nome'] . '</td>
                </tr>
                <tr>
                    <td><strong>Status:</strong> ' . $dados['status'] . '</td>
                    <td><strong>Conformidade:</strong> ' . $dados['conformidade'] . '</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Resultados da Inspeção (Checklist)</div>
            <table class="table-checklist">
                <thead>
                    <tr>
                        <th width="60%">Item Avaliado</th>
                        <th width="15%">Resultado</th>
                        <th width="25%">Observações / Não-Conformidade</th>
                    </tr>
                </thead>
                <tbody>';

        $template = $dados['checklist_template'] ?? [];
        $resultados = $dados['resultado'] ?? [];

        foreach ($template as $item) {
            $id = $item['id'] ?? null;
            $res = $resultados[$id] ?? null;
            $statusText = 'N/A';
            $statusClass = '';
            
            if ($res) {
                if ($res['conforme'] === true || $res['conforme'] === "true") {
                    $statusText = 'CONFORME';
                    $statusClass = 'status-conforme';
                } else {
                    $statusText = 'NÃO CONFORME';
                    $statusClass = 'status-nao-conforme';
                }
            }

            $html .= '
                    <tr>
                        <td>' . ($item['pergunta'] ?? 'Item sem descrição') . '</td>
                        <td class="' . $statusClass . '" style="text-align:center">' . $statusText . '</td>
                        <td>' . ($res['observacao'] ?? '') . '</td>
                    </tr>';
        }

        $html .= '
                </tbody>
            </table>
        </div>';

        if (!empty($dados['observacoes'])) {
            $html .= '
            <div class="section">
                <div class="section-title">Parecer Técnico / Observações Gerais</div>
                <p>' . nl2br($dados['observacoes']) . '</p>
            </div>';
        }

        // Assinaturas
        $html .= '
        <div style="margin-top: 40px; text-align: center;">
            <div class="signature-box" style="margin-right: 5%;">
                ' . ($dados['assinatura_produtor_url'] ? '<img src="' . $dados['assinatura_produtor_url'] . '" style="height: 80px; max-width: 200px;">' : '<div style="height: 80px;"></div>') . '
                <div class="signature-line">Assinatura do Produtor</div>
            </div>
            <div class="signature-box">
                ' . ($dados['assinatura_tecnico_url'] ? '<img src="' . $dados['assinatura_tecnico_url'] . '" style="height: 80px; max-width: 200px;">' : '<div style="height: 80px;"></div>') . '
                <div class="signature-line">Assinatura do Técnico</div>
            </div>
        </div>';

        return $html;
    }

    private function getHeaderVisita($dados) {
        return '
        <table width="100%" style="border-bottom: 1px solid #4CAF50; padding-bottom: 5px;">
            <tr>
                <td width="20%">
                    <img src="' . SITE_URL . '/assets/img/logo_default.png" style="width: 60px;">
                </td>
                <td width="80%" style="text-align: right;">
                    <div style="font-size: 16pt; font-weight: bold; color: #4CAF50;">RELATÓRIO DE INSPEÇÃO TÉCNICA</div>
                    <div style="font-size: 10pt; color: #666;">Sistema Integrado de Produção Orgânica - SINPORG</div>
                </td>
            </tr>
        </table>';
    }

    private function getFooterVisita() {
        return '
        <table width="100%" style="font-size: 8pt; color: #666; border-top: 1px solid #ddd; padding-top: 5px;">
            <tr>
                <td width="33%">Gerado em: ' . date('d/m/Y H:i') . '</td>
                <td width="33%" align="center">Página {PAGENO} de {nb}</td>
                <td width="33%" align="right">' . SITE_URL . '</td>
            </tr>
        </table>';
    }
}
