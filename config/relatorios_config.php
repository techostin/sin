<?php
/**
 * Configurações dos Relatórios
 * 
 * Este arquivo contém as configurações padrão para geração de relatórios
 */

return [
    // Configurações gerais
    'geral' => [
        'titulo_sistema' => 'Sistema Integrado da Produção Orgânica',
        'versao' => '1.0.0',
        'criador' => 'AgroTech Solutions',
        'palavras_chave' => 'relatório, agricultura, gestão, produtividade',
        'tempo_cache' => 3600, // Tempo de cache em segundos (1 hora)
    ],
    
    // Configurações de PDF
    'pdf' => [
        'formato_pagina' => 'A4',
        'orientacao' => 'P', // P = Retrato, L = Paisagem
        'margens' => [
            'esquerda' => 15,
            'direita' => 15,
            'superior' => 20,
            'inferior' => 20,
        ],
        'fonte_padrao' => 'helvetica',
        'tamanho_fonte' => 10,
        'cor_primaria' => '#3498db',
        'cor_secundaria' => '#2c3e50',
        'cor_destaque' => '#e74c3c',
        'caminho_logo' => __DIR__ . '/../assets/img/logo.png',
        'incluir_numero_pagina' => true,
        'incluir_data_geracao' => true,
        'protecao' => [
            'usuario' => '',
            'proprietario' => '',
            'permissoes' => [
                'copiar' => true,
                'imprimir' => 'high_resolution', // 'high_resolution', 'low_resolution' ou false
                'modificar' => false,
                'anotacoes' => true,
                'preencher_campos' => false,
                'copiar_conteudo' => true,
                'copiar_conteudo_para_outros' => false
            ]
        ]
    ],
    
    // Configurações de Excel
    'excel' => [
        'autor' => 'Sistema Integrado da Produção Orgânica',
        'titulo' => 'Relatório Gerado pelo Sistema',
        'assunto' => 'Relatório de Dados',
        'descricao' => 'Relatório gerado automaticamente pelo sistema',
        'palavras_chave' => 'relatorio, dados, sistema',
        'categoria' => 'Relatórios',
        'formato_celulas' => [
            'padrao' => [
                'fonte' => 'Arial',
                'tamanho' => 10,
                'cor' => '000000',
                'fundo' => 'FFFFFF',
                'alinhamento' => 'left',
                'borda' => 'thin',
                'cor_borda' => 'CCCCCC'
            ],
            'cabecalho' => [
                'fonte' => 'Arial',
                'tamanho' => 11,
                'negrito' => true,
                'cor' => 'FFFFFF',
                'fundo' => '3498db',
                'alinhamento' => 'center',
                'borda' => 'thin',
                'cor_borda' => '2980b9'
            ],
            'destaque' => [
                'fonte' => 'Arial',
                'tamanho' => 10,
                'negrito' => true,
                'italico' => true,
                'cor' => '000000',
                'fundo' => 'F1C40F',
                'alinhamento' => 'right',
                'formato_numero' => '#,##0.00'
            ],
            'total' => [
                'fonte' => 'Arial',
                'tamanho' => 10,
                'negrito' => true,
                'cor' => 'FFFFFF',
                'fundo' => '2C3E50',
                'alinhamento' => 'right',
                'formato_numero' => '#,##0.00',
                'borda' => 'thin',
                'cor_borda' => '1A252F'
            ]
        ],
        'largura_colunas' => [
            'padrao' => 15,
            'descricao' => 40,
            'data' => 12,
            'valor' => 15,
            'status' => 10,
            'acoes' => 8
        ],
        'altura_linhas' => [
            'padrao' => 15,
            'cabecalho' => 20,
            'destaque' => 18,
            'total' => 18
        ]
    ],
    
    // Configurações de e-mail
    'email' => [
        'assunto_padrao' => 'Relatório Gerado - {NOME_RELATORIO}',
        'mensagem_padrao' => 'Segue em anexo o relatório solicitado.\n\n' .
                             'Data de geração: {DATA_GERACAO}\n' .
                             'Período: {DATA_INICIAL} a {DATA_FINAL}\n\n' .
                             'Atenciosamente,\n' .
                             'Sistema Integrado da Produção Orgânica',
        'remetente_nome' => 'Sistema de Relatórios',
        'remetente_email' => 'relatorios@agricola.com.br',
        'responder_para' => 'suporte@agricola.com.br',
        'tipo_conteudo' => 'text/plain', // text/plain ou text/html
        'formato_anexo' => 'pdf', // pdf, xlsx, csv
        'manter_anexo' => true, // Manter cópia dos anexos no servidor
        'caminho_anexos' => __DIR__ . '/../temp/relatorios/email/',
        'dias_manter_anexos' => 7 // Número de dias para manter os anexos
    ],
    
    // Configurações de cache
    'cache' => [
        'habilitado' => true,
        'tempo_vida' => 86400, // 24 horas em segundos
        'diretorio' => __DIR__ . '/../temp/relatorios/cache/',
        'compactar' => true // Compactar os arquivos de cache
    ],
    
    // Configurações de log
    'log' => [
        'habilitado' => true,
        'nivel' => 'info', // debug, info, notice, warning, error, critical, alert, emergency
        'arquivo' => __DIR__ . '/../logs/relatorios_' . date('Y-m') . '.log',
        'max_arquivos' => 12, // Número máximo de arquivos de log a manter
        'tamanho_maximo' => 10485760, // 10MB
        'formato' => '[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n',
        'nivel_erro_email' => 'error', // Nível mínimo para enviar notificação por e-mail
        'email_notificacao' => 'erros@agricola.com.br'
    ],
    
    // Configurações de segurança
    'seguranca' => [
        'exigir_autenticacao' => true,
        'niveis_acesso' => [
            'admin' => ['*'], // Acesso total
            'gerente' => ['atividades', 'propriedades', 'insumos', 'financeiro'],
            'usuario' => ['atividades', 'propriedades'],
            'visitante' => [] // Sem acesso
        ],
        'registrar_acesso' => true,
        'limite_requisicoes' => [
            'habilitado' => true,
            'max_requisicoes' => 100, // Número máximo de requisições por hora
            'tempo_bloqueio' => 3600 // Tempo de bloqueio em segundos (1 hora)
        ],
        'validar_origem' => true, // Validar origem das requisições
        'origens_permitidas' => [
            $_SERVER['SERVER_NAME'],
            'localhost',
            '127.0.0.1',
            '::1'
        ]
    ],
    
    // Configurações de exportação
    'exportacao' => [
        'formatos_suportados' => ['pdf', 'xlsx', 'csv', 'html'],
        'limite_registros' => [
            'web' => 1000,
            'desktop' => 10000,
            'servidor' => 100000
        ],
        'diretorio_temporario' => __DIR__ . '/../temp/relatorios/exportados/',
        'manter_arquivos' => true,
        'dias_manter_arquivos' => 7,
        'compactar_arquivos' => true
    ],
    
    // Configurações de templates
    'templates' => [
        'diretorio' => __DIR__ . '/../views/relatorios/templates/',
        'padrao' => 'default.php',
        'estilos' => [
            'padrao' => __DIR__ . '/../assets/css/relatorios/estilo-padrao.css',
            'minimalista' => __DIR__ . '/../assets/css/relatorios/estilo-minimalista.css',
            'corporativo' => __DIR__ . '/../assets/css/relatorios/estilo-corporativo.css'
        ],
        'cache' => true,
        'cache_diretorio' => __DIR__ . '/../temp/relatorios/compilados/'
    ],
    
    // Configurações de notificações
    'notificacoes' => [
        'erro_geracao' => [
            'enviar_email' => true,
            'nivel_minimo' => 'error',
            'destinatarios' => ['suporte@agricola.com.br', 'ti@agricola.com.br']
        ],
        'conclusao_geracao' => [
            'enviar_email' => true,
            'tamanho_minimo' => 1048576, // 1MB
            'destinatarios' => ['relatorios@agricola.com.br']
        ]
    ],
    
    // Configurações de depuração
    'depuracao' => [
        'modo_desenvolvimento' => true,
        'exibir_erros' => true,
        'log_erros' => true,
        'exibir_erros_banco' => false,
        'log_consultas' => true,
        'tempo_execucao' => true,
        'memoria_utilizada' => true
    ]
];

// Definir constantes para facilitar o acesso
if (!defined('RELATORIO_TITULO_SISTEMA')) {
    define('RELATORIO_TITULO_SISTEMA', 'Sistema Integrado da Produção Orgânica');
    define('RELATORIO_VERSAO', '1.0.0');
    define('RELATORIO_DIRETORIO_TEMP', __DIR__ . '/../temp/relatorios/');
    define('RELATORIO_DIRETORIO_LOGS', __DIR__ . '/../logs/');
    define('RELATORIO_DIRETORIO_UPLOAD', __DIR__ . '/../uploads/relatorios/');
    define('RELATORIO_URL_BASE', 'http://' . $_SERVER['HTTP_HOST'] . '/app/relatorios/');
}

// Criar diretórios necessários
$diretorios = [
    RELATORIO_DIRETORIO_TEMP . 'cache/',
    RELATORIO_DIRETORIO_TEMP . 'exportados/',
    RELATORIO_DIRETORIO_TEMP . 'email/',
    RELATORIO_DIRETORIO_LOGS,
    RELATORIO_DIRETORIO_UPLOAD
];

foreach ($diretorios as $diretorio) {
    if (!is_dir($diretorio)) {
        mkdir($diretorio, 0755, true);
    }
}

// Funções auxiliares
if (!function_exists('formatar_moeda')) {
    /**
     * Formata um valor numérico como moeda
     * 
     * @param float $valor Valor a ser formatado
     * @param string $moeda Símbolo da moeda (padrão: R$)
     * @return string Valor formatado
     */
    function formatar_moeda($valor, $moeda = 'R$') {
        return $moeda . ' ' . number_format($valor, 2, ',', '.');
    }
}

if (!function_exists('formatar_data')) {
    /**
     * Formata uma data para o formato brasileiro
     * 
     * @param string $data Data no formato Y-m-d
     * @param bool $incluir_hora Incluir hora na formatação
     * @return string Data formatada
     */
    function formatar_data($data, $incluir_hora = false) {
        if (empty($data) || $data == '0000-00-00') {
            return '';
        }
        
        $timestamp = strtotime($data);
        $formato = $incluir_hora ? 'd/m/Y H:i' : 'd/m/Y';
        
        return date($formato, $timestamp);
    }
}

if (!function_exists('obter_config_relatorio')) {
    /**
     * Obtém uma configuração específica de relatório
     * 
     * @param string $chave Chave da configuração (ex: 'pdf.formato_pagina')
     * @param mixed $padrao Valor padrão caso a chave não exista
     * @return mixed Valor da configuração ou valor padrão
     */
    function obter_config_relatorio($chave, $padrao = null) {
        static $config = null;
        
        if ($config === null) {
            $config = require __DIR__ . '/relatorios_config.php';
        }
        
        // Se a chave for nula, retorna todo o array de configuração
        if ($chave === null) {
            return $config;
        }
        
        // Divide a chave em partes (ex: 'pdf.formato_pagina' => ['pdf', 'formato_pagina'])
        $partes = explode('.', $chave);
        $valor = $config;
        
        // Navega pelo array usando as partes da chave
        foreach ($partes as $parte) {
            if (isset($valor[$parte])) {
                $valor = $valor[$parte];
            } else {
                return $padrao;
            }
        }
        
        return $valor;
    }
}
?>
