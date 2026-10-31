<?php
/**
 * Helper de cabeçalho dos relatórios do SINPORG.
 *
 * Centraliza a exibição da logo do sistema (img/logo_Sinporg-2.png — a mesma
 * usada no menu lateral, com fundo branco e a sigla "SINPORG — Sistema
 * Integrado da Produção Orgânica") no topo central de cada relatório,
 * tanto em HTML (mPDF) quanto na planilha Excel.
 */

if (!function_exists('sinporg_logo_file')) {
    /**
     * Caminho absoluto da imagem da logo utilizada nos relatórios.
     */
    function sinporg_logo_file() {
        return defined('SINPORG_LOGO_PATH') ? SINPORG_LOGO_PATH : (__DIR__ . '/../img/logo_Sinporg-2.png');
    }
}

if (!function_exists('sinporg_logo_url')) {
    /**
     * URL pública da logo (para páginas HTML).
     */
    function sinporg_logo_url() {
        return defined('SINPORG_LOGO_URL') ? SINPORG_LOGO_URL : ((defined('SITE_URL') ? SITE_URL : '/app') . '/img/logo_Sinporg-2.png');
    }
}

if (!function_exists('sinporg_report_header_css')) {
    /**
     * CSS aplicado ao cabeçalho padrão do relatório (embutido no HTML do PDF).
     */
    function sinporg_report_header_css() {
        return <<<CSS
        .sinporg-header { text-align: center; border-bottom: 3px solid #1B5E20; padding-bottom: 10px; margin-bottom: 14px; background: #ffffff; }
        .sinporg-logo { height: 62px; margin-bottom: 6px; }
        .sinporg-title { font-size: 17pt; font-weight: bold; color: #1B5E20; margin-top: 4px; }
        .sinporg-subtitle { font-size: 10pt; color: #666; margin-top: 3px; }
        .sinporg-responsavel { font-size: 10pt; color: #333; margin-top: 5px; font-style: italic; }
        .sinporg-resumo { width: 100%; margin: 12px 0; border-collapse: separate; border-spacing: 8px; }
        .sinporg-resumo td { width: 25%; text-align: center; padding: 10px 6px; border: 1px solid #c8d6c0; border-radius: 6px; background-color: #f6faf3; }
        .sinporg-resumo .n { font-size: 16pt; font-weight: bold; color: #1B5E20; }
        .sinporg-resumo .l { font-size: 8.5pt; color: #666; text-transform: uppercase; letter-spacing: 0.4px; }
        .sinporg-table { width: 100%; border-collapse: collapse; margin: 8px 0 16px 0; font-size: 8.5pt; }
        .sinporg-table th { background-color: #1B5E20; color: #fff; border: 1px solid #1B5E20; padding: 6px 4px; text-align: left; font-weight: bold; }
        .sinporg-table td { border: 1px solid #ddd; padding: 4px; vertical-align: top; }
        .sinporg-table tr:nth-child(even) td { background-color: #f9f9f9; }
        .sinporg-tabela-titulo { font-size: 12pt; font-weight: bold; color: #1B5E20; margin: 14px 0 4px 0; }
        .sinporg-assinatura { margin-top: 40px; text-align: center; }
        .sinporg-assinatura-risco { width: 350px; margin: 0 auto 5px auto; border-top: 2px solid #333; padding-top: 5px; }
        .sinporg-footer { text-align: center; font-size: 7.5pt; color: #999; border-top: 1px solid #ddd; padding-top: 8px; }
CSS;
    }
}

if (!function_exists('sinporg_report_header')) {
    /**
     * HTML do cabeçalho padrão: logo centralizada sobre fundo branco,
     * título do relatório e informações de emissão.
     */
    function sinporg_report_header($titulo, $subtitulo = '', $responsavel_nome = '') {
        $logo = sinporg_logo_file();
        $img = file_exists($logo)
            ? '<img class="sinporg-logo" src="' . $logo . '" alt="SINPORG - Sistema Integrado da Produção Orgânica" />'
            : '<div class="sinporg-title" style="font-size:22pt;">SINPORG</div>';

        $sub = $subtitulo !== '' ? '<div class="sinporg-subtitle">' . htmlspecialchars($subtitulo) . '</div>' : '';
        $resp = $responsavel_nome !== '' ? '<div class="sinporg-responsavel">Responsável: ' . htmlspecialchars($responsavel_nome) . '</div>' : '';

        return '<div class="sinporg-header">'
            . $img
            . '<div class="sinporg-title">' . htmlspecialchars($titulo) . '</div>'
            . $sub
            . $resp
            . '<div class="sinporg-subtitle">Emissão: ' . date('d/m/Y H:i:s') . '</div>'
            . '</div>';
    }
}

if (!function_exists('sinporg_report_footer')) {
    /**
     * HTML do rodapé padrão do relatório (usado pelo mPDF).
     */
    function sinporg_report_footer() {
        return '<div class="sinporg-footer">'
            . 'Relatório gerado pelo SINPORG - Sistema Integrado da Produção Orgânica'
            . ' | Página {PAGENO} de {nbpg} | ' . date('d/m/Y H:i:s')
            . '</div>';
    }
}
