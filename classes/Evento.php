<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Evento {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca todos os eventos do banco de dados.
     * @return array Retorna um array de eventos.
     */
    public function getAllEventos() {
        try {
            $stmt = $this->db->prepare("SELECT * FROM eventos ORDER BY data_evento DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar eventos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca a lista de presença para os eventos.
     * @return array Retorna um array com dados de presença.
     */
    public function getPresencaEventos() {
        try {
            $sql = "SELECT 
                        e.nome as evento_nome,
                        COALESCE(a.nome, u.nome) as associado_nome,
                        COALESCE(a.sobrenome, '') as associado_sobrenome,
                        p.status_presenca,
                        e.data_evento
                    FROM evento_presenca p
                    JOIN eventos e ON p.evento_id = e.id
                    LEFT JOIN associados a ON p.associado_id = a.id
                    LEFT JOIN usuarios u ON p.usuario_id = u.id
                    ORDER BY e.data_evento DESC, COALESCE(a.nome, u.nome) ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar presença: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca a lista de presença para um evento específico.
     * @param int $evento_id ID do evento.
     * @return array Retorna um array associativo [associado_id => status].
     */
    public function getPresencasByEvento($evento_id) {
        try {
            $stmt = $this->db->prepare("SELECT associado_id, usuario_id, status_presenca FROM evento_presenca WHERE evento_id = ?");
            $stmt->execute([$evento_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $presencas = [];
            foreach ($rows as $row) {
                if ($row['associado_id']) {
                    $presencas[$row['associado_id']] = $row['status_presenca'];
                }
                if ($row['usuario_id']) {
                    $presencas['u_' . $row['usuario_id']] = $row['status_presenca'];
                }
            }
            return $presencas;
        } catch (PDOException $e) {
            error_log("Erro ao buscar presença do evento $evento_id: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Atualiza a lista de presença de um evento.
     * @param int $evento_id ID do evento.
     * @param array $presencas Array associativo [associado_id => status].
     * @return bool Retorna true em caso de sucesso.
     */
    public function atualizarPresencas($evento_id, $presencas) {
        try {
            $this->db->beginTransaction();

            // Preparar statements
            $stmt_delete = $this->db->prepare("DELETE FROM evento_presenca WHERE evento_id = ? AND associado_id = ?");
            $stmt_insert = $this->db->prepare("INSERT INTO evento_presenca (evento_id, associado_id, status_presenca) VALUES (?, ?, ?)");
            $stmt_update = $this->db->prepare("UPDATE evento_presenca SET status_presenca = ? WHERE evento_id = ? AND associado_id = ?");

            foreach ($presencas as $associado_id => $status) {
                if (empty($status) || $status === 'Nenhum') {
                    $stmt_delete->execute([$evento_id, $associado_id]);
                } else {
                    // Tenta atualizar, se não houver registros (rowCount == 0), insere
                    $stmt_update->execute([$status, $evento_id, $associado_id]);
                    if ($stmt_update->rowCount() === 0) {
                        // Verifica se realmente não existe ou se o status já era o mesmo
                        $stmt_check = $this->db->prepare("SELECT COUNT(*) FROM evento_presenca WHERE evento_id = ? AND associado_id = ?");
                        $stmt_check->execute([$evento_id, $associado_id]);
                        if ($stmt_check->fetchColumn() == 0) {
                            $stmt_insert->execute([$evento_id, $associado_id, $status]);
                        }
                    }
                }
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Erro ao atualizar presença do evento $evento_id: " . $e->getMessage());
            return false;
        }
    }
}
?>