<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Regras do PRIMEIRO ACESSO (mesma lógica do sistema atual).
 *
 * Depois de validar as credenciais, o login verifica o que falta para a conta
 * ficar utilizável, nesta ordem:
 *
 *  1. `vinculo` — Gestor/Fiscal (nível 2) sem órgão ou especialidade:
 *     informa órgão, cargo/especialidade e setor (etapa 2 do login legado).
 *  2. `perfil`  — conta sem telefone e sem CPF: completa os dados essenciais
 *     (o legado redireciona para /meu-perfil).
 *  3. `ficha`   — conta de produtor (nível 3) ainda sem cadastro de associado:
 *     precisa preencher a FICHA DE ADESÃO (o legado abre o modal da ficha);
 *     a aprovação continua sendo da Diretoria, na mesma tabela `ficha_adesao`.
 */
final class Onboarding
{
    /**
     * Pendências do primeiro acesso, na MESMA ordem do sistema atual
     * (login.php):
     *
     *  1. `vinculo` — Gestor/Fiscal (nível 2) sem órgão, setor e especialidade
     *     (equivalente a hasPerfilCompleto() do legado);
     *  2. `ficha`   — conta nível 3 sem cadastro de associado: o legado redireciona
     *     para o dashboard onde o modal da ficha é exibido (a ficha vem ANTES de
     *     completar o perfil);
     *  3. `perfil`  — telefone e CPF vazios (isPrimeiroAcesso do legado → /meu-perfil),
     *     apenas quando não há ficha pendente.
     *
     * @param array<string, mixed> $identidade
     * @return list<string>
     */
    public static function pendencias(array $identidade): array
    {
        if (($identidade['origem'] ?? '') !== 'usuarios') {
            return [];
        }

        $nivel = (int) ($identidade['nivel'] ?? 3);

        if ($nivel === 2 && self::precisaVinculo($identidade)) {
            return ['vinculo'];
        }

        if ($nivel === 3 && (int) ($identidade['associado_id'] ?? 0) === 0) {
            return ['ficha'];
        }

        if ($nivel !== 1
            && trim((string) ($identidade['telefone'] ?? '')) === ''
            && trim((string) ($identidade['cpf'] ?? '')) === '') {
            return ['perfil'];
        }

        return [];
    }

    /** @param array<string, mixed> $identidade */
    public static function precisaFicha(array $identidade): bool
    {
        return (($identidade['origem'] ?? '') === 'usuarios')
            && (int) ($identidade['nivel'] ?? 0) === 3
            && (int) ($identidade['associado_id'] ?? 0) === 0;
    }

    /**
     * Confere se a ficha tem os campos mínimos para ser aprovada pela Diretoria.
     *
     * @param array<string, mixed> $ficha
     * @return array{ok: bool, faltando: list<string>}
     */
    public static function completudeFicha(array $ficha): array
    {
        $obrigatorios = [
            'tipo_associado' => 'Tipo de associado',
            'nome_completo' => 'Nome completo',
            'data_nascimento' => 'Data de nascimento',
            'cpf' => 'CPF',
            'municipio' => 'Município',
            'estado' => 'UF',
            'telefone_whatsapp' => 'Telefone/WhatsApp',
            'email' => 'E-mail',
            'aceite_termos' => 'Aceite dos termos',
        ];

        $faltando = [];
        foreach ($obrigatorios as $campo => $rotulo) {
            $valor = $ficha[$campo] ?? null;
            $vazio = $campo === 'aceite_termos'
                ? (int) $valor !== 1
                : trim((string) $valor) === '';

            if ($vazio) {
                $faltando[] = $rotulo;
            }
        }

        return ['ok' => $faltando === [], 'faltando' => $faltando];
    }

    /** @param array<string, mixed> $identidade */
    public static function precisaVinculo(array $identidade): bool
    {
        // Equivalente a User::hasPerfilCompleto(): nível 2 precisa dos três
        // campos de vínculo profissional preenchidos.
        return (($identidade['origem'] ?? '') === 'usuarios')
            && (int) ($identidade['nivel'] ?? 0) === 2
            && (trim((string) ($identidade['orgao'] ?? '')) === ''
                || trim((string) ($identidade['setor'] ?? '')) === ''
                || trim((string) ($identidade['especialidade'] ?? '')) === '');
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, erro: ?string}
     */
    public static function salvarVinculo(int $usuarioId, array $post): array
    {
        $orgao = trim((string) ($post['orgao'] ?? ''));
        $especialidade = trim((string) ($post['especialidade'] ?? ''));
        $setor = trim((string) ($post['setor'] ?? ''));

        if ($orgao === '') {
            return ['ok' => false, 'erro' => 'Informe o órgão.'];
        }
        if ($especialidade === '') {
            return ['ok' => false, 'erro' => 'Informe o cargo/especialidade.'];
        }
        if ($setor === '') {
            return ['ok' => false, 'erro' => 'Informe o setor.'];
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                'UPDATE usuarios SET orgao = :orgao, especialidade = :especialidade, setor = :setor WHERE id = :id'
            );
            $stmt->execute([
                'orgao' => $orgao,
                'especialidade' => $especialidade,
                'setor' => $setor,
                'id' => $usuarioId,
            ]);
        } catch (\Throwable $e) {
            error_log('SINPORG onboarding vínculo: ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível salvar seus dados. Tente novamente.'];
        }

        Auditoria::registrar('VINCULO_INFORMADO', null, 'usuarios', $usuarioId, 'primeiro acesso');
        return ['ok' => true, 'erro' => null];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, erro: ?string}
     */
    public static function salvarPerfil(int $usuarioId, array $post): array
    {
        $telefone = preg_replace('/\D+/', '', (string) ($post['telefone'] ?? '')) ?? '';
        $cpf = preg_replace('/\D+/', '', (string) ($post['cpf'] ?? '')) ?? '';

        if ($telefone === '') {
            return ['ok' => false, 'erro' => 'Informe um telefone para contato.'];
        }
        if (strlen($cpf) !== 11) {
            return ['ok' => false, 'erro' => 'Informe um CPF válido (11 dígitos).'];
        }

        try {
            $stmt = Identidades::pdo()->prepare('UPDATE usuarios SET telefone = :telefone, cpf = :cpf WHERE id = :id');
            $stmt->execute(['telefone' => $telefone, 'cpf' => $cpf, 'id' => $usuarioId]);
        } catch (\Throwable $e) {
            error_log('SINPORG onboarding perfil: ' . $e->getMessage());
            return ['ok' => false, 'erro' => 'Não foi possível salvar seus dados. Tente novamente.'];
        }

        Auditoria::registrar('PERFIL_COMPLETADO', null, 'usuarios', $usuarioId, 'primeiro acesso');
        return ['ok' => true, 'erro' => null];
    }

    /**
     * Status da ficha de adesão da conta (reaproveita a classe legada).
     *
     * @return array{status: string, ficha: array<string, mixed>|null, produtos: list<array<string, mixed>>}
     */
    public static function statusFicha(int $usuarioId): array
    {
        self::carregarLegado();

        return (new \FichaAdesao())->statusDoUsuario($usuarioId);
    }

    /**
     * Salva/submete a ficha de adesão usando a MESMA classe do sistema atual.
     *
     * @param array<string, mixed> $post
     * @return array{success: bool, message: string, ficha_id: int|null}
     */
    public static function salvarFicha(int $usuarioId, array $post, bool $submeter = true): array
    {
        self::carregarLegado();

        return (new \FichaAdesao())->salvarPorUsuario($usuarioId, $post, $submeter);
    }

    private static function carregarLegado(): void
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
