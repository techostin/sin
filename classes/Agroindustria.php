<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Agroindustria
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Lista agroindústrias (opcionalmente filtradas por associado).
     */
    public function getAll($filtroAssociado = null)
    {
        $sql = "SELECT a.*,
                       CONCAT(ass.nome, ' ', COALESCE(ass.sobrenome, '')) AS associado_nome
                FROM agroindustrias a
                JOIN associados ass ON a.associado_id = ass.id
                WHERE a.excluido = 0";
        $params = [];
        if ($filtroAssociado) {
            $sql .= " AND a.associado_id = ?";
            $params[] = (int)$filtroAssociado;
        }
        $sql .= " ORDER BY a.nome_fantasia ASC, a.criado_em DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM agroindustrias WHERE id = ? AND excluido = 0");
        $stmt->execute([(int)$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function countByAssociado($associadoId)
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM agroindustrias WHERE associado_id = ? AND excluido = 0");
        $stmt->execute([(int)$associadoId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Lista os associados que integram (são membros de) uma agroindústria.
     * Inclui o proprietário, pois ele é vinculado automaticamente na migração.
     */
    public function getMembros($agroindustriaId)
    {
        $stmt = $this->db->prepare(
            "SELECT a.id, a.nome, a.sobrenome, a.tipo_usuario
             FROM agroindustria_membros m
             JOIN associados a ON m.associado_id = a.id
             WHERE m.agroindustria_id = ? AND m.excluido = 0
               AND a.status = 'ativo'
             ORDER BY a.nome ASC"
        );
        $stmt->execute([(int)$agroindustriaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna todos os agroindustria_id aos quais um associado pertence
     * (como proprietário ou como membro).
     */
    public function getAgroindustriasPorAssociado($associadoId)
    {
        if (!$associadoId) {
            return [];
        }
        $sql = "SELECT DISTINCT a.id
                FROM agroindustrias a
                WHERE a.excluido = 0 AND (
                    (a.associado_id = ? AND a.associado_id IS NOT NULL)
                    OR EXISTS (
                        SELECT 1 FROM agroindustria_membros m
                        WHERE m.agroindustria_id = a.id AND m.associado_id = ? AND m.excluido = 0
                    )
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([(int)$associadoId, (int)$associadoId]);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');
    }

    /**
     * Retorna os associado_id de todos os membros das agroindústrias do
     * associado informado (incluindo ele próprio). Permite compartilhar
     * insumos entre usuários X e Y da mesma agroindústria.
     */
    public function getAssociadosDaMesmaAgroindustria($associadoId)
    {
        if (!$associadoId) {
            return [];
        }
        $agros = $this->getAgroindustriasPorAssociado($associadoId);
        if (!$agros) {
            return [(int)$associadoId];
        }
        $in = implode(',', array_map('intval', $agros));
        $stmt = $this->db->query(
            "SELECT DISTINCT associado_id FROM agroindustria_membros
             WHERE excluido = 0 AND agroindustria_id IN ($in)"
        );
        $ids = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'associado_id');
        $ids[] = (int)$associadoId;
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Adiciona um associado como membro de uma agroindústria.
     * @return bool
     */
    public function adicionarMembro($agroindustriaId, $associadoId, $criadoPor = null)
    {
        try {
            $stmt = $this->db->prepare("INSERT IGNORE INTO agroindustria_membros (agroindustria_id, associado_id, criado_por) VALUES (?, ?, ?)");
            $stmt->execute([(int)$agroindustriaId, (int)$associadoId, $criadoPor ? (int)$criadoPor : null]);
            return $stmt->rowCount() > 0 || true;
        } catch (PDOException $e) {
            error_log("Erro ao adicionar membro na agroindústria: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove um associado da lista de membros de uma agroindústria
     * (nunca remove o proprietário).
     */
    public function removerMembro($agroindustriaId, $associadoId)
    {
        $stmt = $this->db->prepare(
            "UPDATE agroindustria_membros SET excluido = 1
             WHERE agroindustria_id = ? AND associado_id = ?
               AND associado_id <> (SELECT associado_id FROM agroindustrias WHERE id = ? AND excluido = 0)"
        );
        return $stmt->execute([(int)$agroindustriaId, (int)$associadoId, (int)$agroindustriaId]);
    }

    /**
     * Cadastra uma agroindústria e marca o associado como possuidor de
     * agroindústria (tem_agroindustria = 1).
     * @return int|string ID criado, ou mensagem de erro
     */
    public function create($dados)
    {
        $dados = Sanitizer::sanitizeArray($dados);

        $cnpj = $this->soDigitos($dados['cnpj'] ?? '');
        if ($cnpj !== '' && !$this->validarCnpj($cnpj)) {
            return 'CNPJ inválido.';
        }

        $associadoId = (int)($dados['associado_id'] ?? 0);
        if ($associadoId <= 0) {
            return 'Associado obrigatório.';
        }

        try {
            $stmt = $this->db->prepare(
                "INSERT INTO agroindustrias
                    (associado_id, razao_social, nome_fantasia, cnpj, inscricao_estadual,
                     telefone, email, responsavel_tecnico, endereco, numero, bairro, cidade,
                     estado, cep, status, observacoes, criado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $associadoId,
                trim($dados['razao_social'] ?? '') ?: null,
                trim($dados['nome_fantasia'] ?? '') ?: null,
                $cnpj !== '' ? $this->formatarCnpj($cnpj) : null,
                trim($dados['inscricao_estadual'] ?? '') ?: null,
                trim($dados['telefone'] ?? '') ?: null,
                trim($dados['email'] ?? '') ?: null,
                trim($dados['responsavel_tecnico'] ?? '') ?: null,
                trim($dados['endereco'] ?? '') ?: null,
                trim($dados['numero'] ?? '') ?: null,
                trim($dados['bairro'] ?? '') ?: null,
                trim($dados['cidade'] ?? '') ?: null,
                trim($dados['estado'] ?? '') ?: null,
                trim($dados['cep'] ?? '') ?: null,
                ($dados['status'] ?? 'Ativa') === 'Inativa' ? 'Inativa' : 'Ativa',
                trim($dados['observacoes'] ?? '') ?: null,
                !empty($dados['criado_por']) ? (int)$dados['criado_por'] : null,
            ]);
            $novoId = (int)$this->db->lastInsertId();

            $this->marcarAssociadoComAgroindustria($associadoId);

            return $novoId;
        } catch (PDOException $e) {
            error_log("Erro ao criar agroindústria: " . $e->getMessage());
            return 'Erro de banco de dados ao cadastrar a agroindústria.';
        }
    }

    public function update($id, $dados)
    {
        $dados = Sanitizer::sanitizeArray($dados);

        $cnpj = $this->soDigitos($dados['cnpj'] ?? '');
        if ($cnpj !== '' && !$this->validarCnpj($cnpj)) {
            return 'CNPJ inválido.';
        }

        try {
            $stmt = $this->db->prepare(
                "UPDATE agroindustrias
                    SET razao_social = ?, nome_fantasia = ?, cnpj = ?, inscricao_estadual = ?,
                        telefone = ?, email = ?, responsavel_tecnico = ?, endereco = ?, numero = ?,
                        bairro = ?, cidade = ?, estado = ?, cep = ?, status = ?, observacoes = ?
                  WHERE id = ? AND excluido = 0"
            );
            $stmt->execute([
                trim($dados['razao_social'] ?? '') ?: null,
                trim($dados['nome_fantasia'] ?? '') ?: null,
                $cnpj !== '' ? $this->formatarCnpj($cnpj) : null,
                trim($dados['inscricao_estadual'] ?? '') ?: null,
                trim($dados['telefone'] ?? '') ?: null,
                trim($dados['email'] ?? '') ?: null,
                trim($dados['responsavel_tecnico'] ?? '') ?: null,
                trim($dados['endereco'] ?? '') ?: null,
                trim($dados['numero'] ?? '') ?: null,
                trim($dados['bairro'] ?? '') ?: null,
                trim($dados['cidade'] ?? '') ?: null,
                trim($dados['estado'] ?? '') ?: null,
                trim($dados['cep'] ?? '') ?: null,
                ($dados['status'] ?? 'Ativa') === 'Inativa' ? 'Inativa' : 'Ativa',
                trim($dados['observacoes'] ?? '') ?: null,
                (int)$id,
            ]);

            $this->marcarAssociadoComAgroindustria((int)($dados['associado_id'] ?? 0));

            return true;
        } catch (PDOException $e) {
            error_log("Erro ao atualizar agroindústria: " . $e->getMessage());
            return 'Erro de banco de dados ao atualizar a agroindústria.';
        }
    }

    public function softDelete($id)
    {
        $stmt = $this->db->prepare("UPDATE agroindustrias SET excluido = 1 WHERE id = ?");
        return $stmt->execute([(int)$id]);
    }

    private function marcarAssociadoComAgroindustria($associadoId)
    {
        if ($associadoId <= 0) {
            return;
        }
        try {
            $this->db->prepare("UPDATE associados SET tem_agroindustria = 1 WHERE id = ?")
                     ->execute([(int)$associadoId]);
        } catch (PDOException $e) {
            error_log("Erro ao marcar tem_agroindustria: " . $e->getMessage());
        }
    }

    private function soDigitos($valor)
    {
        return preg_replace('/\D+/', '', (string)$valor);
    }

    public function formatarCnpj($cnpj)
    {
        $cnpj = $this->soDigitos($cnpj);
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }
        return substr($cnpj, 0, 2) . '.' . substr($cnpj, 2, 3) . '.' . substr($cnpj, 5, 3)
             . '/' . substr($cnpj, 8, 4) . '-' . substr($cnpj, 12, 2);
    }

    public function validarCnpj($cnpj)
    {
        $cnpj = $this->soDigitos($cnpj);
        if (strlen($cnpj) !== 14) {
            return false;
        }
        if (preg_match('/^(\d)\1+$/', $cnpj)) {
            return false;
        }

        $calc = function ($length) use ($cnpj) {
            $pesos = $length === 12
                ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]
                : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $soma = 0;
            for ($i = 0; $i < $length; $i++) {
                $soma += (int)$cnpj[$i] * $pesos[$i];
            }
            $resto = $soma % 11;
            return $resto < 2 ? 0 : 11 - $resto;
        };

        if ((int)$cnpj[12] !== $calc(12)) {
            return false;
        }
        return (int)$cnpj[13] === $calc(13);
    }
}
