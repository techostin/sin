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

    /**
     * Busca a visão de presença para um usuário comum em todos os eventos agendados.
     * Retorna sempre uma linha por evento agendado, mesmo sem confirmação.
     *
     * @param int $user_id ID do usuário logado.
     * @param string $user_table Tabela de origem da sessão (associados|usuarios).
     * @return array
     */
    public function getPresencasUsuarioAgendados($user_id, $user_table = 'usuarios') {
        try {
            $user_id = (int) $user_id;
            $user_table = strtolower((string) $user_table);

            $associado_id = null;
            $usuario_id = null;

            if ($user_table === 'associados') {
                $associado_id = $user_id;
            } else {
                $usuario_id = $user_id;

                // Se o usuário comum estiver vinculado a um associado, usa ambos os vínculos.
                $stmt_usuario = $this->db->prepare("SELECT associado_id FROM usuarios WHERE id = ?");
                $stmt_usuario->execute([$usuario_id]);
                $usuario_data = $stmt_usuario->fetch(PDO::FETCH_ASSOC);
                if (!empty($usuario_data['associado_id'])) {
                    $associado_id = (int) $usuario_data['associado_id'];
                }
            }

            // Compatibilidade de schema: justificativa pode não existir.
            $tem_coluna_justificativa = false;
            $stmt_col = $this->db->query("SHOW COLUMNS FROM evento_presenca LIKE 'justificativa'");
            if ($stmt_col && $stmt_col->fetch(PDO::FETCH_ASSOC)) {
                $tem_coluna_justificativa = true;
            }
            $select_justificativa = $tem_coluna_justificativa
                ? "COALESCE(p.justificativa, '') AS justificativa"
                : "'' AS justificativa";

            $sql = "SELECT
                        e.id AS evento_id,
                        e.nome AS evento_nome,
                        e.data_evento,
                        e.status AS evento_status,
                        e.confirmados,
                        p.status_presenca,
                        p.data_registro,
                        {$select_justificativa}
                    FROM eventos e
                    LEFT JOIN evento_presenca p
                        ON p.evento_id = e.id
                        AND (
                            (:associado_id IS NOT NULL AND p.associado_id = :associado_id)
                            OR
                            (:usuario_id IS NOT NULL AND p.usuario_id = :usuario_id)
                        )
                    WHERE e.status = 'Agendado'
                    ORDER BY e.data_evento DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':associado_id', $associado_id, $associado_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->bindValue(':usuario_id', $usuario_id, $usuario_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $resultado = [];
            foreach ($rows as $row) {
                $status = $row['status_presenca'] ?? '';
                $justificativa = (string) ($row['justificativa'] ?? '');

                // Fallback: se não houver linha em evento_presenca, verifica JSON de confirmados.
                if ($status === '' || $status === null) {
                    $status = 'Não Confirmado';
                    $confirmados = json_decode($row['confirmados'] ?? '[]', true);
                    if (is_array($confirmados)) {
                        foreach ($confirmados as $conf) {
                            $conf_id = isset($conf['id']) ? (int) $conf['id'] : 0;
                            $conf_table = strtolower((string) ($conf['table'] ?? ''));
                            $match_associado = ($associado_id !== null && $conf_table === 'associados' && $conf_id === $associado_id);
                            $match_usuario = ($usuario_id !== null && $conf_table === 'usuarios' && $conf_id === $usuario_id);
                            if ($match_associado || $match_usuario) {
                                $status = 'Confirmado';
                                if ($justificativa === '' && !empty($conf['justificativa'])) {
                                    $justificativa = (string) $conf['justificativa'];
                                }
                                break;
                            }
                        }
                    }
                }

                $resultado[] = [
                    'evento_id' => (int) $row['evento_id'],
                    'evento_nome' => $row['evento_nome'],
                    'status_presenca' => $status,
                    'justificativa' => $justificativa,
                    'data_evento' => $row['data_evento'],
                ];
            }

            return $resultado;
        } catch (PDOException $e) {
            error_log("Erro ao buscar presenças do usuário em eventos agendados: " . $e->getMessage());
            return [];
        }
    }
}
?>