<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Aprovações do primeiro acesso.
 *
 * Espelha a lógica do sistema atual (classes/AcessoPendente.php e
 * classes/FichaAdesao.php), com a mesma sequência e os mesmos efeitos:
 *
 *  1. LIBERAÇÃO DO ACESSO — conta criada no pré-cadastro (`usuarios.status`
 *     'pendente') aguarda confirmação de privilégios: nível concedido, tipo,
 *     órgão/setor/especialidade, `acesso_confirmado_por/em` e auditoria
 *     ACESSO_CONFIRMADO / ACESSO_NEGADO.
 *  2. FICHA DE ADESÃO — produzida pelo produtor; aprovação da Diretoria cria o
 *     cadastro em `associados`, gera número/data e vincula a conta.
 *
 * Diferença deliberada em relação ao legado: a autorização para liberar o
 * acesso passou do "somente Admin" (AcessoPendente::podeConceder) para a
 * permissão `associados.approve` — diretor/presidente, conforme a regra de
 * negócio da associação. Os efeitos no banco e a auditoria são os mesmos.
 */
final class Pendencias
{
    /** Mapa nível → ENUM usuarios.tipo (igual ao legado). */
    private const TIPO_POR_NIVEL = [
        1 => 'admin',
        2 => 'gestor',
        3 => 'usuario',
    ];

    private const ROTULOS = [
        1 => 'Admin',
        2 => 'Fiscal/Técnico',
        3 => 'Usuário',
    ];

    /**
     * Contas aguardando liberação (pré-cadastro).
     *
     * @return list<array<string, mixed>>
     */
    public static function contas(): array
    {
        return Identidades::pdo()->query(
            "SELECT u.id, u.nome, u.sobrenome, u.email, u.telefone, u.tipo_usuario,
                    u.nivel, u.nivel_solicitado, u.perfil_solicitado, u.area_producao,
                    u.tem_agroindustria, u.status, u.created_at,
                    u.orgao, u.setor, u.especialidade
               FROM usuarios u
              WHERE u.status = 'pendente'
              ORDER BY u.created_at ASC, u.id ASC"
        )->fetchAll();
    }

    /**
     * Fichas de adesão aguardando avaliação (mesma consulta do sistema atual).
     *
     * @return list<array<string, mixed>>
     */
    public static function fichas(): array
    {
        self::carregarFichaAdesao();

        return (new \FichaAdesao())->listarPendentes();
    }

    /**
     * Libera o acesso de uma conta pendente.
     *
     * @param array{orgao?: string, setor?: string, especialidade?: string} $vinculo
     * @return array{success: bool, message: string}
     */
    public static function liberarConta(int $usuarioId, int $nivelConcedido, array $vinculo = []): array
    {
        if (!in_array($nivelConcedido, [1, 2, 3], true)) {
            return ['success' => false, 'message' => 'Selecione um nível válido para liberar o acesso.'];
        }

        $pdo = Identidades::pdo();
        $conta = self::conta($usuarioId);
        if ($conta === null) {
            return ['success' => false, 'message' => 'Solicitação não encontrada.'];
        }
        if ((string) ($conta['status'] ?? '') !== 'pendente') {
            return ['success' => false, 'message' => 'Esta solicitação já foi analisada (status: ' . $conta['status'] . ').'];
        }

        $solicitado = self::nivelSolicitado($conta);
        $adminId = (int) ($_SESSION['user_id'] ?? 0);

        $vinculoNormalizado = [];
        foreach (['orgao', 'setor', 'especialidade'] as $campo) {
            $valor = isset($vinculo[$campo]) && is_string($vinculo[$campo]) ? trim($vinculo[$campo]) : '';
            $vinculoNormalizado[$campo] = $valor === '' ? null : mb_substr($valor, 0, 100);
        }

        try {
            $stmt = $pdo->prepare(
                "UPDATE usuarios SET
                    status = 'ativo',
                    nivel = :nivel,
                    tipo = :tipo,
                    nivel_solicitado = :nivel_solicitado,
                    acesso_confirmado_por = :admin,
                    acesso_confirmado_em = NOW(),
                    acesso_negado_motivo = NULL,
                    orgao = :orgao,
                    setor = :setor,
                    especialidade = :especialidade
                 WHERE id = :id AND status = 'pendente'"
            );
            $stmt->execute([
                'nivel' => $nivelConcedido,
                'tipo' => self::TIPO_POR_NIVEL[$nivelConcedido],
                'nivel_solicitado' => $solicitado,
                'admin' => $adminId > 0 ? $adminId : null,
                'orgao' => $vinculoNormalizado['orgao'],
                'setor' => $vinculoNormalizado['setor'],
                'especialidade' => $vinculoNormalizado['especialidade'],
                'id' => $usuarioId,
            ]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Nada foi alterado: a solicitação pode ter sido analisada em outra aba.'];
            }
        } catch (\Throwable $e) {
            error_log('SINPORG liberarConta: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao liberar o acesso. Tente novamente.'];
        }

        $detalhe = 'solicitado=' . self::ROTULOS[$solicitado] . '(' . $solicitado . ')'
            . ' concedido=' . self::ROTULOS[$nivelConcedido] . '(' . $nivelConcedido . ')'
            . ' perfil_solicitado=' . (string) ($conta['perfil_solicitado'] ?? '—')
            . ' conta=' . (string) ($conta['email'] ?? '');

        Auditoria::registrar('ACESSO_CONFIRMADO', $adminId > 0 ? $adminId : null, 'usuarios', $usuarioId, $detalhe);

        if ($nivelConcedido === $solicitado) {
            $mensagem = 'Acesso liberado como ' . self::ROTULOS[$nivelConcedido] . ' (privilégios solicitados confirmados).';
        } elseif ($nivelConcedido > $solicitado) {
            $mensagem = 'Acesso liberado como ' . self::ROTULOS[$nivelConcedido]
                . ' — o pedido de ' . self::ROTULOS[$solicitado] . ' não foi integralmente concedido (privilégios reduzidos).';
        } else {
            $mensagem = 'Acesso liberado como ' . self::ROTULOS[$nivelConcedido]
                . ' (privilégio superior ao solicitado: era ' . self::ROTULOS[$solicitado] . ').';
        }

        return ['success' => true, 'message' => $mensagem];
    }

    /** @return array{success: bool, message: string} */
    public static function negarConta(int $usuarioId, string $motivo): array
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            return ['success' => false, 'message' => 'Informe o motivo da recusa.'];
        }

        $conta = self::conta($usuarioId);
        if ($conta === null || (string) ($conta['status'] ?? '') !== 'pendente') {
            return ['success' => false, 'message' => 'Solicitação não encontrada ou já analisada.'];
        }

        $adminId = (int) ($_SESSION['user_id'] ?? 0);

        try {
            $stmt = Identidades::pdo()->prepare(
                "UPDATE usuarios SET
                    status = 'inativo',
                    acesso_confirmado_por = :admin,
                    acesso_confirmado_em = NOW(),
                    acesso_negado_motivo = :motivo
                 WHERE id = :id AND status = 'pendente'"
            );
            $stmt->execute([
                'admin' => $adminId > 0 ? $adminId : null,
                'motivo' => mb_substr($motivo, 0, 255),
                'id' => $usuarioId,
            ]);
        } catch (\Throwable $e) {
            error_log('SINPORG negarConta: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao recusar a solicitação. Tente novamente.'];
        }

        Auditoria::registrar(
            'ACESSO_NEGADO',
            $adminId > 0 ? $adminId : null,
            'usuarios',
            $usuarioId,
            'motivo=' . mb_substr($motivo, 0, 200) . ' conta=' . (string) ($conta['email'] ?? '')
        );

        return ['success' => true, 'message' => 'Solicitação recusada. A conta permanece inativa com o motivo registrado.'];
    }

    /** @return array{success: bool, message: string} */
    public static function aprovarFicha(int $fichaId, int $analisadoPor, bool $confirmouCompleta = false): array
    {
        self::carregarFichaAdesao();

        // O analista precisa conferir a ficha e declarar que ela está completa.
        if (!$confirmouCompleta) {
            return [
                'success' => false,
                'message' => 'Confirme que conferiu a ficha e que ela está completa antes de aprovar.',
            ];
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                "SELECT f.*,
                        COALESCE(NULLIF(f.email, ''), u.email, a.email) AS email,
                        COALESCE(NULLIF(f.telefone_whatsapp, ''), u.telefone, a.telefone) AS telefone_whatsapp
                   FROM ficha_adesao f
                   LEFT JOIN usuarios u ON u.id = f.usuario_id
                   LEFT JOIN associados a ON a.id = f.associado_id
                  WHERE f.id = :id LIMIT 1"
            );
            $stmt->execute(['id' => $fichaId]);
            $ficha = $stmt->fetch() ?: null;
        } catch (\Throwable $e) {
            error_log('SINPORG Pendencias::aprovarFicha conferência - ' . $e->getMessage());
            $ficha = null;
        }

        if (!is_array($ficha)) {
            return ['success' => false, 'message' => 'Ficha não encontrada.'];
        }

        $completude = Onboarding::completudeFicha($ficha);
        if (!$completude['ok']) {
            return [
                'success' => false,
                'message' => 'A ficha está incompleta e não pode ser aprovada. Campos pendentes: '
                    . implode(', ', $completude['faltando']) . '.',
            ];
        }

        $resultado = (new \FichaAdesao())->aprovar($fichaId, $analisadoPor);

        if ($resultado['success']) {
            try {
                $stmt = Identidades::pdo()->prepare('SELECT usuario_id FROM ficha_adesao WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $fichaId]);
                $usuarioId = (int) $stmt->fetchColumn();

                if ($usuarioId > 0) {
                    // Conta recém-aprovada é do nível 3 (produtor/consumidor), caso ainda não tenha nível.
                    Identidades::pdo()
                        ->prepare('UPDATE usuarios SET nivel = 3 WHERE id = :id AND (nivel IS NULL OR nivel = 0)')
                        ->execute(['id' => $usuarioId]);

                    // A ficha define o tipo de associado: consumidor/cliente ou produtor.
                    $stmt = Identidades::pdo()->prepare(
                        'SELECT f.tipo_associado, u.perfil_solicitado, u.tipo_usuario
                           FROM ficha_adesao f
                           JOIN usuarios u ON u.id = f.usuario_id
                          WHERE f.id = :id LIMIT 1'
                    );
                    $stmt->execute(['id' => $fichaId]);
                    $dadosFicha = $stmt->fetch() ?: [];

                    $ehConsumidor = in_array('consumidor', [
                        strtolower((string) ($dadosFicha['tipo_associado'] ?? '')),
                        strtolower((string) ($dadosFicha['perfil_solicitado'] ?? '')),
                        strtolower((string) ($dadosFicha['tipo_usuario'] ?? '')),
                    ], true);

                    Papeis::garantirPapel($usuarioId, $ehConsumidor ? 'consumidor' : 'produtor');
                }
            } catch (\Throwable $e) {
                error_log('SINPORG Pendencias::aprovarFicha papel - ' . $e->getMessage());
            }
        }

        return $resultado;
    }

    /** @return array{success: bool, message: string} */
    public static function recusarFicha(int $fichaId, int $analisadoPor, string $motivo): array
    {
        self::carregarFichaAdesao();

        return (new \FichaAdesao())->recusar($fichaId, $analisadoPor, $motivo);
    }

    /** Nível efetivamente solicitado no pré-cadastro.
     *
     * @param array<string, mixed> $conta
     */
    public static function nivelSolicitado(array $conta): int
    {
        $nivel = (int) ($conta['nivel_solicitado'] ?? 0);
        if ($nivel <= 0) {
            $nivel = (int) ($conta['nivel'] ?? 0);
        }

        return in_array($nivel, [1, 2, 3], true) ? $nivel : 3;
    }

    public static function rotuloNivel(int $nivel): string
    {
        return self::ROTULOS[$nivel] ?? ('Nível ' . $nivel);
    }

    /** @return array<string, mixed>|null */
    private static function conta(int $usuarioId): ?array
    {
        $stmt = Identidades::pdo()->prepare('SELECT * FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $usuarioId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    private static function carregarFichaAdesao(): void
    {
        if (class_exists('FichaAdesao')) {
            return;
        }

        $raiz = dirname(__DIR__, 2);
        require_once $raiz . '/classes/Database.php';
        require_once $raiz . '/classes/User.php';
        require_once $raiz . '/classes/FichaAdesao.php';
    }
}
