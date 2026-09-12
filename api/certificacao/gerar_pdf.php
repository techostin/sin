<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Produto.php';

use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Type: application/json');

// Receber dados via POST (pode ser raw JSON ou application/x-www-form-urlencoded)
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

$associado_id = $data['associado_id'] ?? null;
if (!$associado_id) {
    echo json_encode(['success' => false, 'error' => 'ID do associado não informado']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    // Buscar associado
    $stmt = $db->prepare("SELECT * FROM associados WHERE id = ?");
    $stmt->execute([$associado_id]);
    $associado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$associado) {
        throw new Exception("Associado não encontrado.");
    }

    // Estruturar dados para os templates
    $dados_produtor = [
        'nome' => trim($associado['nome'] . ' ' . ($associado['sobrenome'] ?? '')),
        'cnpj' => $associado['cpf_cnpj'] ?? 'Não informado',
        'endereco' => $associado['endereco'] ?? 'Não informado',
        'cidade_uf' => ($associado['cidade'] ?? '') . ' - ' . ($associado['estado'] ?? '') . ', CEP: ' . ($associado['cep'] ?? '')
    ];

    $certificado = [
        'numero' => $data['numero_certificado'] ?? 'GERADO-SISTEMA',
        'escopo' => $data['escopo'] ?? '',
        'normas' => 'Lei Brasileira nº 10.831/2003 e Instruções Normativas (MAPA)',
        'data_emissao' => !empty($data['data_emissao']) ? date('d/m/Y', strtotime($data['data_emissao'])) : date('d/m/Y'),
        'data_validade' => !empty($data['data_validade']) ? date('d/m/Y', strtotime($data['data_validade'])) : date('d/m/Y', strtotime('+1 year')),
        'local' => $associado['cidade'] ?? 'Local'
    ];

    // Buscar produtos
    $produtosSelecionados = $data['produtos'] ?? [];
    $produtos = [];
    if (!empty($produtosSelecionados)) {
        $produtoModel = new Produto();
        $todos = $produtoModel->getByAssociadoId($associado_id);
        
        $ids = array_map('intval', (array) $produtosSelecionados);
        
        $produtosFiltrados = array_filter($todos, function($p) use ($ids) {
            return in_array((int)$p['id'], $ids, true);
        });
        
        foreach ($produtosFiltrados as $row) {
            $produtos[] = [
                'nome' => $row['nome'] ?? 'Produto',
                'area_estimativa' => ($row['quantidade_produzida'] ?? 'N/A') . ' ' . ($row['unidade_medida'] ?? ''),
                'status' => 'Orgânico'
            ];
        }
    }

    // Configurar Dompdf
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultPaperSize', 'A4');
    $options->set('defaultPaperOrientation', 'landscape');

    // 1. Gerar Certificado
    ob_start();
    include __DIR__ . '/../../modulos/roteiros_inspecao/templates/certificado.php';
    $htmlCertificado = ob_get_clean();

    $dompdfCert = new Dompdf($options);
    $dompdfCert->loadHtml($htmlCertificado);
    $dompdfCert->render();
    
    $nomeArquivoCert = 'certificado_' . $associado_id . '_' . time() . '.pdf';
    $caminhoCert = __DIR__ . '/../../uploads/certificados/' . $nomeArquivoCert;
    file_put_contents($caminhoCert, $dompdfCert->output());

    // 2. Gerar Declaração de Compromisso
    ob_start();
    include __DIR__ . '/../../modulos/roteiros_inspecao/templates/declaracao_compromisso.php';
    $htmlDeclaracao = ob_get_clean();

    $dompdfDecl = new Dompdf($options);
    $dompdfDecl->loadHtml($htmlDeclaracao);
    $dompdfDecl->render();

    $nomeArquivoDecl = 'declaracao_' . $associado_id . '_' . time() . '.pdf';
    $caminhoDecl = __DIR__ . '/../../uploads/declaracoes/' . $nomeArquivoDecl;
    file_put_contents($caminhoDecl, $dompdfDecl->output());

    // URLs relativas para salvar no banco
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = '/app'; // Ajustar caso SITE_URL seja diferente
    
    $urlCert = $protocol . $host . $basePath . '/uploads/certificados/' . $nomeArquivoCert;
    $urlDecl = $protocol . $host . $basePath . '/uploads/declaracoes/' . $nomeArquivoDecl;

    echo json_encode([
        'success' => true, 
        'documento_url' => $urlCert, 
        'declaracao_compromisso_url' => $urlDecl
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
