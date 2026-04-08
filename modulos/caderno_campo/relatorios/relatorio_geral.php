<?php
// Incluir autoload do Composer para mPDF
require_once __DIR__ . '/../../../vendor/autoload.php';

// Incluir configuração do banco
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../classes/Database.php';

use Mpdf\Mpdf;

try {
    // Criar instância do mPDF
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L', // Formato paisagem
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15
    ]);

    // Conectar ao banco
    $db = Database::getInstance()->getConnection();

    // Buscar dados da view consolidada
    $stmt = $db->query("SELECT * FROM view_caderno_campo_consolidado ORDER BY data_atividade DESC");
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Buscar dados do associado logado (pega o primeiro associado encontrado na view)
    $usuario = null;
    if (count($dados) > 0) {
        $usuario = [
            'nome' => $dados[0]['nome_usuario'] ?? '',
            'email' => $dados[0]['email_usuario'] ?? '',
            'telefone' => $dados[0]['telefone_usuario'] ?? '',
            'cpf' => $dados[0]['cpf_usuario'] ?? '',
        ];
        // Buscar endereço completo do associado
        $stmtUser = $db->prepare("SELECT * FROM associados WHERE nome = ? LIMIT 1");
        $stmtUser->execute([$usuario['nome']]);
        $rowUser = $stmtUser->fetch(PDO::FETCH_ASSOC);
        if ($rowUser) {
            $usuario['endereco'] = $rowUser['endereco'] ?? '';
            $usuario['cidade'] = $rowUser['cidade'] ?? '';
            $usuario['estado'] = $rowUser['estado'] ?? '';
            $usuario['cep'] = $rowUser['cep'] ?? '';
        }
    }

    // Buscar lotes de produção
    $lotes = [];
    try {
        $stmtLotes = $db->query("SELECT lp.id, lp.nome_lote, lp.data_producao, lp.quantidade, lp.unidade_medida, p.nome AS produto_nome
            FROM lotes_producao lp
            LEFT JOIN produtos p ON lp.produto_id = p.id
            ORDER BY lp.data_producao DESC");
        $lotes = $stmtLotes->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $lotes = [];
    }

    // Calcular estatísticas
    $total_atividades = count($dados);
    $total_propriedades = count(array_unique(array_column($dados, 'propriedade')));
    $total_lavouras = count(array_unique(array_column($dados, 'lavoura')));
    $total_insumos = count(array_unique(array_column($dados, 'insumo_utilisado')));
    $total_produtos = 0; // Não há produto na view simplificada

    // Agrupar por propriedade
    $por_propriedade = [];
    foreach ($dados as $row) {
        $prop = $row['propriedade'] ?? 'Sem propriedade';
        if (!isset($por_propriedade[$prop])) {
            $por_propriedade[$prop] = 0;
        }
        $por_propriedade[$prop]++;
    }

    // Agrupar por tipo de atividade
    $por_tipo = [];
    foreach ($dados as $row) {
        $tipo = $row['tipo_atividade'] ?? 'Sem tipo';
        if (!isset($por_tipo[$tipo])) {
            $por_tipo[$tipo] = 0;
        }
        $por_tipo[$tipo]++;
    }

    // Agrupar por mês
    $por_mes = [];
    foreach ($dados as $row) {
        if ($row['data_atividade']) {
            $mes = date('m/Y', strtotime($row['data_atividade']));
            if (!isset($por_mes[$mes])) {
                $por_mes[$mes] = 0;
            }
            $por_mes[$mes]++;
        }
    }

    // HTML do relatório
    $html = '
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { color: #2c3e50; margin: 0; }
        .header p { color: #7f8c8d; margin: 5px 0; }
        .user-info { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin-bottom: 20px; }
        .user-info strong { color: #2c3e50; }
        .stats { margin: 20px 0; }
        .stat-card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 15px; margin: 10px 0; text-align: center; }
        .stat-number { font-size: 24px; font-weight: bold; color: #2c3e50; }
        .stat-label { color: #7f8c8d; margin-top: 5px; }
        .section { margin: 25px 0; }
        .section h2 { color: #2c3e50; border-bottom: 1px solid #bdc3c7; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .chart-container { margin: 20px 0; }
        .footer { margin-top: 30px; text-align: center; color: #7f8c8d; font-size: 10px; }
    </style>

    <div class="header">
        <h1>RELATÓRIO GERAL - CADERNO DE CAMPO</h1>
        <p>Período: Todos os registros</p>
        <p>Data de geração: ' . date('d/m/Y H:i:s') . '</p>
    </div>';

    // Bloco de informações do usuário logado
    if ($usuario) {
        $html .= '<div class="user-info">
            <strong>Associado:</strong> ' . htmlspecialchars($usuario['nome']) . '<br>
            <strong>Email:</strong> ' . htmlspecialchars($usuario['email']) . '<br>
            <strong>Telefone:</strong> ' . htmlspecialchars($usuario['telefone']) . '<br>
            <strong>CPF:</strong> ' . htmlspecialchars($usuario['cpf']) . '<br>';
        if (!empty($usuario['endereco'])) {
            $html .= '<strong>Endereço:</strong> ' . htmlspecialchars($usuario['endereco']);
            if (!empty($usuario['cidade'])) $html .= ', ' . htmlspecialchars($usuario['cidade']);
            if (!empty($usuario['estado'])) $html .= ' - ' . htmlspecialchars($usuario['estado']);
            if (!empty($usuario['cep'])) $html .= ' CEP: ' . htmlspecialchars($usuario['cep']);
            $html .= '<br>';
        }
        $html .= '</div>';
    }

    $html .= '
    <div class="stats">
        <h2>Estatísticas Gerais</h2>
        <table>
            <tr>
                <td class="stat-card">
                    <div class="stat-number">' . $total_atividades . '</div>
                    <div class="stat-label">Total de Atividades</div>
                </td>
                <td class="stat-card">
                    <div class="stat-number">' . $total_propriedades . '</div>
                    <div class="stat-label">Propriedades</div>
                </td>
                <td class="stat-card">
                    <div class="stat-number">' . $total_lavouras . '</div>
                    <div class="stat-label">Lavouras</div>
                </td>
                <td class="stat-card">
                    <div class="stat-number">' . $total_insumos . '</div>
                    <div class="stat-label">Insumos</div>
                </td>
                <td class="stat-card">
                    <div class="stat-number">' . $total_produtos . '</div>
                    <div class="stat-label">Produtos</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Atividades por Propriedade</h2>
        <table>
            <thead>
                <tr>
                    <th>Propriedade</th>
                    <th>Quantidade de Atividades</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($por_propriedade as $propriedade => $quantidade) {
        $html .= '<tr>
                    <td>' . htmlspecialchars($propriedade) . '</td>
                    <td>' . $quantidade . '</td>
                  </tr>';
    }

    $html .= '</tbody></table></div>

    <div class="section">
        <h2>Atividades por Tipo</h2>
        <table>
            <thead>
                <tr>
                    <th>Tipo de Atividade</th>
                    <th>Quantidade</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($por_tipo as $tipo => $quantidade) {
        $html .= '<tr>
                    <td>' . htmlspecialchars($tipo) . '</td>
                    <td>' . $quantidade . '</td>
                  </tr>';
    }

    $html .= '</tbody></table></div>

    <div class="section">
        <h2>Atividades por Mês</h2>
        <table>
            <thead>
                <tr>
                    <th>Mês/Ano</th>
                    <th>Quantidade de Atividades</th>
                </tr>
            </thead>
            <tbody>';

    foreach ($por_mes as $mes => $quantidade) {
        $html .= '<tr>
                    <td>' . $mes . '</td>
                    <td>' . $quantidade . '</td>
                  </tr>';
    }

    $html .= '</tbody></table></div>

    <div class="section">
        <h2>Detalhamento das Atividades</h2>
        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Atividade</th>
                    <th>Propriedade</th>
                    <th>Lavoura</th>
                    <th>Tipo</th>
                    <th>Status</th>
                    <th>Responsável</th>
                </tr>
            </thead>
            <tbody>';

    // Limitar a 50 registros para não sobrecarregar o PDF
    $dados_limitados = array_slice($dados, 0, 50);
    foreach ($dados_limitados as $row) {
        $html .= '<tr>
                    <td>' . ($row['data_atividade'] ? date('d/m/Y', strtotime($row['data_atividade'])) : '-') . '</td>
                    <td>' . htmlspecialchars($row['nome'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['propriedade'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['lavoura'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['tipo_atividade'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['status'] ?? '-') . '</td>
                    <td>' . htmlspecialchars($row['nome_usuario'] ?? '-') . '</td>
                  </tr>';
    }

    $html .= '</tbody></table>';

    if (count($dados) > 50) {
        $html .= '<p><em>Nota: Exibindo apenas os primeiros 50 registros de ' . count($dados) . ' total.</em></p>';
    }

    $html .= '</div>

    <div class="section">
        <h2>Lotes de Produção</h2>
        <table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Lote</th>
                    <th>Produto</th>
                    <th>Quantidade</th>
                    <th>Data</th>
                </tr>
            </thead>
            <tbody>';
    if (count($lotes) > 0) {
        foreach ($lotes as $lote) {
            $html .= '<tr>'
                . '<td>LOTE-' . str_pad($lote['id'], 6, '0', STR_PAD_LEFT) . '</td>'
                . '<td>' . htmlspecialchars($lote['nome_lote'] ?? '-') . '</td>'
                . '<td>' . htmlspecialchars($lote['produto_nome'] ?? '-') . '</td>'
                . '<td>' . (isset($lote['quantidade']) ? htmlspecialchars($lote['quantidade']) . ' ' . $lote['unidade_medida'] : '-') . '</td>'
                . '<td>' . ($lote['data_producao'] ? date('d/m/Y', strtotime($lote['data_producao'])) : '-') . '</td>'
                . '</tr>';
        }
    } else {
        $html .= '<tr><td colspan="7">Nenhum lote de produção cadastrado.</td></tr>';
    }
    $html .= '</tbody></table></div>';

    $html .= '</div>

    <div class="footer">
        <p>Relatório gerado automaticamente pelo sistema de Caderno de Campo</p>
        <p>Página {PAGENO} de {nbpg}</p>
    </div>';

    // Definir metadados do PDF
    $mpdf->SetTitle('Relatório Geral - Caderno de Campo');
    $mpdf->SetAuthor('Sistema Integrado da Produção Orgânica');
    $mpdf->SetCreator('mPDF');

    // Escrever o HTML no PDF
    $mpdf->WriteHTML($html);

    // Configurar cabeçalhos para download
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="relatorio_geral_caderno_campo.pdf"');
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Gerar o PDF
    $mpdf->Output('relatorio_geral_caderno_campo.pdf', 'I');

} catch (Exception $e) {
    // Em caso de erro, mostrar mensagem
    echo '<div style="font-family: Arial, sans-serif; padding: 20px; text-align: center;">';
    echo '<h2>Erro ao gerar relatório</h2>';
    echo '<p>Ocorreu um erro ao gerar o relatório: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p><a href="../relatorios" style="color: #007bff;">Voltar aos relatórios</a></p>';
    echo '</div>';
}
?> 