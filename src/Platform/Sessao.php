<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Sessão isolada por sistema.
 *
 * Cada sistema usa um nome de sessão próprio e um cookie restrito ao seu
 * caminho (`/app/assoc/`, `/app/social/`, `/app/producao/`). Consequências:
 *  - é possível estar autenticado em um sistema e não no outro;
 *  - o logout de um não derruba os demais;
 *  - um sistema nunca enxerga o cookie de outro.
 *
 * O `admin` continua com acesso total: pode autenticar-se em todos eles.
 */
final class Sessao
{
    public const EXPIRACAO = 8 * 60 * 60; // 8 horas de inatividade

    private static ?string $slug = null;

    public static function iniciar(string $slug): void
    {
        if (!Aplicacoes::existe($slug)) {
            throw new \InvalidArgumentException('Sistema desconhecido: ' . $slug);
        }

        self::$slug = $slug;

        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $caminho = Urls::sistema($slug); // /app/assoc/
        $seguro = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_trans_sid', '0');
        @ini_set('session.cookie_httponly', '1');

        session_name('SINPORG_' . strtoupper($slug));
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => $caminho,
            'secure' => $seguro,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        // Defesa em profundidade: a sessão pertence a este sistema.
        $dono = $_SESSION['plataforma_app'] ?? null;
        if ($dono !== null && $dono !== $slug) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['plataforma_app'] = $slug;
    }

    /** @return array<string, mixed>|null */
    public static function usuario(): ?array
    {
        $identidade = $_SESSION['identidade'] ?? null;
        if (!is_array($identidade)) {
            return null;
        }

        if (($_SESSION['plataforma_app'] ?? null) !== self::$slug) {
            return null;
        }

        $ultimo = (int) ($_SESSION['ultimo_acesso'] ?? 0);
        if ($ultimo > 0 && (time() - $ultimo) > self::EXPIRACAO) {
            self::sair();
            return null;
        }

        // Recarrega do banco a cada requisição: aprovação de ficha e mudança de
        // papéis passam a valer imediatamente, sem precisar de novo login.
        $atualizada = Identidades::porId(
            (string) ($identidade['origem'] ?? ''),
            (int) ($identidade['id'] ?? 0)
        );
        if ($atualizada !== null) {
            $identidade = $atualizada;
            $_SESSION['identidade'] = $identidade;
            $_SESSION['user_nome'] = (string) ($identidade['nome'] ?? '');
            $_SESSION['user_tabela'] = (string) ($identidade['origem'] ?? '');
            $_SESSION['user_tipo'] = (string) ($identidade['tipo'] ?? 'usuario');
            $_SESSION['user_nivel'] = (int) ($identidade['nivel'] ?? 3);
        }

        $_SESSION['ultimo_acesso'] = time();

        return $identidade;
    }

    /** @param array<string, mixed> $identidade */
    public static function entrar(array $identidade): void
    {
        // Qualquer identidade anterior (inclusive de troca de conta) deve ser
        // descartada ANTES de gravar a nova. Sem isso, restos da sessão antiga
        // podiam manter nome/e-mail/associado do usuário anterior.
        self::limparIdentidade(false);
        session_regenerate_id(true);

        $_SESSION['identidade'] = $identidade;
        $_SESSION['plataforma_app'] = self::$slug;
        $_SESSION['login_em'] = time();
        $_SESSION['ultimo_acesso'] = time();

        // Compatibilidade com classes legadas (AuditLog, include/header.php).
        $_SESSION['user_id'] = (int) ($identidade['id'] ?? 0);
        $_SESSION['user_nome'] = (string) ($identidade['nome'] ?? '');
        $_SESSION['user_tabela'] = (string) ($identidade['origem'] ?? '');
        $_SESSION['user_tipo'] = (string) ($identidade['tipo'] ?? 'usuario');
        $_SESSION['user_nivel'] = (int) ($identidade['nivel'] ?? 3);

        // A ficha de adesão aparece apenas uma vez por login (no primeiro
        // dashboard). Depois disso, somente quando o usuário abrir "Minha ficha"
        // pelo menu — nunca a cada clique/navegação.
        $_SESSION['mostrar_ficha_modal'] = true;

        Csrf::rotacionar();
    }

    /**
     * Remove toda identidade autenticada da sessão, preservando apenas dados
     * técnicos da sessão (CSRF, app e tentativas). Usado em troca de conta e
     * em qualquer tentativa de login que não deve herdar a sessão anterior.
     */
    public static function limparIdentidade(bool $regenerarId = true): void
    {
        foreach ([
            'identidade',
            'user_id', 'user_nome', 'user_tabela', 'user_table', 'user_tipo',
            'user_tipo_usuario', 'user_nivel', 'user_orgao', 'user_setor',
            'user_especialidade', 'user_area_producao', 'associado_id',
            'associado_nome', 'associado_cpf', 'associado_email',
            'portal_acesso_via', 'portal_acesso_por', 'login_temp',
            'login_em', 'ultimo_acesso', 'primeiro_acesso',
            'mostrar_modal_eventos',
        ] as $chave) {
            unset($_SESSION[$chave]);
        }

        if ($regenerarId && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function sair(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
