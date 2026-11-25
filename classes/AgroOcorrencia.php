<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class AgroOcorrencia {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($filtroAssociado = null) {
        try {
            $sql = "SELECT o.*, s.nome AS setor_nome
                    FROM agro_ocorrencias o
                    LEFT JOIN agro_setores s ON o.setor_id = s.id
                    WHERE o.excluido = 0";
            $params = [];
            if ($filtroAssociado) {
                $sql .= " AND o.associado_id = ?";
                $params[] = (int)$filtroAssociado;
            }
            $sql .= " ORDER BY o.data_ocorrencia DESC, o.criado_em DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar ocorrências agroindústria: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM agro_ocorrencias WHERE id = :id AND excluido = 0");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar ocorrência por ID: " . $e->getMessage());
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
                "INSERT INTO agro_ocorrencias
                    (uuid, associado_id, setor_id, tipo, titulo, data_ocorrencia, severidade, descricao, acao_manejo, status, criado_em)
                 VALUES
                    (:uuid, :associado_id, :setor_id, :tipo, :titulo, :data_ocorrencia, :severidade, :descricao, :acao_manejo, :status, NOW())"
            );
            $stmt->bindParam(':uuid', $dados['uuid']);
            $stmt->bindValue(':associado_id', (int)($dados['associado_id'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':setor_id', ($dados['setor_id'] ?? '') !== '' ? (int)$dados['setor_id'] : null);
            $stmt->bindParam(':tipo', $dados['tipo']);
            $stmt->bindParam(':titulo', $dados['titulo']);
            $stmt->bindParam(':data_ocorrencia', $dados['data_ocorrencia']);
            $stmt->bindParam(':severidade', $dados['severidade']);
            $stmt->bindValue(':descricao', ($dados['descricao'] ?? '') !== '' ? $dados['descricao'] : null);
            $stmt->bindValue(':acao_manejo', ($dados['acao_manejo'] ?? '') !== '' ? $dados['acao_manejo'] : null);
            $stmt->bindParam(':status', $dados['status']);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar ocorrência agroindústria: " . $e->getMessage());
            return false;
        }
    }

    public function update($id, $dados) {
        try {
            $dados = Sanitizer::sanitizeArray($dados);
            $stmt = $this->db->prepare(
                "UPDATE agro_ocorrencias SET
                    setor_id = :setor_id, tipo = :tipo, titulo = :titulo, data_ocorrencia = :data_ocorrencia,
                    severidade = :severidade, descricao = :descricao, acao_manejo = :acao_manejo, status = :status
                 WHERE id = :id AND excluido = 0"
            );
            $stmt->bindValue(':setor_id', ($dados['setor_id'] ?? '') !== '' ? (int)$dados['setor_id'] : null);
            $stmt->bindParam(':tipo', $dados['tipo']);
            $stmt->bindParam(':titulo', $dados['titulo']);
            $stmt->bindParam(':data_ocorrencia', $dados['data_ocorrencia']);
            $stmt->bindParam(':severidade', $dados['severidade']);
            $stmt->bindValue(':descricao', ($dados['descricao'] ?? '') !== '' ? $dados['descricao'] : null);
            $stmt->bindValue(':acao_manejo', ($dados['acao_manejo'] ?? '') !== '' ? $dados['acao_manejo'] : null);
            $stmt->bindParam(':status', $dados['status']);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar ocorrência agroindústria: " . $e->getMessage());
            return false;
        }
    }

    public function softDelete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE agro_ocorrencias SET excluido = 1 WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao excluir ocorrência agroindústria: " . $e->getMessage());
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