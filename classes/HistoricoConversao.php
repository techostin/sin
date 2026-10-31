<?php
require_once __DIR__ . '/Database.php';

class HistoricoConversao
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getByPropriedade($propriedadeId)
    {
        $stmt = $this->db->prepare("SELECT * FROM historico_conversao WHERE propriedade_id = ? ORDER BY data_inicio DESC");
        $stmt->execute([(int)$propriedadeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM historico_conversao WHERE id = ?");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * RF03: período de conversão conforme o tipo de uso do solo.
     * Culturas anuais: 12 meses; perenes/pastagens: 24 meses (Portaria 52/2021).
     */
    public function mesDeConversao($tipoUsoSolo)
    {
        $t = mb_strtolower($tipoUsoSolo ?? '');
        if (strpos($t, 'peren') !== false || strpos($t, 'pasto') !== false || strpos($t, 'pomar') !== false) {
            return 24;
        }
        return 12;
    }

    /**
     * Calcula a data de conclusão da conversão e quantos meses ainda restam.
     */
    public function calcularConversao($dataInicio, $tipoUsoSolo, $dataFim = null)
    {
        $meses = $this->mesDeConversao($tipoUsoSolo);
        $inicio = new DateTime($dataInicio);
        $fimBase = $dataFim ? new DateTime($dataFim) : $inicio->modify('+' . $meses . ' months');

        $hoje = new DateTime();
        $restanteMeses = 0;
        $concluido = false;
        if ($fimBase < $hoje) {
            $concluido = true;
        } else {
            $diff = $hoje->diff($fimBase);
            $restanteMeses = (int)($diff->y * 12 + $diff->m);
            if ($diff->d > 0) {
                $restanteMeses += 1;
            }
        }

        return [
            'data_conclusao' => $fimBase->format('Y-m-d'),
            'restante_meses' => $restanteMeses,
            'concluido' => $concluido,
            'total_meses' => $meses,
        ];
    }

    public function create($dados)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO historico_conversao (propriedade_id, associado_id, tipo_uso_solo, data_inicio, data_fim, status, observacoes, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            !empty($dados['propriedade_id']) ? (int)$dados['propriedade_id'] : null,
            !empty($dados['associado_id']) ? (int)$dados['associado_id'] : null,
            trim($dados['tipo_uso_solo']),
            $dados['data_inicio'],
            !empty($dados['data_fim']) ? $dados['data_fim'] : null,
            $dados['status'] ?? 'EM_CONVERSAO',
            !empty($dados['observacoes']) ? $dados['observacoes'] : null,
            !empty($dados['criado_por']) ? (int)$dados['criado_por'] : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM historico_conversao WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}
