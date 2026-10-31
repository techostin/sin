<?php
/**
 * PortalAccess — Guarda central de autorização do Portal do Associado.
 *
 * Regra de negócio (política de acesso):
 *   - Admin do sistema e membros da Diretoria Executiva (Presidente,
 *     Vice-Presidente, Diretor, Secretário e Tesoureiro) PODEM acessar os
 *     dados de qualquer associado ativo.
 *   - Qualquer outro usuário autenticado (usuário/associado comum) só pode
 *     acessar os PRÓPRIOS dados. Qualquer tentativa de informar o CPF de
 *     terceiros — em qualquer fluxo do portal (CPF+Senha, Link Mágico ou
 *     confirmação do token) — é bloqueada com a mensagem de segurança.
 *
 * Toda a verificação do portal deve passar por esta classe: assim a regra é
 * única (single source of truth) e não depende de cada tela lembrar de checar.
 *
 * @package Portal
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/PasswordReset.php';
require_once __DIR__ . '/AuditLog.php';

class PortalAccess
{
    /** Mensagem de segurança exibida quando o acesso é negado. */
    public const MSG_ACESSO_NEGADO = 'Você só tem permissão para acessar os seus próprios dados exclusivos.';

    /** Janela (segundos) e limite de tentativas por sessão (anti abuso). */
    public const JANELA_TENTATIVAS = 900;   // 15 minutos
    public const MAX_TENTATIVAS   = 10;

    private static function db(): PDO
    {
        return Database::getInstance()->getConnection();
    }

    // -----------------------------------------------------------------
    // CPF: normalização e busca tolerante
    // -----------------------------------------------------------------

    /**
     * Normaliza um CPF para 11 dígitos.
     *
     * Tolera pontuação e zeros à esquerda ausentes (há registros legados no
     * banco com 10 dígitos, ex.: "3689160604"), garantindo que o mesmo CPF
     * seja sempre comparado na mesma forma.
     *
     * @param string|null $cpf
     * @return string CPF com 11 dígitos, '' se vazio e '' se inválido (>11 dígitos).
     */
    public static function normalizarCpf($cpf): string
    {
        $digitos = preg_replace('/\D+/', '', (string) $cpf);
        if ($digitos === '') {
            return '';
        }
        $digitos = ltrim($digitos, '0');
        if ($digitos === '') {
            return '00000000000';
        }
        if (strlen($digitos) > 11) {
            return '';
        }
        return str_pad($digitos, 11, '0', STR_PAD_LEFT);
    }

    /** Formata um CPF (11 dígitos) como 000.000.000-00. */
    public static function formatarCpf($cpf): string
    {
        $normalizado = self::normalizarCpf($cpf);
        if ($normalizado === '') {
            return '';
        }
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $normalizado);
    }

    /** Mascara o CPF para uso em logs (LGPD): 123.***.***-45. */
    public static function mascararCpf($cpf): string
    {
        $normalizado = self::normalizarCpf($cpf);
        if ($normalizado === '') {
            return '—';
        }
        return substr($normalizado, 0, 3) . '.***.***-' . substr($normalizado, -2);
    }

    /**
     * Expressão SQL que devolve apenas os dígitos de uma coluna de CPF.
     * $coluna é sempre um nome fixo definido internamente (nunca vem do usuário).
     */
    public static function cpfSqlExpr(string $coluna = 'cpf'): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $coluna)) {
            $coluna = 'cpf';
        }
        return "REPLACE(REPLACE(REPLACE(REPLACE(TRIM({$coluna}),'.',''),'-',''),' ',''),'/','')";
    }

    /**
     * Variantes aceitas de um CPF informado (com e sem zeros à esquerda),
     * para consultar registros legados sem perder o registro correto.
     *
     * @return string[]
     */
    public static function variantesCpf($cpf): array
    {
        $digitos = preg_replace('/\D+/', '', (string) $cpf);
        if ($digitos === '') {
            return [];
        }
        $variantes = [$digitos, self::normalizarCpf($digitos), ltrim($digitos, '0')];
        $variantes = array_values(array_unique(array_filter($variantes, static function ($v) {
            return $v !== '' && $v !== null;
        })));
        return $variantes;
    }

    /**
     * Busca um associado ATIVO pelo CPF informado, tolerando pontuação e
     * zeros à esquerda ausentes.
     *
     * @param string $cpf
     * @return array|null Linha do associado ou null.
     */
    public static function buscarAssociadoAtivoPorCpf($cpf): ?array
    {
        $variantes = self::variantesCpf($cpf);
        if (empty($variantes)) {
            return null;
        }
        $placeholders = implode(',', array_fill(0, count($variantes), '?'));
        $sql = 'SELECT * FROM associados WHERE status = \'ativo\' AND '
             . self::cpfSqlExpr('cpf') . " IN ({$placeholders}) LIMIT 1";
        try {
            $stmt = self::db()->prepare($sql);
            $stmt->execute($variantes);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('PortalAccess::buscarAssociadoAtivoPorCpf - erro: ' . $e->getMessage());
            return null;
        }
    }

    /** CPF normalizado de um associado pelo ID ('' quando não encontrado). */
    public static function cpfDoAssociadoId($associadoId): string
    {
        $associadoId = (int) $associadoId;
        if ($associadoId <= 0) {
            return '';
        }
        try {
            $stmt = self::db()->prepare('SELECT cpf FROM associados WHERE id = ? LIMIT 1');
            $stmt->execute([$associadoId]);
            return self::normalizarCpf($stmt->fetchColumn());
        } catch (PDOException $e) {
            return '';
        }
    }

    /**
     * ID do associado ativo dono do e-mail informado.
     *
     * A MESMA pessoa pode ter dois e-mails: o do cadastro (associados.email,
     * muitas vezes o e-mail da família) e o de login da conta de sistema
     * (usuarios.email). Por isso a busca considera os dois — assim o Link
     * Mágico e a recuperação de senha funcionam por qualquer um deles.
     *
     * @return int ID do associado ou 0.
     */
    public static function associadoIdPorEmail($email): int
    {
        $email = trim((string) $email);
        if ($email === '') {
            return 0;
        }
        try {
            $stmt = self::db()->prepare("SELECT id FROM associados WHERE email = ? AND status = 'ativo' LIMIT 1");
            $stmt->execute([$email]);
            $id = (int) $stmt->fetchColumn();
            if ($id > 0) {
                return $id;
            }

            // Não é o e-mail do cadastro: pode ser o e-mail da conta de sistema
            // vinculada a um associado (usuarios.associado_id / associados.user_id).
            $stmt = self::db()->prepare(
                "SELECT a.id
                   FROM usuarios u
                   JOIN associados a ON (a.id = u.associado_id OR a.user_id = u.id)
                  WHERE u.email = ? AND u.status = 'ativo' AND a.status = 'ativo'
                  LIMIT 1"
            );
            $stmt->execute([$email]);
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('PortalAccess::associadoIdPorEmail - erro: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * E-mail que deve receber o Link Mágico de um associado: o da conta de
     * sistema vinculada (login individual) quando existir; caso contrário o
     * e-mail do próprio cadastro.
     */
    public static function emailDeAcessoDoAssociado($associadoId, $emailCadastro = ''): string
    {
        $vinculo = self::usuarioVinculadoAoAssociado((int) $associadoId);
        if ($vinculo && !empty($vinculo['email'])) {
            return (string) $vinculo['email'];
        }
        return trim((string) $emailCadastro);
    }

    /**
     * Usuário da tabela "usuarios" REALMENTE vinculado ao associado informado
     * (usuarios.associado_id ou associados.user_id).
     *
     * Atenção: NÃO basta o e-mail ser igual. O portal antigo vinculava a
     * sessão principal apenas por e-mail; como o próprio associado pode trocar
     * o e-mail no portal (modulos/portal/area.php), isso permitia escalar
     * privilégio assumindo a conta de um usuário da equipe.
     *
     * @return array|null ['id' => int, 'nivel' => int] ou null.
     */
    public static function usuarioVinculadoAoAssociado($associadoId): ?array
    {
        $associadoId = (int) $associadoId;
        if ($associadoId <= 0) {
            return null;
        }
        try {
            $sql = "SELECT u.id, u.nivel, u.email
                    FROM usuarios u
                    WHERE u.status = 'ativo'
                      AND (u.associado_id = ?
                           OR u.id = (SELECT a.user_id FROM associados a WHERE a.id = ?))
                    LIMIT 1";
            $stmt = self::db()->prepare($sql);
            $stmt->execute([$associadoId, $associadoId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('PortalAccess::usuarioVinculadoAoAssociado - erro: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * ID do associado titular de um token de Link Mágico válido (0 se o token
     * for inválido/expirado ou não corresponder a um associado ativo).
     */
    public static function associadoIdDoToken($token): int
    {
        $token = (string) $token;
        if ($token === '') {
            return 0;
        }
        try {
            $reset = (new PasswordReset())->getValidToken($token);
            if (!$reset || empty($reset['email'])) {
                return 0;
            }
            return self::associadoIdPorEmail($reset['email']);
        } catch (Throwable $e) {
            error_log('PortalAccess::associadoIdDoToken - erro: ' . $e->getMessage());
            return 0;
        }
    }

    // -----------------------------------------------------------------
    // Identidade / privilégio
    // -----------------------------------------------------------------

    /** Existe sessão ativa do Portal do Associado? */
    public static function temSessaoPortal(): bool
    {
        return !empty($_SESSION['associado_id']);
    }

    /** O usuário está autenticado (sessão principal ou sessão do portal)? */
    public static function autenticado(?User $user = null): bool
    {
        $user = $user ?: new User();
        return $user->isLoggedIn() || self::temSessaoPortal();
    }

    /**
     * Admin do sistema ou cargo da Diretoria Executiva — únicos perfis com
     * permissão para acessar dados de outros associados pelo portal.
     */
    public static function ehPrivilegiado(?User $user = null): bool
    {
        $user = $user ?: new User();
        if (!$user->isLoggedIn()) {
            return false;
        }
        return $user->hasPermission(NIVEL_ADMIN) || $user->isDiretoriaExecutiva();
    }

    /**
     * CPF (normalizado) vinculado ao usuário autenticado.
     * Cobre: sessão do portal, login na tabela associados e usuário da tabela
     * usuarios com associado_id (ou associados.user_id apontando para ele).
     */
    public static function cpfDoUsuario(?User $user = null): string
    {
        $user = $user ?: new User();

        if (self::temSessaoPortal()) {
            $cpf = self::cpfDoAssociadoId((int) $_SESSION['associado_id']);
            if ($cpf !== '') {
                return $cpf;
            }
        }

        if (!$user->isLoggedIn()) {
            return '';
        }

        $associadoId = (int) $user->getAssociadoIdResolvido();
        if ($associadoId > 0) {
            $cpf = self::cpfDoAssociadoId($associadoId);
            if ($cpf !== '') {
                return $cpf;
            }
        }

        $atual = $user->getCurrentUser();
        if (is_array($atual) && !empty($atual['cpf'])) {
            return self::normalizarCpf($atual['cpf']);
        }

        return '';
    }

    /** O CPF informado pertence ao próprio usuário autenticado? */
    public static function ehTitularDoCpf($cpf, ?User $user = null): bool
    {
        $user = $user ?: new User();
        if (!self::autenticado($user)) {
            return false;
        }
        $alvo = self::normalizarCpf($cpf);
        if ($alvo === '') {
            return false;
        }
        $meu = self::cpfDoUsuario($user);
        return $meu !== '' && hash_equals($meu, $alvo);
    }

    /** O associado informado é o próprio usuário autenticado? */
    public static function ehTitularDoAssociadoId($associadoId, ?User $user = null): bool
    {
        $user = $user ?: new User();
        if (!self::autenticado($user)) {
            return false;
        }
        $associadoId = (int) $associadoId;
        if ($associadoId <= 0) {
            return false;
        }
        $cpf = self::cpfDoAssociadoId($associadoId);
        return $cpf !== '' && self::ehTitularDoCpf($cpf, $user);
    }

    // -----------------------------------------------------------------
    // Decisão de acesso
    // -----------------------------------------------------------------

    /**
     * A guarda restritiva se aplica? (usuário autenticado sem privilégio).
     * Visitantes anônimos não são afetados: o fluxo anônimo é validado por
     * senha ou por token enviado ao e-mail do titular.
     */
    public static function bloqueioAtivo(?User $user = null): bool
    {
        $user = $user ?: new User();
        return self::autenticado($user) && !self::ehPrivilegiado($user);
    }

    /**
     * Pode acessar/impersonar o CPF informado?
     *
     * @return bool true para Admin/Diretoria Executiva, para o próprio titular
     *              e para visitantes anônimos; false em qualquer outro caso.
     */
    public static function podeAcessarCpf($cpf, ?User $user = null): bool
    {
        $user = $user ?: new User();

        if (!self::autenticado($user)) {
            return true; // fluxo de login anônimo (senha ou link enviado por e-mail)
        }
        if (self::ehPrivilegiado($user)) {
            return true;
        }
        return self::ehTitularDoCpf($cpf, $user);
    }

    /** Pode acessar/impersonar o associado informado? (mesma regra, por ID) */
    public static function podeAcessarAssociadoId($associadoId, ?User $user = null): bool
    {
        $user = $user ?: new User();

        if (!self::autenticado($user)) {
            return true;
        }
        if (self::ehPrivilegiado($user)) {
            return true;
        }
        return self::ehTitularDoAssociadoId($associadoId, $user);
    }

    /** O modo Administrativo (consultar qualquer associado) está disponível? */
    public static function podeConsultarOutros(?User $user = null): bool
    {
        return self::ehPrivilegiado($user);
    }

    // -----------------------------------------------------------------
    // Auditoria e anti abuso
    // -----------------------------------------------------------------

    /** Registra uma tentativa de acesso negada (auditoria + log). */
    public static function registrarAcessoNegado($acao, $cpfAlvo = '', $associadoAlvo = 0): void
    {
        $descricao = sprintf(
            'acao=%s cpf_alvo=%s associado_alvo=%d user_id=%s ip=%s',
            (string) $acao,
            self::mascararCpf($cpfAlvo),
            (int) $associadoAlvo,
            (string) ($_SESSION['user_id'] ?? '—'),
            self::ip()
        );
        AuditLog::registrar('PORTAL_ACESSO_NEGADO', 'associados', ((int) $associadoAlvo) ?: null, $descricao);
        error_log('PortalAccess ACESSO NEGADO: ' . $descricao);
    }

    /** Registra um acesso legítimo de Admin/Diretoria ao portal de terceiros. */
    public static function registrarAcessoPrivilegiado($associadoId, $detalhe = ''): void
    {
        $descricao = sprintf(
            'acesso_privilegiado associado=%d user_id=%s ip=%s %s',
            (int) $associadoId,
            (string) ($_SESSION['user_id'] ?? '—'),
            self::ip(),
            (string) $detalhe
        );
        AuditLog::registrar('PORTAL_ACESSO_PRIVILEGIADO', 'associados', (int) $associadoId, $descricao);
        error_log('PortalAccess ' . $descricao);
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    }

    /**
     * Controle simples de tentativas por sessão (anti força bruta / abuso de
     * link mágico). Devolve quantas tentativas restam na janela.
     */
    public static function tentativasRestantes(): int
    {
        $agora = time();
        $tentativas = self::tentativasNaJanela($agora);
        return max(0, self::MAX_TENTATIVAS - count($tentativas));
    }

    /** Registra uma tentativa e devolve true se o limite foi excedido. */
    public static function registrarTentativa(): bool
    {
        $agora = time();
        $tentativas = self::tentativasNaJanela($agora);
        $tentativas[] = $agora;
        $_SESSION['portal_tentativas'] = $tentativas;
        return count($tentativas) > self::MAX_TENTATIVAS;
    }

    /** Limpa o contador (usado após sucesso). */
    public static function limparTentativas(): void
    {
        unset($_SESSION['portal_tentativas']);
    }

    /** @return int[] */
    private static function tentativasNaJanela(int $agora): array
    {
        $bruto = $_SESSION['portal_tentativas'] ?? [];
        if (!is_array($bruto)) {
            return [];
        }
        $validas = [];
        foreach ($bruto as $t) {
            if (is_numeric($t) && ($agora - (int) $t) < self::JANELA_TENTATIVAS) {
                $validas[] = (int) $t;
            }
        }
        return $validas;
    }
}
