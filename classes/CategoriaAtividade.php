<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class CategoriaAtividade {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca todas as categorias de atividades do banco de dados.
     * @return array Retorna um array de categorias.
     */
    public function getAll() {
        try {
            $stmt = $this->db->prepare("SELECT id, nome, descricao, cor FROM categorias_atividades ORDER BY nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar categorias de atividades: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca uma categoria de atividade pelo ID.
     * @param int $id O ID da categoria.
     * @return array|false Retorna os dados da categoria ou false se não encontrada.
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT id, nome, descricao, cor, associado_id FROM categorias_atividades WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar categoria por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cria uma nova categoria de atividade.
     * @param array $dados Os dados da categoria.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function create($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);

            $sql = "INSERT INTO categorias_atividades (nome, descricao, associado_id, cor, created_at) VALUES (:nome, :descricao, :associado_id, :cor, NOW())";
            $stmt = $this->db->prepare($sql);

            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':associado_id', $dados_sanitizados['associado_id']); 
            $stmt->bindParam(':cor', $dados_sanitizados['cor']);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar categoria de atividade: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza uma categoria de atividade existente.
     * @param array $dados Os dados atualizados da categoria.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function update($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);
            
            $sql = "UPDATE categorias_atividades SET nome = :nome, descricao = :descricao, cor = :cor WHERE id = :id";
            $stmt = $this->db->prepare($sql);

            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':cor', $dados_sanitizados['cor']);
            $stmt->bindParam(':id', $dados_sanitizados['id'], PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar categoria de atividade: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui uma categoria de atividade.
     * @param int $id O ID da categoria.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM categorias_atividades WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao excluir categoria de atividade: " . $e->getMessage());
            return false;
        }
    }
}
?>