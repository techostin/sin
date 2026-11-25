<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Ocorrencia {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Retorna todas as ocorrências de um determinado associado.
     */
    public function getAllByAssociadoId($associado_id) {
        try {
            $sql = "SELECT 
                        om.*, 
                        p.propriedade, 
                        l.nome as lavoura_nome,
                        i.nome as insumo_nome,
                        a.nome as associado_nome,
                        a.sobrenome as associado_sobrenome
                    FROM ocorrencias_manejo om
                    LEFT JOIN propriedades p ON om.propriedade_id = p.id
                    LEFT JOIN lavouras l ON om.lavoura_id = l.id
                    LEFT JOIN insumos i ON om.insumo_utilizado_id = i.id
                    LEFT JOIN associados a ON om.associado_id = a.id
                    WHERE om.associado_id = :associado_id AND om.excluido = 0
                    ORDER BY om.data_deteccao DESC, om.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':associado_id', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar ocorrências: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Retorna TODAS as ocorrências (para Gestor/Admin).
     */
    public function getAll() {
        try {
            $sql = "SELECT 
                        om.*, 
                        p.propriedade, 
                        l.nome as lavoura_nome,
                        i.nome as insumo_nome,
                        a.nome as associado_nome,
                        a.sobrenome as associado_sobrenome
                    FROM ocorrencias_manejo om
                    LEFT JOIN propriedades p ON om.propriedade_id = p.id
                    LEFT JOIN lavouras l ON om.lavoura_id = l.id
                    LEFT JOIN insumos i ON om.insumo_utilizado_id = i.id
                    LEFT JOIN associados a ON om.associado_id = a.id
                    WHERE om.excluido = 0
                    ORDER BY om.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar todas as ocorrências: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca uma ocorrência pelo ID.
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM ocorrencias_manejo WHERE id = :id AND excluido = 0");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar ocorrência por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cria um novo registro de ocorrência.
     */
    public function create($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);
            
            $sql = "INSERT INTO ocorrencias_manejo 
                        (associado_id, propriedade_id, lavoura_id, tipo, nome_comum, data_deteccao, severidade, descricao, acao_manejo, insumo_utilizado_id, foto_url) 
                    VALUES 
                        (:associado_id, :propriedade_id, :lavoura_id, :tipo, :nome_comum, :data_deteccao, :severidade, :descricao, :acao_manejo, :insumo_utilizado_id, :foto_url)";
            
            $stmt = $this->db->prepare($sql);

            $stmt->bindParam(':associado_id', $dados_sanitizados['associado_id'], PDO::PARAM_INT);
            $stmt->bindParam(':propriedade_id', $dados_sanitizados['propriedade_id'], PDO::PARAM_INT);
            
            $lavoura_id = !empty($dados_sanitizados['lavoura_id']) ? $dados_sanitizados['lavoura_id'] : null;
            $stmt->bindParam(':lavoura_id', $lavoura_id, PDO::PARAM_INT);
            
            $stmt->bindParam(':tipo', $dados_sanitizados['tipo']);
            $stmt->bindParam(':nome_comum', $dados_sanitizados['nome_comum']);
            $stmt->bindParam(':data_deteccao', $dados_sanitizados['data_deteccao']);
            $stmt->bindParam(':severidade', $dados_sanitizados['severidade']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':acao_manejo', $dados_sanitizados['acao_manejo']);
            
            $insumo_id = !empty($dados_sanitizados['insumo_utilizado_id']) ? $dados_sanitizados['insumo_utilizado_id'] : null;
            $stmt->bindParam(':insumo_utilizado_id', $insumo_id, PDO::PARAM_INT);
            
            $foto_url = !empty($dados_sanitizados['foto_url']) ? $dados_sanitizados['foto_url'] : null;
            $stmt->bindParam(':foto_url', $foto_url);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar ocorrência: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza uma ocorrência existente.
     */
    public function update($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);
            
            $sql = "UPDATE ocorrencias_manejo SET 
                        propriedade_id = :propriedade_id,
                        lavoura_id = :lavoura_id,
                        tipo = :tipo,
                        nome_comum = :nome_comum,
                        data_deteccao = :data_deteccao,
                        severidade = :severidade,
                        descricao = :descricao,
                        acao_manejo = :acao_manejo,
                        insumo_utilizado_id = :insumo_utilizado_id,
                        foto_url = :foto_url
                    WHERE id = :id AND associado_id = :associado_id";
            
            $stmt = $this->db->prepare($sql);

            $stmt->bindParam(':propriedade_id', $dados_sanitizados['propriedade_id'], PDO::PARAM_INT);
            
            $lavoura_id = !empty($dados_sanitizados['lavoura_id']) ? $dados_sanitizados['lavoura_id'] : null;
            $stmt->bindParam(':lavoura_id', $lavoura_id, PDO::PARAM_INT);
            
            $stmt->bindParam(':tipo', $dados_sanitizados['tipo']);
            $stmt->bindParam(':nome_comum', $dados_sanitizados['nome_comum']);
            $stmt->bindParam(':data_deteccao', $dados_sanitizados['data_deteccao']);
            $stmt->bindParam(':severidade', $dados_sanitizados['severidade']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':acao_manejo', $dados_sanitizados['acao_manejo']);
            
            $insumo_id = !empty($dados_sanitizados['insumo_utilizado_id']) ? $dados_sanitizados['insumo_utilizado_id'] : null;
            $stmt->bindParam(':insumo_utilizado_id', $insumo_id, PDO::PARAM_INT);
            
            $foto_url = !empty($dados_sanitizados['foto_url']) ? $dados_sanitizados['foto_url'] : null;
            $stmt->bindParam(':foto_url', $foto_url);
            
            $stmt->bindParam(':id', $dados_sanitizados['id'], PDO::PARAM_INT);
            $stmt->bindParam(':associado_id', $dados_sanitizados['associado_id'], PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar ocorrência: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui (soft delete) uma ocorrência.
     */
    public function delete($id, $associado_id) {
        try {
            $stmt = $this->db->prepare("UPDATE ocorrencias_manejo SET excluido = 1 WHERE id = :id AND associado_id = :associado_id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':associado_id', $associado_id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao excluir ocorrência: " . $e->getMessage());
            return false;
        }
    }
}
?>
