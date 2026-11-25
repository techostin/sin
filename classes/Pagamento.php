<?php
/**
 * Pagamento — consultas auxiliares sobre a tabela `pagamentos`.
 *
 * Agrupa dados de pagamentos efetivados (baixas de mensalidades) para
 * relatórios e resumos: por período, por forma de pagamento, recebido por
 * mês e inadimplência acumulada por mês de referência.
 */
require_once __DIR__ . '/Mensalidade.php';

final class Pagamento
{
    /**
     * Lista pagamentos pagos em um período (com filtro opcional de associação).
     *
     * @return array
     */
    public static function getPorPeriodo($dataInicio = null, $dataFim = null, $associacaoId = null)
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT p.*, CONCAT(a.nome, ' ', a.sobrenome) AS associado_nome
                    FROM pagamentos p
                    LEFT JOIN associados a ON a.id = p.associado_id
                    WHERE p.status_pagamento = 'pago'";
            $params = [];
            if (!empty($dataInicio)) {
                $sql .= " AND p.data_pagamento >= :ini";
                $params[':ini'] = $dataInicio;
            }
            if (!empty($dataFim)) {
                $sql .= " AND p.data_pagamento <= :fim";
                $params[':fim'] = $dataFim;
            }
            if (!empty($associacaoId)) {
                $sql .= " AND p.associacao_id = :assoc";
                $params[':assoc'] = (int) $associacaoId;
            }
            $sql .= " ORDER BY p.data_pagamento DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao listar pagamentos por período: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Pagamentos efetuados agregados por forma de pagamento (PIX, Boleto, etc.).
     *
     * @return array Agrupado por metodo_pagamento com qtd e valor.
     */
    public static function getPagamentosPorForma($associacaoId = null, $dataInicio = null, $dataFim = null, $associadoId = null)
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT metodo_pagamento,
                           COUNT(*) AS qtd,
                           COALESCE(SUM(valor), 0) AS valor
                    FROM pagamentos
                    WHERE status_pagamento = 'pago'";
            $params = [];
            if (!empty($associacaoId)) {
                $sql .= " AND associacao_id = :assoc";
                $params[':assoc'] = (int) $associacaoId;
            }
            if (!empty($associadoId)) {
                $sql .= " AND associado_id = :associd";
                $params[':associd'] = (int) $associadoId;
            }
            if (!empty($dataInicio)) {
                $sql .= " AND data_pagamento >= :ini";
                $params[':ini'] = $dataInicio;
            }
            if (!empty($dataFim)) {
                $sql .= " AND data_pagamento <= :fim";
                $params[':fim'] = $dataFim;
            }
            $sql .= " GROUP BY metodo_pagamento ORDER BY valor DESC, qtd DESC";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao resumir pagamentos por forma: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Total recebido (SOMA valor) de pagamentos pagos em um período.
     *
     * @return float
     */
    public static function getTotalRecebidoPeriodo($dataInicio = null, $dataFim = null, $associacaoId = null)
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT COALESCE(SUM(valor), 0) AS total,
                           COUNT(*) AS quantidade
                    FROM pagamentos
                    WHERE status_pagamento = 'pago'";
            $params = [];
            if (!empty($dataInicio)) {
                $sql .= " AND data_pagamento >= :ini";
                $params[':ini'] = $dataInicio;
            }
            if (!empty($dataFim)) {
                $sql .= " AND data_pagamento <= :fim";
                $params[':fim'] = $dataFim;
            }
            if (!empty($associacaoId)) {
                $sql .= " AND associacao_id = :assoc";
                $params[':assoc'] = (int) $associacaoId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return ['total' => round((float) $row['total'], 2), 'quantidade' => (int) $row['quantidade']];
        } catch (PDOException $e) {
            error_log("Erro ao somar pagamentos por período: " . $e->getMessage());
            return ['total' => 0.0, 'quantidade' => 0];
        }
    }

    /**
     * Receitas efetivamente recebidas por mês (data do pagamento), últimos N meses.
     *
     * @return array [['ano','mes','valor','qtd'], ...]
     */
    public static function getRecebidoPorMes($meses = 6, $associacaoId = null)
    {
        $resultado = [];
        $hoje = new DateTime('today');
        for ($m = (int) $meses - 1; $m >= 0; $m--) {
            $dt = (clone $hoje)->modify('first day of this month')->modify("-{$m} months");
            $resultado[$dt->format('Y-m')] = [
                'ano' => (int) $dt->format('Y'),
                'mes' => (int) $dt->format('m'),
                'valor' => 0.0,
                'qtd' => 0,
            ];
        }

        try {
            $db = Database::getInstance()->getConnection();
            $inicio = (clone $hoje)->modify('first day of this month')
                ->modify('-' . ((int) $meses - 1) . ' months')->format('Y-m-01');
            $sql = "SELECT YEAR(data_pagamento) AS ano, MONTH(data_pagamento) AS mes,
                           COALESCE(SUM(valor), 0) AS valor, COUNT(*) AS qtd
                    FROM pagamentos
                    WHERE status_pagamento = 'pago'
                      AND data_pagamento >= :inicio";
            $params = [':inicio' => $inicio];
            if (!empty($associacaoId)) {
                $sql .= " AND associacao_id = :assoc";
                $params[':assoc'] = (int) $associacaoId;
            }
            $sql .= " GROUP BY YEAR(data_pagamento), MONTH(data_pagamento)";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $chave = $row['ano'] . '-' . str_pad((string) $row['mes'], 2, '0', STR_PAD_LEFT);
                if (!isset($resultado[$chave])) {
                    continue;
                }
                $resultado[$chave]['valor'] = round((float) $row['valor'], 2);
                $resultado[$chave]['qtd'] = (int) $row['qtd'];
            }
        } catch (PDOException $e) {
            error_log("Erro ao calcular recebimento por mês: " . $e->getMessage());
        }

        return array_values($resultado);
    }

    /**
     * Inadimplência agregada por mês de referência (mensalidades pendentes/atrasadas
     * com vencimento já vencido).
     *
     * @return array [['ano','mes','qtd','valor'], ...]
     */
    public static function getInadimplenciaPorMes($associacaoId = null, $limite = 12)
    {
        return (new Mensalidade())->getInadimplenciaPorMes($associacaoId, $limite);
    }

    /**
     * Resumo geral de pagamentos efetivados.
     *
     * @return array ['total_recebido','quantidade','ticket_medio']
     */
    public static function getResumoGeral($associacaoId = null)
    {
        $resumo = self::getTotalRecebidoPeriodo(null, null, $associacaoId);
        return [
            'total_recebido' => $resumo['total'],
            'quantidade' => $resumo['quantidade'],
            'ticket_medio' => $resumo['quantidade'] > 0
                ? round($resumo['total'] / $resumo['quantidade'], 2)
                : 0.0,
        ];
    }
}