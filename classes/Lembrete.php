<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class Lembrete {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca eventos próximos (próximos 7 dias)
     * @param int $dias_antecedencia Quantos dias antes mostrar o lembrete (padrão: 7)
     * @return array Retorna array de eventos próximos
     */
    public function getEventosProximos($dias_antecedencia = 7) {
        try {
            $data_limite = date('Y-m-d', strtotime("+{$dias_antecedencia} days"));
            $data_atual = date('Y-m-d');
            
            // Log para depuração
            error_log("Buscando eventos próximos - Data atual: {$data_atual}, Data limite: {$data_limite}");
            
            $sql = "SELECT 
                        id, 
                        nome, 
                        descricao, 
                        data_evento, 
                        local, 
                        status 
                    FROM eventos 
                    WHERE DATE(data_evento) >= ? 
                    AND DATE(data_evento) <= ? 
                    AND status = 'Agendado'
                    ORDER BY data_evento ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data_atual, $data_limite]);
            $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log para depuração
            error_log("Eventos encontrados: " . count($eventos));
            
            return $eventos;
        } catch (PDOException $e) {
            error_log("Erro ao buscar eventos próximos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca eventos de hoje
     * @return array Retorna array de eventos de hoje
     */
    public function getEventosHoje() {
        try {
            $data_atual = date('Y-m-d');
            error_log("Buscando eventos para hoje: {$data_atual}");
            
            $sql = "SELECT 
                        id, 
                        nome, 
                        descricao, 
                        data_evento, 
                        local, 
                        status 
                    FROM eventos 
                    WHERE DATE(data_evento) = ? 
                    AND status = 'Agendado'
                    ORDER BY data_evento ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data_atual]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar eventos de hoje: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca eventos atrasados (que deveriam ter acontecido mas não foram marcados como realizados)
     * @return array Retorna array de eventos atrasados
     */
    public function getEventosAtrasados() {
        try {
            $data_atual = date('Y-m-d');
            
            $sql = "SELECT 
                        id, 
                        nome, 
                        descricao, 
                        data_evento, 
                        local, 
                        status 
                    FROM eventos 
                    WHERE DATE(data_evento) < ? 
                    AND status = 'Agendado'
                    ORDER BY data_evento DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$data_atual]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar eventos atrasados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica se há eventos próximos e retorna mensagem formatada
     * @param int $dias_antecedencia Quantos dias antes mostrar o lembrete
     * @return array Retorna array com informações dos lembretes
     */
    public function verificarLembretes($dias_antecedencia = 7) {
        $lembretes = [];
        
        // Verificar eventos de hoje
        $eventos_hoje = $this->getEventosHoje();
        if (!empty($eventos_hoje)) {
            $lembretes['hoje'] = [
                'tipo' => 'hoje',
                'titulo' => 'Eventos de Hoje',
                'classe' => 'alert-warning',
                'icone' => 'bi-calendar-event-fill',
                'eventos' => $eventos_hoje,
                'mensagem' => 'Você tem ' . count($eventos_hoje) . ' evento(s) agendado(s) para hoje!'
            ];
        }
        
        // Verificar eventos próximos
        $eventos_proximos = $this->getEventosProximos($dias_antecedencia);
        if (!empty($eventos_proximos)) {
            $lembretes['proximos'] = [
                'tipo' => 'proximos',
                'titulo' => 'Próximos Eventos',
                'classe' => 'alert-info',
                'icone' => 'bi-calendar-week-fill',
                'eventos' => $eventos_proximos,
                'mensagem' => 'Você tem ' . count($eventos_proximos) . ' evento(s) nos próximos ' . $dias_antecedencia . ' dias.'
            ];
        }
        
        // Verificar eventos atrasados
        $eventos_atrasados = $this->getEventosAtrasados();
        if (!empty($eventos_atrasados)) {
            $lembretes['atrasados'] = [
                'tipo' => 'atrasados',
                'titulo' => 'Eventos Atrasados',
                'classe' => 'alert-danger',
                'icone' => 'bi-exclamation-triangle-fill',
                'eventos' => $eventos_atrasados,
                'mensagem' => 'Você tem ' . count($eventos_atrasados) . ' evento(s) que deveriam ter sido realizados.'
            ];
        }
        
        return $lembretes;
    }

    /**
     * Formata a data do evento para exibição
     * @param string $data_evento Data do evento
     * @return string Data formatada
     */
    public function formatarDataEvento($data_evento) {
        $data = new DateTime($data_evento);
        $hoje = new DateTime();
        $amanha = new DateTime('tomorrow');
        
        if ($data->format('Y-m-d') === $hoje->format('Y-m-d')) {
            return 'Hoje às ' . $data->format('H:i');
        } elseif ($data->format('Y-m-d') === $amanha->format('Y-m-d')) {
            return 'Amanhã às ' . $data->format('H:i');
        } else {
            $diferenca = $hoje->diff($data);
            if ($diferenca->days == 1) {
                return 'Em 1 dia às ' . $data->format('H:i');
            } else {
                return 'Em ' . $diferenca->days . ' dias (' . $data->format('d/m/Y H:i') . ')';
            }
        }
    }

    /**
     * Calcula quantos dias faltam para o evento
     * @param string $data_evento Data do evento
     * @return int Número de dias
     */
    public function diasParaEvento($data_evento) {
        $data = new DateTime($data_evento);
        $hoje = new DateTime();
        $diferenca = $hoje->diff($data);
        return $diferenca->invert ? -$diferenca->days : $diferenca->days;
    }
}
?> 