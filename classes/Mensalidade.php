<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Mensalidade
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(array $filtros = [])
    {
        try {
            $sql = "SELECT m.*,
                           CONCAT(a.nome, ' ', a.sobrenome) AS associado_nome,
                           a.email AS associado_email,
                           a.telefone AS associado_telefone,
                           ac.nome AS associacao_nome,
                           ac.sigla AS associacao_sigla,
                           p.nome AS plano_nome
                    FROM mensalidades m
                    LEFT JOIN associados a ON a.id = m.associado_id
                    LEFT JOIN associacoes ac ON ac.id = m.associacao_id
                    LEFT JOIN planos_mensalidades p ON p.id = m.plano_id
                    WHERE 1 = 1";
            $params = [];

            if (!empty($filtros['associacao_id'])) {
                $sql .= " AND m.associacao_id = :assoc";
                $params[':assoc'] = (int) $filtros['associacao_id'];
            }
            if (isset($filtros['ano']) && $filtros['ano'] !== '') {
                $sql .= " AND m.ano_referencia = :ano";
                $params[':ano'] = (int) $filtros['ano'];
            }
            if (isset($filtros['mes']) && $filtros['mes'] !== '') {
                $sql .= " AND m.mes_referencia = :mes";
                $params[':mes'] = (int) $filtros['mes'];
            }
            if (isset($filtros['status']) && $filtros['status'] !== '') {
                $sql .= " AND m.status_mensalidade = :status";
                $params[':status'] = $filtros['status'];
            }
            if (!empty($filtros['associado_id'])) {
                $sql .= " AND m.associado_id = :aid";
                $params[':aid'] = (int) $filtros['associado_id'];
            }

            $sql .= " ORDER BY m.ano_referencia DESC, m.mes_referencia DESC, m.data_vencimento DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao listar mensalidades: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id)
    {
        try {
            $sql = "SELECT m.*, CONCAT(a.nome, ' ', a.sobrenome) AS associado_nome,
                           a.email AS associado_email, a.telefone AS associado_telefone,
                           ac.nome AS associacao_nome, ac.sigla AS associacao_sigla,
                           p.nome AS plano_nome, p.tipo AS plano_tipo
                    FROM mensalidades m
                    LEFT JOIN associados a ON a.id = m.associado_id
                    LEFT JOIN associacoes ac ON ac.id = m.associacao_id
                    LEFT JOIN planos_mensalidades p ON p.id = m.plano_id
                    WHERE m.id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([(int) $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar mensalidade: " . $e->getMessage());
            return false;
        }
    }

    public function getByAssociado($associadoId, $limite = null)
    {
        try {
            $sql = "SELECT m.*, ac.nome AS associacao_nome, ac.sigla AS associacao_sigla,
                           p.nome AS plano_nome
                    FROM mensalidades m
                    LEFT JOIN associacoes ac ON ac.id = m.associacao_id
                    LEFT JOIN planos_mensalidades p ON p.id = m.plano_id
                    WHERE m.associado_id = ?
                    ORDER BY m.ano_referencia DESC, m.mes_referencia DESC";
            if ($limite !== null) {
                $sql .= " LIMIT " . (int) $limite;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute([(int) $associadoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar mensalidades do associado: " . $e->getMessage());
            return [];
        }
    }

    public function getPagamentos($mensalidadeId)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM pagamentos WHERE referencia_type = 'mensalidade' AND referencia_id = ? ORDER BY data_pagamento DESC"
            );
            $stmt->execute([(int) $mensalidadeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar pagamentos da mensalidade: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Gera o lote de mensalidades do mês para todos os associados ativos e
     * vinculados à associação. Não duplica (único por associado/mês/ano/plano).
     */
    public function gerarLote(array $dados)
    {
        try {
            $associacaoId = (int) ($dados['associacao_id'] ?? 0);
            $ano = (int) ($dados['ano'] ?? (int) date('Y'));
            $mes = (int) ($dados['mes'] ?? (int) date('m'));
            $planoId = !empty($dados['plano_id']) ? (int) $dados['plano_id'] : null;
            $valor = Sanitizer::sanitizeMoney($dados['valor'] ?? 0);

            if ($associacaoId <= 0) {
                return ['erro' => 'Selecione uma associação.'];
            }
            if ($valor <= 0) {
                return ['erro' => 'Informe um valor cobrado válido.'];
            }
            if ($mes < 1 || $mes > 12) {
                return ['erro' => 'Mês inválido.'];
            }

            // Associados ativos vinculados à associação (pivot), sem excluir desligados/falecidos da cobrança
            $sql = "SELECT DISTINCT a.id AS associado_id, a.vinculo
                    FROM associado_associacao aa
                    INNER JOIN associados a ON a.id = aa.associado_id
                    WHERE aa.associacao_id = :assoc
                      AND a.status = 'ativo'
                      AND (a.status_associado IS NULL OR a.status_associado NOT IN ('Desligado', 'Falecido'))";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':assoc' => $associacaoId]);
            $associados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($associados)) {
                return ['erro' => 'Nenhum associado ativo vinculado a esta associação.'];
            }

            $diaVencimento = !empty($dados['dia_vencimento']) ? (int) $dados['dia_vencimento'] : 10;
            if ($diaVencimento < 1) $diaVencimento = 10;
            if ($diaVencimento > 28) $diaVencimento = 28;
            $vencimento = sprintf('%04d-%02d-%02d', $ano, $mes, $diaVencimento);

            $inseridos = 0;
            $pulados = 0;
            $check = $this->db->prepare(
                "SELECT COUNT(*) FROM mensalidades
                 WHERE associado_id = ? AND associacao_id = ?
                   AND ano_referencia = ? AND mes_referencia = ?
                   AND (plano_id = ? OR (plano_id IS NULL AND ? IS NULL))"
            );

            $ins = $this->db->prepare(
                "INSERT INTO mensalidades
                   (associado_id, associacao_id, plano_id, fonte, ano_referencia, mes_referencia,
                    valor_cobrado, data_vencimento, status_mensalidade, observacoes, created_at, updated_at)
                 VALUES (?, ?, ?, 'automatica', ?, ?, ?, ?, 'pendente', NULL, NOW(), NOW())"
            );

            foreach ($associados as $assoc) {
                $check->execute([
                    $assoc['associado_id'],
                    $associacaoId,
                    $ano,
                    $mes,
                    $planoId,
                    $planoId,
                ]);
                if ((int) $check->fetchColumn() > 0) {
                    $pulados++;
                    continue;
                }
                $ins->execute([
                    $assoc['associado_id'],
                    $associacaoId,
                    $planoId,
                    $ano,
                    $mes,
                    $valor,
                    $vencimento,
                ]);
                $inseridos++;
            }

            return [
                'sucesso' => true,
                'inseridos' => $inseridos,
                'pulados' => $pulados,
                'total_associados' => count($associados),
            ];
        } catch (PDOException $e) {
            error_log("Erro ao gerar lote de mensalidades: " . $e->getMessage());
            return ['erro' => 'Erro de banco de dados ao gerar o lote: ' . $e->getMessage()];
        }
    }

    /**
     * Baixa manual de uma mensalidade: cria o pagamento e marca a mensalidade como paga.
     */
    public function baixar($id, array $dados)
    {
        try {
            $mensalidade = $this->getById($id);
            if (!$mensalidade) {
                return ['erro' => 'Mensalidade não encontrada.'];
            }

            $metodo = $dados['metodo_pagamento'] ?? 'Dinheiro';
            $metodos = ['PIX', 'Boleto', 'Cartao', 'Dinheiro', 'Transferencia'];
            if (!in_array($metodo, $metodos, true)) {
                $metodo = 'Dinheiro';
            }
            $dataPagamento = !empty($dados['data_pagamento']) ? $dados['data_pagamento'] : date('Y-m-d');
            $valor = isset($dados['valor']) ? Sanitizer::sanitizeMoney($dados['valor']) : (float) $mensalidade['valor_cobrado'];
            $observacoes = trim((string) ($dados['observacoes'] ?? ''));
            $comprovante = !empty($dados['comprovante_url']) ? $dados['comprovante_url'] : null;

            $this->db->beginTransaction();

            $insertPag = $this->db->prepare(
                "INSERT INTO pagamentos
                   (associado_id, associacao_id, descricao_pagamento, valor, data_pagamento,
                    data_vencimento, metodo_pagamento, status_pagamento, referencia_type,
                    referencia_id, comprovante_url, observacoes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pago', 'mensalidade', ?, ?, ?, NOW(), NOW())"
            );
            $descricao = $mensalidade['plano_nome'] ?? 'Mensalidade ' . str_pad((string) $mensalidade['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $mensalidade['ano_referencia'];
            $insertPag->execute([
                $mensalidade['associado_id'],
                $mensalidade['associacao_id'],
                $descricao,
                $valor,
                $dataPagamento,
                $mensalidade['data_vencimento'],
                $metodo,
                $mensalidade['id'],
                $comprovante,
                $observacoes,
            ]);

            $update = $this->db->prepare(
                "UPDATE mensalidades SET status_mensalidade = 'pago', data_pagamento = ?, observacoes = ?, updated_at = NOW() WHERE id = ?"
            );
            $update->execute([$dataPagamento, $observacoes, (int) $id]);

            $insertCaixa = $this->db->prepare(
                "INSERT INTO caixa
                   (associacao_id, tipo, descricao, categoria, valor, data, comprovante_url,
                    referencia_type, referencia_id, criado_por)
                 VALUES (?, 'Entrada', ?, 'Mensalidade', ?, ?, ?, 'mensalidade', ?, ?)"
            );
            $insertCaixa->execute([
                $mensalidade['associacao_id'],
                trim('Mensalidade ' . str_pad((string) $mensalidade['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $mensalidade['ano_referencia'] . ($observacoes !== '' ? ' - ' . $observacoes : '')),
                $valor,
                $dataPagamento,
                $comprovante,
                (int) $id,
                (int) ($_SESSION['user_id'] ?? 0),
            ]);

            $caixaId = (int) $this->db->lastInsertId();

            $this->db->commit();
            return ['sucesso' => true, 'caixa_id' => $caixaId];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Erro ao dar baixa em mensalidade: " . $e->getMessage());
            return ['erro' => 'Erro de banco de dados ao dar baixa: ' . $e->getMessage()];
        }
    }

    /**
     * Estorno da baixa: reverte a mensalidade para pendente/atrasada, remove o
     * pagamento vinculado e o lançamento de caixa correspondente (Entrada).
     */
    public function reverterBaixa($id)
    {
        try {
            $id = (int) $id;
            $mensalidade = $this->getById($id);
            if (!$mensalidade) {
                return ['erro' => 'Mensalidade não encontrada.'];
            }
            if ($mensalidade['status_mensalidade'] !== 'pago') {
                return ['erro' => 'Esta mensalidade não está paga.'];
            }

            $this->db->beginTransaction();

            $update = $this->db->prepare(
                "UPDATE mensalidades SET status_mensalidade = ?, data_pagamento = NULL, observacoes = NULL, updated_at = NOW() WHERE id = ?"
            );
            $novoStatus = (strtotime($mensalidade['data_vencimento']) < strtotime(date('Y-m-d'))) ? 'atrasado' : 'pendente';
            $update->execute([$novoStatus, $id]);

            $delPag = $this->db->prepare(
                "DELETE FROM pagamentos WHERE referencia_type = 'mensalidade' AND referencia_id = ?"
            );
            $delPag->execute([$id]);

            $delCaixa = $this->db->prepare(
                "DELETE FROM caixa WHERE referencia_type = 'mensalidade' AND referencia_id = ? AND tipo = 'Entrada'"
            );
            $delCaixa->execute([$id]);

            $this->db->commit();
            return ['sucesso' => true];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Erro ao reverter baixa de mensalidade: " . $e->getMessage());
            return ['erro' => 'Erro de banco de dados ao estornar: ' . $e->getMessage()];
        }
    }

    /**
     * Lançamento manual de mensalidade (avulsa, jóia de entrada, etc.).
     */
    public function criarManual(array $dados)
    {
        try {
            $d = Sanitizer::sanitizeArray($dados);
            $associadoId = (int) ($d['associado_id'] ?? 0);
            $associacaoId = !empty($d['associacao_id']) ? (int) $d['associacao_id'] : null;
            $planoId = !empty($d['plano_id']) ? (int) $d['plano_id'] : null;
            $ano = (int) ($d['ano_referencia'] ?? (int) date('Y'));
            $mes = (int) ($d['mes_referencia'] ?? (int) date('m'));
            if ($mes < 1) $mes = 1;
            if ($mes > 12) $mes = 12;
            $valor = Sanitizer::sanitizeMoney($d['valor'] ?? 0);
            $vencimento = $d['data_vencimento'] ?? date('Y-m-d');
            $observacoes = trim((string) ($d['observacoes'] ?? ''));
            $metodo = !empty($d['metodo_pagamento']) ? $d['metodo_pagamento'] : null;
            $dataPagamento = !empty($d['data_pagamento']) ? $d['data_pagamento'] : null;

            if ($associadoId <= 0) {
                return ['erro' => 'Selecione um associado.'];
            }
            if ($valor <= 0) {
                return ['erro' => 'Informe um valor válido.'];
            }

            // Associação padrão: a principal do associado (vinculo)
            if (!$associacaoId) {
                $stmt = $this->db->prepare("SELECT vinculo FROM associados WHERE id = ?");
                $stmt->execute([$associadoId]);
                $associacaoId = (int) ($stmt->fetchColumn() ?: 0);
                if ($associacaoId <= 0) {
                    $associacaoId = null;
                }
            }

            $status = (!empty($dataPagamento)) ? 'pago' : 'pendente';

            $this->db->beginTransaction();

            $ins = $this->db->prepare(
                "INSERT INTO mensalidades
                   (associado_id, associacao_id, plano_id, fonte, ano_referencia, mes_referencia,
                    valor_cobrado, data_vencimento, data_pagamento, status_mensalidade, observacoes, created_at, updated_at)
                 VALUES (?, ?, ?, 'manual', ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $ins->execute([
                $associadoId,
                $associacaoId,
                $planoId,
                $ano,
                $mes,
                $valor,
                $vencimento,
                $dataPagamento,
                $status,
                $observacoes,
            ]);
            $mensalidadeId = (int) $this->db->lastInsertId();

            if ($status === 'pago') {
                $metodo = in_array($metodo, ['PIX', 'Boleto', 'Cartao', 'Dinheiro', 'Transferencia'], true) ? $metodo : 'Dinheiro';
                $descricao = (!empty($d['descricao'])) ? $d['descricao'] : 'Lançamento manual';
                $insertPag = $this->db->prepare(
                    "INSERT INTO pagamentos
                       (associado_id, associacao_id, descricao_pagamento, valor, data_pagamento,
                        data_vencimento, metodo_pagamento, status_pagamento, referencia_type,
                        referencia_id, observacoes, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 'pago', 'mensalidade', ?, ?, NOW(), NOW())"
                );
                $insertPag->execute([
                    $associadoId,
                    $associacaoId,
                    $descricao,
                    $valor,
                    $dataPagamento,
                    $vencimento,
                    $metodo,
                    $mensalidadeId,
                    $observacoes,
                ]);

                $insertCaixa = $this->db->prepare(
                    "INSERT INTO caixa
                       (associacao_id, tipo, descricao, categoria, valor, data, comprovante_url,
                        referencia_type, referencia_id, criado_por)
                     VALUES (?, 'Entrada', ?, 'Mensalidade', ?, ?, ?, 'mensalidade', ?, ?)"
                );
                $insertCaixa->execute([
                    $associacaoId,
                    $descricao,
                    $valor,
                    $dataPagamento,
                    !empty($d['comprovante_url']) ? $d['comprovante_url'] : null,
                    $mensalidadeId,
                    (int) ($_SESSION['user_id'] ?? 0),
                ]);
            }

            $this->db->commit();
            return ['sucesso' => true, 'id' => $mensalidadeId];
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Erro ao lançar mensalidade manual: " . $e->getMessage());
            return ['erro' => 'Erro de banco de dados: ' . $e->getMessage()];
        }
    }

    /**
     * Inadimplentes: associados com mensalidades atrasadas/pendentes com vencimento vencido.
     */
    public function getInadimplentes($associacaoId = null, $mes = null, $ano = null)
    {
        try {
            $hoje = date('Y-m-d');
            $sql = "SELECT a.id AS associado_id,
                           CONCAT(a.nome, ' ', a.sobrenome) AS associado_nome,
                           a.telefone, a.email, a.cidade, a.status_associado,
                           m.ano_referencia, m.mes_referencia, m.valor_cobrado, m.data_vencimento,
                           m.id AS mensalidade_id, m.status_mensalidade
                    FROM mensalidades m
                    INNER JOIN associados a ON a.id = m.associado_id
                    WHERE m.status_mensalidade IN ('pendente', 'atrasado')
                      AND m.data_vencimento < :hoje";
            $params = [':hoje' => $hoje];

            if (!empty($associacaoId)) {
                $sql .= " AND m.associacao_id = :assoc";
                $params[':assoc'] = (int) $associacaoId;
            }
            if (!empty($mes)) {
                $sql .= " AND m.mes_referencia = :mes";
                $params[':mes'] = (int) $mes;
            }
            if (!empty($ano)) {
                $sql .= " AND m.ano_referencia = :ano";
                $params[':ano'] = (int) $ano;
            }

            $sql .= " ORDER BY m.data_vencimento ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao listar inadimplentes: " . $e->getMessage());
            return [];
        }
    }

    public function getResumoFinanceiro($associadoId)
    {
        try {
            $sql = "SELECT
                        COALESCE(SUM(CASE WHEN status_mensalidade = 'pago' THEN valor_cobrado ELSE 0 END), 0) AS total_pago,
                        COALESCE(SUM(CASE WHEN status_mensalidade IN ('pendente', 'atrasado') THEN valor_cobrado ELSE 0 END), 0) AS total_em_aberto,
                        SUM(CASE WHEN status_mensalidade IN ('pendente', 'atrasado') THEN 1 ELSE 0 END) AS qtd_em_aberto,
                        SUM(CASE WHEN status_mensalidade = 'pago' THEN 1 ELSE 0 END) AS qtd_pago
                    FROM mensalidades
                    WHERE associado_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([(int) $associadoId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao obter resumo financeiro: " . $e->getMessage());
            return ['total_pago' => 0, 'total_em_aberto' => 0, 'qtd_em_aberto' => 0, 'qtd_pago' => 0];
        }
    }

    /**
     * Inadimplência agregada por mês de referência (pendente/atrasado com vencimento vencido).
     *
     * @return array Agrupado por 'YYYY-MM' com qtd e valor.
     */
    public function getInadimplenciaPorMes($associacaoId = null, $limite = 12)
    {
        $resultado = [];
        $hoje = date('Y-m-d');
        try {
            $sql = "SELECT m.ano_referencia, m.mes_referencia,
                           COUNT(*) AS qtd, COALESCE(SUM(m.valor_cobrado), 0) AS valor
                    FROM mensalidades m
                    WHERE m.status_mensalidade IN ('pendente', 'atrasado')
                      AND m.data_vencimento < :hoje";
            $params = [':hoje' => $hoje];
            if (!empty($associacaoId)) {
                $sql .= " AND m.associacao_id = :assoc";
                $params[':assoc'] = (int) $associacaoId;
            }
            $sql .= " GROUP BY m.ano_referencia, m.mes_referencia
                     ORDER BY m.ano_referencia DESC, m.mes_referencia DESC
                     LIMIT " . (int) $limite;
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $resultado[] = [
                    'ano' => (int) $row['ano_referencia'],
                    'mes' => (int) $row['mes_referencia'],
                    'qtd' => (int) $row['qtd'],
                    'valor' => round((float) $row['valor'], 2),
                ];
            }
        } catch (PDOException $e) {
            error_log("Erro ao agregar inadimplência por mês: " . $e->getMessage());
        }
        return $resultado;
    }

    /**
     * Pagamentos efetuados agregados por forma de pagamento (PIX, Boleto, cartão, etc.).
     *
     * @return array Agrupado por metodo_pagamento com qtd e valor.
     */
    public function getPagamentosPorForma($associacaoId = null, $dataInicio = null, $dataFim = null)
    {
        try {
            $sql = "SELECT metodo_pagamento,
                           COUNT(*) AS qtd, COALESCE(SUM(valor), 0) AS valor
                    FROM pagamentos
                    WHERE status_pagamento = 'pago'";
            $params = [];
            if (!empty($associacaoId)) {
                $sql .= " AND associacao_id = :assoc";
                $params[':assoc'] = (int) $associacaoId;
            }
            if (!empty($dataInicio)) {
                $sql .= " AND data_pagamento >= :ini";
                $params[':ini'] = $dataInicio;
            }
            if (!empty($dataFim)) {
                $sql .= " AND data_pagamento <= :fim";
                $params[':fim'] = $dataFim;
            }
            $sql .= " GROUP BY metodo_pagamento ORDER BY valor DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao resumir pagamentos por forma: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Previsão de receita: mensalidades pendentes/atrasadas com vencimento previsto (3 meses).
     *
     * @return array ['meses' => [['mes_nome', 'valor']], 'total' => float]
     */
    public function getPrevisaoReceita($associacaoId = null, $meses = 3)
    {
        $previsao = [];
        $total = 0.0;
        $hoje = new DateTime('today');
        $mesesPt = [1 => 'JAN', 'FEV', 'MAR', 'ABR', 'MAI', 'JUN', 'JUL', 'AGO', 'SET', 'OUT', 'NOV', 'DEZ'];

        for ($m = 0; $m < (int) $meses; $m++) {
            $dt = (clone $hoje)->modify("first day of this month")->modify("+{$m} months");
            $previsao[] = [
                'chave' => $dt->format('Y-m'),
                'mes_nome' => $mesesPt[(int) $dt->format('n')] . '/' . $dt->format('Y'),
                'valor' => 0.0,
            ];
        }

        try {
            $sql = "SELECT m.ano_referencia, m.mes_referencia,
                           COALESCE(SUM(m.valor_cobrado), 0) AS valor
                    FROM mensalidades m
                    WHERE m.status_mensalidade IN ('pendente', 'atrasado')";
            $params = [];
            if (!empty($associacaoId)) {
                $sql .= " AND m.associacao_id = :assoc";
                $params[':assoc'] = (int) $associacaoId;
            }

            $inicio = (clone $hoje)->modify('first day of this month');
            $fim = (clone $hoje)->modify('first day of this month')->modify('+' . ((int) $meses - 1) . ' months');
            $sql .= " AND (m.ano_referencia = :anoIni AND m.mes_referencia >= :mesIni
                          OR (m.ano_referencia > :anoIni AND m.ano_referencia < :anoFim)
                          OR (m.ano_referencia = :anoFim AND m.mes_referencia <= :mesFim))";
            $params[':anoIni'] = (int) $inicio->format('Y');
            $params[':mesIni'] = (int) $inicio->format('m');
            $params[':anoFim'] = (int) $fim->format('Y');
            $params[':mesFim'] = (int) $fim->format('m');

            $sql .= " GROUP BY m.ano_referencia, m.mes_referencia";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $chave = $row['ano_referencia'] . '-' . str_pad((string) $row['mes_referencia'], 2, '0', STR_PAD_LEFT);
                foreach ($previsao as &$item) {
                    if ($item['chave'] === $chave) {
                        $item['valor'] = round((float) $row['valor'], 2);
                        break;
                    }
                }
                unset($item);
            }

            foreach ($previsao as $item) {
                $total += $item['valor'];
            }
        } catch (PDOException $e) {
            error_log("Erro ao calcular previsão de receita: " . $e->getMessage());
        }

        return ['meses' => $previsao, 'total' => round($total, 2)];
    }

    /**
     * Gera cobrança PIX para uma mensalidade
     */
    public function gerarPix($mensalidadeId, $dados = []): array
    {
        require_once __DIR__ . '/PixProvider.php';

        $mensalidade = $this->getById($mensalidadeId);
        if (!$mensalidade) {
            return ['erro' => 'Mensalidade não encontrada'];
        }
        if ($mensalidade['status_mensalidade'] === 'pago') {
            return ['erro' => 'Mensalidade já paga'];
        }

        // Gerar txid único se não existir
        $txid = $mensalidade['txid'] ?? 'MENS-' . $mensalidadeId . '-' . time();

        // Buscar dados do associado
        $stmt = $this->db->prepare("SELECT * FROM associados WHERE id = ?");
        $stmt->execute([$mensalidade['associado_id']]);
        $associado = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$associado) {
            return ['erro' => 'Associado não encontrado'];
        }

        $pixProvider = new PixProvider();
        $resultado = $pixProvider->criarCobranca([
            'txid' => $txid,
            'valor' => $mensalidade['valor_cobrado'],
            'data_vencimento' => $mensalidade['data_vencimento'],
            'descricao' => $mensalidade['plano_nome'] ?? 'Mensalidade ' . str_pad((string) $mensalidade['mes_referencia'], 2, '0', STR_PAD_LEFT) . '/' . $mensalidade['ano_referencia'],
            'referencia_id' => $mensalidadeId,
            'nome' => $associado['nome'] . ' ' . $associado['sobrenome'],
            'email' => $associado['email'] ?? '',
            'telefone' => $associado['telefone'] ?? '',
            'cpf_cnpj' => $associado['cpf'] ?? '',
            'endereco' => $associado['endereco'] ?? '',
            'numero' => $associado['numero'] ?? '',
            'complemento' => $associado['complemento'] ?? '',
            'bairro' => $associado['bairro'] ?? '',
            'cep' => $associado['cep'] ?? '',
        ]);

        if (!empty($resultado['sucesso'])) {
            // Salvar txid na mensalidade
            $upd = $this->db->prepare("UPDATE mensalidades SET txid = ?, updated_at = NOW() WHERE id = ?");
            $upd->execute([$resultado['txid'], $mensalidadeId]);
        }

        return $resultado;
    }

    /**
     * Processa webhook de confirmação PIX
     */
    public function processarWebhookPix($txid, $dadosWebhook): array
    {
        // Buscar mensalidade pelo txid
        $stmt = $this->db->prepare("SELECT * FROM mensalidades WHERE txid = ?");
        $stmt->execute([$txid]);
        $mensalidade = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mensalidade) {
            return ['erro' => 'Mensalidade não encontrada para txid: ' . $txid];
        }

        if ($mensalidade['status_mensalidade'] === 'pago') {
            return ['sucesso' => true, 'ja_processado' => true];
        }

        $pixProvider = new PixProvider();
        $status = $pixProvider->verificarStatus($txid);

        if (isset($status['erro'])) {
            return ['erro' => $status['erro']];
        }

        if ($status['status'] === 'pago') {
            $dataPagamento = $status['data_pagamento'] ?? date('Y-m-d');
            $valorPago = $status['valor_pago'] ?? $mensalidade['valor_cobrado'];

            // Baixa automática
            $result = $this->baixar($mensalidade['id'], [
                'metodo_pagamento' => 'PIX',
                'data_pagamento' => $dataPagamento,
                'valor' => $valorPago,
                'observacoes' => 'Baixa automática via PIX (webhook)',
                'comprovante_url' => $dadosWebhook['comprovante_url'] ?? null,
            ]);

            return ['sucesso' => true, 'baixa' => $result];
        }

        return ['sucesso' => false, 'status' => $status['status']];
    }
}