<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Gestão de papéis (usuario_papel) do módulo de associação.
 *
 * Papéis atribuíveis nesta tela: produtor, diretor, tesoureiro, secretário,
 * gestor de associação, diretoria e agroindústria. O papel `admin` e os papéis
 * de fiscalização não são alterados por aqui (continuam sob o administrador).
 */
final class Papeis
{
    /** @var array<string, string> */
    private const DISPONIVEIS = [
        'produtor' => 'Membro associado (produtor) — acessa somente os próprios dados: minha ficha, documentos/carteirinha, mensalidades e certificados.',
        'consumidor' => 'Membro consumidor/cliente — associa-se para apoiar e consumir a produção orgânica, sem unidade de produção. Acessa somente os próprios dados.',
        'diretor' => 'Diretor(a)/Presidente — acesso total ao módulo de associação.',
        'tesoureiro' => 'Tesoureiro(a) — área financeira do módulo de associação.',
        'secretario' => 'Secretário(a) — gestão de documentos do módulo de associação.',
        'gestor_associacao' => 'Gestor de associação — administração do quadro e das adesões.',
        'diretoria' => 'Diretoria — leitura do quadro, financeiro, governança e relatórios.',
        'agroindustria' => 'Agroindústria — acesso ao portal do associado e à produção.',
    ];

    /** @return array<string, array{rotulo: string, descricao: string}> */
    public static function disponiveis(): array
    {
        $lista = [];
        foreach (self::DISPONIVEIS as $codigo => $descricao) {
            $lista[$codigo] = [
                'rotulo' => Aplicacoes::rotuloPapel($codigo),
                'descricao' => $descricao,
            ];
        }

        return $lista;
    }

    /**
     * Usuários do portal associado (contas nível 3 ou já vinculadas a um associado).
     *
     * @return list<array<string, mixed>>
     */
    public static function listarUsuarios(string $busca = ''): array
    {
        $busca = trim($busca);
        $stmt = Identidades::pdo()->prepare(
            "SELECT u.id, u.nome, u.sobrenome, u.email, u.status, u.nivel, u.associado_id,
                    GROUP_CONCAT(DISTINCT p.codigo ORDER BY p.codigo SEPARATOR ',') AS papeis
               FROM usuarios u
               LEFT JOIN usuario_papel up ON up.usuario_id = u.id AND up.ativo = 1
               LEFT JOIN papeis p ON p.id = up.papel_id
              WHERE (u.associado_id IS NOT NULL OR u.nivel = 3)
                AND (:q1 = '' OR CONCAT(u.nome, ' ', u.sobrenome) LIKE :q2 OR u.email LIKE :q2)
              GROUP BY u.id, u.nome, u.sobrenome, u.email, u.status, u.nivel, u.associado_id
              ORDER BY u.nome LIMIT 500"
        );
        $stmt->execute(['q1' => $busca, 'q2' => '%' . $busca . '%']);

        return $stmt->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public static function porUsuario(int $usuarioId): ?array
    {
        $stmt = Identidades::pdo()->prepare(
            'SELECT id, nome, sobrenome, email, status, nivel, associado_id
               FROM usuarios WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $usuarioId]);
        $linha = $stmt->fetch();

        return $linha === false ? null : $linha;
    }

    /** @return list<string> */
    public static function papeisDoUsuario(int $usuarioId): array
    {
        $stmt = Identidades::pdo()->prepare(
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
     * Substitui os papéis atribuíveis do usuário, preservando admin/fiscalização.
     *
     * @param list<string> $codigos
     * @return array{ok: bool, erro: string}
     */
    public static function salvar(int $usuarioId, array $codigos, int $autorId): array
    {
        if (self::porUsuario($usuarioId) === null) {
            return ['ok' => false, 'erro' => 'Usuário não encontrado.'];
        }

        $codigos = array_values(array_unique(array_intersect($codigos, array_keys(self::DISPONIVEIS))));
        if ($codigos === []) {
            return ['ok' => false, 'erro' => 'Selecione pelo menos um papel para o associado.'];
        }

        $pdo = Identidades::pdo();
        $marcadores = implode(',', array_fill(0, count($codigos), '?'));

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id, codigo FROM papeis WHERE codigo IN ({$marcadores})");
            $stmt->execute($codigos);
            $mapa = [];
            foreach ($stmt->fetchAll() as $papel) {
                $mapa[(string) $papel['codigo']] = (int) $papel['id'];
            }

            if (count($mapa) !== count($codigos)) {
                $pdo->rollBack();

                return ['ok' => false, 'erro' => 'Um ou mais papéis não existem no banco.'];
            }

            // Remove apenas os papéis administráveis; admin/fiscal permanecem.
            $todosGerenciaveis = array_keys(self::DISPONIVEIS);
            $marcadoresTodos = implode(',', array_fill(0, count($todosGerenciaveis), '?'));
            $stmt = $pdo->prepare(
                "DELETE FROM usuario_papel
                  WHERE usuario_id = ?
                    AND papel_id IN (SELECT id FROM papeis WHERE codigo IN ({$marcadoresTodos}))
                    AND escopo_tipo = 'global' AND escopo_id = 0"
            );
            $stmt->execute(array_merge([$usuarioId], $todosGerenciaveis));

            $insert = $pdo->prepare(
                "INSERT INTO usuario_papel (usuario_id, papel_id, escopo_tipo, escopo_id, ativo)
                 VALUES (?, ?, 'global', 0, 1)
                 ON DUPLICATE KEY UPDATE ativo = 1"
            );
            foreach ($mapa as $papelId) {
                $insert->execute([$usuarioId, $papelId]);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('SINPORG Papeis::salvar - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível salvar os papéis. Tente novamente.'];
        }

        Auditoria::registrar(
            'PAPEIS_ATUALIZADOS',
            $autorId > 0 ? $autorId : null,
            'usuarios',
            $usuarioId,
            'Papéis definidos: ' . implode(', ', $codigos),
            'assoc'
        );

        return ['ok' => true, 'erro' => ''];
    }

    /** Garante um papel para o usuário (sem duplicar). */
    public static function garantirPapel(int $usuarioId, string $codigo): void
    {
        if ($usuarioId <= 0 || $codigo === '') {
            return;
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                "INSERT INTO usuario_papel (usuario_id, papel_id, escopo_tipo, escopo_id, ativo)
                 SELECT :usuario_id, id, 'global', 0, 1 FROM papeis WHERE codigo = :codigo LIMIT 1
                 ON DUPLICATE KEY UPDATE ativo = 1"
            );
            $stmt->execute(['usuario_id' => $usuarioId, 'codigo' => $codigo]);
        } catch (\Throwable $e) {
            error_log('SINPORG Papeis::garantirPapel - ' . $e->getMessage());
        }
    }

    /** Garante o papel de produtor para um associado aprovado (sem duplicar). */
    public static function garantirProdutor(int $usuarioId): void
    {
        self::garantirPapel($usuarioId, 'produtor');
    }
}
