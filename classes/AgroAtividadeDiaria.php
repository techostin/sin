<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class AgroAtividadeDiaria {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($filtroAssociado = null) {
        try {
            $sql = "SELECT ad.*, s.nome AS setor_nome, c.nome AS categoria_nome, c.cor AS categoria_cor
                    FROM agro_atividades_diarias ad
                    LEFT JOIN agro_setores s ON ad.setor_id = s.id
                    LEFT JOIN agro_categorias_atividades c ON ad.categoria_id = c.id
                    WHERE ad.excluido = 0";
            $params = [];
            if ($filtroAssociado) {
                $sql .= " AND ad.associado_id = ?";
                $params[] = (int)$filtroAssociado;
            }
            $sql .= " ORDER BY ad.data_atividade DESC, ad.hora_inicio DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar atividades diárias agroindústria: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM agro_atividades_diarias WHERE id = :id AND excluido = 0");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar atividade diária agroindústria por ID: " . $e->getMessage());
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
                "INSERT INTO agro_atividades_diarias
                    (uuid, associado_id, setor_id, categoria_id, nome, data_atividade, hora_inicio, hora_fim,
                     descricao, status, tipo_atividade, insumo_utilizado, observacoes, criado_em)
                 VALUES
                    (:uuid, :associado_id, :setor_id, :categoria_id, :nome, :data_atividade, :hora_inicio, :hora_fim,
                     :descricao, :status, :tipo_atividade, :insumo_utilizado, :observacoes, NOW())"
            );
            $stmt->bindParam(':uuid', $dados['uuid']);
            $stmt->bindValue(':associado_id', (int)($dados['associado_id'] ?? 0), PDO::PARAM_INT);
            $stmt->bindValue(':setor_id', ($dados['setor_id'] ?? '') !== '' ? (int)$dados['setor_id'] : null);
            $stmt->bindValue(':categoria_id', ($dados['categoria_id'] ?? '') !== '' ? (int)$dados['categoria_id'] : null);
            $stmt->bindParam(':nome', $dados['nome']);
            $stmt->bindParam(':data_atividade', $dados['data_atividade']);
            $stmt->bindValue(':hora_inicio', ($dados['hora_inicio'] ?? '') !== '' ? $dados['hora_inicio'] : null);
            $stmt->bindValue(':hora_fim', ($dados['hora_fim'] ?? '') !== '' ? $dados['hora_fim'] : null);
            $stmt->bindParam(':descricao', $dados['descricao']);
            $stmt->bindParam(':status', $dados['status']);
            $stmt->bindValue(':tipo_atividade', ($dados['tipo_atividade'] ?? '') !== '' ? $dados['tipo_atividade'] : null);
            $stmt->bindValue(':insumo_utilizado', ($dados['insumo_utilizado'] ?? '') !== '' ? $dados['insumo_utilizado'] : null);
            $stmt->bindValue(':observacoes', ($dados['observacoes'] ?? '') !== '' ? $dados['observacoes'] : null);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar atividade diária agroindústria: " . $e->getMessage());
            return false;
        }
    }

    public function update($id, $dados) {
        try {
            $dados = Sanitizer::sanitizeArray($dados);
            $stmt = $this->db->prepare(
                "UPDATE agro_atividades_diarias SET
                    setor_id = :setor_id, categoria_id = :categoria_id, nome = :nome,
                    data_atividade = :data_atividade, hora_inicio = :hora_inicio, hora_fim = :hora_fim,
                    descricao = :descricao, status = :status, tipo_atividade = :tipo_atividade,
                    insumo_utilizado = :insumo_utilizado, observacoes = :observacoes
                 WHERE id = :id AND excluido = 0"
            );
            $stmt->bindValue(':setor_id', ($dados['setor_id'] ?? '') !== '' ? (int)$dados['setor_id'] : null);
            $stmt->bindValue(':categoria_id', ($dados['categoria_id'] ?? '') !== '' ? (int)$dados['categoria_id'] : null);
            $stmt->bindParam(':nome', $dados['nome']);
            $stmt->bindParam(':data_atividade', $dados['data_atividade']);
            $stmt->bindValue(':hora_inicio', ($dados['hora_inicio'] ?? '') !== '' ? $dados['hora_inicio'] : null);
            $stmt->bindValue(':hora_fim', ($dados['hora_fim'] ?? '') !== '' ? $dados['hora_fim'] : null);
            $stmt->bindParam(':descricao', $dados['descricao']);
            $stmt->bindParam(':status', $dados['status']);
            $stmt->bindValue(':tipo_atividade', ($dados['tipo_atividade'] ?? '') !== '' ? $dados['tipo_atividade'] : null);
            $stmt->bindValue(':insumo_utilizado', ($dados['insumo_utilizado'] ?? '') !== '' ? $dados['insumo_utilizado'] : null);
            $stmt->bindValue(':observacoes', ($dados['observacoes'] ?? '') !== '' ? $dados['observacoes'] : null);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar atividade diária agroindústria: " . $e->getMessage());
            return false;
        }
    }

    public function softDelete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE agro_atividades_diarias SET excluido = 1 WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao excluir atividade diária agroindústria: " . $e->getMessage());
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