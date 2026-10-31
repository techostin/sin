<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Caixa
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll(array $filtros = [])
    {
        try {
            $sql = "SELECT cx.*, ac.nome AS associacao_nome, ac.sigla AS associacao_sigla
                    FROM caixa cx
                    LEFT JOIN associacoes ac ON ac.id = cx.associacao_id
                    WHERE 1 = 1";
            $params = [];

            if (!empty($filtros['associacao_id'])) {
                $sql .= " AND cx.associacao_id = :assoc";
                $params[':assoc'] = (int) $filtros['associacao_id'];
            }
            if (!empty($filtros['tipo'])) {
                $sql .= " AND cx.tipo = :tipo";
                $params[':tipo'] = $filtros['tipo'];
            }
            if (!empty($filtros['data_inicio'])) {
                $sql .= " AND cx.data >= :ini";
                $params[':ini'] = $filtros['data_inicio'];
            }
            if (!empty($filtros['data_fim'])) {
                $sql .= " AND cx.data <= :fim";
                $params[':fim'] = $filtros['data_fim'];
            }

            $sql .= " ORDER BY cx.data DESC, cx.id DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao listar caixa: " . $e->getMessage());
            return [];
        }
    }

    public function getById($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM caixa WHERE id = ?");
            $stmt->execute([(int) $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar lançamento de caixa: " . $e->getMessage());
            return false;
        }
    }

    public function create(array $dados)
    {
        try {
            $d = Sanitizer::sanitizeArray($dados);
            $tipo = ($d['tipo'] ?? 'Entrada') === 'Saida' ? 'Saida' : 'Entrada';
            $valor = Sanitizer::sanitizeMoney($d['valor'] ?? 0);
            if ($valor <= 0) {
                return ['erro' => 'Informe um valor válido.'];
            }
            $associacaoId = !empty($d['associacao_id']) ? (int) $d['associacao_id'] : null;
            $data = !empty($d['data']) ? $d['data'] : date('Y-m-d');
            $criadoPor = (int) ($_SESSION['user_id'] ?? 0);

            $stmt = $this->db->prepare(
                "INSERT INTO caixa (associacao_id, tipo, descricao, categoria, valor, data, comprovante_url, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $associacaoId,
                $tipo,
                $d['descricao'] ?? '',
                $d['categoria'] ?? null,
                $valor,
                $data,
                !empty($d['comprovante_url']) ? $d['comprovante_url'] : null,
                $criadoPor,
            ]);
            return ['sucesso' => true, 'id' => (int) $this->db->lastInsertId()];
        } catch (PDOException $e) {
            error_log("Erro ao lançar caixa: " . $e->getMessage());
            return ['erro' => 'Erro de banco de dados: ' . $e->getMessage()];
        }
    }

    public function delete($id)
    {
        try {
            $id = (int) $id;
            $stmt = $this->db->prepare("SELECT * FROM caixa WHERE id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($item && $item['referencia_type'] === 'mensalidade' && !empty($item['referencia_id'])) {
                require_once __DIR__ . '/Mensalidade.php';
                $resultado = (new Mensalidade())->reverterBaixa((int) $item['referencia_id']);
                if (!empty($resultado['erro'])) {
                    error_log("Erro ao reverter mensalidade ao excluir caixa: " . $resultado['erro']);
                }
            }

            $del = $this->db->prepare("DELETE FROM caixa WHERE id = ?");
            return $del->execute([$id]);
        } catch (PDOException $e) {
            error_log("Erro ao excluir lançamento de caixa: " . $e->getMessage());
            return false;
        }
    }

    public function getSaldo($associacaoId = null)
    {
        try {
            $sql = "SELECT COALESCE(SUM(CASE WHEN tipo = 'Entrada' THEN valor ELSE 0 END), 0) AS entradas,
                           COALESCE(SUM(CASE WHEN tipo = 'Saida' THEN valor ELSE 0 END), 0) AS saidas
                    FROM caixa";
            $params = [];
            if (!empty($associacaoId)) {
                $sql .= " WHERE associacao_id = ?";
                $params[] = (int) $associacaoId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'entradas' => round((float) $row['entradas'], 2),
                'saidas' => round((float) $row['saidas'], 2),
                'saldo' => round((float) $row['entradas'] - (float) $row['saidas'], 2),
            ];
        } catch (PDOException $e) {
            error_log("Erro ao calcular saldo de caixa: " . $e->getMessage());
            return ['entradas' => 0, 'saidas' => 0, 'saldo' => 0];
        }
    }

    public function getResumoPeriodo($dataInicio, $dataFim, $associacaoId = null)
    {
        try {
            $sql = "SELECT COALESCE(SUM(CASE WHEN tipo = 'Entrada' THEN valor ELSE 0 END), 0) AS entradas,
                           COALESCE(SUM(CASE WHEN tipo = 'Saida' THEN valor ELSE 0 END), 0) AS saidas
                    FROM caixa
                    WHERE data >= ? AND data <= ?";
            $params = [$dataInicio, $dataFim];
            if (!empty($associacaoId)) {
                $sql .= " AND associacao_id = ?";
                $params[] = (int) $associacaoId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return [
                'entradas' => round((float) $row['entradas'], 2),
                'saidas' => round((float) $row['saidas'], 2),
                'saldo_periodo' => round((float) $row['entradas'] - (float) $row['saidas'], 2),
            ];
        } catch (PDOException $e) {
            error_log("Erro ao calcular resumo do período: " . $e->getMessage());
            return ['entradas' => 0, 'saidas' => 0, 'saldo_periodo' => 0];
        }
    }

    /**
     * Fluxo de caixa agregado por mês (realizado) para o relatório financeiro.
     *
     * @param int|null $associacaoId Filtro por associação.
     * @param int      $meses        Quantidade de meses a partir do mês atual (inclusive), incluindo meses sem movimento.
     *
     * @return array Lista de ['ano', 'mes', 'entradas', 'saidas', 'saldo']
     */
    public function getFluxoPorMes($associacaoId = null, $meses = 6)
    {
        $meses = max(1, (int) $meses);
        $resultado = [];
        $hoje = new DateTime('today');
        for ($m = $meses - 1; $m >= 0; $m--) {
            $dt = clone $hoje;
            $dt->modify("first day of this month");
            $dt->modify("-{$m} months");
            $resultado[$dt->format('Y-m')] = [
                'ano' => (int) $dt->format('Y'),
                'mes' => (int) $dt->format('m'),
                'entradas' => 0.0,
                'saidas' => 0.0,
                'saldo' => 0.0,
            ];
        }

        try {
            $sql = "SELECT YEAR(data) AS ano, MONTH(data) AS mes,
                           COALESCE(SUM(CASE WHEN tipo = 'Entrada' THEN valor ELSE 0 END), 0) AS entradas,
                           COALESCE(SUM(CASE WHEN tipo = 'Saida' THEN valor ELSE 0 END), 0) AS saidas
                    FROM caixa
                    WHERE data >= :inicio
                    GROUP BY YEAR(data), MONTH(data)";
            $inicio = (new DateTime('today'))->modify('first day of this month')->modify('-'.($meses - 1).' months')->format('Y-m-01');
            $params = [':inicio' => $inicio];
            if (!empty($associacaoId)) {
                $sql .= " AND associacao_id = :assoc";
                $params[':assoc'] = (int) $associacaoId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $chave = $row['ano'] . '-' . str_pad((string) $row['mes'], 2, '0', STR_PAD_LEFT);
                if (!isset($resultado[$chave])) {
                    continue;
                }
                $resultado[$chave]['entradas'] = round((float) $row['entradas'], 2);
                $resultado[$chave]['saidas']   = round((float) $row['saidas'], 2);
                $resultado[$chave]['saldo']    = round((float) $row['entradas'] - (float) $row['saidas'], 2);
            }
        } catch (PDOException $e) {
            error_log("Erro ao calcular fluxo de caixa por mês: " . $e->getMessage());
        }

        return array_values($resultado);
    }
}