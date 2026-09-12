<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Insumo {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca todos os insumos do banco de dados com informações relacionadas de associados e fornecedores.
     * @return array Retorna um array de insumos com dados relacionados.
     */
    public function getAll() {
        try {
            $sql = "SELECT 
                        i.id, 
                        i.uuid,
                        i.nome, 
                        i.descricao, 
                        i.data_validade,
                        i.estoq_anterior, 
                        i.quant_recebida, 
                        i.quantidade_estoque,
                        a.id as associado_id,
                        CONCAT(a.nome, ' ', COALESCE(a.sobrenome, '')) as nome_associado,
                        f.id as fornecedor_id,
                        f.nome as nome_fornecedor
                    FROM insumos i
                    LEFT JOIN associados a ON i.associado_id = a.id
                    LEFT JOIN fornecedores f ON i.fornecedor_id = f.id
                    WHERE i.excluido = 0
                    ORDER BY i.nome ASC";
                    
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar insumos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca os insumos vinculados a um associado específico
     * @param int $associado_id ID do associado
     * @return array
     */
    public function getByAssociadoId($associado_id) {
        try {
            $sql = "SELECT 
                        i.id, 
                        i.uuid,
                        i.nome, 
                        i.descricao, 
                        i.data_validade,
                        i.estoq_anterior, 
                        i.quant_recebida, 
                        i.quantidade_estoque,
                        a.id as associado_id,
                        CONCAT(a.nome, ' ', COALESCE(a.sobrenome, '')) as nome_associado,
                        f.id as fornecedor_id,
                        f.nome as nome_fornecedor
                    FROM insumos i
                    LEFT JOIN associados a ON i.associado_id = a.id
                    LEFT JOIN fornecedores f ON i.fornecedor_id = f.id
                    WHERE i.associado_id = :associado_id AND i.excluido = 0
                    ORDER BY i.nome ASC";
                    
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':associado_id', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar insumos por associado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca um insumo pelo UUID.
     * @param string $uuid O UUID do insumo.
     * @return array|false Retorna os dados do insumo ou false se não encontrado.
     */
    public function getByUuid($uuid) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM insumos WHERE uuid = :uuid AND excluido = 0");
            $stmt->bindParam(':uuid', $uuid);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar insumo por UUID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cria um novo insumo.
     * @param array $dados Os dados do insumo.
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

            $sql = "INSERT INTO insumos (uuid, associado_id, nome, descricao, fornecedor_id, data_validade, estoq_anterior, quant_recebida, quantidade_estoque, aprovado_organico, tipo_insumo, insumo_aprovado_id, restricoes) VALUES (:uuid, :associado_id, :nome, :descricao, :fornecedor_id, :data_validade, :estoq_anterior, :quant_recebida, :quantidade_estoque, :aprovado_organico, :tipo_insumo, :insumo_aprovado_id, :restricoes)";
            $stmt = $this->db->prepare($sql);

            $stmt->bindParam(':uuid', $dados_sanitizados['uuid']);
            $stmt->bindParam(':associado_id', $dados_sanitizados['associado_id']);
            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':fornecedor_id', $dados_sanitizados['fornecedor_id']);
            $stmt->bindValue(':data_validade', !empty($dados_sanitizados['data_validade']) ? $dados_sanitizados['data_validade'] : null);
            $stmt->bindParam(':estoq_anterior', $dados_sanitizados['estoq_anterior']);
            $stmt->bindParam(':quant_recebida', $dados_sanitizados['quant_recebida']);
            $stmt->bindParam(':quantidade_estoque', $dados_sanitizados['quantidade_estoque']);
            $stmt->bindValue(':aprovado_organico', isset($dados_sanitizados['aprovado_organico']) ? (int)$dados_sanitizados['aprovado_organico'] : 0, PDO::PARAM_INT);
            $stmt->bindValue(':tipo_insumo', $dados_sanitizados['tipo_insumo'] ?? 'Não Permitido');
            $stmt->bindValue(':insumo_aprovado_id', !empty($dados_sanitizados['insumo_aprovado_id']) ? $dados_sanitizados['insumo_aprovado_id'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':restricoes', $dados_sanitizados['restricoes'] ?? null);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar insumo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca um insumo pelo ID.
     * @param int $id O ID do insumo.
     * @return array|false Retorna os dados do insumo ou false se não encontrado.
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM insumos WHERE id = :id AND excluido = 0");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar insumo por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza um insumo existente.
     * @param array $dados Os dados atualizados do insumo.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function update($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);
            
            $sql = "UPDATE insumos SET 
                        associado_id = :associado_id,
                        nome = :nome, 
                        descricao = :descricao, 
                        fornecedor_id = :fornecedor_id, 
                        data_validade = :data_validade,
                        estoq_anterior = :estoq_anterior, 
                        quant_recebida = :quant_recebida, 
                        quantidade_estoque = :quantidade_estoque,
                        aprovado_organico = :aprovado_organico,
                        tipo_insumo = :tipo_insumo,
                        insumo_aprovado_id = :insumo_aprovado_id,
                        restricoes = :restricoes 
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);

            $stmt->bindParam(':associado_id', $dados_sanitizados['associado_id']);
            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':fornecedor_id', $dados_sanitizados['fornecedor_id']);
            $stmt->bindValue(':data_validade', !empty($dados_sanitizados['data_validade']) ? $dados_sanitizados['data_validade'] : null);
            $stmt->bindParam(':estoq_anterior', $dados_sanitizados['estoq_anterior']);
            $stmt->bindParam(':quant_recebida', $dados_sanitizados['quant_recebida']);
            $stmt->bindParam(':quantidade_estoque', $dados_sanitizados['quantidade_estoque']);
            $stmt->bindValue(':aprovado_organico', isset($dados_sanitizados['aprovado_organico']) ? (int)$dados_sanitizados['aprovado_organico'] : 0, PDO::PARAM_INT);
            $stmt->bindValue(':tipo_insumo', $dados_sanitizados['tipo_insumo'] ?? 'Não Permitido');
            $stmt->bindValue(':insumo_aprovado_id', !empty($dados_sanitizados['insumo_aprovado_id']) ? $dados_sanitizados['insumo_aprovado_id'] : null, PDO::PARAM_INT);
            $stmt->bindValue(':restricoes', $dados_sanitizados['restricoes'] ?? null);
            $stmt->bindParam(':id', $dados_sanitizados['id'], PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar insumo: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui (soft delete) um insumo.
     * @param int $id O ID do insumo.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE insumos SET excluido = 1 WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao excluir insumo: " . $e->getMessage());
            return false;
        }
    }
}
?>