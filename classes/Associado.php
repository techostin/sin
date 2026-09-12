<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Associado
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getDb()
    {
        return $this->db;
    }

    /**
     * Obtém os tipos de vínculo do associado com associações
     */
    public function getTiposVinculo($associadoId)
    {
        $stmt = $this->db->prepare("SELECT associacao_id, tipo_vinculo FROM associado_associacao WHERE associado_id = ?");
        $stmt->execute([(int)$associadoId]);
        $vinculos = [];
        while ($v = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $vinculos[$v['associacao_id']] = $v['tipo_vinculo'];
        }
        return $vinculos;
    }

    /**
     * Cria um novo associado no banco de dados.
     * @param array $dados Os dados do associado vindos do formulário.
     * @return bool|string Retorna true em caso de sucesso, ou uma string com a mensagem de erro em caso de falha.
     */
    public function create($dados)
    {
        try {
            // Sanitiza os dados de entrada
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);

            // Verifica se o e-mail já existe
            $stmt_check = $this->db->prepare("SELECT COUNT(*) FROM associados WHERE email = :email");
            $stmt_check->bindParam(':email', $dados_sanitizados['email']);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) {
                // E-mail já cadastrado
                return "Erro: O e-mail informado já está cadastrado no sistema.";
            }

            // Criptografa a senha
            $senha_hashed = password_hash($dados_sanitizados['senha'], PASSWORD_DEFAULT);

            $sql = "INSERT INTO associados (nome, sobrenome, email, senha, cpf, rg, data_nascimento, endereco, numero, complemento, bairro, cidade, estado, cep, telefone, nivel, tipo, tipo_usuario, tem_agroindustria, status, tipo_associado, status_associado, profissao, data_admissao, data_desligamento, data_cadastro) VALUES (:nome, :sobrenome, :email, :senha, :cpf, :rg, :data_nascimento, :endereco, :numero, :complemento, :bairro, :cidade, :estado, :cep, :telefone, :nivel, :tipo, :tipo_usuario, :tem_agroindustria, :status, :tipo_associado, :status_associado, :profissao, :data_admissao, :data_desligamento, NOW())";

            $stmt = $this->db->prepare($sql);

            // Define o nível e tipo padrão para um novo cadastro
            $nivel_padrao = NIVEL_USUARIO; // Nível 3 = Usuário
            $tipo_padrao = 'Usuario';
            $status_padrao = 'pendente';

            // Perfil: agricultor | produtor_agroindustria | agricultor_agroindustria
            $tipo_usuario = isset($dados_sanitizados['tipo_usuario'])
                ? strtolower(trim((string)$dados_sanitizados['tipo_usuario']))
                : (isset($dados_sanitizados['tipo']) ? strtolower(trim((string)$dados_sanitizados['tipo'])) : '');
            if (!in_array($tipo_usuario, ['agricultor', 'produtor_agroindustria', 'agricultor_agroindustria'], true)) {
                $tipo_usuario = 'agricultor';
            }
            $tem_agroindustria = in_array($tipo_usuario, ['produtor_agroindustria', 'agricultor_agroindustria'], true) ? 1 : 0;

            // Campos do CRM de associação
            $tipo_associado = $dados_sanitizados['tipo_associado'] ?? 'Efetivo';
            $tipos_validos = ['Efetivo', 'Honorario', 'Benemerito', 'Dependente', 'Aspirante'];
            if (!in_array($tipo_associado, $tipos_validos, true)) {
                $tipo_associado = 'Efetivo';
            }
            $status_associado = $dados_sanitizados['status_associado'] ?? 'Ativo';
            $status_validos = ['Ativo', 'Inadimplente', 'Afastado', 'Desligado', 'Falecido'];
            if (!in_array($status_associado, $status_validos, true)) {
                $status_associado = 'Ativo';
            }
            $profissao = $dados_sanitizados['profissao'] ?? null;
            $data_admissao = !empty($dados_sanitizados['data_admissao']) ? $dados_sanitizados['data_admissao'] : null;
            $data_desligamento = !empty($dados_sanitizados['data_desligamento']) ? $dados_sanitizados['data_desligamento'] : null;

            // Bind dos parâmetros
            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':sobrenome', $dados_sanitizados['sobrenome']);
            $stmt->bindParam(':email', $dados_sanitizados['email']);
            $stmt->bindParam(':senha', $senha_hashed);
            $stmt->bindParam(':cpf', $dados_sanitizados['cpf']);
            $stmt->bindParam(':rg', $dados_sanitizados['rg']);
            $stmt->bindParam(':data_nascimento', $dados_sanitizados['data_nascimento']);
            $stmt->bindParam(':endereco', $dados_sanitizados['endereco']);
            $stmt->bindParam(':numero', $dados_sanitizados['numero']);
            $stmt->bindParam(':complemento', $dados_sanitizados['complemento']);
            $stmt->bindParam(':bairro', $dados_sanitizados['bairro']);
            $stmt->bindParam(':cidade', $dados_sanitizados['cidade']);
            $stmt->bindParam(':estado', $dados_sanitizados['estado']);
            $stmt->bindParam(':cep', $dados_sanitizados['cep']);
            $stmt->bindParam(':telefone', $dados_sanitizados['telefone']);
            $stmt->bindParam(':nivel', $nivel_padrao);
            $stmt->bindParam(':tipo', $tipo_padrao);
            $stmt->bindParam(':tipo_usuario', $tipo_usuario);
            $stmt->bindParam(':tem_agroindustria', $tem_agroindustria);
            $stmt->bindParam(':status', $status_padrao);
            $stmt->bindParam(':tipo_associado', $tipo_associado);
            $stmt->bindParam(':status_associado', $status_associado);
            $stmt->bindParam(':profissao', $profissao);
            $stmt->bindParam(':data_admissao', $data_admissao);
            $stmt->bindParam(':data_desligamento', $data_desligamento);

            $stmt->execute();
            $novo_id = $this->db->lastInsertId();

            // Associações (N:M) — formulário envia 'associacoes' (array) ou 'vinculo' (legado)
            $associacoes = $dados_sanitizados['associacoes'] ?? [];
            if (empty($associacoes) && !empty($dados_sanitizados['vinculo'])) {
                $associacoes = [$dados_sanitizados['vinculo']];
            }
            if (!empty($associacoes)) {
                $this->saveAssociacoes($novo_id, $associacoes);
            }

            return $novo_id;

        } catch (PDOException $e) {
            error_log("Erro ao criar associado: " . $e->getMessage());
            return "Erro de banco de dados ao cadastrar: " . $e->getMessage();
        }
    }

    /**
     * Busca todos os associados do banco de dados.
     * @return array Retorna um array de associados.
     */
    public function getAll()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    a.id, 
                    a.nome, 
                    a.sobrenome, 
                    a.email, 
                    a.telefone, 
                    a.cidade AS cidade_id, 
                    c.nome AS cidade_nome,
                    a.status, 
                    a.tipo, 
                    a.tipo_associado,
                    a.status_associado,
                    a.data_admissao,
                    a.foto_url 
                FROM 
                    associados a
                LEFT JOIN 
                    cidades c ON a.cidade = c.id 
                ORDER BY 
                    a.nome ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar associados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca um associado pelo ID.
     * @param int $id O ID do associado a ser buscado.
     * @return array|false Retorna os dados do associado ou false se não encontrado.
     */
    public function getById($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM associados WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar associado por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca todos os estados do banco de dados.
     * @return array Retorna um array de estados.
     */
    public function getEstados()
    {
        try {
            $stmt = $this->db->prepare("SELECT id, nome, uf FROM estados ORDER BY nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar estados: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Atualiza os dados de um associado existente.
     * @param int $id O ID do associado a ser atualizado.
     * @param array $dados Os novos dados do associado.
     * @return bool|string Retorna true em caso de sucesso, ou uma string com a mensagem de erro em caso de falha.
     */
    public function update($id, $dados)
    {
        try {
            // Sanitiza os dados de entrada
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);

            // Verifica se o associado existe
            $atual = $this->getById($id);
            if (!$atual) {
                return "Erro: Associado não encontrado.";
            }

            // Verifica unicidade de e-mail (se alterado)
            if (isset($dados_sanitizados['email']) && $dados_sanitizados['email'] !== $atual['email']) {
                $stmt_check = $this->db->prepare("SELECT COUNT(*) FROM associados WHERE email = :email AND id != :id");
                $stmt_check->bindParam(':email', $dados_sanitizados['email']);
                $stmt_check->bindParam(':id', $id);
                $stmt_check->execute();
                if ($stmt_check->fetchColumn() > 0) {
                    return "Erro: O e-mail informado já está em uso por outro associado.";
                }
            }

            // Verifica unicidade de CPF (se alterado e não vazio — vazio vira NULL, permitido)
            if (isset($dados_sanitizados['cpf']) && $dados_sanitizados['cpf'] !== '' && $dados_sanitizados['cpf'] !== $atual['cpf']) {
                $stmt_check_cpf = $this->db->prepare("SELECT COUNT(*) FROM associados WHERE cpf = :cpf AND id != :id");
                $stmt_check_cpf->bindParam(':cpf', $dados_sanitizados['cpf']);
                $stmt_check_cpf->bindParam(':id', $id);
                $stmt_check_cpf->execute();
                if ($stmt_check_cpf->fetchColumn() > 0) {
                    return "Erro: O CPF informado já está em uso por outro associado.";
                }
            }

            // Construção dinâmica da query de update
            $campos = [];
            $params = [':id' => $id];

            $colunas_permitidas = [
                'nome',
                'sobrenome',
                'email',
                'cpf',
                'rg',
                'data_nascimento',
                'endereco',
                'numero',
                'complemento',
                'bairro',
                'cidade',
                'estado',
                'cep',
                'telefone',
                'nivel',
                'tipo',
                'status',
                'tipo_certificacao',
                'tipo_associado',
                'status_associado',
                'profissao',
                'data_admissao',
                'data_desligamento',
                'foto_url'
            ];

            foreach ($colunas_permitidas as $coluna) {
                if (array_key_exists($coluna, $dados_sanitizados)) {
                    $valor = $dados_sanitizados[$coluna];
                    // Datas vazias viram NULL (evita gravar "0000-00-00" e permite limpar o campo)
                    if (in_array($coluna, ['data_nascimento', 'data_admissao', 'data_desligamento'], true) && $valor === '') {
                        $valor = null;
                    }
                    // Campos com UNIQUE KEY: vazio vira NULL (evita "1062 Duplicate entry '' for key 'cpf'")
                    if (in_array($coluna, ['cpf', 'registro_cnpo'], true) && $valor === '') {
                        $valor = null;
                    }
                    $campos[] = "$coluna = :$coluna";
                    $params[":$coluna"] = $valor;
                }
            }

            // Tratamento especial para senha (só atualiza se não estiver vazia)
            if (!empty($dados_sanitizados['senha'])) {
                $campos[] = "senha = :senha";
                $params[':senha'] = password_hash($dados_sanitizados['senha'], PASSWORD_DEFAULT);
            }

            // Associações (N:M) — quando o formulário envia o campo (inclusive vazio,
            // via sentinela associacoes_enviado, para permitir limpar todos os vínculos)
            $anotouAssociacoes = false;
            if (array_key_exists('associacoes_enviado', $dados_sanitizados) ||
                array_key_exists('associacoes', $dados_sanitizados) ||
                !empty($dados_sanitizados['vinculo'])) {
                $associacoes = $dados_sanitizados['associacoes'] ?? [];
                if (empty($associacoes) && !empty($dados_sanitizados['vinculo'])) {
                    $associacoes = [$dados_sanitizados['vinculo']];
                }
                // Cargos/funções (tipo_vinculo) por associação
                $cargosValidos = ['membro','presidente','vice-presidente','diretor','secretario','tesoureiro','conselho_fiscal','comissao_etica','comissao_educativa'];
                $tiposVinculo = [];
                if (isset($dados_sanitizados['cargo']) && is_array($dados_sanitizados['cargo'])) {
                    foreach ($dados_sanitizados['cargo'] as $assocKey => $cargo) {
                        $assocKey = (int)$assocKey;
                        if ($assocKey > 0) {
                            $tiposVinculo[$assocKey] = in_array((string)$cargo, $cargosValidos, true) ? (string)$cargo : 'membro';
                        }
                    }
                }
                $this->saveAssociacoes($id, $associacoes, $tiposVinculo);
                $anotouAssociacoes = true;
            }

            if (empty($campos)) {
                return $anotouAssociacoes; // Apenas vínculos alterados (ou nada a atualizar)
            }

            $campos[] = "ultima_atualizacao = NOW()";

            $sql = "UPDATE associados SET " . implode(', ', $campos) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);

            if ($stmt->execute($params)) {
                return true;
            } else {
                return "Erro ao atualizar dados.";
            }

        } catch (PDOException $e) {
            error_log("Erro ao atualizar associado: " . $e->getMessage());
            return "Erro de banco de dados ao atualizar: " . $e->getMessage();
        }
    }

    /**
     * Lista as associações ativas do sistema (para o select múltiplo).
     * @return array
     */
    public function getAssociacoesAtivas()
    {
        try {
            $stmt = $this->db->prepare("SELECT id, nome, sigla FROM associacoes WHERE status = 'ativo' ORDER BY nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar associações ativas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca as associações às quais um associado está vinculado.
     * Guarda também a relação principal (vinculo) para compatibilidade com relatórios.
     * @param int $id ID do associado.
     * @return array Lista de associações vinculadas.
     */
    public function getAssociacoesById($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT ac.id, ac.nome, ac.sigla
                FROM associado_associacao aa
                INNER JOIN associacoes ac ON ac.id = aa.associacao_id
                WHERE aa.associado_id = :id
                ORDER BY ac.nome ASC
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar associações do associado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Salva os vínculos N:M de um associado com as associações.
     * Substitui todos os vínculos atuais. Também mantém a coluna de
     * compatibilidade `vinculo` com a primeira associação selecionada,
     * preservando relatórios e APIs existentes.
     *
     * @param int   $associadoId   ID do associado.
     * @param array $associacaoIds Lista de IDs de associações.
     * @param array $tiposVinculo  Opcional: array ['assoc_id' => 'tipo_vinculo']
     * @return bool
     */
    public function saveAssociacoes($associadoId, $associacaoIds, $tiposVinculo = [])
    {
        try {
            $associadoId = (int) $associadoId;
            $associacoes = [];
            foreach ((array) $associacaoIds as $aid) {
                $aid = (int) $aid;
                if ($aid > 0 && !in_array($aid, $associacoes, true)) {
                    $associacoes[] = $aid;
                }
            }

            $stmt = $this->db->prepare("DELETE FROM associado_associacao WHERE associado_id = :id");
            $stmt->bindParam(':id', $associadoId, PDO::PARAM_INT);
            $stmt->execute();

            if (!empty($associacoes)) {
                $sql = "INSERT INTO associado_associacao (associado_id, associacao_id, tipo_vinculo) VALUES ";
                $valores = [];
                $parametros = [];
                foreach ($associacoes as $i => $assocId) {
                    $valores[] = "(:id, :ac{$i}, :tv{$i})";
                    $parametros[":ac{$i}"] = $assocId;
                    $parametros[":tv{$i}"] = $tiposVinculo[$assocId] ?? 'membro';
                }
                $sql .= implode(', ', $valores);
                $stmt = $this->db->prepare($sql);
                $stmt->bindParam(':id', $associadoId, PDO::PARAM_INT);
                foreach ($parametros as $chave => $valor) {
                    $stmt->bindValue($chave, $valor, PDO::PARAM_STR);
                }
                $stmt->execute();
            }

            // Manter coluna de compatibilidade `vinculo` com a associação principal
            $vinculo = !empty($associacoes) ? $associacoes[0] : null;
            $stmt = $this->db->prepare("UPDATE associados SET vinculo = :vinculo WHERE id = :id");
            $stmt->bindParam(':vinculo', $vinculo, $vinculo === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':id', $associadoId, PDO::PARAM_INT);
            $stmt->execute();

            return true;
        } catch (PDOException $e) {
            error_log("Erro ao salvar associações do associado: " . $e->getMessage());
            return $e->getMessage();
        }
    }

    /**
     * Atualiza o tipo_vinculo para uma associação específica do associado
     */
    public function updateTipoVinculo($associadoId, $associacaoId, $tipoVinculo)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE associado_associacao 
                SET tipo_vinculo = ? 
                WHERE associado_id = ? AND associacao_id = ?
            ");
            return $stmt->execute([$tipoVinculo, (int)$associadoId, (int)$associacaoId]);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar tipo_vinculo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Histórico de presença do associado em eventos.
     * @param int $id ID do associado.
     * @return array
     */
    public function getPresencas($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT ep.id, ep.status_presenca, ep.data_registro,
                       ev.nome AS evento_nome, ev.data_evento, ev.local
                FROM evento_presenca ep
                LEFT JOIN eventos ev ON ev.id = ep.evento_id
                WHERE ep.associado_id = ?
                ORDER BY ev.data_evento DESC
            ");
            $stmt->execute([(int) $id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar presenças do associado: " . $e->getMessage());
            return [];
        }
    }
}
?>