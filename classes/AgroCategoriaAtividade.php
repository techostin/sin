<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class AgroCategoriaAtividade {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll() {
        try {
            $stmt = $this->db->prepare("SELECT id, nome, descricao, cor FROM agro_categorias_atividades WHERE excluido = 0 ORDER BY nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar categorias de atividades agroindústria: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM agro_categorias_atividades WHERE id = :id AND excluido = 0");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar categoria por ID: " . $e->getMessage());
            return false;
        }
    }

    public function create($dados) {
        try {
            $dados = Sanitizer::sanitizeArray($dados);
            if (empty($dados['uuid'])) {
                $dados['uuid'] = $this->gerarUuid();
            }
            $stmt = $this->db->prepare(
                "INSERT INTO agro_categorias_atividades (uuid, associado_id, nome, descricao, cor, criado_em)
                 VALUES (:uuid, :associado_id, :nome, :descricao, :cor, NOW())"
            );
            $stmt->bindParam(':uuid', $dados['uuid']);
            $stmt->bindValue(':associado_id', ($dados['associado_id'] ?? '') !== '' ? (int)$dados['associado_id'] : null);
            $stmt->bindParam(':nome', $dados['nome']);
            $stmt->bindParam(':descricao', $dados['descricao']);
            $stmt->bindParam(':cor', $dados['cor']);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar categoria de atividade agroindústria: " . $e->getMessage());
            return false;
        }
    }

    public function update($id, $dados) {
        try {
            $dados = Sanitizer::sanitizeArray($dados);
            $stmt = $this->db->prepare(
                "UPDATE agro_categorias_atividades SET nome = :nome, descricao = :descricao, cor = :cor WHERE id = :id AND excluido = 0"
            );
            $stmt->bindParam(':nome', $dados['nome']);
            $stmt->bindParam(':descricao', $dados['descricao']);
            $stmt->bindParam(':cor', $dados['cor']);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar categoria de atividade agroindústria: " . $e->getMessage());
            return false;
        }
    }

    public function softDelete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE agro_categorias_atividades SET excluido = 1 WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao excluir categoria de atividade agroindústria: " . $e->getMessage());
            return false;
        }
    }

    private function gerarUuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}