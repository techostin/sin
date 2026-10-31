<?php
/**
 * FichaAdesao — Ficha de Adesão ao Quadro Associativo (AAES/RS).
 *
 * Reproduz eletronicamente a "Ficha de Cadastro de Associado" (/doc):
 * identificação pessoal, endereço, contato, dados da propriedade rural,
 * produção agroecológica (com produtos/culturas), dados bancários e
 * observações, com fluxo de análise pela Diretoria/Presidência.
 *
 * Fluxo: rascunho -> pendente -> aprovada | recusada (-> pendente).
 * Enquanto não aprovada, o formulário é reexibido a cada novo acesso.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';

class FichaAdesao
{
    public const STATUS_RASCUNHO = 'rascunho';
    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_APROVADA = 'aprovada';
    public const STATUS_RECUSADA = 'recusada';

    public const TIPOS_ASSOCIADO = [
        'agricultor_familiar' => 'Agricultor(a) Familiar',
        'colaborador'         => 'Colaborador(a)',
        'honorario'           => 'Honorário(a)',
        'institucional'       => 'Institucional',
    ];

    public const ZONAS = [
        'rural'      => 'Rural',
        'urbana'     => 'Urbana',
        'periurbana' => 'Periurbana',
    ];

    public const SITUACOES_FUNDIARIAS = [
        'propria'      => 'Própria',
        'arrendada'    => 'Arrendada',
        'parceria'     => 'Parceria',
        'assentamento' => 'Assentamento',
        'outra'        => 'Outra',
    ];

    public const TIPOS_CERTIFICACAO = [
        'nao_possui' => 'Não possui',
        'auditagem'  => 'Auditagem (3ª parte)',
        'ocs'        => 'OCS',
        'opac'       => 'OPAC',
    ];

    public const SISTEMAS_PRODUCAO = [
        'horticultura'        => 'Horticultura',
        'fruticultura'        => 'Fruticultura',
        'olericultura'        => 'Olericultura',
        'graos_cereais'       => 'Grãos e cereais',
        'pecuaria_leite'      => 'Pecuária / Leite',
        'avicultura'          => 'Avicultura',
        'apicultura'          => 'Apicultura',
        'ervas_medicinais'    => 'Ervas medicinais',
        'plantas_ornamentais' => 'Plantas ornamentais',
        'agrofloresta_saf'    => 'Agrofloresta (SAF)',
        'silvicultura'        => 'Silvicultura',
        'aquicultura'         => 'Aquicultura',
        'beneficiamento'      => 'Beneficiamento',
        'artesanato_rural'    => 'Artesanato rural',
        'outros'              => 'Outros',
    ];

    public const CANAIS_COMERCIALIZACAO = [
        'feiras_livres' => 'Feiras livres',
        'paa_pnae'      => 'PAA / PNAE',
        'csa'           => 'CSA',
        'ceasa'         => 'CEASA',
        'mercado_local' => 'Mercado local',
        'ecommerce'     => 'E-commerce',
    ];

    /** Campos escalares aceitos no salvamento (whitelist). */
    private const CAMPOS = [
        'tipo_associado', 'nome_completo', 'nome_social', 'data_nascimento', 'cpf',
        'rg_orgao_emissor', 'sexo_genero', 'nacionalidade', 'estado_civil', 'escolaridade',
        'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'estado', 'cep',
        'zona', 'latitude_longitude', 'georreferenciamento_sncr',
        'telefone_whatsapp', 'telefone_alternativo', 'email',
        'nome_propriedade', 'nirf_itr', 'area_total_ha', 'area_cultivada_ha',
        'area_preservacao_ha', 'situacao_fundiaria', 'dap_caf', 'pessoas_familia',
        'pessoas_trabalham', 'renda_mensal_estimada',
        'tempo_producao_agroecologica', 'certificacao_organica', 'tipo_certificacao',
        'outra_organizacao',
        'banco', 'agencia', 'conta', 'chave_pix', 'titular_conta',
        'observacoes',
    ];

    private const CAMPOS_DECIMAL = [
        'area_total_ha', 'area_cultivada_ha', 'area_preservacao_ha', 'renda_mensal_estimada',
    ];

    private const CAMPOS_INTEIRO = ['pessoas_familia', 'pessoas_trabalham'];

    /** Campos ENUM e suas listas válidas. */
    private const CAMPOS_ENUM = [
        'tipo_associado'     => self::TIPOS_ASSOCIADO,
        'zona'               => self::ZONAS,
        'situacao_fundiaria' => self::SITUACOES_FUNDIARIAS,
        'tipo_certificacao'  => self::TIPOS_CERTIFICACAO,
    ];

    /**
     * Mensagem única para "este CPF já tem cadastro no sistema" — usada tanto
     * quando o cadastro já possui ficha de adesão registrada quanto quando já
     * está vinculado a outra conta de acesso (evita dois textos para o mesmo
     * desfecho, observação O3 da fiscalização).
     */
    private const MSG_CPF_JA_CADASTRADO = 'Este CPF já possui cadastro de associado no sistema (ficha de adesão e/ou conta de acesso já vinculada). Verifique se não é um pedido duplicado.';

    /** Colunas da tabela de produtos/culturas. */
    private const CAMPOS_PRODUTO = [
        'produto_cultura', 'variedade_especie', 'producao_estimada', 'unidade', 'destino_principal',
    ];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca a ficha (sem produtos) de um associado.
     * @return array|null
     */
    public function getByAssociadoId($associadoId)
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM ficha_adesao WHERE associado_id = ? LIMIT 1');
            $stmt->execute([(int)$associadoId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('FichaAdesao::getByAssociadoId - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Busca a ficha completa (com produtos) pelo ID da ficha.
     * @return array|null ['ficha' => row, 'produtos' => [...]]
     */
    public function getById($fichaId)
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM ficha_adesao WHERE id = ? LIMIT 1');
            $stmt->execute([(int)$fichaId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }
            return ['ficha' => $row, 'produtos' => $this->getProdutos((int)$row['id'])];
        } catch (PDOException $e) {
            error_log('FichaAdesao::getById - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Ficha completa pelo associado.
     * @return array|null ['ficha' => row, 'produtos' => [...]]
     */
    public function getCompletaByAssociadoId($associadoId)
    {
        $ficha = $this->getByAssociadoId($associadoId);
        if (!$ficha) {
            return null;
        }
        return ['ficha' => $ficha, 'produtos' => $this->getProdutos((int)$ficha['id'])];
    }

    public function getProdutos($fichaId)
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT produto_cultura, variedade_especie, producao_estimada, unidade, destino_principal
                 FROM ficha_adesao_produtos WHERE ficha_id = ? ORDER BY id ASC'
            );
            $stmt->execute([(int)$fichaId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('FichaAdesao::getProdutos - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Resumo do status da ficha do associado para o modal de primeiro acesso.
     * @return array ['status' => 'sem_ficha'|'rascunho'|'pendente'|'aprovada'|'recusada',
     *                'ficha' => array|null, 'produtos' => array]
     */
    public function statusDoAssociado($associadoId)
    {
        $ficha = $this->getByAssociadoId($associadoId);
        if (!$ficha) {
            return ['status' => 'sem_ficha', 'ficha' => null, 'produtos' => []];
        }
        $produtos = $this->getProdutos((int)$ficha['id']);
        $status = in_array($ficha['status'], [self::STATUS_RASCUNHO, self::STATUS_PENDENTE, self::STATUS_APROVADA, self::STATUS_RECUSADA], true)
            ? $ficha['status']
            : self::STATUS_RASCUNHO;
        return ['status' => $status, 'ficha' => $ficha, 'produtos' => $produtos];
    }

    /**
     * Ficha (sem produtos) vinculada a uma CONTA de sistema (usuarios.id).
     * É o caso do pedido de acesso que ainda não tem cadastro de associado.
     */
    public function getByUsuarioId($usuarioId)
    {
        $usuarioId = (int)$usuarioId;
        if ($usuarioId <= 0) {
            return null;
        }
        try {
            $stmt = $this->db->prepare('SELECT * FROM ficha_adesao WHERE usuario_id = ? LIMIT 1');
            $stmt->execute([$usuarioId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('FichaAdesao::getByUsuarioId - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Status da ficha de uma CONTA (usuario_id) no mesmo formato de statusDoAssociado().
     */
    public function statusDoUsuario($usuarioId)
    {
        $ficha = $this->getByUsuarioId($usuarioId);
        if (!$ficha) {
            return ['status' => 'sem_ficha', 'ficha' => null, 'produtos' => []];
        }
        $produtos = $this->getProdutos((int)$ficha['id']);
        $status = in_array($ficha['status'], [self::STATUS_RASCUNHO, self::STATUS_PENDENTE, self::STATUS_APROVADA, self::STATUS_RECUSADA], true)
            ? $ficha['status']
            : self::STATUS_RASCUNHO;
        return ['status' => $status, 'ficha' => $ficha, 'produtos' => $produtos];
    }

    /** ID do associado vinculado a uma conta (0 quando não há vínculo real). */
    public function associadoIdDoUsuario($usuarioId)
    {
        $usuarioId = (int)$usuarioId;
        if ($usuarioId <= 0) {
            return 0;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT a.id
                   FROM associados a
                   JOIN usuarios u ON (u.associado_id = a.id OR a.user_id = u.id)
                  WHERE u.id = ?
                  LIMIT 1'
            );
            $stmt->execute([$usuarioId]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /** ID da conta (usuarios) vinculada a um associado (0 quando não há). */
    public function usuarioIdDoAssociado($associadoId)
    {
        $associadoId = (int)$associadoId;
        if ($associadoId <= 0) {
            return 0;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT u.id
                   FROM usuarios u
                   JOIN associados a ON (a.user_id = u.id OR u.associado_id = a.id)
                  WHERE a.id = ?
                  LIMIT 1'
            );
            $stmt->execute([$associadoId]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /** Grava os vínculos da ficha (conta e/ou associado). */
    private function vincularFicha($fichaId, $usuarioId, $associadoId)
    {
        try {
            $stmt = $this->db->prepare('UPDATE ficha_adesao SET usuario_id = ?, associado_id = ? WHERE id = ?');
            $stmt->execute([
                (int)$usuarioId ?: null,
                (int)$associadoId ?: null,
                (int)$fichaId,
            ]);
            return true;
        } catch (PDOException $e) {
            error_log('FichaAdesao::vincularFicha - ' . $e->getMessage());
            return false;
        }
    }
    /**
     * Normaliza um valor de campo escalar conforme o tipo esperado.
     */
    private function normalizarCampo($campo, $valor)
    {
        if (in_array($campo, self::CAMPOS_DECIMAL, true)) {
            $valor = is_string($valor) ? str_replace(',', '.', $valor) : $valor;
            if ($valor === '' || $valor === null) {
                return null;
            }
            return filter_var($valor, FILTER_VALIDATE_FLOAT) !== false ? (float)$valor : null;
        }
        if (in_array($campo, self::CAMPOS_INTEIRO, true)) {
            if ($valor === '' || $valor === null) {
                return null;
            }
            return filter_var($valor, FILTER_VALIDATE_INT) !== false ? (int)$valor : null;
        }
        if ($campo === 'data_nascimento') {
            if (empty($valor)) {
                return null;
            }
            $dt = DateTime::createFromFormat('Y-m-d', (string)$valor);
            return ($dt && $dt->format('Y-m-d') === $valor) ? $valor : null;
        }
        if (array_key_exists($campo, self::CAMPOS_ENUM)) {
            $valor = (string)$valor;
            return array_key_exists($valor, self::CAMPOS_ENUM[$campo]) ? $valor : null;
        }
        if ($campo === 'estado') {
            $valor = strtoupper(trim((string)$valor));
            return preg_match('/^[A-Z]{2}$/', $valor) ? $valor : null;
        }
        if (is_string($valor)) {
            $valor = trim($valor);
            return $valor === '' ? null : mb_substr($valor, 0, ($campo === 'observacoes') ? 5000 : 250);
        }
        return $valor;
    }

    /**
     * Salva a ficha (rascunho ou submissão) do associado.
     *
     * @param int   $associadoId
     * @param array $post        Dados do formulário ($_POST).
     * @param bool  $submeter    true => envia para análise da Diretoria.
     * @return array ['success' => bool, 'message' => string, 'ficha_id' => int|null]
     */
    public function salvar($associadoId, array $post, $submeter = false)
    {
        $associadoId = (int)$associadoId;
        if ($associadoId <= 0) {
            return ['success' => false, 'message' => 'Associado não identificado.', 'ficha_id' => null];
        }

        $fichaAtual = $this->getByAssociadoId($associadoId);
        $preparo = $this->prepararDados($post, $submeter, $fichaAtual);
        if (!$preparo['ok']) {
            return ['success' => false, 'message' => $preparo['message'], 'ficha_id' => $fichaAtual ? (int)$fichaAtual['id'] : null];
        }

        return $this->gravarFicha($fichaAtual, $preparo['dados'], $preparo['produtos'], $submeter, [
            'associado_id' => $associadoId,
            'usuario_id'   => $this->usuarioIdDoAssociado($associadoId) ?: null,
        ]);
    }

    /**
     * Salva a ficha de um PEDIDO DE ACESSO: a ficha pertence à CONTA
     * (usuarios.id), mesmo antes de existir cadastro de associado. Na aprovação
     * o cadastro é criado a partir da ficha e vinculado à mesma conta — é o
     * mesmo ser humano nas duas tabelas, sem duplicidade de identidade.
     */
    public function salvarPorUsuario($usuarioId, array $post, $submeter = false)
    {
        $usuarioId = (int)$usuarioId;
        if ($usuarioId <= 0) {
            return ['success' => false, 'message' => 'Conta não identificada.', 'ficha_id' => null];
        }

        // Conta já vinculada a um cadastro: segue o fluxo normal e garante o vínculo da ficha
        $associadoId = $this->associadoIdDoUsuario($usuarioId);
        if ($associadoId > 0) {
            $res = $this->salvar($associadoId, $post, $submeter);
            if (!empty($res['ficha_id'])) {
                $this->vincularFicha((int)$res['ficha_id'], $usuarioId, $associadoId);
            }
            return $res;
        }

        $fichaAtual = $this->getByUsuarioId($usuarioId);
        $preparo = $this->prepararDados($post, $submeter, $fichaAtual);
        if (!$preparo['ok']) {
            return ['success' => false, 'message' => $preparo['message'], 'ficha_id' => $fichaAtual ? (int)$fichaAtual['id'] : null];
        }

        return $this->gravarFicha($fichaAtual, $preparo['dados'], $preparo['produtos'], $submeter, [
            'associado_id' => null,
            'usuario_id'   => $usuarioId,
        ]);
    }

    /**
     * Normaliza e valida os dados da ficha (compartilhado por salvar e salvarPorUsuario).
     * @return array ['ok' => bool, 'message' => string, 'dados' => array, 'produtos' => array]
     */
    private function prepararDados(array $post, $submeter, $fichaAtual)
    {
        if ($fichaAtual && $fichaAtual['status'] === self::STATUS_APROVADA) {
            return ['ok' => false, 'message' => 'Esta ficha já foi aprovada e não pode ser alterada.', 'dados' => [], 'produtos' => []];
        }

        $dados = [];
        foreach (self::CAMPOS as $campo) {
            if (array_key_exists($campo, $post)) {
                $dados[$campo] = $this->normalizarCampo($campo, $post[$campo]);
            }
        }

        foreach (['sistemas_producao' => self::SISTEMAS_PRODUCAO, 'canais_comercializacao' => self::CANAIS_COMERCIALIZACAO] as $setNome => $validos) {
            $valores = $post[$setNome] ?? null;
            if (is_array($valores)) {
                $filtrados = array_values(array_intersect(array_map('strval', $valores), array_keys($validos)));
                $dados[$setNome] = $filtrados ? implode(',', $filtrados) : null;
            }
        }

        $aceite = !empty($post['aceite_termos']) ? 1 : 0;
        if ($submeter && !$aceite) {
            return ['ok' => false, 'message' => 'É necessário declarar a veracidade das informações (aceite) para submeter a ficha.', 'dados' => [], 'produtos' => []];
        }
        $dados['aceite_termos'] = $aceite;

        if ($submeter) {
            foreach (['tipo_associado', 'nome_completo', 'data_nascimento', 'cpf', 'municipio', 'estado', 'telefone_whatsapp'] as $obrigatorio) {
                if (empty($dados[$obrigatorio])) {
                    return ['ok' => false, 'message' => 'Preencha os campos obrigatórios antes de submeter a ficha para análise.', 'dados' => [], 'produtos' => []];
                }
            }
        }

        $produtos = [];
        if (isset($post['produtos']) && is_array($post['produtos'])) {
            foreach ($post['produtos'] as $linha) {
                if (!is_array($linha)) {
                    continue;
                }
                $item = [];
                foreach (self::CAMPOS_PRODUTO as $campoP) {
                    $v = $linha[$campoP] ?? '';
                    $item[$campoP] = is_string($v) ? trim(mb_substr($v, 0, 150)) : '';
                }
                if ($item['produto_cultura'] !== '' || $item['variedade_especie'] !== '' || $item['producao_estimada'] !== '') {
                    $produtos[] = $item;
                }
                if (count($produtos) >= 5) {
                    break;
                }
            }
        }

        return ['ok' => true, 'message' => '', 'dados' => $dados, 'produtos' => $produtos];
    }

    /**
     * Insere/atualiza a ficha e seus produtos em transação.
     * $vinculos = ['associado_id' => int|null, 'usuario_id' => int|null]
     */
    private function gravarFicha($fichaAtual, array $dados, array $produtos, $submeter, array $vinculos)
    {
        try {
            $this->db->beginTransaction();
            $now = date('Y-m-d H:i:s');

            if ($fichaAtual) {
                $fichaId = (int)$fichaAtual['id'];
                $sets = [];
                $params = [];
                foreach ($dados as $campo => $valor) {
                    $sets[] = $campo . ' = :' . $campo;
                    $params[':' . $campo] = $valor;
                }
                foreach ($vinculos as $campo => $valor) {
                    // Não apaga um vínculo já existente quando o novo é nulo
                    if ($valor === null && !empty($fichaAtual[$campo])) {
                        continue;
                    }
                    $sets[] = $campo . ' = :' . $campo;
                    $params[':' . $campo] = $valor;
                }
                if ($submeter) {
                    $sets[] = 'status = :status';
                    $params[':status'] = self::STATUS_PENDENTE;
                    $sets[] = 'data_submissao = :data_submissao';
                    $params[':data_submissao'] = $now;
                    $sets[] = 'parecer_diretoria = NULL';
                    $sets[] = 'data_analise = NULL';
                    $sets[] = 'analisado_por = NULL';
                }
                $params[':ficha_id'] = $fichaId;
                $sql = 'UPDATE ficha_adesao SET ' . implode(', ', $sets) . ' WHERE id = :ficha_id';
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            } else {
                $dados = array_merge($dados, $vinculos);
                $dados['status'] = $submeter ? self::STATUS_PENDENTE : self::STATUS_RASCUNHO;
                if ($submeter) {
                    $dados['data_submissao'] = $now;
                }
                $dados = array_filter($dados, static function ($v) { return $v !== null; });
                $colunas = array_keys($dados);
                $sql = 'INSERT INTO ficha_adesao (' . implode(', ', $colunas) . ') VALUES (:' . implode(', :', $colunas) . ')';
                $params = [];
                foreach ($dados as $campo => $valor) {
                    $params[':' . $campo] = $valor;
                }
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $fichaId = (int)$this->db->lastInsertId();
            }

            $del = $this->db->prepare('DELETE FROM ficha_adesao_produtos WHERE ficha_id = ?');
            $del->execute([$fichaId]);
            if ($produtos) {
                $ins = $this->db->prepare(
                    'INSERT INTO ficha_adesao_produtos
                     (ficha_id, produto_cultura, variedade_especie, producao_estimada, unidade, destino_principal)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                foreach ($produtos as $p) {
                    $ins->execute([
                        $fichaId,
                        $p['produto_cultura'] !== '' ? $p['produto_cultura'] : null,
                        $p['variedade_especie'] !== '' ? $p['variedade_especie'] : null,
                        $p['producao_estimada'] !== '' ? $p['producao_estimada'] : null,
                        $p['unidade'] !== '' ? $p['unidade'] : null,
                        $p['destino_principal'] !== '' ? $p['destino_principal'] : null,
                    ]);
                }
            }

            $this->db->commit();

            return [
                'success'  => true,
                'message'  => $submeter
                    ? 'Ficha submetida com sucesso! Agora ela aguarda a análise da Diretoria/Presidência.'
                    : 'Rascunho salvo com sucesso. Você poderá concluir a ficha em seu próximo acesso.',
                'ficha_id' => $fichaId,
            ];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('FichaAdesao::gravarFicha - ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao salvar a ficha. Tente novamente.', 'ficha_id' => null];
        }
    }
    /**
     * Fichas pendentes de avaliação (para a DataTable da Diretoria).
     * @return array
     */
    public function listarPendentes()
    {
        try {
            return $this->db->query(
                "SELECT f.*,
                        COALESCE(NULLIF(CONCAT(COALESCE(a.nome,''), ' ', COALESCE(a.sobrenome,'')), ' '), f.nome_completo, u.nome) AS nome,
                        a.sobrenome, COALESCE(a.email, u.email, f.email) AS email,
                        COALESCE(a.telefone, u.telefone, f.telefone_whatsapp) AS telefone,
                        a.foto_url, u.id AS conta_id
                 FROM ficha_adesao f
                 LEFT JOIN associados a ON a.id = f.associado_id
                 LEFT JOIN usuarios  u ON u.id = f.usuario_id
                 WHERE f.status = 'pendente'
                 ORDER BY f.data_submissao ASC, f.id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('FichaAdesao::listarPendentes - ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Últimas fichas avaliadas (aprovadas/recusadas) para histórico da diretoria.
     * @return array
     */
    public function listarAvaliadas($limit = 10)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT f.*,
                        COALESCE(NULLIF(CONCAT(COALESCE(a.nome,''), ' ', COALESCE(a.sobrenome,'')), ' '), f.nome_completo, u.nome) AS nome,
                        a.sobrenome, u.id AS conta_id
                 FROM ficha_adesao f
                 LEFT JOIN associados a ON a.id = f.associado_id
                 LEFT JOIN usuarios  u ON u.id = f.usuario_id
                 WHERE f.status IN ('aprovada','recusada')
                 ORDER BY f.data_analise DESC, f.id DESC
                 LIMIT " . max(1, min(50, (int)$limit))
            );
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('FichaAdesao::listarAvaliadas - ' . $e->getMessage());
            return [];
        }
    }

    public function contarPendentes()
    {
        try {
            return (int)$this->db->query("SELECT COUNT(*) FROM ficha_adesao WHERE status = 'pendente'")->fetchColumn();
        } catch (PDOException $e) {
            return 0;
        }
    }

    /**
     * Notifica Admin, Diretoria e Coordenação sobre nova ficha pendente.
     *
     * @param int $fichaId
     * @param int $associadoId
     * @param string $nomeAssociado
     * @return void
     */
    public function notificarNovaFichaPendente($fichaId, $associadoId, $nomeAssociado, $usuarioId = 0)
    {
        try {
            $tipoNotificacao = 'ficha_adesao_pendente';
            $titulo = 'Nova Ficha de Adesão Aguardando Análise';
            $mensagem = "O solicitante {$nomeAssociado} submeteu a ficha de adesão ao quadro associativo (Ficha #{$fichaId}). A ficha aguarda avaliação da Diretoria/Presidência.";
            $dadosAdicional = json_encode([
                'ficha_id'     => (int)$fichaId,
                'associado_id' => (int)$associadoId ?: null,
                'usuario_id'   => (int)$usuarioId ?: null,
            ], JSON_UNESCAPED_UNICODE);
            $now = date('Y-m-d H:i:s');

            // 1. Notificar Admins (tabela usuarios, nivel = 1)
            $stmtAdmins = $this->db->prepare("SELECT id FROM usuarios WHERE nivel = ? AND status = 'ativo'");
            $stmtAdmins->execute([NIVEL_ADMIN]);
            $adminIds = $stmtAdmins->fetchAll(PDO::FETCH_COLUMN);

            if ($adminIds) {
                $insAdmin = $this->db->prepare(
                    "INSERT INTO notificacoes (usuario_id, tipo_usuario, tipo_notificacao, titulo, mensagem, dados_adicional, criada_em)
                     VALUES (?, 'sistema', ?, ?, ?, ?, ?)"
                );
                foreach ($adminIds as $adminId) {
                    $insAdmin->execute([(int)$adminId, $tipoNotificacao, $titulo, $mensagem, $dadosAdicional, $now]);
                }
            }

            // 2. Notificar Membros da Diretoria/Coordenação (associados com tipo_vinculo de diretoria)
            $tiposDiretoria = User::TIPOS_VINCULO_DIRETORIA;
            $placeholders = implode(',', array_fill(0, count($tiposDiretoria), '?'));
            $sqlDiretoria = "
                SELECT DISTINCT a.id
                FROM associados a
                INNER JOIN associado_associacao aa ON aa.associado_id = a.id
                WHERE aa.tipo_vinculo IN ({$placeholders})
                  AND a.status = 'ativo'
            ";
            $stmtDir = $this->db->prepare($sqlDiretoria);
            $stmtDir->execute($tiposDiretoria);
            $diretoriaIds = $stmtDir->fetchAll(PDO::FETCH_COLUMN);

            if ($diretoriaIds) {
                $insDir = $this->db->prepare(
                    "INSERT INTO notificacoes (usuario_id, tipo_usuario, tipo_notificacao, titulo, mensagem, dados_adicional, criada_em)
                     VALUES (?, 'associado', ?, ?, ?, ?, ?)"
                );
                foreach ($diretoriaIds as $dirId) {
                    $insDir->execute([(int)$dirId, $tipoNotificacao, $titulo, $mensagem, $dadosAdicional, $now]);
                }
            }

        } catch (PDOException $e) {
            error_log('FichaAdesao::notificarNovaFichaPendente - ' . $e->getMessage());
        }
    }

    /**
     * Aprova a ficha.
     *
     * Dois cenários:
     *  a) ficha de PEDIDO DE ACESSO (usuario_id preenchido e sem associado):
     *     cria o cadastro em "associados" a partir dos dados da ficha e vincula
     *     associado <-> conta (mesma pessoa);
     *  b) ficha de associado já cadastrado (fluxo antigo): apenas efetiva a adesão.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function aprovar($fichaId, $analisadoPor)
    {
        $completa = $this->getById((int)$fichaId);
        if (!$completa) {
            return ['success' => false, 'message' => 'Ficha não encontrada.'];
        }
        $ficha = $completa['ficha'];
        if ($ficha['status'] === self::STATUS_APROVADA) {
            return ['success' => false, 'message' => 'Esta ficha já foi aprovada.'];
        }

        $numero = 'AAES-' . str_pad((int)$ficha['id'], 5, '0', STR_PAD_LEFT);

        try {
            $this->db->beginTransaction();

            $associadoId = (int)($ficha['associado_id'] ?? 0);
            $usuarioId   = (int)($ficha['usuario_id'] ?? 0);

            // (a) Pedido de acesso: cria o cadastro do associado a partir da ficha
            if ($associadoId <= 0 && $usuarioId > 0) {
                $associadoId = $this->criarAssociadoDaFicha($ficha, $usuarioId, (int)$analisadoPor);
                if ($associadoId <= 0) {
                    throw new RuntimeException('Não foi possível criar o cadastro do associado a partir da ficha.');
                }
            }

            if ($associadoId <= 0) {
                throw new RuntimeException('Ficha sem vínculo de associado.');
            }

            // Anti-duplicidade: um associado só pode ter UMA conta vinculada.
            // Se o CPF da ficha já pertence a um cadastro ligado a outra conta,
            // a aprovação é recusada em vez de criar/roubar o vínculo.
            $contaDoAssociado = $this->usuarioIdDoAssociado($associadoId);
            if ($contaDoAssociado > 0 && $contaDoAssociado !== $usuarioId) {
                throw new RuntimeException(self::MSG_CPF_JA_CADASTRADO);
            }

            $stmt = $this->db->prepare(
                "UPDATE ficha_adesao
                 SET status = :status, numero_cadastro = :numero, data_entrada = CURDATE(),
                     data_analise = NOW(), analisado_por = :analisado_por, associado_id = :associado_id
                 WHERE id = :id"
            );
            $stmt->execute([
                ':status'        => self::STATUS_APROVADA,
                ':numero'        => $numero,
                ':analisado_por' => (int)$analisadoPor ?: null,
                ':associado_id'  => $associadoId,
                ':id'            => (int)$fichaId,
            ]);

            // Efetiva a adesão no cadastro do associado
            $stmt = $this->db->prepare(
                "UPDATE associados
                 SET data_admissao = COALESCE(data_admissao, CURDATE()),
                     status_associado = 'Ativo',
                     status = 'ativo',
                     ficha_enviada = 1,
                     ficha_aprovada = 1,
                     ficha_aprovada_em = NOW(),
                     ficha_aprovada_por = :aprovador
                 WHERE id = :associado_id"
            );
            $stmt->execute([
                ':aprovador'    => (int)$analisadoPor ?: null,
                ':associado_id' => $associadoId,
            ]);

            // Garante o vínculo nos DOIS lados: mesma pessoa, uma só identidade
            if ($usuarioId > 0) {
                $this->db->prepare('UPDATE usuarios SET associado_id = ? WHERE id = ?')->execute([$associadoId, $usuarioId]);
                $this->db->prepare('UPDATE associados SET user_id = ? WHERE id = ?')->execute([$usuarioId, $associadoId]);
            }

            $this->db->commit();
            return ['success' => true, 'message' => 'Ficha aprovada! Nº de cadastro gerado: ' . $numero];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('FichaAdesao::aprovar - ' . $e->getMessage());

            // Só mensagens de NEGÓCIO chegam ao usuário. PDOException também é
            // RuntimeException, então precisa ser tratada ANTES — erro de banco
            // nunca deve ser exibido (só registrado no log).
            if ($e instanceof PDOException) {
                $msg = 'Não foi possível aprovar a ficha (conflito de dados). Verifique se já existe cadastro/ficha para este CPF.';
            } elseif ($e instanceof RuntimeException) {
                $msg = $e->getMessage();
            } else {
                $msg = 'Erro ao aprovar a ficha.';
            }
            return ['success' => false, 'message' => $msg];
        }
    }

    /**
     * Cria o registro em "associados" a partir dos dados da ficha aprovada e o
     * vincula à conta (usuarios) que fez o pedido de acesso.
     *
     * Se o CPF informado já tiver cadastro, NÃO cria duplicata: reaproveita o
     * associado existente (é a mesma pessoa).
     *
     * @return int ID do associado (0 em caso de falha)
     */
    private function criarAssociadoDaFicha(array $ficha, $usuarioId, $aprovadoPor)
    {
        $usuarioId = (int)$usuarioId;

        $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
        $stmt->execute([$usuarioId]);
        $conta = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$conta) {
            return 0;
        }

        $cpf = preg_replace('/\D/', '', (string)($ficha['cpf'] ?? ''));
        if ($cpf !== '') {
            // Mesma pessoa já cadastrada? Reaproveita e apenas vincula.
            $stmt = $this->db->prepare("SELECT id FROM associados WHERE REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = ? LIMIT 1");
            $stmt->execute([$cpf]);
            $existente = (int)$stmt->fetchColumn();
            if ($existente > 0) {
                // A tabela ficha_adesao aceita UMA ficha por associado
                // (uq_ficha_associado). Se o cadastro já tem ficha registrada,
                // avisamos com mensagem de negócio em vez de estourar a
                // constraint (que devolveria erro SQL cru ao usuário).
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM ficha_adesao WHERE associado_id = ?");
                $stmt->execute([$existente]);
                if ((int)$stmt->fetchColumn() > 0) {
                    throw new RuntimeException(self::MSG_CPF_JA_CADASTRADO);
                }
                return $existente;
            }
        }

        $nomeCompleto = trim((string)($ficha['nome_completo'] ?? ''));
        if ($nomeCompleto === '') {
            $nomeCompleto = trim(($conta['nome'] ?? '') . ' ' . ($conta['sobrenome'] ?? ''));
        }
        $partes = explode(' ', $nomeCompleto, 2);
        $nome = $partes[0] !== '' ? $partes[0] : (string)($conta['nome'] ?? 'Associado');
        $sobrenome = $partes[1] ?? (string)($conta['sobrenome'] ?? '');

        // Mapeamentos entre os enums da ficha e do cadastro
        $mapaTipoAssociado = [
            'agricultor_familiar' => 'Efetivo',
            'colaborador'         => 'Efetivo',
            'honorario'           => 'Honorario',
            'institucional'       => 'Benemerito',
        ];
        $tipoAssociado = $mapaTipoAssociado[(string)($ficha['tipo_associado'] ?? '')] ?? 'Efetivo';
        $mapaCertificacao = [
            'ocs'         => 'OCS',
            'opac'        => 'SPG',
            'auditagem'   => 'AUDITORIA',
            'nao_possui'  => 'SPG',
        ];
        $tipoCertificacao = $mapaCertificacao[(string)($ficha['tipo_certificacao'] ?? '')] ?? 'SPG';

        // E-mail: o do cadastro é o de contato da ficha (o login continua em usuarios).
        $email = trim((string)($ficha['email'] ?? ''));
        if ($email !== '') {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM associados WHERE email = ?');
            $stmt->execute([$email]);
            if ((int)$stmt->fetchColumn() > 0) {
                $email = ''; // já usado por outro cadastro: mantém o login em usuarios
            }
        }

        $areas = (string)($conta['area_producao'] ?? '');
        $tipoUsuario = (string)($conta['tipo_usuario'] ?? 'agricultor');

        $sql = 'INSERT INTO associados
                    (nome, sobrenome, data_nascimento, cpf, rg, endereco, numero, complemento, bairro,
                     cidade, estado, cep, email, telefone, senha, nivel, status, tipo, tipo_usuario,
                     tem_agroindustria, area_producao, status_associado, tipo_associado, tipo_certificacao,
                     data_admissao, data_cadastro, user_id, ficha_enviada, ficha_aprovada, ficha_aprovada_em, ficha_aprovada_por)
                VALUES
                    (:nome, :sobrenome, :data_nascimento, :cpf, :rg, :endereco, :numero, :complemento, :bairro,
                     :cidade, :estado, :cep, :email, :telefone, :senha, :nivel, :status, :tipo, :tipo_usuario,
                     :tem_agroindustria, :area_producao, :status_associado, :tipo_associado, :tipo_certificacao,
                     CURDATE(), NOW(), :user_id, 1, 1, NOW(), :aprovado_por)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':nome'               => mb_substr($nome, 0, 100),
            ':sobrenome'          => mb_substr($sobrenome, 0, 100),
            ':data_nascimento'    => !empty($ficha['data_nascimento']) ? $ficha['data_nascimento'] : null,
            ':cpf'                => $cpf !== '' ? $cpf : null,
            ':rg'                 => $ficha['rg_orgao_emissor'] ?: null,
            ':endereco'           => $ficha['logradouro'] ?: null,
            ':numero'             => $ficha['numero'] ?: null,
            ':complemento'        => $ficha['complemento'] ?: null,
            ':bairro'             => $ficha['bairro'] ?: null,
            ':cidade'             => $ficha['municipio'] ?: null,
            ':estado'             => $ficha['estado'] ?: null,
            ':cep'                => $ficha['cep'] ?: null,
            ':email'              => $email !== '' ? $email : null,
            ':telefone'           => $ficha['telefone_whatsapp'] ?: ($conta['telefone'] ?? null),
            ':senha'              => $conta['senha'],
            ':nivel'              => (int)($conta['nivel'] ?? 3),
            ':status'             => 'ativo',
            ':tipo'               => 'Usuario',
            ':tipo_usuario'       => $tipoUsuario !== '' ? $tipoUsuario : 'agricultor',
            ':tem_agroindustria'  => (int)($conta['tem_agroindustria'] ?? 0),
            ':area_producao'      => $areas,
            ':status_associado'   => 'Ativo',
            ':tipo_associado'     => $tipoAssociado,
            ':tipo_certificacao'  => $tipoCertificacao,
            ':user_id'            => $usuarioId,
            ':aprovado_por'       => (int)$aprovadoPor ?: null,
        ]);

        return (int)$this->db->lastInsertId();
    }
    /**
     * Recusa a ficha com parecer da Diretoria; o associado poderá editar e reenviar.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function recusar($fichaId, $analisadoPor, $motivo)
    {
        $motivo = trim((string)$motivo);
        if ($motivo === '') {
            return ['success' => false, 'message' => 'Informe o motivo da recusa (parecer da Diretoria).'];
        }

        try {
            $stmt = $this->db->prepare(
                "UPDATE ficha_adesao
                 SET status = :status, parecer_diretoria = :parecer,
                     data_analise = NOW(), analisado_por = :analisado_por
                 WHERE id = :id AND status <> 'aprovada'"
            );
            $stmt->execute([
                ':status'        => self::STATUS_RECUSADA,
                ':parecer'       => mb_substr($motivo, 0, 2000),
                ':analisado_por' => (int)$analisadoPor ?: null,
                ':id'            => (int)$fichaId,
            ]);
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Ficha não encontrada ou já aprovada.'];
            }
            return ['success' => true, 'message' => 'Ficha recusada. O associado será informado no próximo acesso.'];
        } catch (PDOException $e) {
            error_log('FichaAdesao::recusar - ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao recusar a ficha.'];
        }
    }

    /**
     * Rótulos para exibição (views/JS).
     */
    public static function labelStatus($status)
    {
        $map = [
            self::STATUS_RASCUNHO => ['Rascunho', 'secondary'],
            self::STATUS_PENDENTE => ['Pendente de análise', 'warning text-dark'],
            self::STATUS_APROVADA => ['Aprovada', 'success'],
            self::STATUS_RECUSADA => ['Recusada', 'danger'],
        ];
        return $map[$status] ?? ['Rascunho', 'secondary'];
    }

    public static function labelTipoAssociado($valor)
    {
        return self::TIPOS_ASSOCIADO[$valor] ?? '—';
    }
}
