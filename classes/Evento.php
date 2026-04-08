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
                        a.nome as associado_nome,
                        a.sobrenome as associado_sobrenome,
                        p.status_presenca,
                        e.data_evento
                    FROM evento_presenca p
                    JOIN eventos e ON p.evento_id = e.id
                    JOIN associados a ON p.associado_id = a.id
                    ORDER BY e.data_evento DESC, a.nome ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar presença: " . $e->getMessage());
            return [];
        }
    }
}
?>