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
            'margin_left' => 18,
            'margin_right' => 18,
            'margin_top' => 32,
            'margin_bottom' => 18
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

    /**
     * Resolve o caminho físico da logo da certificadora para uso no PDF.
     * Prioridade:
     *   1. certificadora vinculada ao associado da visita (associados.certificadora_id)
     *   2. certificadora do usuário logado (usuarios.certificadora_id)
     *   3. certificadora via gestor_id (fallback)
     *
     * @return string|null caminho absoluto do arquivo, ou null se não encontrada
     */
    private function resolverLogoCertificadora()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $logoFile = null;
        $logoUrl = null;
        try {
            // 1. Via associado da visita
            $stmt = $this->db->prepare(
                "SELECT c.logo_filename, c.logo_url FROM certificadoras c
                 INNER JOIN associados a ON a.certificadora_id = c.id
                 INNER JOIN visitas_tecnicas v ON v.associado_id = a.id
                 WHERE v.id = ? LIMIT 1"
            );
            $stmt->execute([$this->visitaId]);
            $cert = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cert) {
                // 2. Via usuário logado
                $userId = $_SESSION['user_id'] ?? null;
                if ($userId) {
                    $stmt2 = $this->db->prepare(
                        "SELECT c.logo_filename, c.logo_url FROM certificadoras c
                         INNER JOIN usuarios u ON u.certificadora_id = c.id
                         WHERE u.id = ? LIMIT 1"
                    );
                    $stmt2->execute([$userId]);
                    $cert = $stmt2->fetch(PDO::FETCH_ASSOC);
                }
            }

            if (!$cert) {
                // 3. Via gestor_id
                $userId = $_SESSION['user_id'] ?? null;
                if ($userId) {
                    $stmt3 = $this->db->prepare(
                        "SELECT logo_filename, logo_url FROM certificadoras WHERE gestor_id = ? LIMIT 1"
                    );
                    $stmt3->execute([$userId]);
                    $cert = $stmt3->fetch(PDO::FETCH_ASSOC);
                }
            }

            if ($cert) {
                $logoFile = $cert['logo_filename'] ?? null;
                $logoUrl = $cert['logo_url'] ?? null;
            }
        } catch (PDOException $e) {
            error_log('VisitaReport::resolverLogoCertificadora: ' . $e->getMessage());
        }

        if (!$logoFile && !$logoUrl) {
            return null;
        }

        $candidatos = [];
        if ($logoFile) {
            $candidatos[] = realpath(__DIR__ . '/../uploads/certificadoras/' . $logoFile);
            $candidatos[] = realpath(__DIR__ . '/../uploads/' . $logoFile);
        }
        if ($logoUrl) {
            $candidatos[] = realpath(__DIR__ . '/../' . str_replace(['../app/', '/app/'], '', parse_url($logoUrl, PHP_URL_PATH)));
        }

        foreach ($candidatos as $c) {
            if ($c && is_file($c)) {
                return $c;
            }
        }

        // Fallback para URL http(s)
        if ($logoUrl && preg_match('#^https?://#', $logoUrl)) {
            return $logoUrl;
        }

        return null;
    }

    private function getHtmlVisita($dados) {
        $html = '
        <style>
            .section { margin-bottom: 14px; border: 1px solid #ddd; padding: 8px; }
            .section-title { background: #f4f4f4; padding: 4px; font-weight: bold; border-bottom: 1px solid #ddd; margin: -8px -8px 8px -8px; font-size: 9pt; }
            .info-grid { display: table; width: 100%; }
            .info-item { display: table-cell; width: 50%; padding: 3px; }
            .table-info { width: 100%; border-collapse: collapse; }
            .table-info td { font-size: 8.5pt; padding: 2px 4px; }
            .table-checklist { width: 100%; border-collapse: collapse; margin-top: 8px; }
            .table-checklist th, .table-checklist td { border: 1px solid #ddd; padding: 5px; font-size: 8.5pt; }
            .table-checklist th { background: #f9f9f9; text-align: left; }
            .status-conforme { color: green; font-weight: bold; }
            .status-nao-conforme { color: red; font-weight: bold; }
            .signature-box { width: 45%; display: inline-block; text-align: center; margin-top: 50px; }
            .signature-line { border-top: 1px solid #000; margin-top: 60px; padding-top: 5px; }
            .score-badge { font-size: 11pt; font-weight: bold; float: right; padding: 8px; border: 2px solid #4CAF50; border-radius: 5px; }
            .observacoes-text { font-size: 9pt; }
            .signature-label { font-size: 8pt; }
        </style>

        <div class="score-badge">
            Pontuação: ' . ($dados['pontuacao'] ?? '0') . '%
        </div>

        <div class="section">
            <div class="section-title">Informações Gerais</div>
            <table class="table-info" width="100%">
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
                <tr>
                    <td><strong>Horário:</strong> ' . ($dados['hora_inicio'] ? substr($dados['hora_inicio'], 0, 5) : '—') . ' às ' . ($dados['hora_fim'] ? substr($dados['hora_fim'], 0, 5) : '—') . '</td>
                    <td><strong>Condições do tempo:</strong> ' . ($dados['condicoes_clima'] ?? '—') . '</td>
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

        // Mesclar com o checklist padrão (produção/processamento orgânico) para
        // exibir no PDF todos os itens que foram avaliados na inspeção
        require_once __DIR__ . '/ChecklistPadrao.php';
        $padraoMap = ChecklistPadrao::mapa();
        $vistos = [];
        foreach ($template as $item) {
            $vistos[(string)($item['id'] ?? '')] = true;
        }
        foreach ($resultados as $key => $res) {
            if (!isset($vistos[(string)$key]) && isset($padraoMap[(int)$key])) {
                $template[] = $padraoMap[(int)$key];
                $vistos[(string)$key] = true;
            }
        }

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
                        <td>' . ($item['pergunta'] ?? $item['item'] ?? 'Item sem descrição') . '</td>
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
                <p class="observacoes-text">' . nl2br($dados['observacoes']) . '</p>
            </div>';
        }

        // Assinaturas
        $html .= '
        <div style="margin-top: 40px; text-align: center;">
            <div class="signature-box" style="margin-right: 5%;">
                ' . ($dados['assinatura_produtor_url'] ? '<img src="' . $dados['assinatura_produtor_url'] . '" style="height: 70px; max-width: 180px;">' : '<div style="height: 70px;"></div>') . '
                <div class="signature-line signature-label">Assinatura do Produtor</div>
            </div>
            <div class="signature-box">
                ' . ($dados['assinatura_tecnico_url'] ? '<img src="' . $dados['assinatura_tecnico_url'] . '" style="height: 70px; max-width: 180px;">' : '<div style="height: 70px;"></div>') . '
                <div class="signature-line signature-label">Assinatura do Técnico</div>
            </div>
        </div>';

        return $html;
    }

    private function getHeaderVisita($dados) {
        $logo = $this->resolverLogoCertificadora();
        $logoHtml = $logo
            ? '<img src="' . $logo . '" style="width: 42px; height: 42px; object-fit: contain;">'
            : '';
        return '
        <table width="100%" style="border-bottom: 1px solid #4CAF50; padding-bottom: 4px;">
            <tr>
                <td width="12%" style="text-align: left; vertical-align: middle;">
                    ' . $logoHtml . '
                </td>
                <td width="76%" style="text-align: center; vertical-align: middle;">
                    <div style="font-size: 13pt; font-weight: bold; color: #2e7d32;">RELATÓRIO DE INSPEÇÃO TÉCNICA</div>
                    <div style="font-size: 9pt; color: #666;">Sistema Integrado de Produção Orgânica - SINPORG</div>
                </td>
                <td width="12%" style="text-align: right; vertical-align: middle;">
                    <div style="font-size: 8pt; color: #999;">Nº ' . ($dados['id'] ?? '&nbsp;') . '</div>
                </td>
            </tr>
        </table>';
    }

    private function getFooterVisita() {
        return '
        <table width="100%" style="font-size: 7pt; color: #666; border-top: 1px solid #ddd; padding-top: 3px;">
            <tr>
                <td width="33%">Gerado em: ' . date('d/m/Y H:i') . '</td>
                <td width="33%" align="center">Página {PAGENO} de {nb}</td>
                <td width="33%" align="right">' . SITE_URL . '</td>
            </tr>
        </table>';
    }
}
