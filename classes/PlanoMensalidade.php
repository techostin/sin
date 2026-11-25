<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class PlanoMensalidade
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($associacaoId = null)
    {
        try {
            $sql = "SELECT p.*, ac.nome AS associacao_nome
                    FROM planos_mensalidades p
                    LEFT JOIN associacoes ac ON ac.id = p.associacao_id";
            $params = [];
            if (!empty($associacaoId)) {
                $sql .= " WHERE p.associacao_id = :assoc";
                $params[':assoc'] = (int) $associacaoId;
            }
            $sql .= " ORDER BY p.ativo DESC, p.nome ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao listar planos: " . $e->getMessage());
            return [];
        }
    }

    public function getAtivos($associacaoId = null)
    {
        $planos = $this->getAll($associacaoId);
        return array_values(array_filter($planos, function ($p) {
            return (int) $p['ativo'] === 1;
        }));
    }

    public function getById($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM planos_mensalidades WHERE id = ?");
            $stmt->execute([(int) $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar plano: " . $e->getMessage());
            return false;
        }
    }

    public function create($dados)
    {
        try {
            $d = Sanitizer::sanitizeArray($dados);
            $associacao_id = !empty($d['associacao_id']) ? (int) $d['associacao_id'] : null;
            $valor = Sanitizer::sanitizeMoney($d['valor'] ?? 0);
            $dia = (int) ($d['dia_vencimento'] ?? 10);
            if ($dia < 1) $dia = 10;
            if ($dia > 28) $dia = 28;
            $tipo = in_array($d['tipo'] ?? '', ['mensalidade', 'anuidade', 'joia'], true) ? $d['tipo'] : 'mensalidade';
            $ativo = isset($d['ativo']) ? 1 : 0;

            $stmt = $this->db->prepare(
                "INSERT INTO planos_mensalidades (associacao_id, nome, tipo, valor, dia_vencimento, ativo)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$associacao_id, $d['nome'] ?? '', $tipo, $valor, $dia, $ativo]);
            return (int) $this->db->lastInsertId();
        } catch (PDOException $e) {
            error_log("Erro ao criar plano: " . $e->getMessage());
            return false;
        }
    }

    public function update($id, $dados)
    {
        try {
            $d = Sanitizer::sanitizeArray($dados);
            $associacao_id = !empty($d['associacao_id']) ? (int) $d['associacao_id'] : null;
            $valor = Sanitizer::sanitizeMoney($d['valor'] ?? 0);
            $dia = (int) ($d['dia_vencimento'] ?? 10);
            if ($dia < 1) $dia = 10;
            if ($dia > 28) $dia = 28;
            $tipo = in_array($d['tipo'] ?? '', ['mensalidade', 'anuidade', 'joia'], true) ? $d['tipo'] : 'mensalidade';
            $ativo = isset($d['ativo']) ? 1 : 0;

            $stmt = $this->db->prepare(
                "UPDATE planos_mensalidades
                 SET associacao_id = ?, nome = ?, tipo = ?, valor = ?, dia_vencimento = ?, ativo = ?
                 WHERE id = ?"
            );
            return $stmt->execute([$associacao_id, $d['nome'] ?? '', $tipo, $valor, $dia, $ativo, (int) $id]);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar plano: " . $e->getMessage());
            return false;
        }
    }

    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM planos_mensalidades WHERE id = ?");
            return $stmt->execute([(int) $id]);
        } catch (PDOException $e) {
            error_log("Erro ao excluir plano: " . $e->getMessage());
            return false;
        }
    }
}