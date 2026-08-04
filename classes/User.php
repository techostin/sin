<?php
require_once __DIR__ . '/Database.php';

class User {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function login($email, $senha) {
        try {
            // Primeiro, tentar na tabela associados
            $stmt = $this->db->prepare("SELECT * FROM associados WHERE email = :email AND status = 'ativo'");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($senha, $user['senha'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nivel'] = $user['nivel'];
                $_SESSION['user_nome'] = $user['nome'];
                $_SESSION['user_tipo'] = $user['tipo'];
                $_SESSION['user_table'] = 'associados';
                return true;
            }

            // Se não encontrou na tabela associados, tentar na tabela usuarios
            $stmt = $this->db->prepare("SELECT * FROM usuarios WHERE email = :email AND status = 'ativo'");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($senha, $user['senha'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_nivel'] = $user['nivel'];
                $_SESSION['user_nome'] = $user['nome'];
                $_SESSION['user_tipo'] = $user['tipo'];
                $_SESSION['user_table'] = 'usuarios';
                return true;
            }

            return false;
        } catch (PDOException $e) {
            error_log("Erro no login: " . $e->getMessage());
            return false;
        }
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function logout() {
        session_destroy();
    }

    public function hasPermission($required_level) {
        if ($this->isLoggedIn() && isset($_SESSION['user_nivel'])) {
            // O nível 1 (Admin) tem acesso a tudo
            if ($_SESSION['user_nivel'] == NIVEL_ADMIN) {
                return true;
            }
            // Verifica se o nível do usuário é igual ou mais privilegiado que o necessário
            return $_SESSION['user_nivel'] <= $required_level;
        }
        return false;
    }

    /**
     * Helper para verificar se o usuário é Gestor ou Admin
     * @return bool
     */
    public function isGestorOuAdmin(): bool {
        return $this->hasPermission(NIVEL_GESTOR);
    }

    /**
     * Busca dados do usuário logado
     * @return array|false Dados do usuário ou false se não encontrado
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $table = $_SESSION['user_table'] ?? 'associados';
        $id = $_SESSION['user_id'];

        try {
            $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id = :id");
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Erro ao buscar usuário atual: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retorna o ID de associado vinculado ao usuário logado
     * @return int|null ID do associado ou null
     */
    public function getAssociadoId() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) return null;

        if (isset($_SESSION['user_table']) && $_SESSION['user_table'] === 'associados') {
            return $currentUser['id'];
        } elseif (isset($currentUser['associado_id'])) {
            return $currentUser['associado_id'];
        }
        return null;
    }

    /**
     * Verifica se o perfil do usuario esta completo (campos de perfil preenchidos)
     * @return bool
     */
    public function hasPerfilCompleto() {
        if (!$this->isLoggedIn()) return false;
        $currentUser = $this->getCurrentUser();
        if (!$currentUser) return false;

        $nivel = $_SESSION['user_nivel'] ?? null;
        
        // Admin nao precisa preencher perfil extra
        if ($nivel == NIVEL_ADMIN) return true;
        
        // Gestor precisa de orgao, setor, especialidade
        if ($nivel == NIVEL_GESTOR) {
            return !empty($currentUser['orgao']) && !empty($currentUser['setor']) && !empty($currentUser['especialidade']);
        }
        
        // Usuario comum precisa de nicho
        if ($nivel == NIVEL_USUARIO) {
            return !empty($currentUser['nicho']);
        }
        
        return false;
    }

    /**
     * Atualiza os dados de perfil do usuario
     * @param array $dados Dados a serem atualizados
     * @return bool
     */
    public function updatePerfil($dados) {
        if (!$this->isLoggedIn()) return false;
        
        $table = $_SESSION['user_table'] ?? 'associados';
        $id = $_SESSION['user_id'];
        
        // Campos permitidos para atualizacao
        $allowed = ['orgao', 'setor', 'especialidade', 'nicho'];
        $sets = [];
        $params = [];
        
        foreach ($dados as $key => $value) {
            if (in_array($key, $allowed)) {
                $sets[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
                // Guardar na sessao
                $_SESSION["user_{$key}"] = $value;
            }
        }
        
        if (empty($sets)) return false;
        
        $params[':id'] = $id;
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = :id";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar perfil: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza os dados pessoais do usuário logado na tabela correta
     * (associados ou usuarios) baseado em $_SESSION['user_table'].
     *
     * Apenas os campos que EXISTEM na tabela do usuário são atualizados.
     * Isso evita erros como "Unknown column 'sobrenome'" quando a tabela
     * 'usuarios' não possui colunas que existem na tabela 'associados'.
     *
     * @param array $dados Dados a serem atualizados (campos pessoais)
     * @return bool|string true em caso de sucesso, ou string de erro
     */
    public function updateCurrentUser($dados) {
        if (!$this->isLoggedIn()) return false;

        $table = $_SESSION['user_table'] ?? 'associados';
        $id = $_SESSION['user_id'];

        // Campos que NUNCA devem ser atualizados via este método (segurança)
        $blocked = ['id', 'senha', 'created_at', 'updated_at', 'data_criacao', 'data_atualizacao', 'ultimo_login', 'user_id'];

        // Buscar as colunas reais da tabela do usuário
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM {$table}");
            $stmt->execute();
            $colunas = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Erro ao buscar colunas da tabela {$table}: " . $e->getMessage());
            return "Erro de banco de dados ao atualizar: " . $e->getMessage();
        }

        if (empty($colunas)) {
            return "Erro de banco de dados ao atualizar: tabela {$table} não encontrada.";
        }

        $sets = [];
        $params = [];

        foreach ($dados as $key => $value) {
            // Só atualiza campos que existem na tabela e não estão bloqueados
            if (in_array($key, $colunas) && !in_array($key, $blocked)) {
                $sets[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }

        if (empty($sets)) return true;

        $params[':id'] = $id;
        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . " WHERE id = :id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Erro ao atualizar usuário atual: " . $e->getMessage());
            return "Erro de banco de dados ao atualizar: " . $e->getMessage();
        }
    }
}
