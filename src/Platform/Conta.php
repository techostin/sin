<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Dados e preferências da própria conta (Meu perfil / Configurações).
 *
 * Mesmos campos e regras de `modulos/associados/meu_perfil.php` e
 * `modulos/usuarios/configuracoes.php` do sistema atual, usando a sessão
 * isolada de cada sistema novo.
 */
final class Conta
{
    /**
     * @param array<string, mixed> $identidade
     * @return array<string, mixed>
     */
    public static function dados(array $identidade): array
    {
        $tabela = self::tabela($identidade);
        $stmt = Identidades::pdo()->prepare('SELECT * FROM ' . $tabela . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) ($identidade['id'] ?? 0)]);
        $linha = $stmt->fetch();

        return is_array($linha) ? self::resolverLocalidade($linha) : [];
    }

    /**
     * O legado grava `cidade`/`estado` como IDs das tabelas `cidades`/`estados`
     * em parte das contas (ex.: "3908"/"23"). O formulário novo precisa exibir
     * os nomes/UF, não os IDs.
     *
     * @param array<string, mixed> $linha
     * @return array<string, mixed>
     */
    public static function resolverLocalidade(array $linha): array
    {
        $cidade = trim((string) ($linha['cidade'] ?? ''));
        $estado = trim((string) ($linha['estado'] ?? ''));
        if ($cidade === '' && $estado === '') {
            return $linha;
        }

        $pdo = Identidades::pdo();
        $cidadeId = ctype_digit($cidade) ? (int) $cidade : 0;
        $estadoId = ctype_digit($estado) ? (int) $estado : 0;

        if ($cidadeId > 0) {
            try {
                $stmt = $pdo->prepare('SELECT nome, estado FROM cidades WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $cidadeId]);
                $cidadeLinha = $stmt->fetch();

                if (is_array($cidadeLinha)) {
                    $linha['cidade'] = (string) $cidadeLinha['nome'];
                    if ($estadoId <= 0 && (int) ($cidadeLinha['estado'] ?? 0) > 0) {
                        $estadoId = (int) $cidadeLinha['estado'];
                    }
                }
            } catch (\Throwable $e) {
                error_log('SINPORG Conta::resolverLocalidade cidade - ' . $e->getMessage());
            }
        }

        if ($estadoId > 0) {
            try {
                $stmt = $pdo->prepare('SELECT uf FROM estados WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $estadoId]);
                $uf = $stmt->fetchColumn();

                if (is_string($uf) && $uf !== '') {
                    $linha['estado'] = $uf;
                }
            } catch (\Throwable $e) {
                error_log('SINPORG Conta::resolverLocalidade estado - ' . $e->getMessage());
            }
        }

        return $linha;
    }

    /**
     * Atualiza nome/contato/endereço/documentos do próprio cadastro.
     *
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $post
     * @return array{ok: bool, erro: string}
     */
    public static function atualizarPerfil(array $identidade, array $post): array
    {
        $tabela = self::tabela($identidade);
        $id = (int) ($identidade['id'] ?? 0);

        $nome = trim((string) ($post['nome'] ?? ''));
        $email = mb_strtolower(trim((string) ($post['email'] ?? '')));
        $cpf = preg_replace('/\D+/', '', (string) ($post['cpf'] ?? '')) ?? '';
        $cep = preg_replace('/\D+/', '', (string) ($post['cep'] ?? '')) ?? '';

        if ($nome === '') {
            return ['ok' => false, 'erro' => 'O nome é obrigatório.'];
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return ['ok' => false, 'erro' => 'Informe um e-mail válido.'];
        }
        if ($cpf !== '' && strlen($cpf) !== 11) {
            return ['ok' => false, 'erro' => 'O CPF deve ter 11 dígitos.'];
        }
        if ($cep !== '' && strlen($cep) !== 8) {
            return ['ok' => false, 'erro' => 'O CEP deve ter 8 dígitos.'];
        }
        $dataNascimento = trim((string) ($post['data_nascimento'] ?? ''));
        if ($dataNascimento !== '' && strtotime($dataNascimento) === false) {
            return ['ok' => false, 'erro' => 'Data de nascimento inválida.'];
        }

        $stmt = Identidades::pdo()->prepare('SELECT id FROM ' . $tabela . ' WHERE email = :email AND id <> :id LIMIT 1');
        $stmt->execute(['email' => $email, 'id' => $id]);
        if ($stmt->fetch() !== false) {
            return ['ok' => false, 'erro' => 'Este e-mail já está em uso por outra conta.'];
        }

        $dados = [
            'nome' => $nome,
            'sobrenome' => trim((string) ($post['sobrenome'] ?? '')),
            'email' => $email,
            'telefone' => trim((string) ($post['telefone'] ?? '')),
            'cpf' => $cpf !== '' ? $cpf : null,
            'data_nascimento' => $dataNascimento !== '' ? $dataNascimento : null,
            'endereco' => trim((string) ($post['endereco'] ?? '')),
            'numero' => trim((string) ($post['numero'] ?? '')),
            'complemento' => trim((string) ($post['complemento'] ?? '')),
            'bairro' => trim((string) ($post['bairro'] ?? '')),
            'cidade' => trim((string) ($post['cidade'] ?? '')),
            'estado' => trim((string) ($post['estado'] ?? '')),
            'cep' => $cep !== '' ? $cep : null,
            'orgao' => trim((string) ($post['orgao'] ?? '')),
            'setor' => trim((string) ($post['setor'] ?? '')),
            'especialidade' => trim((string) ($post['especialidade'] ?? '')),
            'nicho' => trim((string) ($post['nicho'] ?? '')),
        ];

        $sets = [];
        foreach (array_keys($dados) as $campo) {
            $sets[] = $campo . ' = :' . $campo;
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                'UPDATE ' . $tabela . ' SET ' . implode(', ', $sets) . ' WHERE id = :id'
            );
            $stmt->execute($dados + ['id' => $id]);
        } catch (\Throwable $e) {
            error_log('SINPORG Conta::atualizarPerfil - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível salvar os dados. Tente novamente.'];
        }

        return ['ok' => true, 'erro' => ''];
    }

    /**
     * Preferências de interface e notificações.
     *
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $post
     * @return array{ok: bool, erro: string}
     */
    public static function salvarPreferencias(array $identidade, array $post): array
    {
        $tema = in_array(($post['tema'] ?? ''), ['light', 'dark', 'auto'], true) ? (string) $post['tema'] : 'light';
        $idioma = in_array(($post['idioma'] ?? ''), ['pt-br', 'en', 'es'], true) ? (string) $post['idioma'] : 'pt-br';

        $_SESSION['user_config'] = [
            'tema' => $tema,
            'idioma' => $idioma,
            'notificacoes' => isset($post['notificacoes']) ? 1 : 0,
        ];

        return ['ok' => true, 'erro' => ''];
    }

    /**
     * Notificações de eventos (persistidas no cadastro atual).
     *
     * @param array<string, mixed> $identidade
     * @param array<string, mixed> $post
     * @return array{ok: bool, erro: string}
     */
    public static function salvarNotificacoes(array $identidade, array $post): array
    {
        $tabela = self::tabela($identidade);
        $ativas = isset($post['notificacoes_ativas']) ? 1 : 0;
        $dias = (int) ($post['dias_antecedencia_evento'] ?? 7);
        if (!in_array($dias, [0, 1, 3, 7, 15, 30], true)) {
            $dias = 7;
        }

        try {
            $stmt = Identidades::pdo()->prepare(
                'UPDATE ' . $tabela . ' SET notificacoes_ativas = :ativas,
                        dias_antecedencia_evento = :dias WHERE id = :id'
            );
            $stmt->execute(['ativas' => $ativas, 'dias' => $dias, 'id' => (int) ($identidade['id'] ?? 0)]);
        } catch (\Throwable $e) {
            error_log('SINPORG Conta::salvarNotificacoes - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível salvar as notificações.'];
        }

        $_SESSION['user_config']['notif_eventos_hoje'] = isset($post['notif_eventos_hoje']) ? 1 : 0;
        $_SESSION['user_config']['notif_eventos_proximos'] = isset($post['notif_eventos_proximos']) ? 1 : 0;
        $_SESSION['user_config']['notif_eventos_atrasados'] = isset($post['notif_eventos_atrasados']) ? 1 : 0;

        return ['ok' => true, 'erro' => ''];
    }

    /**
     * Troca de senha com conferência da senha atual.
     *
     * @param array<string, mixed> $identidade
     * @return array{ok: bool, erro: string}
     */
    public static function alterarSenha(array $identidade, string $atual, string $nova, string $confirma): array
    {
        if (strlen($nova) < 6) {
            return ['ok' => false, 'erro' => 'A nova senha deve ter pelo menos 6 caracteres.'];
        }
        if ($nova !== $confirma) {
            return ['ok' => false, 'erro' => 'A confirmação não confere com a nova senha.'];
        }

        $tabela = self::tabela($identidade);
        $stmt = Identidades::pdo()->prepare('SELECT senha FROM ' . $tabela . ' WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) ($identidade['id'] ?? 0)]);
        $hash = (string) $stmt->fetchColumn();

        if ($hash === '' || !password_verify($atual, $hash)) {
            return ['ok' => false, 'erro' => 'A senha atual está incorreta.'];
        }

        try {
            $stmt = Identidades::pdo()->prepare('UPDATE ' . $tabela . ' SET senha = :senha WHERE id = :id');
            $stmt->execute(['senha' => password_hash($nova, PASSWORD_DEFAULT), 'id' => (int) ($identidade['id'] ?? 0)]);
        } catch (\Throwable $e) {
            error_log('SINPORG Conta::alterarSenha - ' . $e->getMessage());

            return ['ok' => false, 'erro' => 'Não foi possível alterar a senha.'];
        }

        return ['ok' => true, 'erro' => ''];
    }

    /** @return array<string, mixed> */
    public static function preferencias(): array
    {
        $config = $_SESSION['user_config'] ?? [];

        return [
            'tema' => is_array($config) ? (string) ($config['tema'] ?? 'light') : 'light',
            'idioma' => is_array($config) ? (string) ($config['idioma'] ?? 'pt-br') : 'pt-br',
            'notificacoes' => is_array($config) ? (int) ($config['notificacoes'] ?? 1) : 1,
            'notif_eventos_hoje' => is_array($config) ? (int) ($config['notif_eventos_hoje'] ?? 1) : 1,
            'notif_eventos_proximos' => is_array($config) ? (int) ($config['notif_eventos_proximos'] ?? 1) : 1,
            'notif_eventos_atrasados' => is_array($config) ? (int) ($config['notif_eventos_atrasados'] ?? 1) : 1,
        ];
    }

    /** @param array<string, mixed> $identidade */
    private static function tabela(array $identidade): string
    {
        return ($identidade['origem'] ?? '') === 'associados' ? 'associados' : 'usuarios';
    }
}
