<?php
/**
 * Gerenciamento de recuperação de senha (tokens + redefinição).
 */
require_once __DIR__ . '/Database.php';

class PasswordReset {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Busca o usuário (associado ou usuário interno) por e-mail.
     * Considera apenas contas ativas.
     *
     * @return array|false ['tabela' => 'associados'|'usuarios', 'id' => int, 'nome' => string, 'email' => string]
     */
    public function findUserByEmail($email) {
        // Somente string: um array na consulta (ex.: ?email[]=x) causaria erro.
        if (!is_string($email)) return false;
        $email = trim($email);
        if (empty($email)) return false;

        $stmt = $this->db->prepare("SELECT id, nome, sobrenome, email FROM associados WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            return ['tabela' => 'associados', 'id' => (int)$user['id'], 'nome' => $user['nome'] . ' ' . $user['sobrenome'], 'email' => $user['email']];
        }

        $stmt = $this->db->prepare("SELECT id, nome, sobrenome, email FROM usuarios WHERE email = ? AND status = 'ativo'");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            return ['tabela' => 'usuarios', 'id' => (int)$user['id'], 'nome' => $user['nome'] . ' ' . $user['sobrenome'], 'email' => $user['email']];
        }

        return false;
    }

    /**
     * Gera um token de recuperação válido por 60 minutos e o armazena (hash).
     *
     * @return string Token em texto puro (vai no link do e-mail)
     */
    public function createToken($email) {
        if (!is_string($email) || trim($email) === '') {
            throw new InvalidArgumentException('E-mail inválido para geração de token.');
        }
        $email = trim($email);
        $token  = bin2hex(random_bytes(32));
        $hash   = hash('sha256', $token);
        $expira = date('Y-m-d H:i:s', time() + 3600);

        // Invalida tokens anteriores do mesmo e-mail
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE email = ?");
        $stmt->execute([$email]);

        $stmt = $this->db->prepare("INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$email, $hash, $expira]);

        return $token;
    }

    /**
     * Valida um token (existente, não usado e não expirado).
     *
     * @return array|false Registro da tabela ou false
     */
    public function getValidToken($token) {
        // Apenas string: um array (ex.: ?token[]=x) faria hash() lançar
        // TypeError e a requisição terminaria em HTTP 500.
        if (!is_string($token) || $token === '') return false;
        $hash = hash('sha256', $token);

        $stmt = $this->db->prepare("
            SELECT * FROM password_resets
            WHERE token_hash = ? AND used = 0 AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: false;
    }

    /**
     * Marca um token como usado.
     */
    public function markUsed($id) {
        if (!is_numeric($id)) return false;
        // Consumo atômico: só marca se o token ainda não tiver sido usado.
        // Evita que duas requisições simultâneas usem o mesmo link (TOCTOU).
        $stmt = $this->db->prepare("UPDATE password_resets SET used = 1 WHERE id = ? AND used = 0");
        $stmt->execute([(int)$id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Redefine a senha do usuário na tabela correta.
     *
     * @return bool
     */
    public function updatePassword($tabela, $userId, $novaSenha) {
        if (!in_array($tabela, ['associados', 'usuarios'], true)) return false;
        if (!is_numeric($userId) || !is_string($novaSenha) || $novaSenha === '') return false;
        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("UPDATE {$tabela} SET senha = ? WHERE id = ?");
        return $stmt->execute([$hash, (int)$userId]);
    }

    /**
     * Limpa tokens vencidos/usados (manutenção).
     */
    public function purgeOldTokens() {
        $stmt = $this->db->prepare("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");
        return $stmt->execute();
    }
}
