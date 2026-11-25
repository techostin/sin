<?php

declare(strict_types=1);

namespace App\Platform;

use PDO;

/**
 * Carrega a identidade do usuário e o seu PERFIL.
 *
 * Aceita as duas origens de credencial que o legado mantém:
 *  - `usuarios`      → equipe (admin, gestão, fiscalização, diretoria);
 *  - `associados`    → agricultor/produtor com senha própria (portal).
 *
 * O perfil efetivo vem de `usuario_papel` (K1); quando a conta não tem papéis
 * (contas antigas), deriva dos campos legados `tipo`/`nivel`/`associado_id`.
 */
final class Identidades
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = (string) constant('DB_HOST');
        $nome = (string) constant('DB_NAME');
        $usuario = (string) constant('DB_USER');
        $senha = (string) constant('DB_PASS');

        self::$pdo = new PDO(
            'mysql:host=' . $host . ';dbname=' . $nome . ';charset=utf8mb4',
            $usuario,
            $senha,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
            ]
        );

        return self::$pdo;
    }

    /**
     * Valida credenciais e devolve a identidade com perfil/permissões.
     *
     * @return array<string, mixed>|null
     */
    public static function porCredenciais(string $identificador, string $senha): ?array
    {
        $identificador = trim($identificador);
        if ($identificador === '' || $senha === '') {
            return null;
        }

        $email = mb_strtolower($identificador);
        // CPF só entra na busca quando o identificador é, de fato, um CPF.
        // Um e-mail sem dígitos produzia CPF vazio e casava com associados de
        // CPF vazio — ex.: digitar "naoexiste@x.com" + senha do cici acabava
        // autenticando como cici pelo CPF vazio. Por isso o CPF precisa ter 11
        // dígitos para ser considerado.
        $cpf = self::cpfDeIdentificador($identificador);

        // 1) Conta de sistema (equipe) tem precedência — inclusive Admin.
        //    A senha é conferida mesmo para contas pendentes/inativas, para o
        //    login poder explicar "aguardando liberação" sem revelar status a
        //    quem não sabe a senha.
        $stmt = self::pdo()->prepare(
            'SELECT * FROM usuarios WHERE LOWER(email) = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch() ?: null;

        if (is_array($usuario) && !empty($usuario['senha']) && password_verify($senha, (string) $usuario['senha'])) {
            return self::deUsuario($usuario);
        }

        // 2) Associado/produtor: e-mail ou CPF (somente CPF válido).
        $sql = "SELECT * FROM associados WHERE LOWER(email) = :email";
        $params = ['email' => $email];
        if ($cpf !== '') {
            $sql .= " OR REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = :cpf";
            $params['cpf'] = $cpf;
        }
        $sql .= ' LIMIT 1';
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $associado = $stmt->fetch() ?: null;

        if (is_array($associado) && !empty($associado['senha']) && password_verify($senha, (string) $associado['senha'])) {
            return self::deAssociado($associado);
        }

        return null;
    }

    /**
     * Normaliza um identificador como CPF. Retorna '' quando não for um CPF
     * (ex.: e-mail), evitando casar com registros de CPF vazio.
     */
    public static function cpfDeIdentificador(string $identificador): string
    {
        $somenteDigitos = preg_replace('/\D+/', '', trim($identificador)) ?? '';

        return $somenteDigitos !== '' && strlen($somenteDigitos) === 11 && ctype_digit($somenteDigitos)
            ? $somenteDigitos
            : '';
    }

    /**
     * Carrega a identidade por e-mail, sem senha (diagnóstico/CLI).
     * Procura em `usuarios` e, se não achar, em `associados`.
     *
     * @return array<string, mixed>|null
     */
    public static function porEmail(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }

        $stmt = self::pdo()->prepare('SELECT * FROM usuarios WHERE LOWER(email) = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch() ?: null;
        if (is_array($usuario)) {
            return self::deUsuario($usuario);
        }

        $stmt = self::pdo()->prepare('SELECT * FROM associados WHERE LOWER(email) = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $associado = $stmt->fetch() ?: null;

        return is_array($associado) ? self::deAssociado($associado) : null;
    }

    /**
     * Carrega a identidade por id de `usuarios` (diagnóstico/CLI).
     *
     * @return array<string, mixed>|null
     */
    public static function porUsuarioId(int $usuarioId): ?array
    {
        $stmt = self::pdo()->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $usuarioId]);
        $usuario = $stmt->fetch() ?: null;

        return is_array($usuario) ? self::deUsuario($usuario) : null;
    }

    /**
     * Recarrega a identidade pelo id/tabela (usado para refletir imediatamente
     * mudanças de vínculo/papéis sem exigir novo login).
     *
     * @return array<string, mixed>|null
     */
    public static function porId(string $origem, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $tabela = $origem === 'associados' ? 'associados' : 'usuarios';
        $stmt = self::pdo()->prepare('SELECT * FROM ' . $tabela . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch();

        if (!is_array($linha)) {
            return null;
        }

        return $origem === 'associados' ? self::deAssociado($linha) : self::deUsuario($linha);
    }

    /**
     * @param array<string, mixed> $u
     * @return array<string, mixed>
     */
    private static function deUsuario(array $u): array
    {
        $id = (int) ($u['id'] ?? 0);
        $papeis = self::papeisDe($id);
        if ($papeis === []) {
            $papeis = self::papeisLegados($u);
        }
        $papeis = array_values(array_unique($papeis));

        $associadoVinculado = (int) ($u['associado_id'] ?? 0);
        if ($associadoVinculado > 0) {
            // Cargos da diretoria vêm do vínculo associativo (presidente,
            // diretor, tesoureiro, secretário...).
            $papeis = array_values(array_unique(array_merge($papeis, self::papeisDeVinculo($associadoVinculado))));
        }

        // Associado vinculado sempre tem acesso básico ao portal, mesmo que
        // papéis antigos não incluam nenhum perfil do módulo de associação.
        $papeisDoModulo = ['produtor', 'diretor', 'tesoureiro', 'secretario', 'gestor_associacao', 'diretoria', 'agroindustria'];
        if ($associadoVinculado > 0
            && !in_array('admin', $papeis, true)
            && array_intersect($papeis, $papeisDoModulo) === []) {
            $papeis[] = 'produtor';
        }

        $permissoesBanco = self::permissoesDe($id);
        $permissoes = self::combinarPermissoes($papeis, $permissoesBanco);

        $principal = self::papelPrincipal($papeis);

        return [
            'id' => $id,
            'origem' => 'usuarios',
            'nome' => trim(($u['nome'] ?? '') . ' ' . ($u['sobrenome'] ?? '')),
            'email' => (string) ($u['email'] ?? ''),
            'tipo' => (string) ($u['tipo'] ?? 'usuario'),
            'nivel' => (int) ($u['nivel'] ?? 3),
            'status' => (string) ($u['status'] ?? ''),
            'tipo_usuario' => (string) ($u['tipo_usuario'] ?? ''),
            'perfil_solicitado' => (string) ($u['perfil_solicitado'] ?? ''),
            'nivel_solicitado' => (int) ($u['nivel_solicitado'] ?? 0),
            'telefone' => (string) ($u['telefone'] ?? ''),
            'cpf' => (string) ($u['cpf'] ?? ''),
            'orgao' => (string) ($u['orgao'] ?? ''),
            'setor' => (string) ($u['setor'] ?? ''),
            'especialidade' => (string) ($u['especialidade'] ?? ''),
            'associado_id' => isset($u['associado_id']) ? (int) $u['associado_id'] : null,
            'ficha_pendente' => (int) ($u['nivel'] ?? 3) === 3 && (int) ($u['associado_id'] ?? 0) === 0,
            'area_producao' => (string) ($u['area_producao'] ?? ''),
            'tem_agroindustria' => (int) ($u['tem_agroindustria'] ?? 0),
            'papeis' => $papeis,
            'perfil' => Aplicacoes::rotuloPapel($principal),
            'perfil_codigo' => $principal,
            'permissoes' => $permissoes,
            'admin' => in_array('admin', $papeis, true) || ($u['tipo'] ?? '') === 'admin' || (int) ($u['nivel'] ?? 3) === 1,
            'certificadora_id' => isset($u['certificadora_id']) ? (int) $u['certificadora_id'] : null,
            'associacao_id' => null,
        ];
    }

    /**
     * @param array<string, mixed> $a
     * @return array<string, mixed>
     */
    private static function deAssociado(array $a): array
    {
        $ehConsumidor = in_array('consumidor', [
            strtolower((string) ($a['tipo_associado'] ?? '')),
            strtolower((string) ($a['tipo_usuario'] ?? '')),
        ], true);

        $papeis = array_values(array_unique(array_merge(
            [$ehConsumidor ? 'consumidor' : 'produtor'],
            self::papeisDeVinculo((int) ($a['id'] ?? 0))
        )));
        $principal = self::papelPrincipal($papeis);

        return [
            'id' => (int) ($a['id'] ?? 0),
            'origem' => 'associados',
            'nome' => trim(($a['nome'] ?? '') . ' ' . ($a['sobrenome'] ?? '')),
            'email' => (string) ($a['email'] ?? ''),
            'tipo' => 'produtor',
            'nivel' => 3,
            'status' => (string) ($a['status'] ?? ''),
            'tipo_usuario' => (string) ($a['tipo_usuario'] ?? 'agricultor'),
            'telefone' => (string) ($a['telefone'] ?? ''),
            'cpf' => (string) ($a['cpf'] ?? ''),
            'orgao' => '',
            'setor' => '',
            'especialidade' => '',
            'associado_id' => (int) ($a['id'] ?? 0),
            'area_producao' => (string) ($a['area_producao'] ?? ''),
            'tem_agroindustria' => (int) ($a['tem_agroindustria'] ?? 0),
            'papeis' => $papeis,
            'perfil' => Aplicacoes::rotuloPapel($principal),
            'perfil_codigo' => $principal,
            'permissoes' => self::combinarPermissoes($papeis, []),
            'admin' => false,
            'certificadora_id' => isset($a['certificadora_id']) ? (int) $a['certificadora_id'] : null,
            'associacao_id' => isset($a['associacao_id']) ? (int) $a['associacao_id'] : null,
        ];
    }

    /**
     * Perfis decorrentes do cargo no vínculo associativo (associado_associacao).
     *
     * @return list<string>
     */
    private static function papeisDeVinculo(int $associadoId): array
    {
        if ($associadoId <= 0) {
            return [];
        }

        try {
            $stmt = self::pdo()->prepare(
                'SELECT DISTINCT tipo_vinculo FROM associado_associacao
                 WHERE associado_id = :id AND tipo_vinculo IS NOT NULL'
            );
            $stmt->execute(['id' => $associadoId]);
            $vinculos = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable) {
            return [];
        }

        // Cargos da diretoria executiva e conselho → perfis do módulo associação.
        $mapa = [
            'presidente' => 'diretor',
            'vice-presidente' => 'diretor',
            'diretor' => 'diretor',
            'tesoureiro' => 'tesoureiro',
            'secretario' => 'secretario',
            'conselho_fiscal' => 'diretoria',
        ];

        $papeis = [];
        foreach ($vinculos as $vinculo) {
            $codigo = (string) $vinculo;
            if (isset($mapa[$codigo])) {
                $papeis[] = $mapa[$codigo];
            }
        }

        return array_values(array_unique($papeis));
    }

    /** @return list<string> */
    private static function papeisDe(int $usuarioId): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT DISTINCT p.codigo
             FROM usuario_papel up
             JOIN papeis p ON p.id = up.papel_id
             WHERE up.usuario_id = :id AND up.ativo = 1
             ORDER BY p.codigo'
        );
        $stmt->execute(['id' => $usuarioId]);

        return array_map(static fn(array $r): string => (string) $r['codigo'], $stmt->fetchAll());
    }

    /**
     * @param array<string, mixed> $u
     * @return list<string>
     */
    private static function papeisLegados(array $u): array
    {
        $nivel = (int) ($u['nivel'] ?? 3);
        $tipo = (string) ($u['tipo'] ?? 'usuario');
        $associadoId = (int) ($u['associado_id'] ?? 0);

        if ($tipo === 'admin' || $nivel === 1) {
            return ['admin'];
        }
        if ($tipo === 'gestor' || $nivel === 2) {
            // ATENÇÃO: no legado, o nível 2 é o "Gestor/Fiscal" — na prática, o
            // FISCAL/TÉCNICO que atua na fiscalização e certificação (MAPA,
            // EMATER, prefeituras, certificadoras), e NÃO o gestor da
            // associação. Por isso o mapeamento K1 é gestor_fiscalizacao
            // (perfil que detém o conjunto completo de fiscalizacao.*).
            return ['gestor_fiscalizacao'];
        }
        if ($associadoId > 0) {
            $perfil = strtolower((string) ($u['perfil_solicitado'] ?? ''));
            $tipoUsuario = strtolower((string) ($u['tipo_usuario'] ?? ''));

            return in_array('consumidor', [$perfil, $tipoUsuario], true)
                ? ['consumidor']
                : ['produtor'];
        }

        return [];
    }

    /** @return list<string> */
    private static function permissoesDe(int $usuarioId): array
    {
        $stmt = self::pdo()->prepare(
            'SELECT DISTINCT perm.codigo
             FROM usuario_papel up
             JOIN papel_permissao pp ON pp.papel_id = up.papel_id
             JOIN permissoes perm ON perm.id = pp.permissao_id
             WHERE up.usuario_id = :id AND up.ativo = 1 AND perm.codigo IS NOT NULL'
        );
        $stmt->execute(['id' => $usuarioId]);

        return array_map(static fn(array $r): string => (string) $r['codigo'], $stmt->fetchAll());
    }

    /**
     * Permissões efetivas = banco ∪ permissões padrão do perfil.
     * Perfil admin recebe a lista completa (curinga).
     *
     * @param list<string> $papeis
     * @param list<string> $doBanco
     * @return list<string>
     */
    private static function combinarPermissoes(array $papeis, array $doBanco): array
    {
        if (in_array('admin', $papeis, true)) {
            return ['*'];
        }

        $permissoes = [];
        foreach (self::papeisPermissoes($papeis) as $p) {
            $permissoes[$p] = true;
        }
        foreach ($doBanco as $p) {
            $permissoes[$p] = true;
        }

        return array_keys($permissoes);
    }

    /**
     * @param list<string> $papeis
     * @return list<string>
     */
    private static function papeisPermissoes(array $papeis): array
    {
        $lista = [];
        foreach ($papeis as $papel) {
            foreach (Aplicacoes::permissoesDoPapel($papel) as $p) {
                if ($p !== '*') {
                    $lista[] = $p;
                }
            }
        }

        return array_values(array_unique($lista));
    }

    /** @param list<string> $papeis */
    private static function papelPrincipal(array $papeis): string
    {
        foreach (['admin', 'diretor', 'gestor_associacao', 'tesoureiro', 'secretario', 'gestor_fiscalizacao', 'certificador', 'fiscal_campo', 'diretoria', 'agroindustria', 'produtor'] as $ordem) {
            if (in_array($ordem, $papeis, true)) {
                return $ordem;
            }
        }

        return $papeis[0] ?? 'sem_perfil';
    }
}
