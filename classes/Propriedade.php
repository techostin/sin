<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Propriedade {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca todas as propriedades do banco de dados.
     * @return array Retorna um array de propriedades.
     */
    public function getAll() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    p.id, 
                    p.propriedade, 
                    p.endereco, 
                    c.nome AS cidade_nome,
                    e.uf AS estado_sigla,
                    p.area_total, 
                    p.imagem_principal, 
                    p.imagem_secundaria 
                FROM propriedades p
                LEFT JOIN cidades c ON p.cidade = c.id
                LEFT JOIN estados e ON p.estado = e.uf
                ORDER BY p.propriedade ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar propriedades: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca todas as propriedades de um associado específico.
     * @param int $associado_id ID do associado
     * @return array Retorna um array de propriedades.
     */
    public function getByAssociadoId($associado_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    p.id, 
                    p.propriedade, 
                    p.endereco, 
                    c.nome AS cidade_nome,
                    e.uf AS estado_sigla,
                    p.area_total, 
                    p.imagem_principal, 
                    p.imagem_secundaria 
                FROM propriedades p
                LEFT JOIN cidades c ON p.cidade = c.id
                LEFT JOIN estados e ON p.estado = e.uf
                WHERE p.associado_id = :associado_id
                ORDER BY p.propriedade ASC
            ");
            $stmt->bindParam(':associado_id', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar propriedades por associado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca uma propriedade pelo ID.
     * @param int $id O ID da propriedade.
     * @return array|false Retorna os dados da propriedade ou false se não encontrada.
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, c.nome AS cidade_nome, e.uf AS estado_sigla
                FROM propriedades p
                LEFT JOIN cidades c ON p.cidade = c.id
                LEFT JOIN estados e ON p.estado = e.uf
                WHERE p.id = :id
            ");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar propriedade por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cria uma nova propriedade.
     * @param array $dados Os dados da propriedade.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function create($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);

            $sql = "INSERT INTO propriedades (propriedade, endereco, cidade, estado, cep, bairro, associado_id, area_total, latitude, longitude, observacoes, imagem_principal, imagem_secundaria, created_at) VALUES (:propriedade, :endereco, :cidade, :estado, :cep, :bairro, :associado_id, :area_total, :latitude, :longitude, :observacoes, :imagem_principal, :imagem_secundaria, NOW())";
            $stmt = $this->db->prepare($sql);

            $stmt->bindParam(':propriedade', $dados_sanitizados['propriedade']);
            $stmt->bindParam(':endereco', $dados_sanitizados['endereco']);
            $stmt->bindParam(':cidade', $dados_sanitizados['cidade']);
            $stmt->bindParam(':estado', $dados_sanitizados['estado']);
            $stmt->bindParam(':cep', $dados_sanitizados['cep']);
            $stmt->bindParam(':bairro', $dados_sanitizados['bairro']);
            $stmt->bindParam(':associado_id', $dados_sanitizados['associado_id']); 
            $stmt->bindParam(':area_total', $dados_sanitizados['area_total']);
            $stmt->bindParam(':latitude', $dados_sanitizados['latitude']);
            $stmt->bindParam(':longitude', $dados_sanitizados['longitude']);
            $stmt->bindParam(':observacoes', $dados_sanitizados['observacoes']);
            $stmt->bindParam(':imagem_principal', $dados_sanitizados['imagem_principal']);
            $stmt->bindParam(':imagem_secundaria', $dados_sanitizados['imagem_secundaria']);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar propriedade: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza uma propriedade existente.
     * @param array $dados Os dados atualizados da propriedade.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function update($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);
            $zona_amortecimento = $dados_sanitizados['zona_amortecimento'] ?? null;
            
            $sql = "UPDATE propriedades SET propriedade = :propriedade, endereco = :endereco, cidade = :cidade, estado = :estado, cep = :cep, bairro = :bairro, area_total = :area_total, latitude = :latitude, longitude = :longitude, observacoes = :observacoes, zona_amortecimento = :zona_amortecimento, imagem_principal = :imagem_principal, imagem_secundaria = :imagem_secundaria WHERE id = :id";
            $stmt = $this->db->prepare($sql);

            $stmt->bindParam(':propriedade', $dados_sanitizados['propriedade']);
            $stmt->bindParam(':endereco', $dados_sanitizados['endereco']);
            $stmt->bindParam(':cidade', $dados_sanitizados['cidade']);
            $stmt->bindParam(':estado', $dados_sanitizados['estado']);
            $stmt->bindParam(':cep', $dados_sanitizados['cep']);
            $stmt->bindParam(':bairro', $dados_sanitizados['bairro']);
            $stmt->bindParam(':area_total', $dados_sanitizados['area_total']);
            $stmt->bindParam(':latitude', $dados_sanitizados['latitude']);
            $stmt->bindParam(':longitude', $dados_sanitizados['longitude']);
            $stmt->bindParam(':observacoes', $dados_sanitizados['observacoes']);
            $stmt->bindParam(':zona_amortecimento', $zona_amortecimento);
            $stmt->bindParam(':imagem_principal', $dados_sanitizados['imagem_principal']);
            $stmt->bindParam(':imagem_secundaria', $dados_sanitizados['imagem_secundaria']);
            $stmt->bindParam(':id', $dados_sanitizados['id'], PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar propriedade: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui uma propriedade.
     * @param int $id O ID da propriedade.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM propriedades WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao excluir propriedade: " . $e->getMessage());
            return false;
        }
    }
}
?>