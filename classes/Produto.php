<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Produto {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca todos os produtos do banco de dados.
     * @return array Retorna um array de produtos.
     */
    public function getAll() {
        try {
            $stmt = $this->db->prepare("SELECT id, uuid, nome, descricao, tipo, unidade_medida, preco_unitario, quantidade_produzida, data_colheita, lavoura_id FROM produtos ORDER BY nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar produtos: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca os produtos vinculados a um associado específico.
     * @param int $associado_id ID do associado
     * @return array
     */
    public function getByAssociadoId($associado_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT pr.id, pr.uuid, pr.nome, pr.descricao, pr.tipo, pr.unidade_medida, pr.preco_unitario, pr.quantidade_produzida, pr.data_colheita 
                FROM produtos pr
                INNER JOIN lavouras l ON pr.lavoura_id = l.id
                INNER JOIN propriedades p ON l.propriedade_id = p.id
                WHERE p.associado_id = :associado_id
                ORDER BY pr.nome ASC
            ");
            $stmt->bindParam(':associado_id', $associado_id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar produtos por associado: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca um produto pelo UUID.
     * @param string $uuid O UUID do produto.
     * @return array|false Retorna os dados do produto ou false se não encontrado.
     */
    public function getByUuid($uuid) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM produtos WHERE uuid = :uuid");
            $stmt->bindParam(':uuid', $uuid);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar produto por UUID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Cria um novo produto.
     * @param array $dados Os dados do produto.
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

            // Sanitização e tipagem corretas
            $quantidade = isset($dados_sanitizados['quantidade_produzida']) && $dados_sanitizados['quantidade_produzida'] !== ''
                ? Sanitizer::sanitizeFloat($dados_sanitizados['quantidade_produzida'])
                : null;

            $preco = isset($dados_sanitizados['preco_unitario']) && $dados_sanitizados['preco_unitario'] !== ''
                ? Sanitizer::sanitizeFloat($dados_sanitizados['preco_unitario'])
                : null;

            $lavouraId = isset($dados_sanitizados['lavoura_id']) && $dados_sanitizados['lavoura_id'] !== ''
                ? Sanitizer::sanitizeInt($dados_sanitizados['lavoura_id'])
                : null;

            $sql = "INSERT INTO produtos (uuid, nome, descricao, tipo, unidade_medida, preco_unitario, quantidade_produzida, data_colheita, lavoura_id, observacoes, criado_em)
                    VALUES (:uuid, :nome, :descricao, :tipo, :unidade_medida, :preco_unitario, :quantidade_produzida, :data_colheita, :lavoura_id, :observacoes, NOW())";
            $stmt = $this->db->prepare($sql);

            // Se o valor vier da máscara (apenas dígitos), converter de centavos para decimal
            $preco = $dados_sanitizados['preco_unitario'];
            if (is_numeric($preco) && !strpos($preco, '.') && !strpos($preco, ',')) {
                $preco = (float)$preco / 100;
            }

            $stmt->bindParam(':uuid', $dados_sanitizados['uuid']);
            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':tipo', $dados_sanitizados['tipo']);
            $stmt->bindParam(':unidade_medida', $dados_sanitizados['unidade_medida']);

            // Usa bindValue para permitir null
            $stmt->bindValue(':preco_unitario', $preco);
            $stmt->bindValue(':quantidade_produzida', $quantidade);
            $stmt->bindParam(':data_colheita', $dados_sanitizados['data_colheita']);

            if ($lavouraId === null) {
                $stmt->bindValue(':lavoura_id', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':lavoura_id', $lavouraId, PDO::PARAM_INT);
            }

            $stmt->bindParam(':observacoes', $dados_sanitizados['observacoes']);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao criar produto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca um produto pelo ID.
     * @param int $id O ID do produto.
     * @return array|false Retorna os dados do produto ou false se não encontrado.
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM produtos WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar produto por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza um produto existente.
     * @param array $dados Os dados atualizados do produto.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function update($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);
            
            $sql = "UPDATE produtos SET 
                        nome = :nome, 
                        descricao = :descricao,
                        tipo = :tipo, 
                        unidade_medida = :unidade_medida, 
                        preco_unitario = :preco_unitario,
                        quantidade_produzida = :quantidade_produzida, 
                        data_colheita = :data_colheita, 
                        lavoura_id = :lavoura_id, 
                        observacoes = :observacoes 
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);

            // Se o valor vier da máscara (apenas dígitos), converter de centavos para decimal
            $preco = $dados_sanitizados['preco_unitario'];
            if (is_numeric($preco) && !strpos($preco, '.') && !strpos($preco, ',')) {
                $preco = (float)$preco / 100;
            }

            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':tipo', $dados_sanitizados['tipo']);
            $stmt->bindParam(':unidade_medida', $dados_sanitizados['unidade_medida']);
            $stmt->bindValue(':preco_unitario', $preco);
            $stmt->bindParam(':quantidade_produzida', $dados_sanitizados['quantidade_produzida']);
            $stmt->bindParam(':data_colheita', $dados_sanitizados['data_colheita']);
            $stmt->bindParam(':lavoura_id', $dados_sanitizados['lavoura_id']);
            $stmt->bindParam(':observacoes', $dados_sanitizados['observacoes']);
            $stmt->bindParam(':id', $dados_sanitizados['id'], PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao atualizar produto: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui um produto.
     * @param int $id O ID do produto.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM produtos WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erro ao excluir produto: " . $e->getMessage());
            return false;
        }
    }
}
?>