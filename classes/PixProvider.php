<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

/**
 * Provedor de PIX - Suporte a múltiplos provedores (Asaas, Gerencianet, Mercado Pago)
 * Configurar PIX_PROVIDER em config.php
 */
class PixProvider
{
    private $db;
    private $provider;
    private $config;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->provider = defined('PIX_PROVIDER') ? PIX_PROVIDER : 'asaas';
        $this->loadConfig();
    }

    private function loadConfig()
    {
        $this->config = [
            'asaas' => [
                'api_key' => defined('ASAAS_API_KEY') ? ASAAS_API_KEY : '',
                'url' => defined('ASAAS_URL') ? ASAAS_URL : 'https://api.asaas.com/v3',
                'webhook_url' => SITE_URL . '/api/pix/webhook/asaas',
            ],
            'gerencianet' => [
                'client_id' => defined('GN_CLIENT_ID') ? GN_CLIENT_ID : '',
                'client_secret' => defined('GN_CLIENT_SECRET') ? GN_CLIENT_SECRET : '',
                'url' => defined('GN_URL') ? GN_URL : 'https://api.gerencianet.com.br/v1',
                'webhook_url' => SITE_URL . '/api/pix/webhook/gerencianet',
            ],
            'mercadopago' => [
                'access_token' => defined('MP_ACCESS_TOKEN') ? MP_ACCESS_TOKEN : '',
                'url' => 'https://api.mercadopago.com/v1',
                'webhook_url' => SITE_URL . '/api/pix/webhook/mercadopago',
            ],
        ];
    }

    /**
     * Cria cobrança PIX
     */
    public function criarCobranca(array $dados): array
    {
        switch ($this->provider) {
            case 'asaas':
                return $this->criarCobrancaAsaas($dados);
            case 'gerencianet':
                return $this->criarCobrancaGerencianet($dados);
            case 'mercadopago':
                return $this->criarCobrancaMercadoPago($dados);
            default:
                return ['erro' => 'Provedor PIX não configurado'];
        }
    }

    /**
     * Verifica status de cobrança
     */
    public function verificarStatus(string $txid): array
    {
        switch ($this->provider) {
            case 'asaas':
                return $this->verificarStatusAsaas($txid);
            case 'gerencianet':
                return $this->verificarStatusGerencianet($txid);
            case 'mercadopago':
                return $this->verificarStatusMercadoPago($txid);
            default:
                return ['erro' => 'Provedor PIX não configurado'];
        }
    }

    // ========== ASAAS ==========
    private function criarCobrancaAsaas(array $dados): array
    {
        $cfg = $this->config['asaas'];
        if (empty($cfg['api_key'])) {
            return ['erro' => 'ASAAS_API_KEY não configurado'];
        }

        $payload = [
            'billingType' => 'PIX',
            'customer' => $dados['customer_id'] ?? $this->getOrCreateAsaasCustomer($dados),
            'value' => (float) $dados['valor'],
            'dueDate' => $dados['data_vencimento'],
            'description' => $dados['descricao'] ?? 'Mensalidade',
            'externalReference' => $dados['referencia_id'] ?? '',
            'notificationUrl' => $cfg['webhook_url'],
        ];

        if (!empty($dados['txid'])) {
            $payload['externalReference'] = $dados['txid'];
        }

        $ch = curl_init($cfg['url'] . '/payments');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'access_token: ' . $cfg['api_key'],
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            $txid = $result['id'] ?? $result['transactionReceiptId'] ?? $dados['txid'] ?? '';
            return [
                'sucesso' => true,
                'txid' => $txid,
                'pix_copia_cola' => $result['pixCopiaCola'] ?? '',
                'pix_qrcode' => $result['qrCode'] ?? '',
                'data_expiracao' => $result['pixExpirationDate'] ?? '',
                'dados_brutos' => $result,
            ];
        }

        return ['erro' => $result['errors'][0]['description'] ?? 'Erro ao criar cobrança Asaas', 'http_code' => $httpCode];
    }

    private function getOrCreateAsaasCustomer(array $dados): string
    {
        $cfg = $this->config['asaas'];

        // Buscar customer existente por CPF/CNPJ
        if (!empty($dados['cpf_cnpj'])) {
            $ch = curl_init($cfg['url'] . '/customers?cpfCnpj=' . urlencode($dados['cpf_cnpj']));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['access_token: ' . $cfg['api_key']],
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            $result = json_decode($response, true);
            if (!empty($result['data'][0]['id'])) {
                return $result['data'][0]['id'];
            }
        }

        // Criar novo customer
        $payload = [
            'name' => $dados['nome'] ?? 'Associado',
            'email' => $dados['email'] ?? '',
            'phone' => $dados['telefone'] ?? '',
            'mobilePhone' => $dados['telefone'] ?? '',
            'cpfCnpj' => $dados['cpf_cnpj'] ?? '',
            'address' => $dados['endereco'] ?? '',
            'addressNumber' => $dados['numero'] ?? '',
            'complement' => $dados['complemento'] ?? '',
            'province' => $dados['bairro'] ?? '',
            'postalCode' => str_replace('-', '', $dados['cep'] ?? ''),
        ];

        $ch = curl_init($cfg['url'] . '/customers');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'access_token: ' . $cfg['api_key'],
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);

        return $result['id'] ?? '';
    }

    private function verificarStatusAsaas(string $txid): array
    {
        $cfg = $this->config['asaas'];
        $ch = curl_init($cfg['url'] . '/payments/' . $txid);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['access_token: ' . $cfg['api_key']],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            $statusMap = [
                'PENDING' => 'pendente',
                'RECEIVED' => 'pago',
                'CONFIRMED' => 'pago',
                'OVERDUE' => 'atrasado',
                'REFUNDED' => 'cancelado',
                'RECEIVED_IN_CASH' => 'pago',
            ];
            return [
                'status' => $statusMap[$result['status']] ?? 'pendente',
                'data_pagamento' => $result['paymentDate'] ?? null,
                'valor_pago' => $result['value'] ?? 0,
                'dados_brutos' => $result,
            ];
        }
        return ['erro' => 'Erro ao consultar status Asaas'];
    }

    // ========== GERENCIANET ==========
    private function criarCobrancaGerencianet(array $dados): array
    {
        $cfg = $this->config['gerencianet'];
        if (empty($cfg['client_id']) || empty($cfg['client_secret'])) {
            return ['erro' => 'GN_CLIENT_ID/SECRET não configurados'];
        }

        // Gerencianet requer token OAuth
        $token = $this->getGerencianetToken();
        if (!$token) {
            return ['erro' => 'Falha ao obter token Gerencianet'];
        }

        $payload = [
            'calendario' => ['expiracao' => 3600],
            'devedor' => [
                'cpf' => preg_replace('/\D/', '', $dados['cpf_cnpj'] ?? ''),
                'nome' => $dados['nome'] ?? 'Associado',
            ],
            'valor' => ['original' => number_format((float) $dados['valor'], 2, '.', '')],
            'chave' => $dados['pix_key'] ?? '',
            'solicitacaoPagador' => $dados['descricao'] ?? 'Mensalidade',
        ];

        $ch = curl_init($cfg['url'] . '/cob/imediata');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'sucesso' => true,
                'txid' => $result['txid'] ?? '',
                'pix_copia_cola' => $result['pixCopiaECola'] ?? '',
                'pix_qrcode' => $result['imagemQrcode'] ?? '',
                'dados_brutos' => $result,
            ];
        }

        return ['erro' => $result['nome'] ?? 'Erro ao criar cobrança Gerencianet'];
    }

    private function getGerencianetToken(): ?string
    {
        $cfg = $this->config['gerencianet'];
        $ch = curl_init('https://api.gerencianet.com.br/oauth/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['grant_type' => 'client_credentials']),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($cfg['client_id'] . ':' . $cfg['client_secret']),
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        return $result['access_token'] ?? null;
    }

    private function verificarStatusGerencianet(string $txid): array
    {
        $cfg = $this->config['gerencianet'];
        $token = $this->getGerencianetToken();
        if (!$token) return ['erro' => 'Token inválido'];

        $ch = curl_init($cfg['url'] . '/cob/' . $txid);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            $statusMap = ['ATIVA' => 'pendente', 'CONCLUIDA' => 'pago', 'REMOVIDA_PELO_USUARIO_RECEBEDOR' => 'cancelado', 'EXPIRADA' => 'atrasado'];
            return ['status' => $statusMap[$result['status']] ?? 'pendente', 'dados_brutos' => $result];
        }
        return ['erro' => 'Erro ao consultar Gerencianet'];
    }

    // ========== MERCADO PAGO ==========
    private function criarCobrancaMercadoPago(array $dados): array
    {
        $cfg = $this->config['mercadopago'];
        if (empty($cfg['access_token'])) {
            return ['erro' => 'MP_ACCESS_TOKEN não configurado'];
        }

        $payload = [
            'transaction_amount' => (float) $dados['valor'],
            'description' => $dados['descricao'] ?? 'Mensalidade',
            'payment_method_id' => 'pix',
            'payer' => [
                'email' => $dados['email'] ?? '',
                'first_name' => explode(' ', $dados['nome'] ?? 'Associado')[0],
                'last_name' => end(explode(' ', $dados['nome'] ?? 'Associado')),
                'identification' => ['type' => 'CPF', 'number' => preg_replace('/\D/', '', $dados['cpf_cnpj'] ?? '')],
            ],
            'notification_url' => $cfg['webhook_url'],
            'external_reference' => $dados['referencia_id'] ?? '',
        ];

        $ch = curl_init($cfg['url'] . '/payments');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $cfg['access_token'],
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'sucesso' => true,
                'txid' => $result['id'] ?? '',
                'pix_copia_cola' => $result['point_of_interaction']['transaction_data']['qr_code'] ?? '',
                'pix_qrcode' => $result['point_of_interaction']['transaction_data']['qr_code_base64'] ?? '',
                'dados_brutos' => $result,
            ];
        }
        return ['erro' => $result['message'] ?? 'Erro Mercado Pago'];
    }

    private function verificarStatusMercadoPago(string $txid): array
    {
        $cfg = $this->config['mercadopago'];
        $ch = curl_init($cfg['url'] . '/payments/' . $txid);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cfg['access_token']],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            $statusMap = ['pending' => 'pendente', 'approved' => 'pago', 'rejected' => 'cancelado', 'cancelled' => 'cancelado', 'in_process' => 'pendente'];
            return ['status' => $statusMap[$result['status']] ?? 'pendente', 'data_pagamento' => $result['date_approved'] ?? null, 'valor_pago' => $result['transaction_amount'] ?? 0, 'dados_brutos' => $result];
        }
        return ['erro' => 'Erro ao consultar Mercado Pago'];
    }
}