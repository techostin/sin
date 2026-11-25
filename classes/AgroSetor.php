<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class AgroSetor {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($filtroAssociado = null) {
        try {
            $sql = "SELECT * FROM agro_setores WHERE excluido = 0";
            $params = [];
            if ($filtroAssociado) {
                $sql .= " AND associado_id = ?";
                $params[] = (int)$filtroAssociado;
            }
            $sql .= " ORDER BY nome ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar setores agroindústria: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM agro_setores WHERE id = :id AND excluido = 0");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar setor por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lista os setores das agroindústrias acessíveis ao associado (dono ou membro).
     * Retorna também o agroindustria_id para filtro por unidade no formulário.
     */
    public function getSetoresDoAssociado($associado_id) {
        if (!$associado_id) return [];
        try {
            $stmt = $this->db->prepare("
                SELECT s.id, s.nome, s.descricao, s.agroindustria_id,
                       COALESCE(NULLIF(ag.nome_fantasia, ''), NULLIF(ag.razao_social, ''), CONCAT('Agroindústria #', ag.id)) AS agroindustria_nome
                FROM agro_setores s
                JOIN agroindustrias ag ON s.agroindustria_id = ag.id AND ag.excluido = 0
                WHERE s.excluido = 0
                  AND (ag.associado_id = :assoc
                       OR EXISTS (SELECT 1 FROM agroindustria_membros am
                                  WHERE am.agroindustria_id = ag.id AND am.associado_id = :assoc AND am.excluido = 0))
                ORDER BY ag.id ASC, s.nome ASC
            ");
            $stmt->bindParam(':assoc', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar setores do associado: " . $e->getMessage());
            return [];
        }
    }

    public function create($dados) {
        try {
            $dados = Sanitizer::sanitizeArray($dados);
            if (empty($dados['uuid'])) {
                $dados['uuid'] = $this->gerarUuid();
            }
            $stmt = $this->db->prepare(
                "INSERT INTO agro_setores (uuid, associado_id, agroindustria_id, nome, descricao, criado_em)
                 VALUES (:uuid, :associado_id, :agroindustria_id, :nome, :descricao, NOW())"
            );
            $stmt->bindParam(':uuid', $dados['uuid']);
            $stmt->bindValue(':associado_id', (int)($dados['associado_id'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':agroindustria_id', ($dados['agroindustria_id'] ?? '') !== '' ? (int)$dados['agroindustria_id'] : null);
            $stmt->bindParam(':nome', $dados['nome']);
            $stmt->bindParam(':descricao', $dados['descricao']);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar setor: " . $e->getMessage());
            return false;
        }
    }

    public function update($id, $dados) {
        try {
            $dados = Sanitizer::sanitizeArray($dados);
            $stmt = $this->db->prepare(
                "UPDATE agro_setores SET agroindustria_id = :agroindustria_id, nome = :nome, descricao = :descricao WHERE id = :id AND excluido = 0"
            );
            $stmt->bindValue(':agroindustria_id', ($dados['agroindustria_id'] ?? '') !== '' ? (int)$dados['agroindustria_id'] : null);
            $stmt->bindParam(':nome', $dados['nome']);
            $stmt->bindParam(':descricao', $dados['descricao']);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar setor: " . $e->getMessage());
            return false;
        }
    }

    public function softDelete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE agro_setores SET excluido = 1 WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao excluir setor: " . $e->getMessage());
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
