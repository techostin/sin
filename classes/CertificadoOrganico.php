<?php
/**
 * Geração do Certificado de Conformidade Orgânica
 * Layout inspirado no certificado IBD (Serralimpa / SisOrg)
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Produto.php';

class CertificadoOrganico
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca dados completos a partir do ID da certificação
     */
    public function buscarPorCertificacaoId(int $id): ?array
    {
        $sql = "SELECT c.*,
                       a.nome AS associado_nome,
                       a.sobrenome AS associado_sobrenome,
                       a.cpf,
                       a.registro_cnpo,
                       a.certificadora_id,
                       a.endereco,
                       a.numero,
                       a.complemento,
                       a.bairro,
                       a.cidade,
                       a.estado,
                       a.cep,
                       a.email,
                       a.telefone
                FROM certificacao_participativa c
                INNER JOIN associados a ON c.associado_id = a.id
                WHERE c.id = ?";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->montarDados($row);
    }

    /**
     * Monta dados a partir do formulário (pré-visualização antes de salvar)
     */
    public function buscarPorFormulario(array $input): ?array
    {
        if (empty($input['associado_id']) || !is_numeric($input['associado_id'])) {
            return null;
        }

        $stmt = $this->db->prepare("SELECT id, nome, sobrenome, cpf, registro_cnpo,
                                           certificadora_id,
                                           endereco, numero, complemento, bairro,
                                           cidade, estado, cep, email, telefone
                                    FROM associados WHERE id = ?");
        $stmt->execute([(int) $input['associado_id']]);
        $associado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$associado) {
            return null;
        }

        $row = array_merge($associado, [
            'associado_id' => $associado['id'],
            'associado_nome' => $associado['nome'],
            'associado_sobrenome' => $associado['sobrenome'],
            'tipo_certificacao' => $input['tipo_certificacao'] ?? 'SPG',
            'numero_certificado' => $input['numero_certificado'] ?? null,
            'data_emissao' => $input['data_emissao'] ?? null,
            'data_validade' => $input['data_validade'] ?? null,
            'status' => $input['status'] ?? 'Ativa',
            'organismo_certificador' => $input['organismo_certificador'] ?? null,
            'nome_grupo' => $input['nome_grupo'] ?? null,
            'observacoes' => $input['observacoes'] ?? null,
        ]);

        $produtosIds = $input['produtos'] ?? [];
        if (is_string($produtosIds)) {
            $produtosIds = array_filter(array_map('intval', explode(',', $produtosIds)));
        }

        $row['dados_adicionais'] = json_encode([
            'escopo' => $input['escopo'] ?? '',
            'produtos' => $this->resolverProdutos((int) $input['associado_id'], $produtosIds),
        ]);

        return $this->montarDados($row);
    }

    /**
     * Busca a certificadora vinculada ao usuário logado (tabela usuarios.certificadora_id)
     * com fallback para gestor_id na tabela certificadoras.
     */
    public function buscarCertificadoraDoUsuario(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return null;
        }

        try {
            // 1. Via certificadora_id na tabela usuarios
            $stmt = $this->db->prepare(
                "SELECT c.* FROM certificadoras c
                 INNER JOIN usuarios u ON u.certificadora_id = c.id
                 WHERE u.id = ? LIMIT 1"
            );
            $stmt->execute([$userId]);
            $cert = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($cert) {
                return $cert;
            }

            // 2. Fallback: via gestor_id na tabela certificadoras
            $stmt2 = $this->db->prepare(
                "SELECT * FROM certificadoras WHERE gestor_id = ? LIMIT 1"
            );
            $stmt2->execute([$userId]);
            $cert2 = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($cert2) {
                return $cert2;
            }
        } catch (PDOException $e) {
            error_log('Erro ao buscar certificadora do usuário: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Busca a certificadora vinculada ao associado do certificado.
     * Prioridade:
     *   1. associados.certificadora_id (vínculo direto do associado)
     *   2. códigos de certificadoras pelo organismo_certificador (sigla)
     *   3. certificadora do usuário logado (fallback)
     */
    public function buscarCertificadoraDoAssociado(?int $associadoId, ?string $organismoCertificador = null): ?array
    {
        try {
            // 1. Via associados.certificadora_id
            if ($associadoId) {
                $stmt = $this->db->prepare(
                    "SELECT c.* FROM certificadoras c
                     INNER JOIN associados a ON a.certificadora_id = c.id
                     WHERE a.id = ? LIMIT 1"
                );
                $stmt->execute([$associadoId]);
                $cert = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($cert) {
                    return $cert;
                }
            }

            // 2. Match pelo organismo_certificador (sigla da certificadora)
            if (!empty($organismoCertificador)) {
                $stmt2 = $this->db->prepare(
                    "SELECT * FROM certificadoras WHERE sigla = ? LIMIT 1"
                );
                $stmt2->execute([$organismoCertificador]);
                $cert2 = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($cert2) {
                    return $cert2;
                }

                // Match parcial (ex: "OPAC COCEARGS")
                $stmt3 = $this->db->prepare(
                    "SELECT * FROM certificadoras WHERE nome LIKE ? OR sigla LIKE ? LIMIT 1"
                );
                $like = '%' . $organismoCertificador . '%';
                $stmt3->execute([$like, $like]);
                $cert3 = $stmt3->fetch(PDO::FETCH_ASSOC);
                if ($cert3) {
                    return $cert3;
                }
            }
        } catch (PDOException $e) {
            error_log('Erro ao buscar certificadora do associado: ' . $e->getMessage());
        }

        // 3. Fallback: certificadora do usuário logado
        return $this->buscarCertificadoraDoUsuario();
    }

    /**
     * Resolve o caminho da logo da certificadora (arquivo físico ou URL pública).
     * @return string|null Caminho absoluto para PDF ou URL para HTML
     */
    private function resolverLogoCertificadora(array $certificadora, bool $paraPdf): ?string
    {
        $logoFile = $certificadora['logo_filename'] ?? null;
        $logoUrl = $certificadora['logo_url'] ?? null;

        if (!$logoFile && !$logoUrl) {
            return null;
        }

        // Caminho físico em uploads/certificadoras/
        $candidatos = [];
        if ($logoFile) {
            $candidatos[] = realpath(__DIR__ . '/../uploads/certificadoras/' . $logoFile);
            $candidatos[] = realpath(__DIR__ . '/../uploads/' . $logoFile);
        }
        // URL pública com logo_url (ex: ../app/uploads/certificadoras/)
        if ($logoUrl) {
            $candidatos[] = realpath(__DIR__ . '/../' . str_replace(['../app/', '/app/'], '', $logoUrl) . ($logoFile ?? ''));
        }

        foreach ($candidatos as $c) {
            if ($c && is_file($c)) {
                return $paraPdf ? $c : SITE_URL . '/uploads/certificadoras/' . basename($c);
            }
        }

        // Fallback: se logo_url é uma URL http(s)
        if ($logoUrl && preg_match('#^https?://#', $logoUrl)) {
            return $logoUrl;
        }

        return null;
    }

    /**
     * Normaliza estrutura de dados para o template
     */
    private function montarDados(array $row): array
    {
        $adicionais = [];
        if (!empty($row['dados_adicionais'])) {
            $adicionais = is_array($row['dados_adicionais'])
                ? $row['dados_adicionais']
                : json_decode($row['dados_adicionais'], true) ?? [];
        }

        $produtos = $adicionais['produtos'] ?? [];
        if (empty($produtos) && !empty($row['associado_id'])) {
            $produtos = $this->resolverProdutos((int) $row['associado_id'], []);
        }

        $propriedades = $this->buscarPropriedades((int) $row['associado_id']);

        // Busca certificadora vinculada ao associado para uso no certificado
        $certificadora = $this->buscarCertificadoraDoAssociado(
            (int) ($row['associado_id'] ?? 0),
            $row['organismo_certificador'] ?? null
        );

        return [
            'id' => $row['id'] ?? null,
            'numero_certificado' => $row['numero_certificado'] ?: $this->gerarNumeroProvisorio($row),
            'tipo_certificacao' => $row['tipo_certificacao'] ?? 'SPG',
            'status' => $row['status'] ?? 'Ativa',
            'data_emissao' => $row['data_emissao'] ?? date('Y-m-d'),
            'data_validade' => $row['data_validade'] ?? date('Y-m-d', strtotime('+1 year')),
            'organismo_certificador' => $row['organismo_certificador'] ?: ($certificadora['nome'] ?? 'Sistema Participativo de Garantia'),
            'nome_grupo' => $row['nome_grupo'] ?? '',
            'observacoes' => $row['observacoes'] ?? '',
            'escopo' => $adicionais['escopo'] ?? $this->montarEscopoPadrao($propriedades),
            'produtos' => $produtos,
            'propriedades' => $propriedades,
            'certificadora' => $certificadora ?: null,
            'produtor' => [
                'nome' => trim(($row['associado_nome'] ?? '') . ' ' . ($row['associado_sobrenome'] ?? '')),
                'cpf' => $this->formatarCpf($row['cpf'] ?? ''),
                'cnpo' => $row['registro_cnpo'] ?? '',
                'endereco' => $this->montarEndereco($row),
                'email' => $row['email'] ?? '',
                'telefone' => $row['telefone'] ?? '',
            ],
        ];
    }

    private function gerarNumeroProvisorio(array $row): string
    {
        $ano = date('Y');
        $id = $row['id'] ?? str_pad((string) ($row['associado_id'] ?? '0'), 4, '0', STR_PAD_LEFT);
        return "SPG-{$id}/{$ano}";
    }

    private function montarEndereco(array $row): string
    {
        $partes = array_filter([
            trim(($row['endereco'] ?? '') . (!empty($row['numero']) ? ', ' . $row['numero'] : '')),
            $row['complemento'] ?? '',
            $row['bairro'] ?? '',
            trim(($row['cidade'] ?? '') . (!empty($row['estado']) ? ' - ' . $row['estado'] : '')),
            !empty($row['cep']) ? 'CEP ' . $row['cep'] : '',
        ]);

        return implode(' — ', $partes) ?: 'Endereço não informado';
    }

    private function montarEscopoPadrao(array $propriedades): string
    {
        if (empty($propriedades)) {
            return 'Produção primária vegetal — cultivo orgânico';
        }

        $nomes = array_column($propriedades, 'propriedade');
        return 'Produção primária vegetal — propriedade(s): ' . implode(', ', $nomes);
    }

    private function buscarPropriedades(int $associadoId): array
    {
        $stmt = $this->db->prepare(
            "SELECT propriedade, endereco, cidade, estado, area_total
             FROM propriedades WHERE associado_id = ? ORDER BY propriedade"
        );
        $stmt->execute([$associadoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function resolverProdutos(int $associadoId, array $produtoIds): array
    {
        $produtoModel = new Produto();
        $todos = $produtoModel->getByAssociadoId($associadoId);

        if (empty($produtoIds)) {
            return $todos;
        }

        $ids = array_map('intval', $produtoIds);
        return array_values(array_filter($todos, fn($p) => in_array((int) $p['id'], $ids, true)));
    }

    private function formatarCpf(?string $cpf): string
    {
        $cpf = preg_replace('/\D/', '', $cpf ?? '');
        if (strlen($cpf) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cpf);
        }
        if (strlen($cpf) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cpf);
        }
        return $cpf ?: '—';
    }

    private function formatarData(?string $data): string
    {
        if (empty($data)) {
            return '—';
        }
        return date('d/m/Y', strtotime($data));
    }

    private function statusRotulo(string $status): string
    {
        $map = [
            'Ativa' => 'ORGÂNICO',
            'Em Conversão' => 'EM CONVERSÃO',
            'Vencida' => 'VENCIDO',
            'Suspensa' => 'SUSPENSO',
            'Cancelada' => 'CANCELADO',
        ];
        return $map[$status] ?? strtoupper($status);
    }

    /**
     * HTML do certificado (visualização e base do PDF)
     */
    public function gerarHtml(array $dados, bool $paraPdf = false): string
    {
        // Logo da certificadora do usuário logado (canto esquerdo)
        $certificadora = $dados['certificadora'] ?? null;
        $logoCertSrc = null;
        if ($certificadora) {
            $logoCertSrc = $this->resolverLogoCertificadora($certificadora, $paraPdf);
        }
        if (!$logoCertSrc) {
            $logoPath = realpath(__DIR__ . '/../assets/img/logo-organico-brasil.svg');
            $logoCertSrc = $paraPdf && $logoPath
                ? $logoPath
                : SITE_URL . '/assets/img/logo-organico-brasil.svg';
        }

        // Selo SisOrg (canto direito)
        $seloPath = realpath(__DIR__ . '/../uploads/selo_SisOrg.jpeg');
        $seloSrc = $paraPdf && $seloPath
            ? $seloPath
            : SITE_URL . '/uploads/selo_SisOrg.jpeg';

        $organismo = htmlspecialchars($dados['organismo_certificador']);
        $produtor = htmlspecialchars($dados['produtor']['nome']);
        $numero = htmlspecialchars($dados['numero_certificado']);
        $tipo = htmlspecialchars($dados['tipo_certificacao']);
        $statusLabel = htmlspecialchars($this->statusRotulo($dados['status']));
        $escopo = htmlspecialchars($dados['escopo']);
        $emissao = $this->formatarData($dados['data_emissao']);
        $validade = $this->formatarData($dados['data_validade']);
        $cpf = htmlspecialchars($dados['produtor']['cpf']);
        $endereco = htmlspecialchars($dados['produtor']['endereco']);
        $cnpo = !empty($dados['produtor']['cnpo'])
            ? '<tr><td class="label">Registro CNPO:</td><td>' . htmlspecialchars($dados['produtor']['cnpo']) . '</td></tr>'
            : '';

        $produtosHtml = '';
        if (!empty($dados['produtos'])) {
            foreach ($dados['produtos'] as $i => $prod) {
                $nome = htmlspecialchars($prod['nome'] ?? '—');
                $tipoProd = htmlspecialchars($prod['tipo'] ?? 'In natura');
                $unidade = htmlspecialchars($prod['unidade_medida'] ?? '—');
                $produtosHtml .= "<tr>
                    <td class=\"center\">" . ($i + 1) . "</td>
                    <td>{$nome}</td>
                    <td>{$tipoProd}</td>
                    <td class=\"center\">{$unidade}</td>
                    <td class=\"center\">{$statusLabel}</td>
                </tr>";
            }
        } else {
            $produtosHtml = '<tr><td colspan="5" class="center muted">Nenhum produto cadastrado para este produtor</td></tr>';
        }

        $grupoHtml = !empty($dados['nome_grupo'])
            ? '<p class="grupo"><strong>Grupo SPG/OCS:</strong> ' . htmlspecialchars($dados['nome_grupo']) . '</p>'
            : '';

        $obsHtml = !empty($dados['observacoes'])
            ? '<div class="obs"><strong>Observações:</strong> ' . nl2br(htmlspecialchars($dados['observacoes'])) . '</div>'
            : '';

        return <<<HTML
<style>
    @page { margin: 10mm; }
    .cert-page { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1a1a1a; font-size: 10pt; }
    .cert-border { border: 3px double #2d6a2d; padding: 10px 14px; }
    .cert-inner { border: 1px solid #8fbc8f; padding: 10px 14px; }
    .cert-header { width: 100%; border-collapse: collapse; margin-bottom: 8px; border-bottom: 2px solid #2d6a2d; }
    .cert-header .logo-left { width: 25%; text-align: left; vertical-align: middle; padding-bottom: 8px; }
    .cert-header .logo-right { width: 25%; text-align: right; vertical-align: middle; padding-bottom: 8px; }
    .cert-header .header-center { width: 50%; text-align: center; vertical-align: middle; padding-bottom: 8px; }
    .cert-header img { margin: 0; padding: 0; }
    .cert-org { font-size: 11pt; font-weight: bold; color: #2d6a2d; letter-spacing: 0.5px; }
    .cert-suborg { font-size: 8pt; color: #555; margin-top: 2px; }
    .cert-title { text-align: center; margin: 8px 0 2px; }
    .cert-title h1 { font-size: 13pt; color: #2d6a2d; margin: 0; letter-spacing: 1px; text-transform: uppercase; }
    .cert-title h2 { font-size: 10pt; color: #444; margin: 4px 0 0; font-weight: normal; }
    .cert-number { text-align: center; font-size: 10pt; font-weight: bold; color: #1a1a1a; margin: 6px 0 8px; }
    .cert-number span { background: #f0f7f0; padding: 4px 14px; border: 1px solid #2d6a2d; }
    .cert-body { line-height: 1.3; text-align: justify; margin-bottom: 12px; }
    .info-table { width: 100%; border-collapse: collapse; margin: 10px 0 14px; }
    .info-table td { padding: 2px 5px; vertical-align: top; font-size: 9.5pt; }
    .info-table .label { font-weight: bold; width: 28%; color: #2d6a2d; white-space: nowrap; }
    .products-title { font-size: 9pt; font-weight: bold; color: #2d6a2d; margin: 8px 0 4px; text-transform: uppercase; }
    .products-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .products-table th { background: #2d6a2d; color: #fff; padding: 3px 6px; font-size: 8.5pt; text-align: left; }
    .products-table td { border: 1px solid #ccc; padding: 2px 6px; font-size: 9pt; }
    .products-table .center { text-align: center; }
    .products-table .muted { color: #888; font-style: italic; }
    .validity-box { background: #f0f7f0; border: 1px solid #2d6a2d; padding: 5px 12px; margin: 6px 0; text-align: center; }
    .validity-box strong { color: #2d6a2d; }
    .legal { font-size: 6.5pt; color: #666; text-align: justify; margin-top: 2px; line-height: 1.25; border-top: 1px solid #ddd; padding-top: 6px; }
    .signatures { margin-top: 8px; width: 100%; border-collapse: collapse; }
    .sig-box { width: 48%; text-align: center; vertical-align: bottom; }
    .sig-line { border-top: 1px solid #333; margin: 8px 20px 2px; padding-top: 4px; font-size: 8.5pt; }
    .grupo { font-size: 8.5pt; margin: 4px 0; }
    .obs { font-size: 8pt; margin-top: 2px; color: #555; }
    .badge-status { display: inline-block; background: #2d6a2d; color: #fff; padding: 2px 10px; font-size: 9pt; border-radius: 3px; }
</style>
<div class="cert-page">
<div class="cert-border">
<div class="cert-inner">
    <table class="cert-header">
        <tr>
            <td class="logo-left">
                <img src="{$logoCertSrc}" alt="Logo Certificadora" width="80" height="80">
            </td>
            <td class="header-center">
                <div class="cert-org">{$organismo}</div>
                <div class="cert-suborg">Certificação Orgânica — Portaria MAPA nº 52/2021</div>
            </td>
            <td class="logo-right">
                <img src="{$seloSrc}" alt="Selo SisOrg Brasil" width="150" height="66">
            </td>
        </tr>
    </table>

    <div class="cert-title">
        <h1>Certificado de Conformidade Orgânica</h1>
        <h2>Sistema Brasileiro de Avaliação da Conformidade Orgânica (SisOrg)</h2>
    </div>

    <div class="cert-number">Nº <span>{$numero}</span></div>

    <div class="cert-body">
        Certificamos que o(a) produtor(a) abaixo identificado(a) cumpre os requisitos
        estabelecidos pela Lei nº 10.831/2003 e normas correlatas, estando em conformidade
        com o escopo de certificação indicado neste documento.
    </div>

    <table class="info-table">
        <tr><td class="label">Produtor(a):</td><td><strong>{$produtor}</strong></td></tr>
        <tr><td class="label">CPF/CNPJ:</td><td>{$cpf}</td></tr>
        {$cnpo}
        <tr><td class="label">Endereço:</td><td>{$endereco}</td></tr>
        <tr><td class="label">Tipo:</td><td>{$tipo}</td></tr>
        <tr><td class="label">Escopo:</td><td>{$escopo}</td></tr>
        <tr><td class="label">Situação:</td><td><span class="badge-status">{$statusLabel}</span></td></tr>
    </table>

    {$grupoHtml}

    <div class="products-title">Produtos / Culturas Certificados</div>
    <table class="products-table">
        <thead>
            <tr>
                <th style="width:6%">#</th>
                <th style="width:40%">Produto / Cultura</th>
                <th style="width:20%">Categoria</th>
                <th style="width:14%">Unidade</th>
                <th style="width:20%">Status</th>
            </tr>
        </thead>
        <tbody>
            {$produtosHtml}
        </tbody>
    </table>

    <div class="validity-box">
        <strong>Período de Validade:</strong>
        de {$emissao} até {$validade}
    </div>

    {$obsHtml}

    <div class="legal">
        Este certificado atesta a conformidade orgânica do produtor acima identificado,
        conforme o Sistema Participativo de Garantia (SPG) ou Organização de Controle Social (OCS),
        nos termos da Portaria MAPA nº 52, de 3 de dezembro de 2021, e demais normas aplicáveis.
        A validade deste certificado está condicionada ao cumprimento contínuo dos requisitos
        do sistema de certificação participativa. Produtos comercializados como orgânicos
        devem exibir o selo Brasil Orgânico/SisOrg conforme legislação vigente.
    </div>

    <table class="signatures">
        <tr>
            <td class="sig-box">
                <div class="sig-line">Responsável pelo SPG/OCS<br><em>Organismo Certificador</em></div>
            </td>
            <td class="sig-box">
                <div class="sig-line">Produtor(a) Certificado(a)<br><em>{$produtor}</em></div>
            </td>
        </tr>
    </table>
</div>
</div>
</div>
HTML;
    }

    /**
     * Gera PDF binário via mPDF
     */
    public function gerarPdf(array $dados): array
    {
        require_once __DIR__ . '/../vendor/autoload.php';

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 12,
        ]);

        $html = $this->gerarHtml($dados, true);
        $mpdf->WriteHTML($html);

        $nomeArquivo = 'Certificado_Organico_' .
            preg_replace('/[^a-zA-Z0-9_-]/', '_', $dados['produtor']['nome']) .
            '_' . date('Ymd') . '.pdf';

        return [
            'content' => $mpdf->Output($nomeArquivo, 'S'),
            'filename' => $nomeArquivo,
        ];
    }
}
