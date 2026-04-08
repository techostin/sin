<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sanitizer.php';

class Associado
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Cria um novo associado no banco de dados.
     * @param array $dados Os dados do associado vindos do formulário.
     * @return bool|string Retorna true em caso de sucesso, ou uma string com a mensagem de erro em caso de falha.
     */
    public function create($dados)
    {
        try {
            // Sanitiza os dados de entrada
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);

            // Verifica se o e-mail já existe
            $stmt_check = $this->db->prepare("SELECT COUNT(*) FROM associados WHERE email = :email");
            $stmt_check->bindParam(':email', $dados_sanitizados['email']);
            $stmt_check->execute();
            if ($stmt_check->fetchColumn() > 0) {
                // E-mail já cadastrado
                return "Erro: O e-mail informado já está cadastrado no sistema.";
            }

            // Criptografa a senha
            $senha_hashed = password_hash($dados_sanitizados['senha'], PASSWORD_DEFAULT);

            $sql = "INSERT INTO associados (nome, sobrenome, email, senha, cpf, rg, data_nascimento, endereco, numero, complemento, bairro, cidade, estado, cep, telefone, nivel, tipo, status, data_cadastro) VALUES (:nome, :sobrenome, :email, :senha, :cpf, :rg, :data_nascimento, :endereco, :numero, :complemento, :bairro, :cidade, :estado, :cep, :telefone, :nivel, :tipo, :status, NOW())";

            $stmt = $this->db->prepare($sql);

            // Define o nível e tipo padrão para um novo cadastro
            $nivel_padrao = NIVEL_USUARIO; // Nível 3 = Usuário
            $tipo_padrao = 'Usuario';
            $status_padrao = 'pendente';

            // Bind dos parâmetros
            $stmt->bindParam(':nome', $dados_sanitizados['nome']);
            $stmt->bindParam(':sobrenome', $dados_sanitizados['sobrenome']);
            $stmt->bindParam(':email', $dados_sanitizados['email']);
            $stmt->bindParam(':senha', $senha_hashed);
            $stmt->bindParam(':cpf', $dados_sanitizados['cpf']);
            $stmt->bindParam(':rg', $dados_sanitizados['rg']);
            $stmt->bindParam(':data_nascimento', $dados_sanitizados['data_nascimento']);
            $stmt->bindParam(':endereco', $dados_sanitizados['endereco']);
            $stmt->bindParam(':numero', $dados_sanitizados['numero']);
            $stmt->bindParam(':complemento', $dados_sanitizados['complemento']);
            $stmt->bindParam(':bairro', $dados_sanitizados['bairro']);
            $stmt->bindParam(':cidade', $dados_sanitizados['cidade']);
            $stmt->bindParam(':estado', $dados_sanitizados['estado']);
            $stmt->bindParam(':cep', $dados_sanitizados['cep']);
            $stmt->bindParam(':telefone', $dados_sanitizados['telefone']);
            $stmt->bindParam(':nivel', $nivel_padrao);
            $stmt->bindParam(':tipo', $tipo_padrao);
            $stmt->bindParam(':status', $status_padrao);

            $stmt->execute();
            return $this->db->lastInsertId();

        } catch (PDOException $e) {
            error_log("Erro ao criar associado: " . $e->getMessage());
            return "Erro de banco de dados ao cadastrar: " . $e->getMessage();
        }
    }

    /**
     * Busca todos os associados do banco de dados.
     * @return array Retorna um array de associados.
     */
    public function getAll()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    a.id, 
                    a.nome, 
                    a.sobrenome, 
                    a.email, 
                    a.telefone, 
                    a.cidade AS cidade_id, 
                    c.nome AS cidade_nome,
                    a.status, 
                    a.tipo, 
                    a.foto_url 
                FROM 
                    associados a
                LEFT JOIN 
                    cidades c ON a.cidade = c.id 
                ORDER BY 
                    a.nome ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar associados: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Busca um associado pelo ID.
     * @param int $id O ID do associado a ser buscado.
     * @return array|false Retorna os dados do associado ou false se não encontrado.
     */
    public function getById($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM associados WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar associado por ID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca todos os estados do banco de dados.
     * @return array Retorna um array de estados.
     */
    public function getEstados()
    {
        try {
            $stmt = $this->db->prepare("SELECT id, nome, uf FROM estados ORDER BY nome ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar estados: " . $e->getMessage());
            return [];
        }
    }
    /**
     * Atualiza os dados de um associado existente.
     * @param int $id O ID do associado a ser atualizado.
     * @param array $dados Os novos dados do associado.
     * @return bool|string Retorna true em caso de sucesso, ou uma string com a mensagem de erro em caso de falha.
     */
    public function update($id, $dados)
    {
        try {
            // Sanitiza os dados de entrada
            $dados_sanitizados = Sanitizer::sanitizeArray($dados);

            // Verifica se o associado existe
            $atual = $this->getById($id);
            if (!$atual) {
                return "Erro: Associado não encontrado.";
            }

            // Verifica unicidade de e-mail (se alterado)
            if (isset($dados_sanitizados['email']) && $dados_sanitizados['email'] !== $atual['email']) {
                $stmt_check = $this->db->prepare("SELECT COUNT(*) FROM associados WHERE email = :email AND id != :id");
                $stmt_check->bindParam(':email', $dados_sanitizados['email']);
                $stmt_check->bindParam(':id', $id);
                $stmt_check->execute();
                if ($stmt_check->fetchColumn() > 0) {
                    return "Erro: O e-mail informado já está em uso por outro associado.";
                }
            }

            // Verifica unicidade de CPF (se alterado)
            if (isset($dados_sanitizados['cpf']) && $dados_sanitizados['cpf'] !== $atual['cpf']) {
                $stmt_check_cpf = $this->db->prepare("SELECT COUNT(*) FROM associados WHERE cpf = :cpf AND id != :id");
                $stmt_check_cpf->bindParam(':cpf', $dados_sanitizados['cpf']);
                $stmt_check_cpf->bindParam(':id', $id);
                $stmt_check_cpf->execute();
                if ($stmt_check_cpf->fetchColumn() > 0) {
                    return "Erro: O CPF informado já está em uso por outro associado.";
                }
            }

            // Construção dinâmica da query de update
            $campos = [];
            $params = [':id' => $id];

            $colunas_permitidas = [
                'nome',
                'sobrenome',
                'email',
                'cpf',
                'rg',
                'data_nascimento',
                'endereco',
                'numero',
                'complemento',
                'bairro',
                'cidade',
                'estado',
                'cep',
                'telefone',
                'nivel',
                'tipo',
                'status',
                'foto_url'
            ];

            foreach ($colunas_permitidas as $coluna) {
                if (isset($dados_sanitizados[$coluna])) {
                    $campos[] = "$coluna = :$coluna";
                    $params[":$coluna"] = $dados_sanitizados[$coluna];
                }
            }

            // Tratamento especial para senha (só atualiza se não estiver vazia)
            if (!empty($dados_sanitizados['senha'])) {
                $campos[] = "senha = :senha";
                $params[':senha'] = password_hash($dados_sanitizados['senha'], PASSWORD_DEFAULT);
            }

            if (empty($campos)) {
                return true; // Nada a atualizar
            }

            $campos[] = "ultima_atualizacao = NOW()";

            $sql = "UPDATE associados SET " . implode(', ', $campos) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);

            if ($stmt->execute($params)) {
                return true;
            } else {
                return "Erro ao atualizar dados.";
            }

        } catch (PDOException $e) {
            error_log("Erro ao atualizar associado: " . $e->getMessage());
            return "Erro de banco de dados ao atualizar: " . $e->getMessage();
        }
    }
}
?>