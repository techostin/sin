<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Pré-cadastro (aba "Primeiro Acesso" / "Perfil de Acesso" do login).
 *
 * Mesma lógica do sistema atual (api/pre_cadastro.php):
 *  - o usuário informa em qual situação se classifica
 *    (vegetal, animal, agroindústria, mista ou gestor);
 *  - a conta nasce em `usuarios` com status `pendente`;
 *  - o nível/perfil SOLICITADOS ficam registrados para o administrador
 *    confirmar em "Cadastros pendentes";
 *  - nenhum cadastro em `associados` é criado aqui: ele nasce na aprovação
 *    da ficha de adesão (mesma regra do legado).
 */
final class Cadastro
{
    /** Situações (perfis de acesso) apresentadas no primeiro acesso. */
    public const SITUACOES = [
        'vegetal' => [
            'rotulo' => 'Vegetal',
            'descricao' => 'Lavouras, produtos, atividades, insumos e rastreabilidade.',
            'icone' => '🌱',
        ],
        'animal' => [
            'rotulo' => 'Animal',
            'descricao' => 'Rebanho, lotes, alimentação, sanidade, reprodução e ambientes.',
            'icone' => '🐄',
        ],
        'agroindustria' => [
            'rotulo' => 'Agroindústria',
            'descricao' => 'Beneficiamento, receitas, ordens de produção e rastreabilidade.',
            'icone' => '🏭',
        ],
        'vegetal_agro' => [
            'rotulo' => 'Vegetal + Agroindústria',
            'descricao' => 'Hortaliças/vegetais e processamento (sucos, geleias, biscoitos etc.).',
            'icone' => '🌱🏭',
        ],
        'animal_agro' => [
            'rotulo' => 'Animal + Agroindústria',
            'descricao' => 'Criação animal e processamento dos produtos.',
            'icone' => '🐄🏭',
        ],
        'mista' => [
            'rotulo' => 'Mista',
            'descricao' => 'Produção diversificada: animal, vegetal e/ou agroindústria.',
            'icone' => '🌾',
        ],
        'gestor' => [
            'rotulo' => 'Fiscal / Técnico',
            'descricao' => 'Fiscalização e órgãos de controle. Acesso aprovado pela administração.',
            'icone' => '🛡️',
        ],
        'consumidor' => [
            'rotulo' => 'Consumidor / Cliente',
            'descricao' => 'Pessoa que se associa para apoiar e consumir a produção orgânica, sem unidade de produção.',
            'icone' => '🛒',
        ],
    ];

    /**
     * @param array<string, mixed> $post
     * @return array{ok: bool, mensagem: string, tab: string}
     */
    public static function processar(array $post): array
    {
        $nome = trim((string) ($post['nome'] ?? ''));
        $sobrenome = trim((string) ($post['sobrenome'] ?? ''));
        $email = mb_strtolower(trim((string) ($post['email'] ?? '')));
        $senha = (string) ($post['senha'] ?? '');
        $confirma = (string) ($post['confirma_senha'] ?? '');
        $situacao = trim((string) ($post['perfil'] ?? ''));

        if (!isset(self::SITUACOES[$situacao])) {
            return self::falha('Selecione um perfil de acesso.', 'perfil');
        }

        $erros = [];
        if ($nome === '') {
            $erros[] = 'O nome é obrigatório.';
        }
        if ($sobrenome === '') {
            $erros[] = 'O sobrenome é obrigatório.';
        }
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $erros[] = 'Informe um e-mail válido.';
        }
        if (mb_strlen($senha) < 6) {
            $erros[] = 'A senha deve ter pelo menos 6 caracteres.';
        }
        if ($senha !== $confirma) {
            $erros[] = 'As senhas não coincidem.';
        }
        if ($erros !== []) {
            return self::falha(implode(' ', $erros), 'cadastro');
        }

        $pdo = Identidades::pdo();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios WHERE LOWER(email) = :email');
        $stmt->execute(['email' => $email]);
        if ((int) $stmt->fetchColumn() > 0) {
            return self::falha('Este e-mail já está cadastrado. Tente fazer login.', 'cadastro');
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM associados WHERE LOWER(email) = :email');
        $stmt->execute(['email' => $email]);
        if ((int) $stmt->fetchColumn() > 0) {
            return self::falha('Este e-mail já está cadastrado. Tente fazer login.', 'cadastro');
        }

        $mapaTipo = [
            'vegetal' => 'agricultor',
            'animal' => 'agricultor',
            'agroindustria' => 'produtor_agroindustria',
            'vegetal_agro' => 'agricultor_agroindustria',
            'animal_agro' => 'agricultor_agroindustria',
            'mista' => 'agricultor_agroindustria',
            'gestor' => 'gestor',
            'consumidor' => 'consumidor',
        ];
        $mapaArea = [
            'vegetal' => 'vegetal',
            'animal' => 'animal',
            'agroindustria' => 'agroindustria',
            'vegetal_agro' => 'vegetal,agroindustria',
            'animal_agro' => 'animal,agroindustria',
            'mista' => 'animal,vegetal,agroindustria',
            'gestor' => '',
            'consumidor' => '',
        ];

        $tipoUsuario = $mapaTipo[$situacao];
        $areaProducao = $mapaArea[$situacao];
        $temAgroindustria = in_array($tipoUsuario, ['produtor_agroindustria', 'agricultor_agroindustria'], true) ? 1 : 0;
        $nivel = $tipoUsuario === 'gestor' ? 2 : 3;

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO usuarios
                    (nome, sobrenome, email, senha, nivel, nivel_solicitado, perfil_solicitado,
                     tipo, tipo_usuario, tem_agroindustria, area_producao, status, created_at)
                 VALUES
                    (:nome, :sobrenome, :email, :senha, :nivel, :nivel_solicitado, :perfil_solicitado,
                     :tipo, :tipo_usuario, :tem_agroindustria, :area_producao, :status, NOW())'
            );
            $stmt->execute([
                'nome' => $nome,
                'sobrenome' => $sobrenome,
                'email' => $email,
                'senha' => password_hash($senha, PASSWORD_DEFAULT),
                'nivel' => $nivel,
                'nivel_solicitado' => $nivel,
                'perfil_solicitado' => $situacao,
                'tipo' => $tipoUsuario === 'gestor' ? 'gestor' : 'usuario',
                'tipo_usuario' => $tipoUsuario,
                'tem_agroindustria' => $temAgroindustria,
                'area_producao' => $areaProducao,
                'status' => 'pendente',
            ]);
        } catch (\Throwable $e) {
            error_log('SINPORG pre-cadastro: ' . $e->getMessage());
            return self::falha('Não foi possível concluir o cadastro. Tente novamente em instantes.', 'cadastro');
        }

        $novoId = (int) $pdo->lastInsertId();
        Auditoria::registrar('PRECADASTRO', null, 'usuarios', $novoId, 'perfil_solicitado=' . $situacao);

        return [
            'ok' => true,
            'tab' => 'login',
            'mensagem' => $tipoUsuario === 'gestor'
                ? 'Solicitação enviada! O administrador vai confirmar os privilégios de Gestor/Fiscal antes de liberar seu acesso.'
                : 'Cadastro realizado! Aguarde a liberação pelo administrador para acessar o sistema.',
        ];
    }

    /** @return array{ok: bool, mensagem: string, tab: string} */
    private static function falha(string $mensagem, string $tab): array
    {
        return ['ok' => false, 'mensagem' => $mensagem, 'tab' => $tab];
    }
}
