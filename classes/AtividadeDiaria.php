<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class AtividadeDiaria {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca todas as atividades diárias do banco de dados.
     * @return array Retorna um array de atividades diárias.
     */
    public function getAll() {
        try {
            $sql = "SELECT ad.id, ad.nome, ad.data_atividade, ad.tipo_atividade, ad.status, l.nome as lavoura_nome, p.nome as propriedade_nome
                    FROM atividades_diarias ad
                    LEFT JOIN lavouras l ON ad.lavoura_id = l.id
                    LEFT JOIN propriedades p ON ad.propriedade_id = p.id
                    ORDER BY ad.data_atividade DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar atividades diárias: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cria uma nova atividade diária.
     * @param array $dados Os dados da atividade diária.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     * Busca uma atividade pelo UUID.
     * @param string $uuid O UUID da atividade.
     * @return array|false
     */
    public function getByUuid($uuid) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM atividades_diarias WHERE uuid = :uuid");
            $stmt->bindParam(':uuid', $uuid);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar atividade por UUID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca uma atividade diária pelo ID.
     * @param int $id O ID da atividade diária.
     * @return array|null Retorna os dados da atividade ou null se não encontrada.
     */
    public function getById($id) {
        try {
            $sql = "SELECT ad.*, 
                           p.nome as propriedade_nome, 
                           l.nome as lavoura_nome,
                           a.nome as associado_nome,
                           ca.nome as categoria_nome,
                           ca.cor as categoria_cor
                    FROM atividades_diarias ad
                    LEFT JOIN propriedades p ON ad.propriedade_id = p.id
                    LEFT JOIN lavouras l ON ad.lavoura_id = l.id
                    LEFT JOIN associados a ON ad.associado_id = a.id
                    LEFT JOIN categorias_atividades ca ON ad.categoria_id = ca.id
                    WHERE ad.id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar atividade diária por ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cria uma nova atividade diária.
     * @param array $dados Os dados da atividade diária.
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

            $sql = "INSERT INTO atividades_diarias (uuid, nome, associado_id, propriedade_id, lavoura_id, categoria_id, data_atividade, hora_inicio, hora_fim, descricao, status, tipo_atividade, insumo_utilisado, observações, criado_em) 
                    VALUES (:uuid, :nome, :associado_id, :propriedade_id, :lavoura_id, :categoria_id, :data_atividade, :hora_inicio, :hora_fim, :descricao, :status, :tipo_atividade, :insumo_utilisado, :observacoes, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':uuid', $dados_sanitizados['uuid']);
            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':associado_id', $dados_sanitizados['associado_id']);
            $stmt->bindParam(':propriedade_id', $dados_sanitizados['propriedade_id']);
            $stmt->bindParam(':lavoura_id', $dados_sanitizados['lavoura_id']);
            $stmt->bindParam(':categoria_id', $dados_sanitizados['categoria_id']);
            $stmt->bindParam(':data_atividade', $dados_sanitizados['data_atividade']);
            $stmt->bindParam(':hora_inicio', $dados_sanitizados['hora_inicio']);
            $stmt->bindParam(':hora_fim', $dados_sanitizados['hora_fim']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':status', $dados_sanitizados['status']);
            $stmt->bindParam(':tipo_atividade', $dados_sanitizados['tipo_atividade']);
            $stmt->bindParam(':insumo_utilisado', $dados_sanitizados['insumo_utilisado']);
            $stmt->bindParam(':observacoes', $dados_sanitizados['observacoes']);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar atividade diária: " . $e->getMessage());
            return false;
        }
    }

    // Métodos update e delete seriam adicionados aqui futuramente
}
?>