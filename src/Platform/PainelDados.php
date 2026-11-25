<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Indicadores e listas dos painéis de Entrada (associação e produção).
 * Mantém cada painel no seu escopo e reutiliza a mesma apresentação visual.
 */
final class PainelDados
{
    /**
     * @param array<string, mixed> $identidade
     * @return array<string, mixed>
     */
    public static function assoc(array $identidade): array
    {
        $pdo = Identidades::pdo();
        $veTudo = self::veTudo($identidade);
        $associadoId = self::associadoId($identidade);
        $q = static function (string $sql) use ($pdo): int {
            try {
                return (int) $pdo->query($sql)->fetchColumn();
            } catch (\Throwable $e) {
                return 0;
            }
        };

        $cards = [
            ['Associados ativos', $q("SELECT COUNT(*) FROM associados WHERE status = 'ativo'"), '👥', Urls::app('assoc', 'index.php?funcao=associados.quadro'), $veTudo],
            ['Adesões pendentes', $q("SELECT COUNT(*) FROM ficha_adesao WHERE status = 'pendente'"), '📝', Urls::app('assoc', 'index.php?funcao=adesoes.aprovar'), $veTudo],
            ['Mensalidades em aberto', $veTudo
                ? $q("SELECT COUNT(*) FROM mensalidades WHERE status_mensalidade IN ('pendente','atrasado')")
                : ($associadoId > 0 ? $q("SELECT COUNT(*) FROM mensalidades WHERE associado_id = {$associadoId} AND status_mensalidade IN ('pendente','atrasado')") : 0), '💳', Urls::app('assoc', 'index.php?funcao=financeiro.mensalidades'), true],
            ['Visitas agendadas', $veTudo
                ? $q("SELECT COUNT(*) FROM visitas_tecnicas WHERE status = 'Agendada'")
                : ($associadoId > 0 ? $q("SELECT COUNT(*) FROM visitas_tecnicas WHERE associado_id = {$associadoId} AND status = 'Agendada'") : 0), '📅', Urls::app('assoc', 'index.php?funcao=portal.meu-cadastro'), true],
            ['Não conformidades abertas', $veTudo
                ? $q("SELECT COUNT(*) FROM nao_conformidades WHERE status IN ('Aberta','Em Correção')")
                : 0, '⚠️', Urls::app('assoc', 'index.php?funcao=relatorios.assoc'), $veTudo],
            ['Assembleias ativas', $q("SELECT COUNT(*) FROM assembleias WHERE status = 'ativa'"), '🏛️', Urls::app('assoc', 'index.php?funcao=governanca.assembleias'), true],
        ];

        if (Autorizacao::ehAdmin($identidade)) {
            $cards[] = ['Usuários ativos', $q("SELECT COUNT(*) FROM usuarios WHERE status = 'ativo'"), '👤', Urls::app('assoc', 'index.php?funcao=papeis.atribuir'), true];
            $cards[] = ['Certificações ativas', $q("SELECT COUNT(*) FROM certificacao_participativa WHERE status = 'Ativa' AND excluido = 0"), '🏅', Urls::app('social', 'certificacoes.php'), true];
            $cards[] = ['Ordens de produção', $q("SELECT COUNT(*) FROM ordens_producao WHERE excluido = 0"), '🏭', Urls::app('producao', 'registro.php?recurso=agro_ordens'), true];
            $cards[] = ['Produtores no CNPO', $q('SELECT COUNT(*) FROM cnpo'), '🔎', Urls::app('social', 'modulo.php?modulo=cnpo'), true];
        }

        $vencimentos = [];
        $aniversariantes = [];
        $assembleias = [];
        try {
            $stmt = $pdo->query(
                "SELECT m.id, m.ano_referencia, m.mes_referencia, m.valor_cobrado, m.data_vencimento,
                        m.status_mensalidade,
                        CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS associado
                   FROM mensalidades m
                   LEFT JOIN associados a ON a.id = m.associado_id
                  WHERE m.status_mensalidade IN ('pendente','atrasado')
                  ORDER BY m.data_vencimento ASC LIMIT 6"
            );
            $vencimentos = $stmt->fetchAll();
        } catch (\Throwable) {
        }

        try {
            $stmt = $pdo->query(
                "SELECT id, CONCAT(COALESCE(nome, ''), ' ', COALESCE(sobrenome, '')) AS nome,
                        DAY(data_nascimento) AS dia
                   FROM associados
                  WHERE status = 'ativo' AND data_nascimento IS NOT NULL
                    AND MONTH(data_nascimento) = MONTH(CURDATE())
                  ORDER BY dia ASC LIMIT 8"
            );
            $aniversariantes = $stmt->fetchAll();
        } catch (\Throwable) {
        }

        try {
            $stmt = $pdo->query(
                "SELECT titulo, data_inicio, status FROM assembleias
                  WHERE status IN ('rascunho','ativa')
                  ORDER BY data_inicio ASC LIMIT 5"
            );
            $assembleias = $stmt->fetchAll();
        } catch (\Throwable) {
        }

        $secoes = [];
        if ($veTudo) {
            $secoes[] = [
                'titulo' => 'Próximos vencimentos',
                'colunas' => ['associado', 'referencia', 'vencimento', 'valor', 'situacao'],
                'linhas' => array_map(static fn(array $v): array => [
                    'associado' => (string) ($v['associado'] ?? '—'),
                    'referencia' => (int) $v['mes_referencia'] . '/' . (int) $v['ano_referencia'],
                    'vencimento' => self::data((string) ($v['data_vencimento'] ?? '')),
                    'valor' => 'R$ ' . number_format((float) ($v['valor_cobrado'] ?? 0), 2, ',', '.'),
                    'situacao' => (string) ($v['status_mensalidade'] ?? ''),
                ], $vencimentos),
                'vazio' => 'Nenhuma mensalidade em aberto.',
            ];
            $secoes[] = [
                'titulo' => 'Aniversariantes do mês',
                'colunas' => ['nome', 'dia'],
                'linhas' => array_map(static fn(array $a): array => [
                    'nome' => (string) ($a['nome'] ?? '—'),
                    'dia' => (int) ($a['dia'] ?? 0),
                ], $aniversariantes),
                'vazio' => 'Nenhum aniversariante neste mês.',
            ];
            $secoes[] = [
                'titulo' => 'Assembleias e eventos',
                'colunas' => ['titulo', 'inicio', 'situacao'],
                'linhas' => array_map(static fn(array $a): array => [
                    'titulo' => (string) ($a['titulo'] ?? '—'),
                    'inicio' => self::datahora((string) ($a['data_inicio'] ?? '')),
                    'situacao' => (string) ($a['status'] ?? ''),
                ], $assembleias),
                'vazio' => 'Nenhuma assembleia prevista.',
            ];
        }

        $ficha = self::statusFicha($identidade);
        if ($ficha !== null) {
            $secoes[] = [
                'titulo' => 'Minha ficha de adesão',
                'colunas' => ['situacao', 'numero', 'submetida'],
                'linhas' => [[
                    'situacao' => MinhaFicha::rotuloStatus((string) ($ficha['status'] ?? 'sem_ficha')),
                    'numero' => (string) ($ficha['ficha']['numero_cadastro'] ?? '—'),
                    'submetida' => self::data((string) ($ficha['ficha']['data_submissao'] ?? '')),
                ]],
                'vazio' => '',
            ];
        }

        return [
            'titulo' => 'Painel da Associação',
            'subtitulo' => 'Visão do quadro associativo, financeiro, governança e sua participação.',
            'cards' => array_values(array_filter($cards, static fn(array $c): bool => (bool) $c[4])),
            'secoes' => $secoes,
        ];
    }

    /**
     * @param array<string, mixed> $identidade
     * @return array<string, mixed>
     */
    public static function producao(array $identidade): array
    {
        $pdo = Identidades::pdo();
        $veTudo = self::veTudo($identidade);
        $associadoId = self::associadoId($identidade);
        $areas = ProducaoAreas::doUsuario($identidade);
        $filtro = $veTudo ? '' : ' AND associado_id = ' . max(0, $associadoId);

        $q = static function (string $sql) use ($pdo): int {
            try {
                return (int) $pdo->query($sql)->fetchColumn();
            } catch (\Throwable) {
                return 0;
            }
        };

        $cards = [];
        if (in_array('vegetal', $areas, true)) {
            $cards[] = ['Propriedades', $q('SELECT COUNT(*) FROM propriedades' . ($veTudo ? '' : " WHERE associado_id = {$associadoId}")), '🏡', Urls::app('producao', 'registro.php?recurso=propriedades'), true];
            $cards[] = ['Lavouras', $q('SELECT COUNT(*) FROM lavouras'), '🌾', Urls::app('producao', 'registro.php?recurso=lavouras'), true];
            $cards[] = ['Atividades na semana', $q("SELECT COUNT(*) FROM atividades_diarias WHERE excluido = 0 AND YEARWEEK(data_atividade,1) = YEARWEEK(CURDATE(),1){$filtro}"), '📓', Urls::app('producao', 'registro.php?recurso=atividades'), true];
            $cards[] = ['Insumos em falta', $q("SELECT COUNT(*) FROM insumos WHERE excluido = 0 AND quantidade_estoque < 10{$filtro}"), '🧪', Urls::app('producao', 'registro.php?recurso=insumos'), true];
            $cards[] = ['Lotes de produção', $q("SELECT COUNT(*) FROM lotes_producao WHERE excluido = 0{$filtro}"), '📦', Urls::app('producao', 'registro.php?recurso=lotes'), true];
            $cards[] = ['Ocorrências abertas', $q("SELECT COUNT(*) FROM ocorrencias_manejo WHERE status IN ('Aberta','Em Correção') AND excluido = 0{$filtro}"), '⚠️', Urls::app('producao', 'registro.php?recurso=ocorrencias'), true];
        }
        if (in_array('animal', $areas, true)) {
            $cards[] = ['Animais no rebanho', $q("SELECT COUNT(*) FROM animais WHERE excluido = 0{$filtro}"), '🐄', Urls::app('producao', 'registro.php?recurso=animais'), true];
            $cards[] = ['Lotes de animais', $q("SELECT COUNT(*) FROM animal_lotes WHERE excluido = 0{$filtro}"), '🐑', Urls::app('producao', 'registro.php?recurso=animal_lotes'), true];
            $cards[] = ['Registros sanitários', $q("SELECT COUNT(*) FROM controle_sanidade_animal WHERE excluido = 0{$filtro}"), '💉', Urls::app('producao', 'registro.php?recurso=sanidade'), true];
        }
        if (in_array('agroindustria', $areas, true)) {
            $cards[] = ['Ordens de produção', $q("SELECT COUNT(*) FROM ordens_producao WHERE excluido = 0{$filtro}"), '🏭', Urls::app('producao', 'registro.php?recurso=agro_ordens'), true];
            $cards[] = ['Ordens aprovadas', $q("SELECT COUNT(*) FROM ordens_producao WHERE status_auditoria = 'APROVADO' AND excluido = 0{$filtro}"), '✅', Urls::app('producao', 'registro.php?recurso=agro_ordens'), true];
            $cards[] = ['Receitas', $q("SELECT COUNT(*) FROM receitas_agroindustria WHERE excluido = 0{$filtro}"), '📖', Urls::app('producao', 'registro.php?recurso=agro_receitas'), true];
            $cards[] = ['Higienizações', $q("SELECT COUNT(*) FROM controle_higienizacao WHERE excluido = 0{$filtro}"), '🧼', Urls::app('producao', 'registro.php?recurso=agro_higienizacao'), true];
        }

        $secoes = [];
        $aviso = null;
        if ($associadoId > 0) {
            $situacaoPlano = PlanoManejo::situacao($associadoId);
            $rotuloPlano = $situacaoPlano['status'] === 'sem_plano'
                ? 'Não enviado'
                : (string) $situacaoPlano['status'];
            $cards[] = ['Plano de manejo', $rotuloPlano, '📔', Urls::app('producao', 'meu-plano.php'), true];
            if (!$situacaoPlano['ok']) {
                $aviso = [
                    'titulo' => 'Plano de manejo obrigatório',
                    'texto' => 'Todo agricultor, independentemente da área de produção, precisa manter um plano de manejo enviado para análise.',
                    'link' => Urls::app('producao', 'meu-plano.php'),
                    'rotulo' => 'Preencher meu plano agora',
                ];
            }
        }

        try {
            $stmt = $pdo->query(
                "SELECT nome, data_atividade, tipo_atividade, status
                   FROM atividades_diarias
                  WHERE excluido = 0{$filtro}
                  ORDER BY data_atividade DESC LIMIT 5"
            );
            $secoes[] = [
                'titulo' => 'Últimas atividades do caderno',
                'colunas' => ['nome', 'data', 'tipo', 'situacao'],
                'linhas' => array_map(static fn(array $a): array => [
                    'nome' => (string) ($a['nome'] ?? '—'),
                    'data' => self::data((string) ($a['data_atividade'] ?? '')),
                    'tipo' => (string) ($a['tipo_atividade'] ?? '—'),
                    'situacao' => (string) ($a['status'] ?? '—'),
                ], $stmt->fetchAll()),
                'vazio' => 'Nenhuma atividade registrada.',
            ];
        } catch (\Throwable) {
        }

        if (in_array('agroindustria', $areas, true)) {
            try {
                $stmt = $pdo->query(
                    "SELECT o.lote_processado_final, o.data_processamento, o.status_auditoria,
                            COALESCE(r.nome_produto_final, '—') AS produto
                       FROM ordens_producao o
                       LEFT JOIN receitas_agroindustria r ON r.id = o.receita_id
                      WHERE o.excluido = 0{$filtro}
                      ORDER BY o.data_processamento DESC LIMIT 5"
                );
                $secoes[] = [
                    'titulo' => 'Ordens de produção recentes',
                    'colunas' => ['produto', 'lote', 'data', 'auditoria'],
                    'linhas' => array_map(static fn(array $o): array => [
                        'produto' => (string) ($o['produto'] ?? '—'),
                        'lote' => (string) ($o['lote_processado_final'] ?? '—'),
                        'data' => self::data((string) ($o['data_processamento'] ?? '')),
                        'auditoria' => (string) ($o['status_auditoria'] ?? '—'),
                    ], $stmt->fetchAll()),
                    'vazio' => 'Nenhuma ordem registrada.',
                ];
            } catch (\Throwable) {
            }
        }

        return [
            'titulo' => 'Painel da Produção',
            'subtitulo' => 'Sua produção, caderno de campo e agroindústria no mesmo lugar.',
            'cards' => $cards,
            'secoes' => $secoes,
            'aviso' => $aviso,
        ];
    }

    /** @param array<string, mixed> $identidade */
    public static function veTudo(array $identidade): bool
    {
        if (Autorizacao::ehAdmin($identidade)) {
            return true;
        }

        $papeis = array_map('strval', (array) ($identidade['papeis'] ?? []));

        return array_intersect($papeis, ['diretor', 'gestor_associacao', 'tesoureiro', 'secretario', 'diretoria']) !== [];
    }

    /** @param array<string, mixed> $identidade */
    public static function associadoId(array $identidade): int
    {
        return ($identidade['origem'] ?? '') === 'associados'
            ? (int) ($identidade['id'] ?? 0)
            : (int) ($identidade['associado_id'] ?? 0);
    }

    /**
     * @param array<string, mixed> $identidade
     * @return array<string, mixed>|null
     */
    private static function statusFicha(array $identidade): ?array
    {
        if (($identidade['origem'] ?? '') !== 'usuarios') {
            return null;
        }

        try {
            return Onboarding::statusFicha((int) ($identidade['id'] ?? 0));
        } catch (\Throwable) {
            return null;
        }
    }

    private static function data(string $valor): string
    {
        $ts = $valor !== '' ? strtotime($valor) : false;

        return $ts !== false ? date('d/m/Y', $ts) : '—';
    }

    private static function datahora(string $valor): string
    {
        $ts = $valor !== '' ? strtotime($valor) : false;

        return $ts !== false ? date('d/m/Y H:i', $ts) : '—';
    }
}
