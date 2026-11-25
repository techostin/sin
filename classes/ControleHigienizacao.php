<?php
require_once __DIR__ . '/Database.php';

class ControleHigienizacao
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function getAll($filtroAssociado = null)
    {
        $sql = "SELECT * FROM controle_higienizacao WHERE excluido = 0";
        $params = [];
        if ($filtroAssociado) {
            $sql .= " AND associado_id = ?";
            $params[] = (int)$filtroAssociado;
        }
        $sql .= " ORDER BY data_hora_inicio DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM controle_higienizacao WHERE id = ? AND excluido = 0");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * RF08: verifica se existe higienização válida (concluída) dentro do intervalo
     * de horas anteriores à data informada, para o equipamento/linha indicado.
     */
    public function haHigienizacaoValida($dataProcessamento, $equipamentoLinha, $associadoId, $intervaloHoras = 24)
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM controle_higienizacao
              WHERE excluido = 0
                AND data_hora_fim IS NOT NULL
                AND equipamento_linha = ?
                AND data_hora_fim <= ?
                AND data_hora_fim >= DATE_SUB(?, INTERVAL ? HOUR)"
        );
        $stmt->execute([$equipamentoLinha, $dataProcessamento, $dataProcessamento, (int)$intervaloHoras]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Lista oficial de sanitizantes permitidos para higienização de instalações,
     * equipamentos e utensílios em sistemas orgânicos (Anexo I da Portaria 52/2021,
     * com redação dada pela Portaria SDA 811/2023).
     * @return array
     */
    public function listarSanitizantesPermitidos()
    {
        $stmt = $this->db->query(
            "SELECT id, nome, subcategoria, restricoes, normativa
               FROM insumos_organicos_aprovados
              WHERE categoria = 'Higienização' AND permitido = 1
              ORDER BY nome ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($dados)
    {
        $stmt = $this->db->prepare(
            "INSERT INTO controle_higienizacao
                (data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, criado_por)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $dados['data_hora_inicio'],
            !empty($dados['data_hora_fim']) ? $dados['data_hora_fim'] : null,
            trim($dados['equipamento_linha']),
            trim($dados['sanitizante_utilizado']),
            trim($dados['responsavel_nome']),
            !empty($dados['associado_id']) ? (int)$dados['associado_id'] : null,
            !empty($dados['observacoes']) ? $dados['observacoes'] : null,
            !empty($dados['criado_por']) ? (int)$dados['criado_por'] : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function softDelete($id)
    {
        $stmt = $this->db->prepare("UPDATE controle_higienizacao SET excluido = 1 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }
}
