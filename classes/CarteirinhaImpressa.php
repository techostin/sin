<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Associado.php';
require_once __DIR__ . '/Mensalidade.php';
require_once __DIR__ . '/Carteirinha.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

/**
 * Carteirinha fÃ­sica/impressa do associado.
 *
 * Modelo de impressÃ£o (A5 paisagem) com foto, dados cadastrais completos,
 * vÃ­nculos com associaÃ§Ãµes e situaÃ§Ã£o financeira atual. O PDF gerado pode ser
 * impresso ou anexado e enviado por e-mail ao associado.
 */
class CarteirinhaImpressa
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Monta todos os dados necessÃ¡rios para a carteirinha de um associado.
     */
    public function getDados($associadoId)
    {
        $associadoId = (int) $associadoId;

        $associado = (new Associado())->getById($associadoId);
        if (!$associado) {
            return ['erro' => 'Associado nÃ£o encontrado.'];
        }

        $associacoes = (new Associado())->getAssociacoesById($associadoId);
        $financeiro = (new Mensalidade())->getResumoFinanceiro($associadoId);
        $historico = (new Mensalidade())->getByAssociado($associadoId);

        $carteirinhaService = new Carteirinha();
        $carteirinha = $carteirinhaService->getByAssociado($associadoId);
        if (!$carteirinha) {
            $r = $carteirinhaService->gerar($associadoId);
            $carteirinha = $carteirinhaService->getByAssociado($associadoId);
        }
        $validade = $carteirinhaService->validaToken($carteirinha['token'] ?? '');

        $situacaoFinanceira = ((int) ($financeiro['qtd_em_aberto'] ?? 0)) > 0 ? 'Inadimplente' : 'Em dia';

        $fotoBase64 = '';
        $fotoMime = 'image/jpeg';
        if (!empty($associado['foto_url']) && file_exists(__DIR__ . '/../uploads/associados/' . $associado['foto_url'])) {
            $fotoBase64 = base64_encode(file_get_contents(__DIR__ . '/../uploads/associados/' . $associado['foto_url']));
            $fotoInfo = @getimagesize(__DIR__ . '/../uploads/associados/' . $associado['foto_url']);
            if (is_array($fotoInfo) && !empty($fotoInfo['mime'])) {
                $fotoMime = $fotoInfo['mime'];
            }
        }

        $urlCarteirinha = SITE_URL . '/carteirinha/' . ($carteirinha['token'] ?? '');
        $qrBase64 = '';
        $ctx = @file_get_contents('https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=0&data=' . urlencode($urlCarteirinha));
        if ($ctx !== false && substr($ctx, 0, 4) === "\x89PNG") {
            $qrBase64 = base64_encode($ctx);
        }

        return [
            'associado' => $associado,
            'associacoes' => $associacoes,
            'financeiro' => $financeiro,
            'historico' => $historico,
            'carteirinha' => $carteirinha,
            'validade' => $validade,
            'situacaoFinanceira' => $situacaoFinanceira,
            'foto_base64' => $fotoBase64,
            'foto_mime' => $fotoMime,
            'qr_base64' => $qrBase64,
            'url_carteirinha' => $urlCarteirinha,
        ];
    }

    /**
     * Helper de formataÃ§Ã£o de data com seguranÃ§a contra valores vazios.
     */
    private function fmtData($data)
    {
        if (empty($data) || $data === '0000-00-00' || $data === null) {
            return 'â€”';
        }
        return date('d/m/Y', strtotime($data));
    }

    private function esc($v)
    {
        return htmlspecialchars((string) $v);
    }

    /**
     * Monta o HTML da carteirinha (A5 paisagem).
     * Visual em bege claro com degradÃª e detalhes em laranja, verde e amarelo (tons suaves).
     */
    public function buildHtml(array $d)
    {
        $a = $d['associado'];
        $siglas = [];
        foreach ($d['associacoes'] as $ac) {
            $siglas[] = $this->esc(!empty($ac['sigla']) ? $ac['sigla'] : $ac['nome']);
        }
        $associacaoNome = $siglas ? implode(' â€¢ ', $siglas) : 'â€”';
        $siglaAssoc = $siglas ? $siglas[0] : ''; // primeira para o topo do cartÃ£o

        $fotoMime = $d['foto_mime'] ?? 'image/jpeg';
        $fotoHtml = $d['foto_base64'] !== ''
            ? '<img src="data:' . $fotoMime . ';base64,' . $d['foto_base64'] . '" style="width:30mm;height:40mm;object-fit:cover;border-radius:3mm;border:1mm solid #C8CDD3;">'
            : '<div style="width:30mm;height:40mm;background:#E4E6E9;border:0.5mm dashed #B9BDC2;border-radius:3mm;display:table-cell;vertical-align:middle;text-align:center;color:#7A828A;font-size:11px;font-weight:600;">SEM FOTO<br><span style="font-weight:400;font-size:9px;">associado</span></div>';

        $qrHtml = $d['qr_base64'] !== ''
            ? '<img src="data:image/png;base64,' . $d['qr_base64'] . '" style="width:34mm;height:34mm;">'
            : '<div style="font-size:7px;color:#7A828A;max-width:34mm;text-align:center;">Token: ' . $this->esc($d['carteirinha']['token'] ?? '') . '</div>';

        $valida = ($d['validade']['valida'] ?? false);
        $situacaoCor = $valida ? '#5F8F5F' : '#C26E2E';
        $validadeTxt = !empty($d['carteirinha']['validade'])
            ? date('d/m/Y', strtotime($d['carteirinha']['validade']))
            : 'â€”';

        $statusAssociado = $this->esc(ucfirst($a['status_associado'] ?? 'Ativo'));
        $situacaoFin = $this->esc($d['situacaoFinanceira']);
        $situacaoFinCor = $d['situacaoFinanceira'] === 'Em dia' ? '#5F8F5F' : '#C26E2E';
        $tipo = $this->esc(ucfirst($a['tipo_associado'] ?? 'Efetivo'));
        $nomeCompleto = $this->esc(trim(($a['nome'] ?? '') . ' ' . ($a['sobrenome'] ?? '')));
        $cpf = $this->esc($a['cpf'] ?? 'â€”');
        $rg = $this->esc($a['rg'] ?? 'â€”');
        $nasc = $this->fmtData($a['data_nascimento'] ?? null);
        $profissao = $this->esc($a['profissao'] ?? 'â€”');
        $cnpo = $this->esc($a['registro_cnpo'] ?? 'â€”');
        $admissao = $this->fmtData($a['data_admissao'] ?? null);
        $email = $this->esc($a['email'] ?? 'â€”');
        $telefone = $this->esc($a['telefone'] ?? 'â€”');
        $cidadeUf = $this->esc(trim(($a['cidade'] ?? '') . ($a['estado'] ? ' - ' . $a['estado'] : '')));

        $emDiaQtd = (int) ($d['financeiro']['qtd_pago'] ?? 0);
        $emAbertoQtd = (int) ($d['financeiro']['qtd_em_aberto'] ?? 0);
        $totalEmAberto = number_format((float) ($d['financeiro']['total_em_aberto'] ?? 0), 2, ',', '.');

        // PendÃªncias atuais (nÃ£o pagas)
        $pendentesHtml = '';
        $pendentes = array_filter((array) $d['historico'], function ($h) {
            return ($h['status_mensalidade'] ?? '') !== 'pago';
        });
        if ($pendentes) {
            $linhas = [];
            $pendentes = array_slice(array_values($pendentes), 0, 3);
            foreach ($pendentes as $p) {
                $linhas[] = '<span style="background:#C26E2E;color:#fff;padding:0.6mm 1.8mm;border-radius:1.5mm;font-size:8px;font-weight:700;">'
                    . str_pad((string) $p['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $p['ano_referencia']
                    . ' R$ ' . number_format((float) $p['valor_cobrado'], 2, ',', '.') . '</span>';
            }
            $pendentesHtml = '<div style="margin-top:2mm;line-height:4.5mm;">' . implode(' ', array_slice($linhas, 0, 3)) . '</div>';
        }

        $html = '
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
</head>
<body style="font-family:Helvetica,Arial,sans-serif;margin:0;">
<div style="width:210mm;height:148mm;background:linear-gradient(135deg,#F6F7F8 0%,#E4E6E9 55%,#D2D5D9 100%);border:0.8mm solid #B9BDC2;border-radius:6mm;box-sizing:border-box;padding:7mm 9mm;position:relative;color:#33383D;">
    <!-- Faixa decorativa laranja/verde/amarelo -->
    <table cellpadding="0" cellspacing="0" style="width:100%;margin-bottom:4mm;">
        <tr>
            <td style="width:33.33%;height:1.6mm;border-radius:1mm;background:#B8D4B8;border-right:0.5mm solid #FFF;"></td>
            <td style="width:33.33%;height:1.6mm;background:#EBD79A;border-right:0.5mm solid #FFF;"></td>
            <td style="width:33.34%;height:1.6mm;border-radius:1mm;background:#EDB87D;"></td>
        </tr>
    </table>

    <!-- Topo -->
    <table cellpadding="0" cellspacing="0" style="width:100%;">
        <tr>
            <td style="vertical-align:middle;">
                <div style="font-size:22px;font-weight:800;letter-spacing:1px;color:#2F353B;">' . $this->esc(defined('SITE_TITLE') ? SITE_TITLE : 'SINPORG') . '</div>
                <div style="font-size:10px;color:#7A828A;margin-top:1mm;">' . ($siglaAssoc ? $siglaAssoc . ' â€” ' : '') . 'AssociaÃ§Ã£o de Produtores</div>
            </td>
            <td style="text-align:right;vertical-align:middle;">
                <span style="background:#E8A74F;border:0.5mm solid #D9903A;padding:1.8mm 4mm;border-radius:10mm;font-size:10px;font-weight:700;color:#FFFDF7;">CARTEIRINHA DE ASSOCIADO</span>
            </td>
        </tr>
    </table>

    <table cellpadding="0" cellspacing="0" style="width:100%;margin-top:5mm;">
        <tr>
            <td style="width:62mm;vertical-align:top;">
                <div style="padding:2.4mm;">' . $fotoHtml . '</div>
            </td>
            <td style="vertical-align:top;padding-left:5mm;">
                <div style="font-size:17px;font-weight:800;line-height:6.5mm;color:#33383D;">' . $nomeCompleto . '</div>
                <table cellpadding="0" cellspacing="0" style="width:100%;margin-top:2.4mm;">
                    <tr>
                        <td style="font-size:8.5px;color:#7A828A;padding:0.7mm 0;width:34mm;">CPF</td>
                        <td style="font-size:10px;font-weight:700;padding:0.7mm 0;">' . $cpf . '</td>
                        <td style="font-size:8.5px;color:#7A828A;padding:0.7mm 0;width:26mm;">Registro CNPO</td>
                        <td style="font-size:10px;font-weight:700;padding:0.7mm 0;">' . $cnpo . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:8.5px;color:#7A828A;padding:0.7mm 0;">RG / Ã“rgÃ£o</td>
                        <td style="font-size:10px;font-weight:700;padding:0.7mm 0;">' . $rg . '</td>
                        <td style="font-size:8.5px;color:#7A828A;padding:0.7mm 0;">Nascimento</td>
                        <td style="font-size:10px;font-weight:700;padding:0.7mm 0;">' . $nasc . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:8.5px;color:#7A828A;padding:0.7mm 0;">Categoria</td>
                        <td style="font-size:10px;font-weight:700;padding:0.7mm 0;">' . $tipo . '</td>
                        <td style="font-size:8.5px;color:#7A828A;padding:0.7mm 0;">ProfissÃ£o</td>
                        <td style="font-size:10px;font-weight:700;padding:0.7mm 0;">' . $profissao . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:8.5px;color:#7A828A;padding:0.7mm 0;">AdmissÃ£o</td>
                        <td style="font-size:10px;font-weight:700;padding:0.7mm 0;">' . $admissao . '</td>
                        <td style="font-size:8.5px;color:#7A828A;padding:0.7mm 0;">Contato</td>
                        <td style="font-size:10px;font-weight:700;padding:0.7mm 0;">' . $telefone . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:8.5px;color:#7A828A;padding:0.7mm 0;">E-mail</td>
                        <td colspan="3" style="font-size:9px;padding:0.7mm 0;">' . $email . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:8.5px;color:#7A828A;padding:0.7mm 0;">Cidade/UF</td>
                        <td colspan="3" style="font-size:9px;padding:0.7mm 0;">' . $cidadeUf . ' â€¢ <strong>' . $associacaoNome . '</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- SituaÃ§Ã£o atual -->
    <table cellpadding="0" cellspacing="0" style="width:100%;margin-top:4mm;background:rgba(148,163,178,0.18);border:0.4mm solid #C6CCD2;border-radius:3mm;padding:2.5mm 4mm;">
        <tr>
            <td style="width:28mm;text-align:center;border-right:0.3mm solid #CBD1D6;">
                <div style="font-size:8px;color:#7A828A;">SituaÃ§Ã£o</div>
                <div style="font-size:11px;font-weight:800;color:' . $situacaoCor . ';margin-top:0.6mm;">' . $statusAssociado . '</div>
            </td>
            <td style="text-align:center;padding-left:4mm;border-right:0.3mm solid #CBD1D6;">
                <div style="font-size:8px;color:#7A828A;">SituaÃ§Ã£o Financeira</div>
                <div style="font-size:11px;font-weight:800;color:' . $situacaoFinCor . ';margin-top:0.6mm;">' . $situacaoFin . '</div>
                <div style="font-size:8px;color:#5A636B;margin-top:0.5mm;">' . $emDiaQtd . ' paga(s) â€¢ ' . $emAbertoQtd . ' pendente(s) â€¢ Em aberto R$ ' . $totalEmAberto . '</div>
                ' . $pendentesHtml . '
            </td>
            <td style="width:44mm;text-align:center;vertical-align:middle;padding-left:3mm;">
                <div style="text-align:center;">' . $qrHtml . '</div>
                <div style="font-size:7.5px;color:#7A828A;margin-top:1mm;">Validade: ' . $validadeTxt . '</div>
                <div style="font-size:7.5px;color:#5F8F5F;font-weight:700;">' . ($valida ? 'VÃ¡lida' : 'Necessita renovaÃ§Ã£o') . '</div>
            </td>
        </tr>
    </table>

    <div style="position:absolute;left:9mm;right:9mm;bottom:4mm;font-size:7px;color:#7A828A;text-align:center;">
        Verifique a autenticidade pelo QR Code â€¢ ' . $this->esc(defined('SITE_TITLE') ? SITE_TITLE : 'SINPORG') . ' â€¢ Documento gerado em ' . date('d/m/Y H:i') . '
    </div>
</div>
</body>
</html>';

        return $html;
    }

    /**
     * Gera o PDF da carteirinha. Retorna binÃ¡rio do PDF.
     */
    public function gerarPdf(array $d)
    {
        try {
            $mpdf = new Mpdf([
                'format' => 'A5-L',
                'margin_left' => 0,
                'margin_right' => 0,
                'margin_top' => 0,
                'margin_bottom' => 0,
            ]);
            $mpdf->SetTitle('Carteirinha - ' . trim(($d['associado']['nome'] ?? '') . ' ' . ($d['associado']['sobrenome'] ?? '')));
            $mpdf->WriteHTML($this->buildHtml($d));
            return $mpdf->Output('', 'S');
        } catch (\Exception $e) {
            error_log("Erro ao gerar PDF da carteirinha: " . $e->getMessage());
            return null;
        }
    }
}