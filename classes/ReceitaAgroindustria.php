<?php
require_once __DIR__ . '/Database.php';

class ReceitaAgroindustria
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($filtroAssociado = null)
    {
        $sql = "SELECT r.*,
                       (SELECT COUNT(*) FROM ingredientes_receita i WHERE i.receita_id = r.id) AS total_ingredientes
                FROM receitas_agroindustria r
                WHERE r.excluido = 0";
        $params = [];
        if ($filtroAssociado) {
            $sql .= " AND r.associado_id = ?";
            $params[] = (int)$filtroAssociado;
        }
        $sql .= " ORDER BY r.criado_em DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM receitas_agroindustria WHERE id = ? AND excluido = 0");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getIngredientes($receitaId)
    {
        $stmt = $this->db->prepare("SELECT * FROM ingredientes_receita WHERE receita_id = ? ORDER BY id ASC");
        $stmt->execute([(int)$receitaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Fator de conversão para uma base comum (gramas / mL).
     */
    private function fatorConversao($unidade)
    {
        switch (strtolower(trim((string)$unidade))) {
            case 'kg': return 1000;
            case 'mg': return 0.001;
            case 't':  return 1000000;
            case 'l':  return 1000;
            case 'ml': return 1;
            case 'un':
            case 'und': return 1;
            default:   return 1; // g / mL / un / outro
        }
    }

    /**
     * Calcula o percentual orgânico a partir das quantidades de cada ingrediente.
     * percentual = (soma das quantidades orgânicas) / (soma das quantidades) * 100
     */
    public function calcularPercentualOrganico(array $ingredientes)
    {
        $somaPeso = 0;
        $somaOrganico = 0;
        foreach ($ingredientes as $ing) {
            $qtd = (float)($ing['quantidade'] ?? $ing['proporcao_porcentagem'] ?? 0);
            $peso = $qtd * $this->fatorConversao($ing['unidade_medida'] ?? 'kg');
            $somaPeso += $peso;
            if (!empty($ing['eh_organico'])) {
                $somaOrganico += $peso;
            }
        }
        if ($somaPeso <= 0) {
            return 0.0;
        }
        return round(($somaOrganico / $somaPeso) * 100, 2);
    }

    /**
     * Classificação do selo conforme Portaria 52/2021:
     * - >= 95% orgânico -> PRODUTO_ORGANICO_BRASIL
     * - >= 70% e < 95% -> INGREDIENTES_ORGANICOS
     * - < 70% -> SEM_SELO
     */
    public function classificarSelo($percentual)
    {
        $percentual = (float)$percentual;
        if ($percentual >= 95.0) {
            return 'PRODUTO_ORGANICO_BRASIL';
        }
        if ($percentual >= 70.0) {
            return 'INGREDIENTES_ORGANICOS';
        }
        return 'SEM_SELO';
    }

    public function create($dados, array $ingredientes)
    {
        $percentual = $this->calcularPercentualOrganico($ingredientes);
        $selo = $this->classificarSelo($percentual);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO receitas_agroindustria
                    (nome_produto_final, descricao, percentual_organico_calculado, selo_autorizado, associado_id, status, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                trim($dados['nome_produto_final']),
                !empty($dados['descricao']) ? $dados['descricao'] : null,
                $percentual,
                $selo,
                !empty($dados['associado_id']) ? (int)$dados['associado_id'] : null,
                $dados['status'] ?? 'Ativa',
                !empty($dados['criado_por']) ? (int)$dados['criado_por'] : null,
            ]);
            $receitaId = (int)$this->db->lastInsertId();

            $this->salvarIngredientes($receitaId, $ingredientes);
            $this->db->commit();
            return $receitaId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function update($id, $dados, array $ingredientes)
    {
        $percentual = $this->calcularPercentualOrganico($ingredientes);
        $selo = $this->classificarSelo($percentual);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "UPDATE receitas_agroindustria
                    SET nome_produto_final = ?, descricao = ?, percentual_organico_calculado = ?,
                        selo_autorizado = ?, associado_id = ?, status = ?
                  WHERE id = ?"
            );
            $stmt->execute([
                trim($dados['nome_produto_final']),
                !empty($dados['descricao']) ? $dados['descricao'] : null,
                $percentual,
                $selo,
                !empty($dados['associado_id']) ? (int)$dados['associado_id'] : null,
                $dados['status'] ?? 'Ativa',
                (int)$id,
            ]);

            $del = $this->db->prepare("DELETE FROM ingredientes_receita WHERE receita_id = ?");
            $del->execute([(int)$id]);
            $this->salvarIngredientes((int)$id, $ingredientes);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function salvarIngredientes($receitaId, array $ingredientes)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO ingredientes_receita (receita_id, nome_ingrediente, eh_organico, quantidade, unidade_medida, proporcao_porcentagem, observacoes)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($ingredientes as $ing) {
            $nome = trim($ing['nome_ingrediente'] ?? '');
            $quantidade = (float)($ing['quantidade'] ?? 0);
            $unidade = trim($ing['unidade_medida'] ?? '') !== '' ? $ing['unidade_medida'] : 'kg';
            $peso = $quantidade * $this->fatorConversao($unidade);
            if ($nome === '' || $peso <= 0) {
                continue;
            }
            $stmt->execute([
                $receitaId,
                $nome,
                !empty($ing['eh_organico']) ? 1 : 0,
                $quantidade,
                $unidade,
                $peso,
                !empty($ing['observacoes']) ? $ing['observacoes'] : null,
            ]);
        }
    }

    public function softDelete($id)
    {
        $stmt = $this->db->prepare("UPDATE receitas_agroindustria SET excluido = 1 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}
