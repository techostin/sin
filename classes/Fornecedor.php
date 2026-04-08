<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Fornecedor {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Cria um novo fornecedor no banco de dados.
     * @param array $dados Os dados do fornecedor vindos do formulário.
     * @return bool|string Retorna true em caso de sucesso, ou uma string com a mensagem de erro em caso de falha.
     */
    public function create($dados) {
        try {
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);

            $sql = "INSERT INTO fornecedores (nome, razao_social, representante, cnpj, email, telefone, produto_fornecido, endereco, bairro, cidade, estado, status, data_cadastro) 
                    VALUES (:nome, :razao_social, :representante, :cnpj, :email, :telefone, :produto_fornecido, :endereco, :bairro, :cidade, :estado, :status, NOW())";
            
            $stmt = $this->db->prepare($sql);

            $status_padrao = 'ativo';

            // Bind dos parâmetros
            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':razao_social', $dados_sanitizados['razao_social']);
            $stmt->bindParam(':representante', $dados_sanitizados['representante']);
            $stmt->bindParam(':cnpj', $dados_sanitizados['cnpj']);
            $stmt->bindParam(':email', $dados_sanitizados['email']);
            $stmt->bindParam(':telefone', $dados_sanitizados['telefone']);
            $stmt->bindParam(':produto_fornecido', $dados_sanitizados['produto_fornecido']);
            $stmt->bindParam(':endereco', $dados_sanitizados['endereco']);
            $stmt->bindParam(':bairro', $dados_sanitizados['bairro']);
            $stmt->bindParam(':cidade', $dados_sanitizados['cidade']);
            $stmt->bindParam(':estado', $dados_sanitizados['estado']);
            $stmt->bindParam(':status', $status_padrao);

            $stmt->execute();
            return true;

        } catch (PDOException $e) {
            // Verifica se o erro é de chave duplicada (UNIQUE constraint)
            if ($e->getCode() == 23000) {
                if (strpos(strtolower($e->getMessage()), 'cnpj') !== false) {
                    return "Erro: O CNPJ informado já está cadastrado.";
                }
                if (strpos(strtolower($e->getMessage()), 'email') !== false) {
                    return "Erro: O e-mail informado já está em uso.";
                }
            }
            error_log("Erro ao criar fornecedor: " . $e->getMessage());
            return "Erro de banco de dados ao cadastrar: " . $e->getMessage();
        }
    }

    /**
     * Busca todos os fornecedores do banco de dados com nomes de cidade e estado.
     * @return array Retorna um array de fornecedores.
     */
    public function getAll() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    f.id, f.nome, f.representante, 
                    c.nome as cidade_nome, 
                    e.uf as estado_sigla 
                FROM fornecedores f 
                LEFT JOIN cidades c ON f.cidade = c.id 
                LEFT JOIN estados e ON f.estado = e.id 
                ORDER BY f.nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar fornecedores: " . $e->getMessage());
            return [];
        }
    }
}
?>