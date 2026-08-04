<?php
// Arquivo centralizador de exportação de relatórios
// Redireciona para os arquivos específicos de cada tipo de relatório

// Verificar parâmetros
$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'pdf';
$relatorio = isset($_GET['relatorio']) ? $_GET['relatorio'] : '';

// Remover parâmetros tipo e relatorio da query string
$params = $_GET;
unset($params['tipo']);
unset($params['relatorio']);
$queryString = http_build_query($params);

// Determinar qual arquivo chamar baseado no tipo de relatório
$arquivo = '';

switch ($relatorio) {
    case 'atividades':
        if ($tipo === 'excel') {
            $arquivo = 'atividades_excel.php';
        } else {
            $arquivo = 'atividades.php';
        }
        break;

    case 'insumos':
        if ($tipo === 'excel') {
            $arquivo = 'insumos_excel.php';
        } else {
            $arquivo = 'insumos.php';
        }
        break;

    case 'producao':
        if ($tipo === 'excel') {
            $arquivo = 'producao_excel.php';
        } else {
            $arquivo = 'producao.php';
        }
        break;

    case 'lotes':
        if ($tipo === 'excel') {
            $arquivo = 'lotes_excel.php';
        } else {
            $arquivo = 'lotes.php';
        }
        break;

    case 'produtores':
        if ($tipo === 'excel') {
            $arquivo = 'produtores_excel.php';
        } else {
            $arquivo = 'produtores.php';
        }
        break;

    case 'propriedades':
        if ($tipo === 'excel') {
            $arquivo = 'propriedades_excel.php';
        } else {
            $arquivo = 'propriedades.php';
        }
        break;

    case 'ocorrencias':
        if ($tipo === 'excel') {
            $arquivo = 'ocorrencias_excel.php';
        } else {
            $arquivo = 'ocorrencias.php';
        }
        break;

    case 'propriedade':
        $arquivo = 'propriedade.php'; // Apenas PDF disponível
        break;

    case 'geral':
        $arquivo = 'relatorio_geral.php'; // Apenas PDF disponível
        break;

    default:
        die('Tipo de relatório não reconhecido.');
}

// Se o arquivo para Excel não existe ainda, usar o PDF
if ($tipo === 'excel' && !file_exists(__DIR__ . '/' . $arquivo)) {
    // Substituir por PDF se Excel não existir
    $arquivo = str_replace('_excel.php', '.php', $arquivo);
    if (!file_exists(__DIR__ . '/' . $arquivo)) {
        die('Relatório não encontrado.');
    }
}

// Incluir o arquivo correto diretamente em vez de redirecionar
if (file_exists(__DIR__ . '/' . $arquivo)) {
    require __DIR__ . '/' . $arquivo;
} else {
    die('Erro: Arquivo do relatório não encontrado em ' . __DIR__ . '/' . $arquivo);
}
exit;
?>