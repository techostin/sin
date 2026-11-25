<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Produto {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca todos os produtos do banco de dados.
     * @return array Retorna um array de produtos.
     */
    public function getAll() {
        try {
            $stmt = $this->db->prepare("SELECT id, uuid, nome, descricao, tipo, unidade_medida, preco_unitario, quantidade_produzida, data_colheita, lavoura_id, setor_id, associado_id, unidade_tipo, unidade_id FROM produtos ORDER BY nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar produtos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca os produtos vinculados a um associado específico.
     * @param int $associado_id ID do associado
     * @return array
     */
    public function getByAssociadoId($associado_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT pr.id, pr.uuid, pr.nome, pr.descricao, pr.tipo, pr.unidade_medida, pr.preco_unitario, pr.quantidade_produzida, pr.data_colheita, pr.lavoura_id, pr.setor_id, pr.associado_id, pr.unidade_tipo, pr.unidade_id
                FROM produtos pr
                INNER JOIN lavouras l ON pr.lavoura_id = l.id
                INNER JOIN propriedades p ON l.propriedade_id = p.id
                WHERE p.associado_id = :associado_id
                ORDER BY pr.nome ASC
            ");
            $stmt->bindParam(':associado_id', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar produtos por associado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca os produtos de processamento da agroindústria (sem lavoura vinculada).
     * @deprecated Prefira getProdutosDoAssociado() que respeita o vínculo de dono.
     * @return array
     */
    public function getProdutosSemLavoura() {
        try {
            $stmt = $this->db->prepare("SELECT id, uuid, nome, descricao, tipo, unidade_medida FROM produtos WHERE lavoura_id IS NULL ORDER BY nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar produtos sem lavoura: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca os produtos acessíveis a um associado, considerando o vínculo de
     * dono e de unidade produtiva (propriedade/agroindústria/criação animal).
     *
     * Regras:
     * - Produtos agrícolas (com lavoura): de propriedades em que o associado
     *   é proprietário OU membro (propriedade_membros).
     * - Produtos de agroindústria (sem lavoura): de agroindústrias em que o
     *   associado é dono OU membro (agroindustria_membros).
     * - Produtos de criação animal: de ambientes em que o associado é dono
     *   OU membro (ambiente_criacao_membros).
     *
     * @param int $associado_id ID do associado
     * @return array
     */
    public function getProdutosDoAssociado($associado_id) {
        if (!$associado_id) return [];
        try {
            $stmt = $this->db->prepare("
                SELECT pr.id, pr.uuid, pr.nome, pr.descricao, pr.tipo, pr.unidade_medida,
                       pr.preco_unitario, pr.quantidade_produzida, pr.data_colheita,
                       pr.lavoura_id, pr.setor_id, pr.associado_id, pr.unidade_tipo, pr.unidade_id,
                       COALESCE(u_nome.nome_unidade, '') AS nome_unidade
                FROM produtos pr
                LEFT JOIN (
                    SELECT 'propriedade' AS unidade_tipo, p.id AS unidade_id, p.propriedade AS nome_unidade
                    FROM propriedades p
                    WHERE p.associado_id = :assoc OR EXISTS (
                        SELECT 1 FROM propriedade_membros pm
                        WHERE pm.propriedade_id = p.id AND pm.associado_id = :assoc AND pm.excluido = 0
                    )
                ) u_nome ON u_nome.unidade_tipo = pr.unidade_tipo AND u_nome.unidade_id = pr.unidade_id
                WHERE (
                    pr.associado_id = :assoc
                    OR (
                        pr.unidade_tipo = 'propriedade' AND pr.unidade_id IN (
                            SELECT p.id FROM propriedades p
                            WHERE p.associado_id = :assoc OR EXISTS (
                                SELECT 1 FROM propriedade_membros pm
                                WHERE pm.propriedade_id = p.id AND pm.associado_id = :assoc AND pm.excluido = 0
                            )
                        )
                    )
                    OR (
                        pr.unidade_tipo = 'agroindustria' AND pr.unidade_id IN (
                            SELECT ag.id FROM agroindustrias ag
                            WHERE ag.excluido = 0 AND (
                                ag.associado_id = :assoc OR EXISTS (
                                    SELECT 1 FROM agroindustria_membros am
                                    WHERE am.agroindustria_id = ag.id AND am.associado_id = :assoc AND am.excluido = 0
                                )
                            )
                        )
                    )
                    OR (
                        pr.unidade_tipo = 'animal' AND pr.unidade_id IN (
                            SELECT amb.id FROM ambiente_criacao_animal amb
                            WHERE amb.excluido = 0 AND (
                                amb.associado_id = :assoc OR EXISTS (
                                    SELECT 1 FROM ambiente_criacao_membros em
                                    WHERE em.ambiente_id = amb.id AND em.associado_id = :assoc AND em.excluido = 0
                                )
                            )
                        )
                    )
                    OR (
                        pr.lavoura_id IS NOT NULL AND pr.lavoura_id IN (
                            SELECT l.id FROM lavouras l
                            JOIN propriedades p ON l.propriedade_id = p.id
                            WHERE p.associado_id = :assoc OR EXISTS (
                                SELECT 1 FROM propriedade_membros pm
                                WHERE pm.propriedade_id = p.id AND pm.associado_id = :assoc AND pm.excluido = 0
                            )
                        )
                    )
                )
                ORDER BY pr.nome ASC
            ");
            $stmt->bindParam(':assoc', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar produtos do associado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Lista as unidades produtivas acessíveis a um associado, para o select
     * "Unidade de origem" no cadastro/edição de produto.
     *
     * @param int $associado_id ID do associado
     * @return array [{id, tipo, nome}]
     */
    public function getUnidadesDoAssociado($associado_id) {
        if (!$associado_id) return [];
        $unidades = [];
        try {
            // Propriedades (dono ou membro)
            $stmt = $this->db->prepare("
                SELECT p.id, 'propriedade' AS tipo, p.propriedade AS nome
                FROM propriedades p
                WHERE p.associado_id = :assoc
                   OR EXISTS (SELECT 1 FROM propriedade_membros pm WHERE pm.propriedade_id = p.id AND pm.associado_id = :assoc AND pm.excluido = 0)
                ORDER BY p.propriedade ASC
            ");
            $stmt->bindParam(':assoc', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $unidades[] = $r;

            // Agroindústrias (dono ou membro)
            $stmt = $this->db->prepare("
                SELECT ag.id, 'agroindustria' AS tipo, COALESCE(NULLIF(ag.nome_fantasia, ''), NULLIF(ag.razao_social, ''), CONCAT('Agroindústria #', ag.id)) AS nome
                FROM agroindustrias ag
                WHERE ag.excluido = 0 AND (
                    ag.associado_id = :assoc
                    OR EXISTS (SELECT 1 FROM agroindustria_membros am WHERE am.agroindustria_id = ag.id AND am.associado_id = :assoc AND am.excluido = 0)
                )
                ORDER BY nome ASC
            ");
            $stmt->bindParam(':assoc', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $unidades[] = $r;

            // Ambientes de criação animal (dono ou membro)
            $stmt = $this->db->prepare("
                SELECT amb.id, 'animal' AS tipo, amb.nome_instalacao AS nome
                FROM ambiente_criacao_animal amb
                WHERE amb.excluido = 0 AND (
                    amb.associado_id = :assoc
                    OR EXISTS (SELECT 1 FROM ambiente_criacao_membros em WHERE em.ambiente_id = amb.id AND em.associado_id = :assoc AND em.excluido = 0)
                )
                ORDER BY amb.nome_instalacao ASC
            ");
            $stmt->bindParam(':assoc', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $unidades[] = $r;

            return $unidades;
        } catch (PDOException $e) {
            error_log("Erro ao buscar unidades do associado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca um produto pelo UUID.
     * @param string $uuid O UUID do produto.
     * @return array|false Retorna os dados do produto ou false se não encontrado.
     */
    public function getByUuid($uuid) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM produtos WHERE uuid = :uuid");
            $stmt->bindParam(':uuid', $uuid);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar produto por UUID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cria um novo produto.
     * @param array $dados Os dados do produto.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function create($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);
            
            // Gerar UUID se não foi fornecido
            if (empty($dados_sanitizados['uuid'])) {
                $dados_sanitizados['uuid'] = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
            }

            // Sanitização e tipagem corretas
            $quantidade = isset($dados_sanitizados['quantidade_produzida']) && $dados_sanitizados['quantidade_produzida'] !== ''
                ? Sanitizer::sanitizeFloat($dados_sanitizados['quantidade_produzida'])
                : null;

            $preco = isset($dados_sanitizados['preco_unitario']) && $dados_sanitizados['preco_unitario'] !== ''
                ? Sanitizer::sanitizeFloat($dados_sanitizados['preco_unitario'])
                : null;

            $lavouraId = isset($dados_sanitizados['lavoura_id']) && $dados_sanitizados['lavoura_id'] !== ''
                ? Sanitizer::sanitizeInt($dados_sanitizados['lavoura_id'])
                : null;

            $setorId = isset($dados_sanitizados['setor_id']) && $dados_sanitizados['setor_id'] !== ''
                ? Sanitizer::sanitizeInt($dados_sanitizados['setor_id'])
                : null;

            // Vínculo de dono e unidade produtiva
            $associadoId = isset($dados_sanitizados['associado_id']) && $dados_sanitizados['associado_id'] !== ''
                ? Sanitizer::sanitizeInt($dados_sanitizados['associado_id'])
                : null;
            $unidadeTipo = isset($dados_sanitizados['unidade_tipo']) && in_array($dados_sanitizados['unidade_tipo'], ['propriedade', 'agroindustria', 'animal'], true)
                ? $dados_sanitizados['unidade_tipo']
                : null;
            $unidadeId = isset($dados_sanitizados['unidade_id']) && $dados_sanitizados['unidade_id'] !== ''
                ? Sanitizer::sanitizeInt($dados_sanitizados['unidade_id'])
                : null;

            $sql = "INSERT INTO produtos (uuid, nome, descricao, tipo, unidade_medida, preco_unitario, quantidade_produzida, data_colheita, lavoura_id, setor_id, associado_id, unidade_tipo, unidade_id, observacoes, criado_em)
                    VALUES (:uuid, :nome, :descricao, :tipo, :unidade_medida, :preco_unitario, :quantidade_produzida, :data_colheita, :lavoura_id, :setor_id, :associado_id, :unidade_tipo, :unidade_id, :observacoes, NOW())";
            $stmt = $this->db->prepare($sql);

            // Se o valor vier da máscara (apenas dígitos), converter de centavos para decimal
            $preco = $dados_sanitizados['preco_unitario'] ?? null;
            if (is_numeric($preco) && !strpos($preco, '.') && !strpos($preco, ',')) {
                $preco = (float)$preco / 100;
            }

            $stmt->bindParam(':uuid', $dados_sanitizados['uuid']);
            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':tipo', $dados_sanitizados['tipo']);
            $stmt->bindParam(':unidade_medida', $dados_sanitizados['unidade_medida']);

            // Usa bindValue para permitir null
            $stmt->bindValue(':preco_unitario', $preco);
            $stmt->bindValue(':quantidade_produzida', $quantidade);
            $stmt->bindParam(':data_colheita', $dados_sanitizados['data_colheita']);

            if ($lavouraId === null) {
                $stmt->bindValue(':lavoura_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':lavoura_id', $lavouraId, PDO::PARAM_INT);
            }
            if ($setorId === null) {
                $stmt->bindValue(':setor_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':setor_id', $setorId, PDO::PARAM_INT);
            }
            $stmt->bindValue(':associado_id', $associadoId, $associadoId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':unidade_tipo', $unidadeTipo, $unidadeTipo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':unidade_id', $unidadeId, $unidadeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);

            $stmt->bindParam(':observacoes', $dados_sanitizados['observacoes']);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar produto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca um produto pelo ID.
     * @param int $id O ID do produto.
     * @return array|false Retorna os dados do produto ou false se não encontrado.
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM produtos WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar produto por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza um produto existente.
     * @param array $dados Os dados atualizados do produto.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function update($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);
            
            $sql = "UPDATE produtos SET 
                        nome = :nome, 
                        descricao = :descricao,
                        tipo = :tipo, 
                        unidade_medida = :unidade_medida, 
                        preco_unitario = :preco_unitario,
                        quantidade_produzida = :quantidade_produzida, 
                        data_colheita = :data_colheita, 
                        lavoura_id = :lavoura_id, 
                        setor_id = :setor_id,
                        associado_id = :associado_id,
                        unidade_tipo = :unidade_tipo,
                        unidade_id = :unidade_id,
                        observacoes = :observacoes 
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);

            $associadoId = isset($dados_sanitizados['associado_id']) && $dados_sanitizados['associado_id'] !== ''
                ? Sanitizer::sanitizeInt($dados_sanitizados['associado_id'])
                : null;
            $setorId = isset($dados_sanitizados['setor_id']) && $dados_sanitizados['setor_id'] !== ''
                ? Sanitizer::sanitizeInt($dados_sanitizados['setor_id'])
                : null;
            $unidadeTipo = isset($dados_sanitizados['unidade_tipo']) && in_array($dados_sanitizados['unidade_tipo'], ['propriedade', 'agroindustria', 'animal'], true)
                ? $dados_sanitizados['unidade_tipo']
                : null;
            $unidadeId = isset($dados_sanitizados['unidade_id']) && $dados_sanitizados['unidade_id'] !== ''
                ? Sanitizer::sanitizeInt($dados_sanitizados['unidade_id'])
                : null;

            // Se o valor vier da máscara (apenas dígitos), converter de centavos para decimal
            $preco = $dados_sanitizados['preco_unitario'] ?? null;
            if (is_numeric($preco) && !strpos($preco, '.') && !strpos($preco, ',')) {
                $preco = (float)$preco / 100;
            }

            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':tipo', $dados_sanitizados['tipo']);
            $stmt->bindParam(':unidade_medida', $dados_sanitizados['unidade_medida']);
            $stmt->bindValue(':preco_unitario', $preco);
            $stmt->bindParam(':quantidade_produzida', $dados_sanitizados['quantidade_produzida']);
            $stmt->bindParam(':data_colheita', $dados_sanitizados['data_colheita']);
            $stmt->bindParam(':lavoura_id', $dados_sanitizados['lavoura_id']);
            $stmt->bindValue(':setor_id', $setorId, $setorId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':associado_id', $associadoId, $associadoId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':unidade_tipo', $unidadeTipo, $unidadeTipo === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':unidade_id', $unidadeId, $unidadeId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindParam(':observacoes', $dados_sanitizados['observacoes']);
            $stmt->bindParam(':id', $dados_sanitizados['id'], PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar produto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui um produto.
     * @param int $id O ID do produto.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM produtos WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao excluir produto: " . $e->getMessage());
            return false;
        }
    }
}
?>