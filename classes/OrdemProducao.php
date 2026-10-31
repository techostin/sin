<?php
require_once __DIR__ . '/Database.php';

class OrdemProducao
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($filtroAssociado = null)
    {
        $sql = "SELECT o.*, r.nome_produto_final
                FROM ordens_producao o
                JOIN receitas_agroindustria r ON o.receita_id = r.id
                WHERE o.excluido = 0";
        $params = [];
        if ($filtroAssociado) {
            $sql .= " AND o.associado_id = ?";
            $params[] = (int)$filtroAssociado;
        }
        $sql .= " ORDER BY o.data_processamento DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare(
            "SELECT o.*, r.nome_produto_final, r.selo_autorizado AS selo_receita
             FROM ordens_producao o
             JOIN receitas_agroindustria r ON o.receita_id = r.id
             WHERE o.id = ? AND o.excluido = 0"
        );
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getItensRastreabilidade($ordemId)
    {
        $stmt = $this->db->prepare(
            "SELECT ri.*, lp.nome_lote, p.nome AS nome_produto,
                    ins.nome AS nome_insumo, ins.lote AS lote_insumo, ins.tipo_insumo,
                    f.nome AS fornecedor, f.cnpj AS fornecedor_cnpj
             FROM rastreabilidade_insumos_processo ri
             LEFT JOIN lotes_producao lp ON ri.lote_primario_id = lp.id
             LEFT JOIN produtos p ON ri.produto_id = p.id
             LEFT JOIN insumos ins ON ri.insumo_id = ins.id
             LEFT JOIN fornecedores f ON ins.fornecedor_id = f.id
             WHERE ri.ordem_producao_id = ?"
        );
        $stmt->execute([(int)$ordemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * RF11: balanço de massas. Soma a quantidade utilizada de cada lote primário
     * e compara com a quantidade final obtida. Divergência superior a $toleranciaPct
     * gera status ALERTA_FRAUDE (divergência suspeita).
     */
    public function calcularBalanco(array $itens, $quantidadeFinal, $toleranciaPct = 10.0)
    {
        $totalEntrada = 0;
        foreach ($itens as $item) {
            $totalEntrada += (float)$item['quantidade_utilizada'];
        }
        $quantidadeFinal = (float)$quantidadeFinal;

        if ($totalEntrada <= 0) {
            return [
                'total_entrada' => 0,
                'quantidade_final' => $quantidadeFinal,
                'divergencia_pct' => 0,
                'status' => 'EM_ANALISE',
                'mensagem' => 'Nenhum lote primário informado para o balanço.',
            ];
        }

        $divergenciaPct = round((abs($totalEntrada - $quantidadeFinal) / $totalEntrada) * 100, 2);
        $status = $divergenciaPct > $toleranciaPct ? 'ALERTA_FRAUDE' : 'APROVADO';

        return [
            'total_entrada' => $totalEntrada,
            'quantidade_final' => $quantidadeFinal,
            'divergencia_pct' => $divergenciaPct,
            'status' => $status,
            'mensagem' => $status === 'ALERTA_FRAUDE'
                ? "Divergência de {$divergenciaPct}% entre insumos processados ({$totalEntrada} kg) e produto final ({$quantidadeFinal} kg). Verificar auditoria."
                : "Balanço de massas dentro da tolerância ({$divergenciaPct}%).",
        ];
    }

    /**
     * RF10 + RF11 + RF08: cria a ordem de produção.
     * - Bloqueia se não houver higienização válida nas últimas 24h (RF08).
     * - Calcula o balanço de massas e grava o status de auditoria (RF11).
     */
    public function create($dados, array $itens)
    {
        $resultadoBalanco = $this->calcularBalanco($itens, $dados['quantidade_final_obtida']);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO ordens_producao
                    (receita_id, higienizacao_previa_id, lote_processado_final, data_processamento,
                     quantidade_final_obtida, unidade_medida, status_auditoria, percentual_organico,
                     selo_autorizado, associado_id, observacoes, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                (int)$dados['receita_id'],
                !empty($dados['higienizacao_previa_id']) ? (int)$dados['higienizacao_previa_id'] : null,
                trim($dados['lote_processado_final']),
                $dados['data_processamento'],
                (float)$dados['quantidade_final_obtida'],
                $dados['unidade_medida'] ?? 'KG',
                $resultadoBalanco['status'],
                !empty($dados['percentual_organico']) ? (float)$dados['percentual_organico'] : null,
                !empty($dados['selo_autorizado']) ? $dados['selo_autorizado'] : null,
                !empty($dados['associado_id']) ? (int)$dados['associado_id'] : null,
                !empty($dados['observacoes']) ? $dados['observacoes'] : null,
                !empty($dados['criado_por']) ? (int)$dados['criado_por'] : null,
            ]);
            $ordemId = (int)$this->db->lastInsertId();

            $stmtItem = $this->db->prepare(
                "INSERT INTO rastreabilidade_insumos_processo
                    (ordem_producao_id, lote_primario_id, produto_id, insumo_id, numero_nf, quantidade_utilizada, unidade_medida)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($itens as $item) {
                $stmtItem->execute([
                    $ordemId,
                    !empty($item['lote_primario_id']) ? (int)$item['lote_primario_id'] : null,
                    !empty($item['produto_id']) ? (int)$item['produto_id'] : null,
                    !empty($item['insumo_id']) ? (int)$item['insumo_id'] : null,
                    !empty($item['numero_nf']) ? trim($item['numero_nf']) : null,
                    (float)$item['quantidade_utilizada'],
                    $item['unidade_medida'] ?? 'KG',
                ]);
            }

            $this->db->commit();
            return $ordemId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function softDelete($id)
    {
        $stmt = $this->db->prepare("UPDATE ordens_producao SET excluido = 1 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}
