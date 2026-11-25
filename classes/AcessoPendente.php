<?php
/**
 * AcessoPendente — confirmação de privilégios do pedido de acesso.
 *
 * Fluxo: a pessoa pede acesso pelo formulário público informando o perfil
 * desejado (ex.: "Gestor/Fiscal"). O pedido entra em "usuarios" com
 * status 'pendente', guardando o que foi SOLICITADO:
 *
 *   nivel_solicitado  -> nível pedido (2 para Gestor/Fiscal, 3 para produtor)
 *   perfil_solicitado -> perfil escolhido no formulário (gestor, vegetal, ...)
 *
 * O acesso só é liberado quando um ADMIN confirma (ou ajusta) os privilégios:
 *
 *   $acesso = new AcessoPendente();
 *   $res = $acesso->confirmarPrivilegios($usuarioId, NIVEL_GESTOR, [
 *       'orgao' => '...', 'setor' => '...', 'especialidade' => '...',
 *   ], $adminLogado);
 *
 * O nível CONCEDIDO pode ser igual, menor (rebaixado) ou diferente do
 * solicitado — decisão do Admin — e fica registrado quem confirmou, quando e
 * qual era o pedido original (audit_log: ACESSO_CONFIRMADO / ACESSO_NEGADO).
 *
 * @package Seguranca
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/User.php';
require_once __DIR__ . '/AuditLog.php';

class AcessoPendente
{
    /** Níveis que o Admin pode conceder. */
    public const NIVEIS_VALIDOS = [NIVEL_ADMIN, NIVEL_GESTOR, NIVEL_USUARIO];

    /** Rótulos usados na interface e nas mensagens. */
    public const ROTULOS = [
        NIVEL_ADMIN   => 'Admin',
        NIVEL_GESTOR  => 'Gestor/Fiscal',
        NIVEL_USUARIO => 'Usuário',
    ];

    /** Mapeia o nível para o valor do ENUM usuarios.tipo. */
    private const TIPO_POR_NIVEL = [
        NIVEL_ADMIN   => 'admin',
        NIVEL_GESTOR  => 'gestor',
        NIVEL_USUARIO => 'usuario',
    ];

    /** Campos de vínculo profissional preenchidos na confirmação (Gestor/Fiscal). */
    private const CAMPOS_VINCULO = ['orgao', 'setor', 'especialidade'];

    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /** Rótulo de um nível (ex.: 2 -> "Gestor/Fiscal"). */
    public static function rotulo($nivel): string
    {
        $nivel = (int) $nivel;
        return self::ROTULOS[$nivel] ?? ('Nível ' . $nivel);
    }

    /** Nível solicitado no pedido (compatível com pedidos antigos sem a coluna). */
    public static function nivelSolicitado(array $usuario): int
    {
        $nivel = (int) ($usuario['nivel_solicitado'] ?? 0);
        if ($nivel <= 0) {
            $nivel = (int) ($usuario['nivel'] ?? 0);
        }
        return in_array($nivel, self::NIVEIS_VALIDOS, true) ? $nivel : NIVEL_USUARIO;
    }

    /**
     * Quem está analisando pode conceder este nível?
     * Regra: somente Admin concede qualquer nível; ninguém além dele concede
     * privilégios de equipe (Gestor/Fiscal) ou de Admin.
     */
    public static function podeConceder(?User $quem, $nivel): bool
    {
        $quem = $quem ?: new User();
        if (!$quem->isLoggedIn() || !$quem->hasPermission(NIVEL_ADMIN)) {
            return false;
        }
        return in_array((int) $nivel, self::NIVEIS_VALIDOS, true);
    }

    /**
     * Pedidos de acesso aguardando confirmação do Admin.
     * @return array
     */
    public function solicitacoesPendentes(): array
    {
        try {
            return $this->db->query(
                "SELECT u.id, u.nome, u.sobrenome, u.email, u.telefone, u.tipo_usuario,
                        u.nivel, u.nivel_solicitado, u.perfil_solicitado, u.area_producao,
                        u.tem_agroindustria, u.status, u.created_at,
                        u.orgao, u.setor, u.especialidade
                   FROM usuarios u
                  WHERE u.status = 'pendente'
                  ORDER BY u.created_at ASC, u.id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('AcessoPendente::solicitacoesPendentes - ' . $e->getMessage());
            return [];
        }
    }

    /** Uma solicitação pelo id da conta (null quando não existe). */
    public function solicitacao($usuarioId): ?array
    {
        try {
            $stmt = $this->db->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $usuarioId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Últimas análises de acesso (confirmações e recusas) para o painel.
     * @return array
     */
    public function ultimasAnalises($limit = 10): array
    {
        try {
            $limit = max(1, min(50, (int) $limit));
            return $this->db->query(
                "SELECT u.id, u.nome, u.sobrenome, u.email, u.nivel, u.nivel_solicitado,
                        u.acesso_confirmado_em, u.acesso_negado_motivo,
                        c.nome AS confirmado_por_nome
                   FROM usuarios u
                   LEFT JOIN usuarios c ON c.id = u.acesso_confirmado_por
                  WHERE u.acesso_confirmado_em IS NOT NULL
                  ORDER BY u.acesso_confirmado_em DESC, u.id DESC
                  LIMIT {$limit}"
            )->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * MÉTODO PRINCIPAL — confirma (ou ajusta) os privilégios de um pedido de
     * acesso e libera a conta.
     *
     * @param int   $usuarioId       Conta pendente em "usuarios".
     * @param int   $nivelConcedido  Nível efetivamente autorizado (1, 2 ou 3).
     * @param array $dados           ['orgao' => ?, 'setor' => ?, 'especialidade' => ?]
     * @param User  $admin           Admin que está confirmando.
     *
     * @return array ['success' => bool, 'message' => string, 'solicitado' => int, 'concedido' => int]
     */
    public function confirmarPrivilegios($usuarioId, $nivelConcedido, array $dados = [], ?User $admin = null): array
    {
        $usuarioId = (int) $usuarioId;
        $nivelConcedido = (int) $nivelConcedido;
        $admin = $admin ?: new User();

        if (!self::podeConceder($admin, $nivelConcedido)) {
            return ['success' => false, 'message' => 'Acesso negado: somente o Admin pode confirmar os privilégios solicitados.', 'solicitado' => 0, 'concedido' => 0];
        }

        $usuario = $this->solicitacao($usuarioId);
        if (!$usuario) {
            return ['success' => false, 'message' => 'Solicitação não encontrada.', 'solicitado' => 0, 'concedido' => 0];
        }
        if (($usuario['status'] ?? '') !== 'pendente') {
            return [
                'success' => false,
                'message' => 'Esta solicitação já foi analisada (status atual: ' . $usuario['status'] . ').',
                'solicitado' => self::nivelSolicitado($usuario),
                'concedido' => (int) $usuario['nivel'],
            ];
        }

        $solicitado = self::nivelSolicitado($usuario);
        $adminId = (int) ($_SESSION['user_id'] ?? 0);
        if ($adminId <= 0) {
            $adminId = (int) ($admin->getCurrentUser()['id'] ?? 0);
        }

        // Limites de segurança no texto livre do vínculo profissional
        $vinculo = [];
        foreach (self::CAMPOS_VINCULO as $campo) {
            $valor = isset($dados[$campo]) && is_string($dados[$campo]) ? trim($dados[$campo]) : '';
            $vinculo[$campo] = $valor === '' ? null : mb_substr($valor, 0, 100);
        }

        try {
            $sets = [
                "status = 'ativo'",
                'nivel = :nivel',
                'tipo = :tipo',
                'nivel_solicitado = :nivel_solicitado',
                'acesso_confirmado_por = :admin',
                'acesso_confirmado_em = NOW()',
                'acesso_negado_motivo = NULL',
                'orgao = :orgao',
                'setor = :setor',
                'especialidade = :especialidade',
            ];
            $params = [
                ':nivel' => $nivelConcedido,
                ':tipo' => self::TIPO_POR_NIVEL[$nivelConcedido] ?? 'usuario',
                ':nivel_solicitado' => $solicitado,
                ':admin' => $adminId ?: null,
                ':orgao' => $vinculo['orgao'],
                ':setor' => $vinculo['setor'],
                ':especialidade' => $vinculo['especialidade'],
                ':id' => $usuarioId,
            ];
            $stmt = $this->db->prepare("UPDATE usuarios SET " . implode(', ', $sets) . " WHERE id = :id AND status = 'pendente'");
            $stmt->execute($params);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Nada foi alterado: a solicitação pode ter sido analisada em outra aba.', 'solicitado' => $solicitado, 'concedido' => $nivelConcedido];
            }

            $rotuloSolicitado = self::rotulo($solicitado);
            $rotuloConcedido = self::rotulo($nivelConcedido);
            $detalhe = "solicitado={$rotuloSolicitado}({$solicitado}) concedido={$rotuloConcedido}({$nivelConcedido})"
                . ' perfil_solicitado=' . (string) ($usuario['perfil_solicitado'] ?? '—')
                . " conta={$usuario['email']}";

            AuditLog::registrar('ACESSO_CONFIRMADO', 'usuarios', $usuarioId, $detalhe);

            // ATENÇÃO à semântica do projeto: níveis MENORES têm MAIS poder
            // (1 = Admin, 2 = Gestor/Fiscal, 3 = Usuário). Por isso conceder um
            // número MAIOR que o solicitado significa REDUZIR privilégios.
            if ($nivelConcedido === $solicitado) {
                $mensagem = "Acesso liberado como {$rotuloConcedido} (privilégios solicitados confirmados).";
            } elseif ($nivelConcedido > $solicitado) {
                $mensagem = "Acesso liberado como {$rotuloConcedido} — o pedido de {$rotuloSolicitado} NÃO foi integralmente concedido (privilégios reduzidos).";
            } else {
                $mensagem = "Acesso liberado como {$rotuloConcedido} (privilégio superior ao solicitado: era {$rotuloSolicitado}).";
            }

            return ['success' => true, 'message' => $mensagem, 'solicitado' => $solicitado, 'concedido' => $nivelConcedido];
        } catch (PDOException $e) {
            error_log('AcessoPendente::confirmarPrivilegios - ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao confirmar os privilégios. Tente novamente.', 'solicitado' => $solicitado, 'concedido' => 0];
        }
    }

    /**
     * Nega o pedido de acesso (mantém a conta inativa e registra o motivo).
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function negarSolicitacao($usuarioId, $motivo, ?User $admin = null): array
    {
        $usuarioId = (int) $usuarioId;
        $admin = $admin ?: new User();
        $motivo = trim((string) $motivo);

        if (!$admin->isLoggedIn() || !$admin->hasPermission(NIVEL_ADMIN)) {
            return ['success' => false, 'message' => 'Acesso negado: somente o Admin pode analisar solicitações de acesso.'];
        }
        if ($motivo === '') {
            return ['success' => false, 'message' => 'Informe o motivo da recusa.'];
        }

        $usuario = $this->solicitacao($usuarioId);
        if (!$usuario) {
            return ['success' => false, 'message' => 'Solicitação não encontrada.'];
        }
        if (($usuario['status'] ?? '') !== 'pendente') {
            return ['success' => false, 'message' => 'Esta solicitação já foi analisada.'];
        }

        $adminId = (int) ($_SESSION['user_id'] ?? 0);
        try {
            $stmt = $this->db->prepare(
                "UPDATE usuarios
                    SET status = 'inativo',
                        acesso_negado_motivo = :motivo,
                        acesso_confirmado_por = :admin,
                        acesso_confirmado_em = NOW()
                  WHERE id = :id AND status = 'pendente'"
            );
            $stmt->execute([
                ':motivo' => mb_substr($motivo, 0, 255),
                ':admin' => $adminId ?: null,
                ':id' => $usuarioId,
            ]);
            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Nada foi alterado: a solicitação pode ter sido analisada em outra aba.'];
            }

            AuditLog::registrar('ACESSO_NEGADO', 'usuarios', $usuarioId, 'motivo=' . mb_substr($motivo, 0, 180) . ' conta=' . $usuario['email']);

            return ['success' => true, 'message' => 'Solicitação recusada. O interessado verá o motivo ao tentar acessar.'];
        } catch (PDOException $e) {
            error_log('AcessoPendente::negarSolicitacao - ' . $e->getMessage());
            return ['success' => false, 'message' => 'Erro ao recusar a solicitação.'];
        }
    }
}
