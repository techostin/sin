<?php
/**
 * Envio de e-mails transacionais via SDK oficial do Resend.
 */
require_once __DIR__ . '/../vendor/autoload.php';

class Mailer {
    /**
     * Envia um e-mail transacional pelo Resend.
     *
     * @param string $to          Destinatário
     * @param string $subject     Assunto
     * @param string $html        Corpo em HTML
     * @param string|null $text   Versão em texto puro (opcional)
     * @param array $attachments  Lista de anexos: [['content' => base64, 'filename' => 'x.pdf', 'content_type' => 'application/pdf']]
     *
     * @return array ['success' => bool, 'id' => ?string, 'error' => ?string]
     */
    public static function send($to, $subject, $html, $text = null, $attachments = []) {
        $apiKey = defined('RESEND_API_KEY') ? RESEND_API_KEY : '';
        if (empty($apiKey) || strpos($apiKey, 're_') !== 0) {
            return ['success' => false, 'error' => 'RESEND_API_KEY não configurada.'];
        }

        try {
            $client = Resend::client($apiKey);
            $payload = [
                'from'    => (defined('EMAIL_FROM_NAME') && EMAIL_FROM_NAME ? EMAIL_FROM_NAME . ' <' : '') . EMAIL_FROM_ADDRESS . (defined('EMAIL_FROM_NAME') && EMAIL_FROM_NAME ? '>' : ''),
                'to'      => [$to],
                'subject' => $subject,
                'html'    => $html,
                'text'    => $text !== null ? $text : strip_tags($html),
            ];
            foreach ((array) $attachments as $att) {
                if (!empty($att['content']) && !empty($att['filename'])) {
                    $item = ['content' => $att['content'], 'filename' => $att['filename']];
                    if (!empty($att['content_type'])) {
                        $item['content_type'] = $att['content_type'];
                    }
                    $payload['attachments'][] = $item;
                }
            }

            $result = $client->emails->send($payload);

            return ['success' => true, 'id' => $result->id ?? null, 'error' => null];
        } catch (Exception $e) {
            error_log("Mailer/Resend: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Renderiza o template HTML padrão dos e-mails do sistema.
     */
    public static function layout($titulo, $conteudoHtml) {
        $logo = defined('SINPORG_LOGO_URL') ? SINPORG_LOGO_URL : '';
        return '
        <div style="background:#f3f6f5;padding:32px 16px;font-family:Segoe UI,Roboto,Arial,sans-serif;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 8px 28px rgba(0,0,0,0.10);">
                <tr>
                    <td style="background:linear-gradient(135deg,#0f5132,#198754 60%,#20c997);padding:24px 28px;text-align:center;">
                        ' . ($logo ? '<img src="' . $logo . '" alt="' . (defined('SITE_TITLE') ? SITE_TITLE : '') . '" style="max-width:140px;height:auto;background:#fff;border-radius:8px;padding:6px;">' : '') . '
                        <h1 style="margin:14px 0 0;font-size:18px;color:#ffffff;font-weight:700;">' . htmlspecialchars($titulo) . '</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;color:#1f2937;font-size:14px;line-height:1.6;">
                        ' . $conteudoHtml . '
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 28px;background:#f8faf9;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px;text-align:center;">
                        ' . (defined('SITE_TITLE') ? SITE_TITLE : 'SINPORG') . ' &mdash; Sistema Integrado de Produção Orgânica<br>
                        Se você não solicitou este e-mail, pode ignorá-lo com segurança.
                    </td>
                </tr>
            </table>
        </div>';
    }
}
