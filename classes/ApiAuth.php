<?php
/**
 * ApiAuth — guarda de autorização para endpoints (APIs/AJAX).
 *
 * Centraliza as verificações que cada endpoint precisa fazer antes de devolver
 * dados, evitando o padrão atual em que vários arquivos não checam nada e
 * expõem dados pessoais para qualquer visitante anônimo:
 *
 *     require_once __DIR__ . '/../../classes/ApiAuth.php';
 *     ApiAuth::exigirLogin();          // qualquer usuário logado
 *     ApiAuth::exigirStaff();          // Admin ou Gestor
 *     ApiAuth::exigirProprioAssociado($associadoId); // Admin/Gestor ou o dono
 *
 * Semântica de níveis (igual a User::hasPermission): 1 = Admin (acesso total),
 * 2 = Gestor, 3 = Usuário/Associado. Nível menor = mais privilégio.
 *
 * @package Seguranca
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';

class ApiAuth
{
    /** Inicia a sessão de forma idempotente. */
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private static function nivelAdmin(): int
    {
        return defined('NIVEL_ADMIN') ? (int) NIVEL_ADMIN : 1;
    }

    private static function nivelGestor(): int
    {
        return defined('NIVEL_GESTOR') ? (int) NIVEL_GESTOR : 2;
    }

    /**
     * Nível do usuário autenticado (0 = sem sessão válida).
     *
     * Aceita também a sessão do Portal do Associado (sem user_id): nesse caso
     * o nível efetivo é o de usuário comum (NIVEL_USUARIO), nunca elevado —
     * o Portal não concede privilégios de equipe.
     */
    public static function nivel(): int
    {
        self::iniciar();

        if (!empty($_SESSION['user_id'])) {
            $nivel = $_SESSION['user_nivel'] ?? null;
            if (!is_numeric($nivel)) {
                return 0;
            }
            $nivel = (int) $nivel;
            return $nivel > 0 ? $nivel : 0;
        }

        if (!empty($_SESSION['associado_id'])) {
            return defined('NIVEL_USUARIO') ? (int) NIVEL_USUARIO : 3;
        }

        return 0;
    }

    /** Existe usuário autenticado (sessão principal ou do Portal)? */
    public static function autenticado(): bool
    {
        self::iniciar();
        return self::nivel() > 0;
    }

    /** O usuário é Admin do sistema? */
    public static function ehAdmin(): bool
    {
        return self::nivel() === self::nivelAdmin();
    }

    /** O usuário é Admin ou Gestor (equipe interna)? */
    public static function ehStaff(): bool
    {
        $nivel = self::nivel();
        return $nivel > 0 && $nivel <= self::nivelGestor();
    }

    /**
     * O usuário tem cargo da Diretoria Executiva (presidente, vice-presidente,
     * diretor, secretário, tesoureiro) em alguma associação?
     *
     * Mesma política usada pelo Portal (PortalAccess::ehPrivilegiado): Admin e
     * Diretoria Executiva podem acessar dados de outros associados.
     */
    public static function ehDiretoriaExecutiva(): bool
    {
        if (!self::autenticado()) {
            return false;
        }
        try {
            return (new User())->isDiretoriaExecutiva();
        } catch (Throwable $e) {
            error_log('ApiAuth::ehDiretoriaExecutiva - erro: ' . $e->getMessage());
            return false;
        }
    }

    /** ID do associado vinculado ao usuário logado (0 se não houver). */
    public static function associadoId(): int
    {
        if (!self::autenticado()) {
            return 0;
        }
        try {
            return (int) (new User())->getAssociadoIdResolvido();
        } catch (Throwable $e) {
            error_log('ApiAuth::associadoId - erro: ' . $e->getMessage());
            return 0;
        }
    }

    // -----------------------------------------------------------------
    // Verificações (encerram a requisição quando negam)
    // -----------------------------------------------------------------

    /**
     * Exige um usuário autenticado.
     *
     * @param bool $json Responder em JSON (padrão) ou texto simples.
     */
    public static function exigirLogin(bool $json = true): void
    {
        if (!self::autenticado()) {
            self::negar(401, 'Não autenticado. Faça login para acessar este recurso.', $json);
        }
    }

    /**
     * Exige equipe interna (Admin ou Gestor).
     *
     * @param bool $json Responder em JSON (padrão) ou texto simples.
     */
    public static function exigirStaff(bool $json = true): void
    {
        self::exigirLogin($json);
        if (!self::ehStaff()) {
            self::negar(403, 'Você não tem permissão para acessar este recurso.', $json);
        }
    }

    /**
     * Exige o nível mínimo (mesma semântica de User::hasPermission).
     * Ex.: exigirNivel(NIVEL_GESTOR) libera Admin (1) e Gestor (2).
     */
    public static function exigirNivel(int $nivelNecessario, bool $json = true): void
    {
        self::exigirLogin($json);
        $nivel = self::nivel();
        if ($nivel > self::nivelAdmin() && $nivel > $nivelNecessario) {
            self::negar(403, 'Você não tem permissão para acessar este recurso.', $json);
        }
    }

    /**
     * Exige que o usuário seja Admin/Gestor ou o DONO do associado informado.
     * É a defesa padrão contra IDOR (acesso a dados de outro produtor).
     */
    public static function exigirProprioAssociado($associadoId, bool $json = true): void
    {
        self::exigirLogin($json);
        // Admin/Gestor (equipe) e Diretoria Executiva podem acessar terceiros —
        // é a mesma política do Portal (PortalAccess::ehPrivilegiado).
        if (self::ehStaff() || self::ehDiretoriaExecutiva()) {
            return;
        }
        $alvo = (int) $associadoId;
        $meu = self::associadoId();
        if ($alvo <= 0 || $meu <= 0 || $alvo !== $meu) {
            self::negar(403, 'Acesso negado: você só pode acessar os seus próprios dados.', $json);
        }
    }

    /** Encerra a requisição com o código e a mensagem informados. */
    public static function negar(int $codigo, string $mensagem, bool $json = true): void
    {
        if (!headers_sent()) {
            http_response_code($codigo);
            header('Content-Type: ' . ($json ? 'application/json; charset=utf-8' : 'text/plain; charset=utf-8'));
        }
        echo $json ? json_encode(['erro' => $mensagem], JSON_UNESCAPED_UNICODE) : $mensagem;
        exit;
    }
}
