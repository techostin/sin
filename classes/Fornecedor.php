<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Fornecedor {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Cria um novo fornecedor no banco de dados.
     * @param array $dados Os dados do fornecedor vindos do formulário.
     * @return bool|string|int Retorna o novo id em caso de sucesso, ou uma string com a mensagem de erro em caso de falha.
     */
    public function create($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);

            $sql = "INSERT INTO fornecedores (nome, razao_social, representante, cnpj, email, telefone, produto_fornecido, endereco, bairro, cidade, estado, status, data_cadastro) 
                    VALUES (:nome, :razao_social, :representante, :cnpj, :email, :telefone, :produto_fornecido, :endereco, :bairro, :cidade, :estado, :status, NOW())";
            
            $stmt = $this->db->prepare($sql);

            $status_padrao = 'ativo';

            // Bind dos parâmetros
            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':razao_social', $dados_sanitizados['razao_social']);
            $stmt->bindParam(':representante', $dados_sanitizados['representante']);
            $stmt->bindParam(':cnpj', $dados_sanitizados['cnpj']);
            $stmt->bindParam(':email', $dados_sanitizados['email']);
            $stmt->bindParam(':telefone', $dados_sanitizados['telefone']);
            $stmt->bindParam(':produto_fornecido', $dados_sanitizados['produto_fornecido']);
            $stmt->bindParam(':endereco', $dados_sanitizados['endereco']);
            $stmt->bindParam(':bairro', $dados_sanitizados['bairro']);
            $stmt->bindParam(':cidade', $dados_sanitizados['cidade']);
            $stmt->bindParam(':estado', $dados_sanitizados['estado']);
            $stmt->bindParam(':status', $status_padrao);

            $stmt->execute();
            return (int)$this->db->lastInsertId();

        } catch (PDOException $e) {
            // Verifica se o erro é de chave duplicada (UNIQUE constraint)
            if ($e->getCode() == 23000) {
                if (strpos(strtolower($e->getMessage()), 'cnpj') !== false) {
                    return "Erro: O CNPJ informado já está cadastrado.";
                }
                if (strpos(strtolower($e->getMessage()), 'email') !== false) {
                    return "Erro: O e-mail informado já está em uso.";
                }
            }
            error_log("Erro ao criar fornecedor: " . $e->getMessage());
            return "Erro de banco de dados ao cadastrar: " . $e->getMessage();
        }
    }

    /**
     * Busca todos os fornecedores do banco de dados com nomes de cidade e estado.
     * @return array Retorna um array de fornecedores.
     */
    public function getAll() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    f.id, f.nome, f.representante, 
                    c.nome as cidade_nome, 
                    e.uf as estado_sigla 
                FROM fornecedores f 
                LEFT JOIN cidades c ON f.cidade = c.id 
                LEFT JOIN estados e ON f.estado = e.id 
                ORDER BY f.nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar fornecedores: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Lista TODAS as unidades (3 tipos) — uso exclusivo de gestor/admin na
     * edição de fornecedores sem dono conhecido.
     * @return array [{id, tipo, nome}]
     */
    public function getTodasUnidades() {
        $unidades = [];
        try {
            $stmt = $this->db->query("
                SELECT p.id, 'propriedade' AS tipo, p.propriedade AS nome
                FROM propriedades p
                ORDER BY p.propriedade ASC");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $unidades[] = $r;

            $stmt = $this->db->query("
                SELECT ag.id, 'agroindustria' AS tipo, COALESCE(NULLIF(ag.nome_fantasia, ''), NULLIF(ag.razao_social, ''), CONCAT('Agroindústria #', ag.id)) AS nome
                FROM agroindustrias ag
                WHERE ag.excluido = 0
                ORDER BY nome ASC");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $unidades[] = $r;

            $stmt = $this->db->query("
                SELECT amb.id, 'animal' AS tipo, amb.nome_instalacao AS nome
                FROM ambiente_criacao_animal amb
                WHERE amb.excluido = 0
                ORDER BY amb.nome_instalacao ASC");
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) $unidades[] = $r;
        } catch (PDOException $e) {
            error_log("Erro ao buscar todas as unidades: " . $e->getMessage());
        }
        return $unidades;
    }

    /**
     * Busca um fornecedor pelo ID (com dados de endereço completos).
     * @param int $id
     * @return array|false
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT f.*, c.nome as cidade_nome, e.uf as estado_sigla
                FROM fornecedores f
                LEFT JOIN cidades c ON f.cidade = c.id
                LEFT JOIN estados e ON f.estado = e.id
                WHERE f.id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar fornecedor por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retorna os fornecedores visíveis a um associado, considerando os vínculos
     * na pivot fornecedor_unidades para os TRÊS tipos de unidade
     * (propriedade / agroindústria / criação animal), como DONO ou MEMBRO.
     *
     * Regras:
     * - O vínculo fornecedor->unidade é resolvido em unidades onde o associado
     *   é proprietário OU integra (propriedade_membros, agroindustria_membros,
     *   ambiente_criacao_membros).
     * - Gestor/Admin recebe a lista completa via getAll().
     *
     * @param int $associado_id
     * @return array
     */
    public function getVisiveisParaAssociado($associado_id) {
        if (!$associado_id) return [];
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT
                    f.id, f.nome, f.representante, f.produto_fornecido,
                    c.nome as cidade_nome,
                    e.uf as estado_sigla
                FROM fornecedores f
                JOIN fornecedor_unidades fu ON fu.fornecedor_id = f.id AND fu.excluido = 0
                LEFT JOIN cidades c ON f.cidade = c.id
                LEFT JOIN estados e ON f.estado = e.id
                WHERE f.status = 'ativo' AND (
                    -- 1) propriedade: dono ou membro
                    (fu.unidade_tipo = 'propriedade' AND fu.unidade_id IN (
                        SELECT p.id FROM propriedades p
                        WHERE p.associado_id = :assoc
                           OR EXISTS (SELECT 1 FROM propriedade_membros pm
                                      WHERE pm.propriedade_id = p.id AND pm.associado_id = :assoc AND pm.excluido = 0)
                    ))
                    OR
                    -- 2) agroindústria: dono ou membro
                    (fu.unidade_tipo = 'agroindustria' AND fu.unidade_id IN (
                        SELECT ag.id FROM agroindustrias ag
                        WHERE ag.excluido = 0 AND (
                            ag.associado_id = :assoc
                            OR EXISTS (SELECT 1 FROM agroindustria_membros am
                                       WHERE am.agroindustria_id = ag.id AND am.associado_id = :assoc AND am.excluido = 0)
                        )
                    ))
                    OR
                    -- 3) criação animal: dono ou membro
                    (fu.unidade_tipo = 'animal' AND fu.unidade_id IN (
                        SELECT amb.id FROM ambiente_criacao_animal amb
                        WHERE amb.excluido = 0 AND (
                            amb.associado_id = :assoc
                            OR EXISTS (SELECT 1 FROM ambiente_criacao_membros em
                                       WHERE em.ambiente_id = amb.id AND em.associado_id = :assoc AND em.excluido = 0)
                        )
                    ))
                )
                ORDER BY f.nome ASC
            ");
            $stmt->bindParam(':assoc', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar fornecedores do associado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Lista as unidades vinculadas a um fornecedor (para edição/preenchimento).
     * @param int $fornecedor_id
     * @return array [{unidade_tipo, unidade_id, nome_unidade}]
     */
    public function getUnidadesPorFornecedor($fornecedor_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT fu.unidade_tipo, fu.unidade_id, fu.associado_id,
                       COALESCE(u.nome_unidade, CONCAT('Unidade #', fu.unidade_id)) AS nome_unidade
                FROM fornecedor_unidades fu
                LEFT JOIN (
                    SELECT 'propriedade' AS unidade_tipo, id AS unidade_id, propriedade AS nome_unidade FROM propriedades
                    UNION ALL
                    SELECT 'agroindustria', id, COALESCE(NULLIF(nome_fantasia,''), NULLIF(razao_social,''), CONCAT('Agroindústria #', id)) FROM agroindustrias WHERE excluido = 0
                    UNION ALL
                    SELECT 'animal', id, nome_instalacao FROM ambiente_criacao_animal WHERE excluido = 0
                ) u ON u.unidade_tipo = fu.unidade_tipo AND u.unidade_id = fu.unidade_id
                WHERE fu.fornecedor_id = :fid AND fu.excluido = 0
                ORDER BY fu.unidade_tipo ASC, fu.unidade_id ASC
            ");
            $stmt->bindParam(':fid', $fornecedor_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar unidades do fornecedor: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Substitui os vínculos (unidades) de um fornecedor por um novo conjunto.
     * @param int $fornecedor_id
     * @param array $unidades Array de ['tipo'=>, 'id'=>]
     * @param int|null $associado_id Dono do vínculo (session)
     */
    public function substituirUnidades($fornecedor_id, array $unidades, $associado_id = null) {
        try {
            $this->db->beginTransaction();
            // Soft delete dos vínculos atuais
            $up = $this->db->prepare("UPDATE fornecedor_unidades SET excluido = 1 WHERE fornecedor_id = :fid");
            $up->bindParam(':fid', $fornecedor_id, PDO::PARAM_INT);
            $up->execute();

            $ins = $this->db->prepare("INSERT INTO fornecedor_unidades (fornecedor_id, unidade_tipo, unidade_id, associado_id)
                VALUES (:fid, :tipo, :uid, :assoc)
                ON DUPLICATE KEY UPDATE excluido = 0, associado_id = VALUES(associado_id)");
            foreach ($unidades as $u) {
                $tipo = $u['tipo'] ?? '';
                $uid  = (int)($u['id'] ?? 0);
                if (!in_array($tipo, ['propriedade', 'agroindustria', 'animal'], true) || $uid <= 0) {
                    continue;
                }
                $ins->bindValue(':fid', $fornecedor_id, PDO::PARAM_INT);
                $ins->bindValue(':tipo', $tipo, PDO::PARAM_STR);
                $ins->bindValue(':uid', $uid, PDO::PARAM_INT);
                $ins->bindValue(':assoc', $associado_id, $associado_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                $ins->execute();
            }
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            error_log("Erro ao vincular unidades do fornecedor: " . $e->getMessage());
            return false;
        }
    }
}
?>