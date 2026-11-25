<?php
require_once __DIR__ . '/Database.php';

class ManejoAnimal
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($filtroAssociado = null)
    {
        $sql = "SELECT a.*, l.nome_lote AS lote_nome,
                       (SELECT COUNT(*) FROM manejo_animal_registros r WHERE r.animal_id = a.id) AS total_registros
                FROM animais a
                LEFT JOIN animal_lotes l ON a.lote_id = l.id
                WHERE a.excluido = 0";
        $params = [];
        if ($filtroAssociado) {
            $sql .= " AND a.associado_id = ?";
            $params[] = (int)$filtroAssociado;
        }
        $sql .= " ORDER BY a.criado_em DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT a.*, l.nome_lote AS lote_nome FROM animais a LEFT JOIN animal_lotes l ON a.lote_id = l.id WHERE a.id = ? AND a.excluido = 0");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getRegistros($animalId)
    {
        $stmt = $this->db->prepare("SELECT * FROM manejo_animal_registros WHERE animal_id = ? ORDER BY data_registro DESC");
        $stmt->execute([(int)$animalId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($dados)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO animais (associado_id, nome_animal, especie, raca, identificacao, sexo, data_nascimento, status, origem_conversao, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            !empty($dados['associado_id']) ? (int)$dados['associado_id'] : null,
            trim($dados['nome_animal']),
            trim($dados['especie']),
            !empty($dados['raca']) ? trim($dados['raca']) : null,
            !empty($dados['identificacao']) ? trim($dados['identificacao']) : null,
            !empty($dados['sexo']) ? $dados['sexo'] : null,
            !empty($dados['data_nascimento']) ? $dados['data_nascimento'] : null,
            $dados['status'] ?? 'ATIVO',
            $dados['origem_conversao'] ?? 'ORGANICO',
            !empty($dados['criado_por']) ? (int)$dados['criado_por'] : null,
        ]);
        $id = (int)$this->db->lastInsertId();

        if (!empty($dados['lote_id']) || !empty($dados['peso_kg']) || !empty($dados['tipo_entrada'])) {
            $this->atualizarAnexos($id, $dados);
        }
        return $id;
    }

    /**
     * Atualiza as colunas expandidas (lote, peso, entrada/origem) do animal.
     */
    public function atualizarAnexos($id, $dados)
    {
        $stmt = $this->db->prepare(
            "UPDATE animais SET lote_id = ?, peso_kg = ?, tipo_entrada = ?, fornecedor_origem = ?,
                data_entrada = ?, observacoes_origem = ? WHERE id = ?"
        );
        return $stmt->execute([
            !empty($dados['lote_id']) ? (int)$dados['lote_id'] : null,
            !empty($dados['peso_kg']) ? (float)$dados['peso_kg'] : null,
            !empty($dados['tipo_entrada']) ? $dados['tipo_entrada'] : null,
            !empty($dados['fornecedor_origem']) ? trim($dados['fornecedor_origem']) : null,
            !empty($dados['data_entrada']) ? $dados['data_entrada'] : null,
            !empty($dados['observacoes_origem']) ? $dados['observacoes_origem'] : null,
            (int)$id,
        ]);
    }

    public function softDelete($id)
    {
        $stmt = $this->db->prepare("UPDATE animais SET excluido = 1 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    public function adicionarRegistro($dados)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO manejo_animal_registros (animal_id, tipo_registro, data_registro, descricao, produto_utilizado, dose_quantidade, responsavel, observacoes, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int)$dados['animal_id'],
            $dados['tipo_registro'],
            $dados['data_registro'],
            !empty($dados['descricao']) ? trim($dados['descricao']) : null,
            !empty($dados['produto_utilizado']) ? trim($dados['produto_utilizado']) : null,
            !empty($dados['dose_quantidade']) ? trim($dados['dose_quantidade']) : null,
            !empty($dados['responsavel']) ? trim($dados['responsavel']) : null,
            !empty($dados['observacoes']) ? $dados['observacoes'] : null,
            !empty($dados['criado_por']) ? (int)$dados['criado_por'] : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function removerRegistro($registroId)
    {
        $stmt = $this->db->prepare("DELETE FROM manejo_animal_registros WHERE id = ?");
        return $stmt->execute([(int)$registroId]);
    }
}
