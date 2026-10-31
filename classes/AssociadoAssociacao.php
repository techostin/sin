<?php
/**
 * AssociadoAssociacao — gerenciamento dos vínculos N:N entre associados e associações.
 *
 * A tabela `associado_associacao` guarda a relação (chave composta), enquanto a coluna
 * `vinculo` de `associados` preserva a associação principal para compatibilidade.
 */
final class AssociadoAssociacao
{
    /**
     * Lista as associações vinculadas a um associado.
     *
     * @return array
     */
    public static function getByAssociado($associadoId)
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT ac.id, ac.nome, ac.sigla, ac.status, aa.data_vinculo
                 FROM associado_associacao aa
                 INNER JOIN associacoes ac ON ac.id = aa.associacao_id
                 WHERE aa.associado_id = :assoc
                 ORDER BY ac.nome ASC"
            );
            $stmt->bindValue(':assoc', (int) $associadoId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar vínculos do associado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * IDs das associações vinculadas a um associado (útil para checkboxes).
     *
     * @return int[]
     */
    public static function getIdsByAssociado($associadoId)
    {
        $vinculos = self::getByAssociado($associadoId);
        return array_map('intval', array_column($vinculos, 'id'));
    }

    /**
     * Lista associados vinculados a uma associação.
     *
     * @return array
     */
    public static function getAssociadosByAssociacao($associacaoId)
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT a.id, a.nome, a.sobrenome, a.cpf, a.telefone, a.email,
                        a.status_associado, a.tipo_associado, aa.data_vinculo
                 FROM associado_associacao aa
                 INNER JOIN associados a ON a.id = aa.associado_id
                 WHERE aa.associacao_id = :assoc
                 ORDER BY a.nome ASC, a.sobrenome ASC"
            );
            $stmt->bindValue(':assoc', (int) $associacaoId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar associados da associação: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Associações ativas disponíveis para vínculo.
     *
     * @return array
     */
    public static function listAssociacoes()
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->query("SELECT id, nome, sigla FROM associacoes WHERE status = 'ativo' ORDER BY nome ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao listar associações: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Substitui os vínculos de um associado e mantém a associação principal
     * (primeira selecionada) na coluna `vinculo` de `associados`.
     *
     * @return bool
     */
    public static function save($associadoId, array $associacaoIds)
    {
        try {
            $db = Database::getInstance()->getConnection();
            $associadoId = (int) $associadoId;
            $associacaoIds = array_values(array_filter(array_map('intval', $associacaoIds)));

            $db->beginTransaction();

            $del = $db->prepare("DELETE FROM associado_associacao WHERE associado_id = ?");
            $del->execute([$associadoId]);

            if (!empty($associacaoIds)) {
                $ins = $db->prepare(
                    "INSERT INTO associado_associacao (associado_id, associacao_id) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE data_vinculo = CURRENT_TIMESTAMP"
                );
                foreach ($associacaoIds as $assocId) {
                    $ins->execute([$associadoId, $assocId]);
                }

                $principal = $associacaoIds[0];
                $upd = $db->prepare("UPDATE associados SET vinculo = ?, updated_at = NOW() WHERE id = ?");
                $upd->execute([$principal, $associadoId]);
            } else {
                $upd = $db->prepare("UPDATE associados SET vinculo = NULL, updated_at = NOW() WHERE id = ?");
                $upd->execute([$associadoId]);
            }

            $db->commit();
            return true;
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("Erro ao salvar vínculos do associado: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove todos os vínculos de um associado.
     *
     * @return bool
     */
    public static function removeAll($associadoId)
    {
        return self::save((int) $associadoId, []);
    }
}