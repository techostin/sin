<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class LoteProducao {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Cria um novo lote de produção.
     * @param array $dados Os dados do lote.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function create($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);
            
            // Gerar UUID se não foi fornecido
            if (empty($dados_sanitizados['uuid'])) {
                $dados_sanitizados['uuid'] = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                    mt_rand(0, 0xffff),
                    mt_rand(0, 0x0fff) | 0x4000,
                    mt_rand(0, 0x3fff) | 0x8000,
                    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
                );
            }

            $sql = "INSERT INTO lotes_producao (uuid, nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes, criado_em) 
                    VALUES (:uuid, :nome_lote, :produto_id, :associado_id, :data_producao, :quantidade, :unidade_medida, :observacoes, NOW())";
            $stmt = $this->db->prepare($sql);

            $stmt->bindParam(':uuid', $dados_sanitizados['uuid']);
            $stmt->bindParam(':nome_lote', $dados_sanitizados['nome_lote']);
            $stmt->bindParam(':produto_id', $dados_sanitizados['produto_id']);
            $stmt->bindValue(':associado_id', isset($dados_sanitizados['associado_id']) && $dados_sanitizados['associado_id'] !== '' ? (int)$dados_sanitizados['associado_id'] : null);
            $stmt->bindParam(':data_producao', $dados_sanitizados['data_producao']);
            $stmt->bindParam(':quantidade', $dados_sanitizados['quantidade']);
            $stmt->bindParam(':unidade_medida', $dados_sanitizados['unidade_medida']);
            $stmt->bindParam(':observacoes', $dados_sanitizados['observacoes']);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar lote de produção: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca todos os lotes de produção.
     * @return array Retorna um array de lotes.
     */
    public function getAll() {
        try {
            $sql = "SELECT lp.id, lp.uuid, lp.nome_lote, lp.data_producao, lp.quantidade, lp.unidade_medida, p.nome as produto_nome
                    FROM lotes_producao lp
                    LEFT JOIN produtos p ON lp.produto_id = p.id
                    WHERE lp.excluido = 0
                    ORDER BY lp.data_producao DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar lotes de produção: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca os lotes de produção vinculados a um associado específico.
     * @param int $associado_id ID do associado
     * @return array
     */
    public function getByAssociadoId($associado_id) {
        try {
            $sql = "SELECT lp.id, lp.uuid, lp.nome_lote, lp.data_producao, lp.quantidade, lp.unidade_medida, pr.nome as produto_nome
                    FROM lotes_producao lp
                    LEFT JOIN produtos pr ON lp.produto_id = pr.id
                    LEFT JOIN lavouras l ON pr.lavoura_id = l.id
                    LEFT JOIN propriedades p ON l.propriedade_id = p.id
                    WHERE lp.excluido = 0
                      AND (lp.associado_id = :associado_id OR (lp.associado_id IS NULL AND p.associado_id = :associado_id))
                    ORDER BY lp.data_producao DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':associado_id', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar lotes de produção por associado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca um lote de produção pelo UUID.
     * @param string $uuid O UUID do lote.
     * @return array|false Retorna os dados do lote ou false se não encontrado.
     */
    public function getByUuid($uuid) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM lotes_producao WHERE uuid = :uuid AND excluido = 0");
            $stmt->bindParam(':uuid', $uuid);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar lote por UUID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca um lote de produção pelo ID.
     * @param int $id O ID do lote.
     * @return array|false Retorna os dados do lote ou false se não encontrado.
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM lotes_producao WHERE id = :id AND excluido = 0");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar lote por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca as atividades associadas a um lote através da lavoura do produto.
     * @param int $lote_id O ID do lote.
     * @return array Retorna um array de atividades.
     */
    public function getAtividadesByLoteId($lote_id) {
        try {
            $sql = "SELECT ad.nome, ad.data_atividade, ad.tipo_atividade, ad.descricao, ad.hora_inicio, ad.hora_fim, ad.status
                    FROM lotes_producao lp
                    JOIN produtos p ON lp.produto_id = p.id
                    JOIN atividades_diarias ad ON p.lavoura_id = ad.lavoura_id
                    WHERE lp.id = :lote_id AND ad.excluido = 0
                    ORDER BY ad.data_atividade ASC, ad.hora_inicio ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':lote_id', $lote_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar atividades do lote: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca os insumos utilizados nas atividades relacionadas ao lote.
     * @param int $lote_id O ID do lote.
     * @return array Retorna um array de insumos.
     */
    public function getInsumosByLoteId($lote_id) {
        try {
            $sql = "SELECT DISTINCT ad.insumo_utilisado, ad.data_atividade, ad.tipo_atividade
                    FROM lotes_producao lp
                    JOIN produtos p ON lp.produto_id = p.id
                    JOIN atividades_diarias ad ON p.lavoura_id = ad.lavoura_id
                    WHERE lp.id = :lote_id AND ad.excluido = 0 AND ad.insumo_utilisado IS NOT NULL AND ad.insumo_utilisado != ''
                    ORDER BY ad.data_atividade ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':lote_id', $lote_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar insumos do lote: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Atualiza um lote de produção existente.
     * @param int $id O ID do lote.
     * @param array $dados Os dados atualizados do lote.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function update($id, $dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);
            $sql = "UPDATE lotes_producao SET nome_lote = :nome_lote, produto_id = :produto_id, data_producao = :data_producao, quantidade = :quantidade, unidade_medida = :unidade_medida, observacoes = :observacoes WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':nome_lote', $dados_sanitizados['nome_lote']);
            $stmt->bindParam(':produto_id', $dados_sanitizados['produto_id']);
            $stmt->bindParam(':data_producao', $dados_sanitizados['data_producao']);
            $stmt->bindParam(':quantidade', $dados_sanitizados['quantidade']);
            $stmt->bindParam(':unidade_medida', $dados_sanitizados['unidade_medida']);
            $stmt->bindParam(':observacoes', $dados_sanitizados['observacoes']);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar lote de produção: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui (soft delete) um lote de produção.
     * @param int $id O ID do lote.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("UPDATE lotes_producao SET excluido = 1 WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao excluir lote de produção: " . $e->getMessage());
            return false;
        }
    }

    // Métodos para adicionar/remover atividades e insumos a um lote seriam adicionados aqui
}
?>