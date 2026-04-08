<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Associacao {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Cria uma nova associação no banco de dados.
     * @param array $dados Os dados da associação vindos do formulário.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function create($dados) {
        try {
            // Sanitiza os dados de entrada
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);

            // Verifica se o nome já existe
            $stmt_check = $this->db->prepare("SELECT COUNT(*) FROM associacoes WHERE nome = :nome");
            $stmt_check->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) {
                // Nome já cadastrado
                return false;
            }

            $sql = "INSERT INTO associacoes (nome, sigla, descricao, objetivo, data_fundacao, endereco, cidade, estado, cep, telefone, email, site, responsavel, cargo_responsavel, status, data_cadastro) VALUES (:nome, :sigla, :descricao, :objetivo, :data_fundacao, :endereco, :cidade, :estado, :cep, :telefone, :email, :site, :responsavel, :cargo_responsavel, :status, NOW())";
            
            $stmt = $this->db->prepare($sql);

            // Define o status padrão
            $status_padrao = 'ativo';

            // Bind dos parâmetros
            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':sigla', $dados_sanitizados['sigla']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':objetivo', $dados_sanitizados['objetivo']);
            $stmt->bindParam(':data_fundacao', $dados_sanitizados['data_fundacao']);
            $stmt->bindParam(':endereco', $dados_sanitizados['endereco']);
            $stmt->bindParam(':cidade', $dados_sanitizados['cidade']);
            $stmt->bindParam(':estado', $dados_sanitizados['estado']);
            $stmt->bindParam(':cep', $dados_sanitizados['cep']);
            $stmt->bindParam(':telefone', $dados_sanitizados['telefone']);
            $stmt->bindParam(':email', $dados_sanitizados['email']);
            $stmt->bindParam(':site', $dados_sanitizados['site']);
            $stmt->bindParam(':responsavel', $dados_sanitizados['responsavel']);
            $stmt->bindParam(':cargo_responsavel', $dados_sanitizados['cargo_responsavel']);
            $stmt->bindParam(':status', $status_padrao);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Erro ao criar associação: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca todas as associações do banco de dados com nomes das cidades e estados.
     * @return array Retorna um array de associações.
     */
    public function getAll() {
        try {
            $stmt = $this->db->prepare("
                SELECT a.id, a.nome, a.sigla, a.status,
                       c.nome as nome_cidade, e.nome as nome_estado, e.uf
                FROM associacoes a
                LEFT JOIN cidades c ON a.cidade = c.id
                LEFT JOIN estados e ON a.estado = e.id
                ORDER BY a.nome ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar associações: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca os detalhes completos de uma associação por ID.
     * @param int $id O ID da associação.
     * @return array|false Retorna os dados completos da associação ou false se não encontrada.
     */
    public function getDetalhesCompletos($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT a.*, c.nome as nome_cidade, e.nome as nome_estado, e.uf
                FROM associacoes a
                LEFT JOIN cidades c ON a.cidade = c.id
                LEFT JOIN estados e ON a.estado = e.id
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar detalhes da associação: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca uma associação específica por ID.
     * @param int $id O ID da associação.
     * @return array|false Retorna os dados da associação ou false se não encontrada.
     */
    public function getById($id) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM associacoes WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar associação: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza uma associação existente.
     * @param int $id O ID da associação.
     * @param array $dados Os novos dados da associação.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function update($id, $dados) {
        try {
            // Sanitiza os dados de entrada
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);

            $sql = "UPDATE associacoes SET 
                    nome = :nome, 
                    sigla = :sigla, 
                    descricao = :descricao, 
                    objetivo = :objetivo, 
                    data_fundacao = :data_fundacao, 
                    endereco = :endereco, 
                    cidade = :cidade, 
                    estado = :estado, 
                    cep = :cep, 
                    telefone = :telefone, 
                    email = :email, 
                    site = :site, 
                    responsavel = :responsavel, 
                    cargo_responsavel = :cargo_responsavel, 
                    status = :status 
                    WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);

            // Bind dos parâmetros
            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':sigla', $dados_sanitizados['sigla']);
            $stmt->bindParam(':descricao', $dados_sanitizados['descricao']);
            $stmt->bindParam(':objetivo', $dados_sanitizados['objetivo']);
            $stmt->bindParam(':data_fundacao', $dados_sanitizados['data_fundacao']);
            $stmt->bindParam(':endereco', $dados_sanitizados['endereco']);
            $stmt->bindParam(':cidade', $dados_sanitizados['cidade']);
            $stmt->bindParam(':estado', $dados_sanitizados['estado']);
            $stmt->bindParam(':cep', $dados_sanitizados['cep']);
            $stmt->bindParam(':telefone', $dados_sanitizados['telefone']);
            $stmt->bindParam(':email', $dados_sanitizados['email']);
            $stmt->bindParam(':site', $dados_sanitizados['site']);
            $stmt->bindParam(':responsavel', $dados_sanitizados['responsavel']);
            $stmt->bindParam(':cargo_responsavel', $dados_sanitizados['cargo_responsavel']);
            $stmt->bindParam(':status', $dados_sanitizados['status']);
            $stmt->bindParam(':id', $id);

            return $stmt->execute();

        } catch (PDOException $e) {
            error_log("Erro ao atualizar associação: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Exclui uma associação.
     * @param int $id O ID da associação.
     * @return bool Retorna true em caso de sucesso, false em caso de falha.
     */
    public function delete($id) {
        try {
            // Verificar se há associados vinculados
            $stmt_check = $this->db->prepare("SELECT COUNT(*) FROM associados WHERE vinculo = ?");
            $stmt_check->execute([$id]);
            if ($stmt_check->fetchColumn() > 0) {
                return false; // Não pode excluir se há associados vinculados
            }

            $stmt = $this->db->prepare("DELETE FROM associacoes WHERE id = ?");
            return $stmt->execute([$id]);

        } catch (PDOException $e) {
            error_log("Erro ao excluir associação: " . $e->getMessage());
            return false;
        }
    }
}
?> 