<?php
/**
 * Fontes de dados dos Relatórios de Conformidade Orgânica (Fase B).
 *
 * Cada método retorna uma estrutura normalizada:
 *   [
 *     'titulo'    => string,
 *     'descricao' => string,
 *     'resumo'    => [ ['label'=>..., 'valor'=>...], ... ] | null,
 *     'tabelas'   => [
 *         ['titulo' => string, 'colunas' => [], 'campos' => [], 'dados' => []],
 *         ...
 *     ]
 *   ]
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/Database.php';

class ReportConformidade {

    /** @var PDO */
    private static $db = null;

    /**
     * @return PDO
     */
    private static function db() {
        if (self::$db === null) {
            self::$db = Database::getInstance()->getConnection();
        }
        return self::$db;
    }

    /**
     * Retorna os rótulos legíveis dos status de conversão.
     */
    private static function statusConversao($status) {
        return [
            'EM_CONVERSAO' => 'Em conversão',
            'ORGANICO' => 'Orgânico',
            'CONVENCIONAL' => 'Convencional',
        ][$status] ?? $status;
    }

    /**
     * Retorna os rótulos legíveis dos status de animais.
     */
    private static function statusAnimal($status) {
        return [
            'ATIVO' => 'Ativo',
            'VENDIDO' => 'Vendido',
            'ABATIDO' => 'Abatido',
            'MORTO' => 'Morto',
        ][$status] ?? $status;
    }

    /**
     * Retorna os rótulos legíveis da origem de conversão dos animais.
     */
    private static function origemAnimal($origem) {
        return [
            'ORGANICO' => 'Orgânico',
            'EM_CONVERSAO' => 'Em conversão',
            'CONVENCIONAL' => 'Convencional',
        ][$origem] ?? $origem;
    }

    /**
     * Aplica um filtro BETWEEN de datas quando ambos os limites são informados.
     */
    private static function filtroPeriodo(&$where, &$params, $campo, $filtros) {
        $inicio = $filtros['data_inicio'] ?? '';
        $fim = $filtros['data_fim'] ?? '';
        if (!empty($inicio) && !empty($fim)) {
            $where[] = "({$campo} BETWEEN :periodo_inicio AND :periodo_fim)";
            $params[':periodo_inicio'] = $inicio;
            $params[':periodo_fim'] = $fim;
        } elseif (!empty($inicio)) {
            $where[] = "({$campo} >= :periodo_inicio)";
            $params[':periodo_inicio'] = $inicio;
        } elseif (!empty($fim)) {
            $where[] = "({$campo} <= :periodo_fim)";
            $params[':periodo_fim'] = $fim;
        }
    }

    /**
     * Remove acentos e normaliza uma string para comparação de nomes.
     */
    private static function normalizarNome($texto) {
        $texto = (string)$texto;
        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
        $texto = strtolower(preg_replace('/[^A-Za-z0-9]/', ' ', $texto));
        return preg_replace('/\s+/', ' ', trim($texto));
    }

    /**
     * Aplica o filtro de associado (o valor é forçado externamente para
     * usuários não-gestores).
     */
    private static function filtroAssociado(&$where, &$params, $campo, $filtros) {
        if (!empty($filtros['associado_id'])) {
            $where[] = "{$campo} = :associado_id";
            $params[':associado_id'] = (int)$filtros['associado_id'];
        }
    }

    /**
     * Filtra um enum quando informado.
     */
    private static function filtroEnum(&$where, &$params, $campo, $valor) {
        if (!empty($valor)) {
            $where[] = "{$campo} = :filtro_enum";
            $params[':filtro_enum'] = $valor;
        }
    }

    /* ============================================================
     * 1) BALANÇO DE MASSAS (RF11)
     * ============================================================ */

    public static function balancoMassas($filtros = []) {
        $where = ["o.excluido = 0"];
        $params = [];
        self::filtroPeriodo($where, $params, 'DATE(o.data_processamento)', $filtros);
        self::filtroAssociado($where, $params, 'o.associado_id', $filtros);
        self::filtroEnum($where, $params, 'o.status_auditoria', $filtros['status'] ?? '');

        $sql = "SELECT
                    o.lote_processado_final,
                    o.data_processamento,
                    r.nome_produto_final,
                    o.quantidade_final_obtida,
                    o.unidade_medida,
                    o.status_auditoria,
                    o.percentual_organico,
                    a.nome AS associado_nome,
                    a.sobrenome AS associado_sobrenome,
                    COALESCE(SUM(ri.quantidade_utilizada), 0) AS total_entrada
                FROM ordens_producao o
                JOIN receitas_agroindustria r ON o.receita_id = r.id
                LEFT JOIN associados a ON o.associado_id = a.id
                LEFT JOIN rastreabilidade_insumos_processo ri ON ri.ordem_producao_id = o.id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY o.id
                ORDER BY o.data_processamento DESC";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totais = ['entrada' => 0, 'saida' => 0, 'aprovados' => 0, 'alertas' => 0];
        $dados = [];
        foreach ($rows as $r) {
            $entrada = (float)$r['total_entrada'];
            $saida = (float)$r['quantidade_final_obtida'];
            $divergencia = $entrada > 0 ? (abs($entrada - $saida) / $entrada) * 100 : null;
            $status = $r['status_auditoria'] ?? 'EM_ANALISE';
            $dados[] = [
                'lote' => $r['lote_processado_final'],
                'data' => date('d/m/Y', strtotime($r['data_processamento'])),
                'produto' => $r['nome_produto_final'],
                'entrada' => number_format($entrada, 2, ',', '.'),
                'saida' => number_format($saida, 2, ',', '.'),
                'unidade' => $r['unidade_medida'],
                'divergencia' => $divergencia !== null ? number_format($divergencia, 2, ',', '.') . '%' : '—',
                'status' => $status,
                'associado' => trim(($r['associado_nome'] ?? '') . ' ' . ($r['associado_sobrenome'] ?? '')),
            ];
            $totais['entrada'] += $entrada;
            $totais['saida'] += $saida;
            if ($status === 'APROVADO') { $totais['aprovados']++; }
            if ($status === 'ALERTA_FRAUDE') { $totais['alertas']++; }
        }

        return [
            'titulo' => 'Relatório de Balanço de Massas',
            'descricao' => 'RF11 — Entrada de matéria-prima x quantidade final obtida por ordem de produção, com a divergência percentual e o status de auditoria (tolerância de 10%).',
            'resumo' => [
                ['label' => 'Ordens de Produção', 'valor' => count($dados)],
                ['label' => 'Entrada Total (kg)', 'valor' => number_format($totais['entrada'], 2, ',', '.')],
                ['label' => 'Saída Total (kg)', 'valor' => number_format($totais['saida'], 2, ',', '.')],
                ['label' => 'Alertas de Fraude', 'valor' => $totais['alertas']],
            ],
            'tabelas' => [[
                'titulo' => 'Balanço de massas por ordem de produção',
                'colunas' => ['Lote Final', 'Data', 'Produto Final', 'Entrada', 'Saída', 'Un.', 'Divergência', 'Status Auditoria', 'Associado'],
                'campos' => ['lote', 'data', 'produto', 'entrada', 'saida', 'unidade', 'divergencia', 'status', 'associado'],
                'dados' => $dados,
            ]],
        ];
    }

    /* ============================================================
     * 2) TRILHA DE AUDITORIA (NFR03)
     * ============================================================ */

    public static function trilhaAuditoria($filtros = []) {
        $where = ["1 = 1"];
        $params = [];
        self::filtroPeriodo($where, $params, 'DATE(al.created_at)', $filtros);
        self::filtroEnum($where, $params, 'al.acao', $filtros['acao'] ?? '');
        if (!empty($filtros['user_id'])) {
            $where[] = "al.user_id = :user_id";
            $params[':user_id'] = (int)$filtros['user_id'];
        }

        $sql = "SELECT
                    al.id,
                    al.created_at,
                    al.acao,
                    al.tabela,
                    al.registro_id,
                    al.descricao,
                    al.ip,
                    COALESCE(u.nome, assoc.nome) AS usuario_nome,
                    COALESCE(u.email, assoc.email) AS usuario_email
                FROM audit_log al
                LEFT JOIN usuarios u ON al.user_id = u.id
                LEFT JOIN associados assoc ON assoc.user_id = al.user_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY al.created_at DESC
                LIMIT 5000";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados = [];
        $por_acao = [];
        foreach ($rows as $r) {
            $dados[] = [
                'data' => date('d/m/Y H:i:s', strtotime($r['created_at'])),
                'usuario' => $r['usuario_nome'] ?? 'Sistema',
                'acao' => $r['acao'],
                'tabela' => $r['tabela'] ?? '—',
                'registro_id' => $r['registro_id'] ?? '—',
                'descricao' => $r['descricao'] ?? '—',
                'ip' => $r['ip'] ?? '—',
            ];
            $acao = $r['acao'] ?? 'OUTROS';
            $por_acao[$acao] = ($por_acao[$acao] ?? 0) + 1;
        }
        arsort($por_acao);

        return [
            'titulo' => 'Trilha de Auditoria',
            'descricao' => 'NFR03 — Registro cronológico de todas as ações realizadas no sistema (logs de acesso e alterações).',
            'resumo' => [
                ['label' => 'Eventos Registrados', 'valor' => count($dados)],
                ['label' => 'Usuários', 'valor' => count(array_unique(array_column($dados, 'usuario')))],
                ['label' => 'Ações Distintas', 'valor' => count($por_acao)],
                ['label' => 'Ação Mais Frequente', 'valor' => key($por_acao) ?: '—'],
            ],
            'tabelas' => [[
                'titulo' => 'Eventos de auditoria',
                'colunas' => ['Data/Hora', 'Usuário', 'Ação', 'Tabela', 'Registro', 'Descrição', 'IP'],
                'campos' => ['data', 'usuario', 'acao', 'tabela', 'registro_id', 'descricao', 'ip'],
                'dados' => $dados,
            ]],
        ];
    }

    /* ============================================================
     * 3) RASTREABILIDADE CONSOLIDADA (RF12)
     * ============================================================ */

    public static function rastreabilidadeConsolidada($filtros = []) {
        $where = ["o.excluido = 0"];
        $params = [];
        self::filtroPeriodo($where, $params, 'DATE(o.data_processamento)', $filtros);
        self::filtroAssociado($where, $params, 'o.associado_id', $filtros);

        $sql = "SELECT
                    o.id AS ordem_id,
                    o.lote_processado_final,
                    o.data_processamento,
                    o.quantidade_final_obtida,
                    o.unidade_medida,
                    o.percentual_organico,
                    o.selo_autorizado,
                    r.nome_produto_final,
                    a.nome AS associado_nome,
                    a.sobrenome AS associado_sobrenome
                FROM ordens_producao o
                JOIN receitas_agroindustria r ON o.receita_id = r.id
                LEFT JOIN associados a ON o.associado_id = a.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY o.data_processamento DESC";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $ops = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados_ops = [];
        $dados_insumos = [];
        $total_lotes = 0;

        foreach ($ops as $op) {
            $dados_ops[] = [
                'lote' => $op['lote_processado_final'],
                'data' => date('d/m/Y', strtotime($op['data_processamento'])),
                'produto' => $op['nome_produto_final'],
                'qtd' => number_format((float)$op['quantidade_final_obtida'], 2, ',', '.'),
                'unidade' => $op['unidade_medida'],
                'organico' => $op['percentual_organico'] !== null ? number_format((float)$op['percentual_organico'], 2, ',', '.') . '%' : '—',
                'selo' => $op['selo_autorizado'] ?? '—',
                'associado' => trim(($op['associado_nome'] ?? '') . ' ' . ($op['associado_sobrenome'] ?? '')),
            ];

            $stmt2 = self::db()->prepare(
                "SELECT ri.quantidade_utilizada, ri.unidade_medida, ri.numero_nf,
                        lp.nome_lote, p.nome AS nome_produto,
                        ins.nome AS nome_insumo, ins.lote AS lote_insumo,
                        f.nome AS fornecedor, f.cnpj AS fornecedor_cnpj
                 FROM rastreabilidade_insumos_processo ri
                 LEFT JOIN lotes_producao lp ON ri.lote_primario_id = lp.id
                 LEFT JOIN produtos p ON ri.produto_id = p.id
                 LEFT JOIN insumos ins ON ri.insumo_id = ins.id
                 LEFT JOIN fornecedores f ON ins.fornecedor_id = f.id
                 WHERE ri.ordem_producao_id = ?"
            );
            $stmt2->execute([$op['ordem_id'] ?? 0]);
            foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $ins) {
                $dados_insumos[] = [
                    'lote_final' => $op['lote_processado_final'],
                    'origem' => $ins['nome_insumo'] ?? ($ins['nome_lote'] ?? '—'),
                    'fornecedor' => $ins['fornecedor'] ? trim($ins['fornecedor'] . ($ins['fornecedor_cnpj'] ? ' (CNPJ ' . $ins['fornecedor_cnpj'] . ')' : '')) : '—',
                    'numero_nf' => $ins['numero_nf'] ?: '—',
                    'lote_origem' => $ins['nome_lote'] ?? '—',
                    'produto_origem' => $ins['nome_produto'] ?? '—',
                    'qtd' => number_format((float)$ins['quantidade_utilizada'], 2, ',', '.'),
                    'unidade' => $ins['unidade_medida'],
                ];
                $total_lotes++;
            }
        }

        return [
            'titulo' => 'Rastreabilidade Consolidada',
            'descricao' => 'RF12 — Cadeia de rastreabilidade dos produtos processados: lote final, origem do insumo (fornecedor/NF), lote primário de origem (campo) e insumos utilizados.',
            'resumo' => [
                ['label' => 'Lotes Finais', 'valor' => count($dados_ops)],
                ['label' => 'Vínculos Origem', 'valor' => $total_lotes],
                ['label' => 'Selo POB', 'valor' => count(array_filter($dados_ops, fn($d) => strpos($d['selo'], 'PRODUTO_ORGANICO') !== false))],
            ],
            'tabelas' => [
                [
                    'titulo' => 'Lotes finais processados',
                    'colunas' => ['Lote Final', 'Data', 'Produto', 'Qtd.', 'Un.', '% Orgânico', 'Selo', 'Associado'],
                    'campos' => ['lote', 'data', 'produto', 'qtd', 'unidade', 'organico', 'selo', 'associado'],
                    'dados' => $dados_ops,
                ],
                [
                    'titulo' => 'Origem dos insumos (fornecedor/NF até o lote final)',
                    'colunas' => ['Lote Final', 'Origem (Insumo/Lote)', 'Fornecedor', 'NF', 'Lote Primário', 'Produto de Origem', 'Qtd. Utilizada', 'Un.'],
                    'campos' => ['lote_final', 'origem', 'fornecedor', 'numero_nf', 'lote_origem', 'produto_origem', 'qtd', 'unidade'],
                    'dados' => $dados_insumos,
                ],
            ],
        ];
    }

    /* ============================================================
     * 4) VALIDAÇÃO DE INSUMOS (RF06)
     * ============================================================ */

    public static function validacaoInsumos($filtros = []) {
        $where = ["i.excluido = 0"];
        $params = [];
        self::filtroAssociado($where, $params, 'i.associado_id', $filtros);
        self::filtroEnum($where, $params, 'i.tipo_insumo', $filtros['status'] ?? '');
        if (($filtros['status'] ?? '') === '') {
            // status neste relatório se refere ao tipo_insumo; sem filtro = todos
        }

        $sql = "SELECT
                    i.nome, i.tipo_insumo, i.aprovado_organico, i.certificacao_insumo,
                    i.data_validade, i.lote, i.fabricante, i.composicao, i.restricoes,
                    f.nome AS fornecedor_nome,
                    a.nome AS associado_nome, a.sobrenome AS associado_sobrenome,
                    ia.nome AS aprovado_nome, ia.permitido AS aprovado_permitido,
                    ia.categoria AS aprovado_categoria, ia.restricoes AS aprovado_restricoes,
                    ia.normativa AS aprovado_normativa
                FROM insumos i
                LEFT JOIN fornecedores f ON i.fornecedor_id = f.id
                LEFT JOIN associados a ON i.associado_id = a.id
                LEFT JOIN insumos_organicos_aprovados ia ON i.insumo_aprovado_id = ia.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY i.nome ASC";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Lista oficial da Portaria 52/2021 (para correspondência por nome
        // quando o insumo não possui vínculo persistido com insumo_aprovado_id).
        $aprovados = self::db()->query(
            "SELECT nome, nome_comercial, categoria, permitido, restricoes, normativa FROM insumos_organicos_aprovados"
        )->fetchAll(PDO::FETCH_ASSOC);
        $indiceAprovados = [];
        foreach ($aprovados as $aprovado) {
            foreach ([$aprovado['nome'], $aprovado['nome_comercial']] as $nomeAlt) {
                if (!empty($nomeAlt)) {
                    $indiceAprovados[self::normalizarNome($nomeAlt)] = $aprovado;
                }
            }
        }

        $dados = [];
        $cont = ['total' => 0, 'aprovado' => 0, 'restrito' => 0, 'nao_aprovado' => 0];
        foreach ($rows as $r) {
            $aprovado = (int)($r['aprovado_organico'] ?? 0) === 1;
            $match = null;
            if (!$aprovado) {
                $chave = self::normalizarNome($r['nome']);
                if (isset($indiceAprovados[$chave])) {
                    $match = $indiceAprovados[$chave];
                } elseif (!empty($r['insumo_aprovado_id'])) {
                    foreach ($aprovados as $aprovadoRow) {
                        if ((int)$aprovadoRow['id'] === (int)$r['insumo_aprovado_id']) {
                            $match = $aprovadoRow;
                            break;
                        }
                    }
                }
                if ($match) {
                    $aprovado = (int)($match['permitido'] ?? 1) === 1;
                }
            }

            $restrito = false;
            if (!$aprovado && $match && (int)($match['permitido'] ?? 1) === 0) {
                $restrito = true;
            }
            if ($aprovado) {
                $status = 'Aprovado';
                $cont['aprovado']++;
            } elseif ($restrito) {
                $status = 'Restrito/Proibido';
                $cont['restrito']++;
            } else {
                $status = 'Não aprovado';
                $cont['nao_aprovado']++;
            }
            $cont['total']++;

            $dados[] = [
                'nome' => $r['nome'],
                'tipo' => $r['tipo_insumo'] ?: '—',
                'status' => $status,
                'aprovado_oficial' => ($match['nome'] ?? $r['aprovado_nome']) ?: '—',
                'categoria' => ($match['categoria'] ?? $r['aprovado_categoria']) ?: '—',
                'normativa' => ($match['normativa'] ?? $r['aprovado_normativa']) ?: '—',
                'fabricante' => $r['fabricante'] ?: ($r['fornecedor_nome'] ?? '—'),
                'lote' => $r['lote'] ?? '—',
                'validade' => !empty($r['data_validade']) ? date('d/m/Y', strtotime($r['data_validade'])) : '—',
                'restricoes' => ($match['restricoes'] ?? $r['aprovado_restricoes'] ?: ($r['restricoes'] ?? '—')),
                'associado' => trim(($r['associado_nome'] ?? '') . ' ' . ($r['associado_sobrenome'] ?? '')),
            ];
        }

        return [
            'titulo' => 'Relatório de Validação de Insumos',
            'descricao' => 'RF06 — Situação de conformidade orgânica dos insumos cadastrados, cruzada com a lista oficial da Portaria 52/2021.',
            'resumo' => [
                ['label' => 'Total de Insumos', 'valor' => $cont['total']],
                ['label' => 'Aprovados', 'valor' => $cont['aprovado']],
                ['label' => 'Restritos/Proibidos', 'valor' => $cont['restrito']],
                ['label' => 'Não Aprovados', 'valor' => $cont['nao_aprovado']],
            ],
            'tabelas' => [[
                'titulo' => 'Insumos e status de validação',
                'colunas' => ['Nome', 'Tipo', 'Status', 'Item na Lista (Portaria 52)', 'Categoria', 'Normativa', 'Fabricante', 'Lote', 'Validade', 'Restrições', 'Associado'],
                'campos' => ['nome', 'tipo', 'status', 'aprovado_oficial', 'categoria', 'normativa', 'fabricante', 'lote', 'validade', 'restricoes', 'associado'],
                'dados' => $dados,
            ]],
        ];
    }

    /* ============================================================
     * 5) HISTÓRICO DE CONVERSÃO (RF03)
     * ============================================================ */

    public static function historicoConversao($filtros = []) {
        $where = ["1 = 1"];
        $params = [];
        self::filtroPeriodo($where, $params, 'hc.data_inicio', $filtros);
        self::filtroAssociado($where, $params, 'hc.associado_id', $filtros);
        self::filtroEnum($where, $params, 'hc.status', $filtros['status'] ?? '');

        $sql = "SELECT
                    hc.id, hc.tipo_uso_solo, hc.data_inicio, hc.data_fim, hc.status, hc.observacoes,
                    p.propriedade AS propriedade_nome, p.cidade AS propriedade_cidade, p.estado AS propriedade_uf,
                    a.nome AS associado_nome, a.sobrenome AS associado_sobrenome
                FROM historico_conversao hc
                LEFT JOIN propriedades p ON hc.propriedade_id = p.id
                LEFT JOIN associados a ON hc.associado_id = a.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY hc.data_inicio DESC";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados = [];
        $cont = ['EM_CONVERSAO' => 0, 'ORGANICO' => 0, 'CONVENCIONAL' => 0];
        foreach ($rows as $r) {
            $status = $r['status'] ?? 'EM_CONVERSAO';
            $cont[$status] = ($cont[$status] ?? 0) + 1;
            $dados[] = [
                'propriedade' => $r['propriedade_nome'] ?? '—',
                'cidade' => trim(($r['propriedade_cidade'] ?? '') . '/' . ($r['propriedade_uf'] ?? '')),
                'uso_solo' => $r['tipo_uso_solo'],
                'inicio' => date('d/m/Y', strtotime($r['data_inicio'])),
                'fim' => !empty($r['data_fim']) ? date('d/m/Y', strtotime($r['data_fim'])) : '—',
                'status' => self::statusConversao($status),
                'associado' => trim(($r['associado_nome'] ?? '') . ' ' . ($r['associado_sobrenome'] ?? '')),
                'observacoes' => $r['observacoes'] ?? '—',
            ];
        }

        return [
            'titulo' => 'Relatório de Histórico de Conversão',
            'descricao' => 'RF03 — Histórico de conversão para o modo de produção orgânico por propriedade, conforme Portaria 52/2021 (12 meses anuais / 24 meses perenes).',
            'resumo' => [
                ['label' => 'Registros', 'valor' => count($dados)],
                ['label' => 'Em Conversão', 'valor' => $cont['EM_CONVERSAO']],
                ['label' => 'Orgânicas', 'valor' => $cont['ORGANICO']],
                ['label' => 'Convencionais', 'valor' => $cont['CONVENCIONAL']],
            ],
            'tabelas' => [[
                'titulo' => 'Histórico de conversão por propriedade',
                'colunas' => ['Propriedade', 'Localidade', 'Uso do Solo', 'Início', 'Fim', 'Status', 'Associado', 'Observações'],
                'campos' => ['propriedade', 'cidade', 'uso_solo', 'inicio', 'fim', 'status', 'associado', 'observacoes'],
                'dados' => $dados,
            ]],
        ];
    }

    /* ============================================================
     * 6) MANEJO ANIMAL (RF05)
     * ============================================================ */

    public static function manejoAnimal($filtros = []) {
        $where = ["a.excluido = 0"];
        $params = [];
        self::filtroAssociado($where, $params, 'a.associado_id', $filtros);
        self::filtroEnum($where, $params, 'a.status', $filtros['status'] ?? '');

        $sql = "SELECT
                    a.id, a.nome_animal, a.especie, a.raca, a.identificacao, a.sexo,
                    a.data_nascimento, a.status, a.origem_conversao,
                    assoc.nome AS associado_nome, assoc.sobrenome AS associado_sobrenome,
                    (SELECT COUNT(*) FROM manejo_animal_registros r WHERE r.animal_id = a.id) AS total_registros
                FROM animais a
                LEFT JOIN associados assoc ON a.associado_id = assoc.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY a.criado_em DESC";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados_animais = [];
        $cont_status = ['ATIVO' => 0, 'VENDIDO' => 0, 'ABATIDO' => 0, 'MORTO' => 0];
        foreach ($rows as $r) {
            $status = $r['status'] ?? 'ATIVO';
            $cont_status[$status] = ($cont_status[$status] ?? 0) + 1;
            $dados_animais[] = [
                'id' => $r['id'],
                'nome' => $r['nome_animal'],
                'especie' => $r['especie'],
                'raca' => $r['raca'] ?? '—',
                'identificacao' => $r['identificacao'] ?? '—',
                'sexo' => $r['sexo'] ?? '—',
                'nascimento' => !empty($r['data_nascimento']) ? date('d/m/Y', strtotime($r['data_nascimento'])) : '—',
                'status' => self::statusAnimal($status),
                'origem' => self::origemAnimal($r['origem_conversao'] ?? 'ORGANICO'),
                'registros' => $r['total_registros'],
                'associado' => trim(($r['associado_nome'] ?? '') . ' ' . ($r['associado_sobrenome'] ?? '')),
            ];
        }

        // Registros de manejo (vacinação/sanidade, alimentação, clínico)
        $whereReg = ["1 = 1"];
        $paramsReg = [];
        self::filtroPeriodo($whereReg, $paramsReg, 'r.data_registro', $filtros);
        if (!empty($filtros['associado_id'])) {
            $whereReg[] = "a.associado_id = :associado_id";
            $paramsReg[':associado_id'] = (int)$filtros['associado_id'];
        }

        $sqlReg = "SELECT
                    r.data_registro, r.tipo_registro, r.descricao, r.produto_utilizado,
                    r.dose_quantidade, r.responsavel, r.observacoes,
                    a.nome_animal, a.especie
                FROM manejo_animal_registros r
                JOIN animais a ON r.animal_id = a.id
                WHERE " . implode(' AND ', $whereReg) . "
                ORDER BY r.data_registro DESC
                LIMIT 5000";

        $stmtReg = self::db()->prepare($sqlReg);
        $stmtReg->execute($paramsReg);
        $regRows = $stmtReg->fetchAll(PDO::FETCH_ASSOC);

        $dados_registros = [];
        $cont_tipo = ['CLINICO' => 0, 'VACINACAO' => 0, 'ALIMENTACAO' => 0];
        foreach ($regRows as $r) {
            $tipo = $r['tipo_registro'] ?? 'CLINICO';
            $cont_tipo[$tipo] = ($cont_tipo[$tipo] ?? 0) + 1;
            $dados_registros[] = [
                'data' => date('d/m/Y', strtotime($r['data_registro'])),
                'animal' => $r['nome_animal'],
                'especie' => $r['especie'],
                'tipo' => $tipo,
                'produto' => $r['produto_utilizado'] ?? '—',
                'dose' => $r['dose_quantidade'] ?? '—',
                'responsavel' => $r['responsavel'] ?? '—',
                'descricao' => $r['descricao'] ?? '—',
                'observacoes' => $r['observacoes'] ?? '—',
            ];
        }

        return [
            'titulo' => 'Relatório de Manejo Animal',
            'descricao' => 'RF05 — Rebanho cadastrado, origem de conversão e registros de manejo (sanidade, vacinação e alimentação).',
            'resumo' => [
                ['label' => 'Animais', 'valor' => count($dados_animais)],
                ['label' => 'Ativos', 'valor' => $cont_status['ATIVO']],
                ['label' => 'Registros de Manejo', 'valor' => count($dados_registros)],
                ['label' => 'Vacinações', 'valor' => $cont_tipo['VACINACAO']],
            ],
            'tabelas' => [
                [
                    'titulo' => 'Animais cadastrados',
                    'colunas' => ['ID', 'Nome', 'Espécie', 'Raça', 'Identificação', 'Sexo', 'Nascimento', 'Status', 'Origem', 'Registros', 'Associado'],
                    'campos' => ['id', 'nome', 'especie', 'raca', 'identificacao', 'sexo', 'nascimento', 'status', 'origem', 'registros', 'associado'],
                    'dados' => $dados_animais,
                ],
                [
                    'titulo' => 'Registros de manejo',
                    'colunas' => ['Data', 'Animal', 'Espécie', 'Tipo', 'Produto/Vacina', 'Dose', 'Responsável', 'Descrição', 'Observações'],
                    'campos' => ['data', 'animal', 'especie', 'tipo', 'produto', 'dose', 'responsavel', 'descricao', 'observacoes'],
                    'dados' => $dados_registros,
                ],
            ],
        ];
    }

    /* ============================================================
     * 7) AGROINDÚSTRIA CONSOLIDADO (RF07-RF10, RF12)
     * ============================================================ */

    public static function agroindustriaConsolidado($filtros = []) {
        // Receitas
        $whereR = ["r.excluido = 0"];
        $paramsR = [];
        self::filtroAssociado($whereR, $paramsR, 'r.associado_id', $filtros);
        self::filtroEnum($whereR, $paramsR, 'r.status', $filtros['status'] ?? '');

        $sqlR = "SELECT r.*,
                    a.nome AS associado_nome, a.sobrenome AS associado_sobrenome,
                    (SELECT COUNT(*) FROM ingredientes_receita i WHERE i.receita_id = r.id) AS total_ingredientes
                FROM receitas_agroindustria r
                LEFT JOIN associados a ON r.associado_id = a.id
                WHERE " . implode(' AND ', $whereR) . "
                ORDER BY r.criado_em DESC";
        $stmt = self::db()->prepare($sqlR);
        $stmt->execute($paramsR);
        $receitas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados_receitas = [];
        foreach ($receitas as $r) {
            $dados_receitas[] = [
                'produto' => $r['nome_produto_final'],
                'descricao' => $r['descricao'] ?? '—',
                'org' => $r['percentual_organico_calculado'] !== null ? number_format((float)$r['percentual_organico_calculado'], 2, ',', '.') . '%' : '—',
                'selo' => $r['selo_autorizado'] ?? '—',
                'ingredientes' => $r['total_ingredientes'],
                'status' => $r['status'],
                'associado' => trim(($r['associado_nome'] ?? '') . ' ' . ($r['associado_sobrenome'] ?? '')),
            ];
        }

        // Ordens de produção
        $whereO = ["o.excluido = 0"];
        $paramsO = [];
        self::filtroPeriodo($whereO, $paramsO, 'DATE(o.data_processamento)', $filtros);
        self::filtroAssociado($whereO, $paramsO, 'o.associado_id', $filtros);

        $sqlO = "SELECT o.*, r.nome_produto_final,
                    a.nome AS associado_nome, a.sobrenome AS associado_sobrenome
                FROM ordens_producao o
                JOIN receitas_agroindustria r ON o.receita_id = r.id
                LEFT JOIN associados a ON o.associado_id = a.id
                WHERE " . implode(' AND ', $whereO) . "
                ORDER BY o.data_processamento DESC";
        $stmt = self::db()->prepare($sqlO);
        $stmt->execute($paramsO);
        $ops = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados_ops = [];
        foreach ($ops as $o) {
            $dados_ops[] = [
                'lote' => $o['lote_processado_final'],
                'data' => date('d/m/Y', strtotime($o['data_processamento'])),
                'produto' => $o['nome_produto_final'],
                'qtd' => number_format((float)$o['quantidade_final_obtida'], 2, ',', '.'),
                'unidade' => $o['unidade_medida'],
                'org' => $o['percentual_organico'] !== null ? number_format((float)$o['percentual_organico'], 2, ',', '.') . '%' : '—',
                'selo' => $o['selo_autorizado'] ?? '—',
                'status' => $o['status_auditoria'] ?? 'EM_ANALISE',
                'associado' => trim(($o['associado_nome'] ?? '') . ' ' . ($o['associado_sobrenome'] ?? '')),
            ];
        }

        return [
            'titulo' => 'Relatório Consolidado de Agroindústria',
            'descricao' => 'RF07–RF10 e RF12 — Receitas (produto final orgânico), ordens de produção e vínculos de rastreabilidade.',
            'resumo' => [
                ['label' => 'Receitas', 'valor' => count($dados_receitas)],
                ['label' => 'Ordens de Produção', 'valor' => count($dados_ops)],
                ['label' => 'Com Selo POB', 'valor' => count(array_filter($dados_ops, fn($d) => strpos($d['selo'], 'PRODUTO_ORGANICO') !== false))],
            ],
            'tabelas' => [
                [
                    'titulo' => 'Receitas de agroindústria',
                    'colunas' => ['Produto Final', 'Descrição', '% Orgânico', 'Selo Autorizado', 'Ingredientes', 'Status', 'Associado'],
                    'campos' => ['produto', 'descricao', 'org', 'selo', 'ingredientes', 'status', 'associado'],
                    'dados' => $dados_receitas,
                ],
                [
                    'titulo' => 'Ordens de produção',
                    'colunas' => ['Lote Final', 'Data', 'Produto', 'Qtd.', 'Un.', '% Orgânico', 'Selo', 'Status Auditoria', 'Associado'],
                    'campos' => ['lote', 'data', 'produto', 'qtd', 'unidade', 'org', 'selo', 'status', 'associado'],
                    'dados' => $dados_ops,
                ],
            ],
        ];
    }

    /* ============================================================
     * 8) LIVRO DE HIGIENIZAÇÃO (RF08)
     * ============================================================ */

    public static function higienizacao($filtros = []) {
        $where = ["h.excluido = 0"];
        $params = [];
        self::filtroPeriodo($where, $params, 'DATE(h.data_hora_inicio)', $filtros);
        self::filtroAssociado($where, $params, 'h.associado_id', $filtros);

        $sql = "SELECT h.*, a.nome AS associado_nome, a.sobrenome AS associado_sobrenome
                FROM controle_higienizacao h
                LEFT JOIN associados a ON h.associado_id = a.id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY h.data_hora_inicio DESC";

        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados = [];
        $validadas = 0;
        foreach ($rows as $r) {
            $fim = !empty($r['data_hora_fim']) ? date('d/m/Y H:i', strtotime($r['data_hora_fim'])) : 'Em andamento';
            if (!empty($r['data_hora_fim'])) { $validadas++; }
            $dados[] = [
                'data' => date('d/m/Y H:i', strtotime($r['data_hora_inicio'])),
                'fim' => $fim,
                'equipamento' => $r['equipamento_linha'] ?? '—',
                'sanitizante' => $r['sanitizante_utilizado'] ?? '—',
                'responsavel' => $r['responsavel_nome'] ?? '—',
                'associado' => trim(($r['associado_nome'] ?? '') . ' ' . ($r['associado_sobrenome'] ?? '')),
                'observacoes' => $r['observacoes'] ?? '—',
            ];
        }

        return [
            'titulo' => 'Livro de Higienização',
            'descricao' => 'RF08 — Registro de higienização de equipamentos e linhas de produção da agroindústria.',
            'resumo' => [
                ['label' => 'Registros', 'valor' => count($dados)],
                ['label' => 'Concluídas', 'valor' => $validadas],
                ['label' => 'Em Andamento', 'valor' => count($dados) - $validadas],
            ],
            'tabelas' => [[
                'titulo' => 'Registros de higienização',
                'colunas' => ['Início', 'Fim', 'Equipamento/Linha', 'Sanitizante', 'Responsável', 'Associado', 'Observações'],
                'campos' => ['data', 'fim', 'equipamento', 'sanitizante', 'responsavel', 'associado', 'observacoes'],
                'dados' => $dados,
            ]],
        ];
    }

    /* ============================================================
     * 9) PRODUÇÃO ANIMAL CONSOLIDADO (Portaria MAPA 52/2021, Título III)
     * ============================================================ */

    public static function producaoAnimalConsolidado($filtros = []) {
        // Lotes
        $whereL = ["l.excluido = 0"];
        $paramsL = [];
        self::filtroAssociado($whereL, $paramsL, 'l.associado_id', $filtros);

        $sqlL = "SELECT l.*, a.nome AS associado_nome, a.sobrenome AS associado_sobrenome,
                    (SELECT COUNT(*) FROM animais an WHERE an.lote_id = l.id AND an.excluido = 0) AS animais_total
                FROM animal_lotes l
                LEFT JOIN associados a ON l.associado_id = a.id
                WHERE " . implode(' AND ', $whereL) . "
                ORDER BY l.criado_em DESC";
        $stmt = self::db()->prepare($sqlL);
        $stmt->execute($paramsL);
        $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados_lotes = [];
        foreach ($lotes as $l) {
            $dados_lotes[] = [
                'lote' => $l['nome_lote'],
                'especie' => $l['especie'] ?? '—',
                'categoria' => $l['categoria'] ?? '—',
                'quantidade' => (int)$l['quantidade'],
                'animais' => (int)$l['animais_total'],
                'associado' => trim(($l['associado_nome'] ?? '') . ' ' . ($l['associado_sobrenome'] ?? '')),
            ];
        }

        // Alimentação (art. 34-37)
        $whereA = ["c.excluido = 0"];
        $paramsA = [];
        self::filtroPeriodo($whereA, $paramsA, 'c.data_alimentacao', $filtros);
        self::filtroAssociado($whereA, $paramsA, 'c.associado_id', $filtros);

        $sqlA = "SELECT c.*, a.nome_animal, l.nome_lote,
                    aso.nome AS associado_nome, aso.sobrenome AS associado_sobrenome
                FROM controle_alimentacao_animal c
                LEFT JOIN animais a ON a.id = c.animal_id
                LEFT JOIN animal_lotes l ON l.id = c.lote_id
                LEFT JOIN associados aso ON c.associado_id = aso.id
                WHERE " . implode(' AND ', $whereA) . "
                ORDER BY c.data_alimentacao DESC LIMIT 5000";
        $stmt = self::db()->prepare($sqlA);
        $stmt->execute($paramsA);
        $alim = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados_alim = [];
        $nao_organico_ms = [15.0, 20.0];
        foreach ($alim as $r) {
            $dados_alim[] = [
                'data' => date('d/m/Y', strtotime($r['data_alimentacao'])),
                'animal' => $r['nome_animal'] ?? 'Lote: ' . ($r['nome_lote'] ?? '—'),
                'tipo' => $r['tipo_alimento'],
                'produto' => $r['produto_utilizado'] ?? '—',
                'kg' => $r['quantidade_kg'] !== null ? $r['quantidade_kg'] : '—',
                'ms' => $r['percentual_materia_seca'] !== null ? $r['percentual_materia_seca'] . '%' : '—',
                'origem' => $r['origem'],
                'associado' => trim(($r['associado_nome'] ?? '') . ' ' . ($r['associado_sobrenome'] ?? '')),
            ];
        }

        // Sanidade (art. 59-63)
        $whereS = ["c.excluido = 0"];
        $paramsS = [];
        self::filtroPeriodo($whereS, $paramsS, 'c.data_tratamento', $filtros);
        self::filtroAssociado($whereS, $paramsS, 'c.associado_id', $filtros);

        $sqlS = "SELECT c.*, a.nome_animal, l.nome_lote,
                    aso.nome AS associado_nome, aso.sobrenome AS associado_sobrenome
                FROM controle_sanidade_animal c
                LEFT JOIN animais a ON a.id = c.animal_id
                LEFT JOIN animal_lotes l ON l.id = c.lote_id
                LEFT JOIN associados aso ON c.associado_id = aso.id
                WHERE " . implode(' AND ', $whereS) . "
                ORDER BY c.data_tratamento DESC LIMIT 5000";
        $stmt = self::db()->prepare($sqlS);
        $stmt->execute($paramsS);
        $sans = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados_sans = [];
        $excepcional = 0;
        foreach ($sans as $r) {
            if (empty($r['produto_normatizado'])) { $excepcional++; }
            $dados_sans[] = [
                'data' => date('d/m/Y', strtotime($r['data_tratamento'])),
                'animal' => $r['nome_animal'] ?? 'Lote: ' . ($r['nome_lote'] ?? '—'),
                'tipo' => $r['tipo_tratamento'],
                'produto' => $r['produto_utilizado'] ?? '—',
                'carencia' => $r['periodo_carencia_horas'] !== null ? $r['periodo_carencia_horas'] . 'h' : '—',
                'norma' => !empty($r['produto_normatizado']) ? 'Anexo II' : 'Excepcional',
                'liberacao' => !empty($r['data_liberacao_organica']) ? date('d/m/Y', strtotime($r['data_liberacao_organica'])) : '—',
                'associado' => trim(($r['associado_nome'] ?? '') . ' ' . ($r['associado_sobrenome'] ?? '')),
            ];
        }

        // Reprodução
        $whereR = ["c.excluido = 0"];
        $paramsR = [];
        self::filtroPeriodo($whereR, $paramsR, 'c.data_evento', $filtros);
        self::filtroAssociado($whereR, $paramsR, 'c.associado_id', $filtros);

        $sqlR = "SELECT c.*, a.nome_animal,
                    aso.nome AS associado_nome, aso.sobrenome AS associado_sobrenome
                FROM controle_reproducao_animal c
                LEFT JOIN animais a ON a.id = c.animal_id
                LEFT JOIN associados aso ON c.associado_id = aso.id
                WHERE " . implode(' AND ', $whereR) . "
                ORDER BY c.data_evento DESC LIMIT 5000";
        $stmt = self::db()->prepare($sqlR);
        $stmt->execute($paramsR);
        $reps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dados_reps = [];
        foreach ($reps as $r) {
            $dados_reps[] = [
                'data' => date('d/m/Y', strtotime($r['data_evento'])),
                'animal' => $r['nome_animal'] ?? '—',
                'evento' => $r['tipo_evento'],
                'reprodutor' => $r['macho_reprodutor_id'] ?? '—',
                'vivos' => $r['nascidos_vivos'] !== null ? $r['nascidos_vivos'] : '—',
                'natimortos' => $r['natimortos'] !== null ? $r['natimortos'] : '—',
                'aleitamento' => $r['periodo_aleitamento_dias'] !== null ? $r['periodo_aleitamento_dias'] . 'd' : '—',
                'associado' => trim(($r['associado_nome'] ?? '') . ' ' . ($r['associado_sobrenome'] ?? '')),
            ];
        }

        return [
            'titulo' => 'Relatório de Produção Animal',
            'descricao' => 'Portaria MAPA 52/2021 (Título III) — Lotes, alimentação, sanidade e reprodução do rebanho orgânico.',
            'resumo' => [
                ['label' => 'Lotes', 'valor' => count($dados_lotes)],
                ['label' => 'Registros de Alimentação', 'valor' => count($dados_alim)],
                ['label' => 'Registros de Sanidade', 'valor' => count($dados_sans)],
                ['label' => 'Tratamentos Excepcionais', 'valor' => $excepcional],
                ['label' => 'Eventos Reprodutivos', 'valor' => count($dados_reps)],
            ],
            'tabelas' => [
                [
                    'titulo' => 'Lotes de animais',
                    'colunas' => ['Lote', 'Espécie', 'Categoria', 'Qtd.', 'Animais', 'Associado'],
                    'campos' => ['lote', 'especie', 'categoria', 'quantidade', 'animais', 'associado'],
                    'dados' => $dados_lotes,
                ],
                [
                    'titulo' => 'Controle de alimentação (art. 34-37)',
                    'colunas' => ['Data', 'Animal/Lote', 'Tipo', 'Produto', 'Kg', '% MS', 'Origem', 'Associado'],
                    'campos' => ['data', 'animal', 'tipo', 'produto', 'kg', 'ms', 'origem', 'associado'],
                    'dados' => $dados_alim,
                ],
                [
                    'titulo' => 'Controle de sanidade (art. 59-63)',
                    'colunas' => ['Data', 'Animal/Lote', 'Tipo', 'Produto', 'Carência', 'Norma', 'Liberação', 'Associado'],
                    'campos' => ['data', 'animal', 'tipo', 'produto', 'carencia', 'norma', 'liberacao', 'associado'],
                    'dados' => $dados_sans,
                ],
                [
                    'titulo' => 'Controle de reprodução',
                    'colunas' => ['Data', 'Animal', 'Evento', 'Reprodutor', 'Vivos', 'Natimortos', 'Aleitamento', 'Associado'],
                    'campos' => ['data', 'animal', 'evento', 'reprodutor', 'vivos', 'natimortos', 'aleitamento', 'associado'],
                    'dados' => $dados_reps,
                ],
            ],
        ];
    }

    /**
     * Lista dos relatórios disponíveis com metadados (usado na página e no gerador).
     */
    public static function catalogo() {
        return [
            'balanco-massas' => [
                'metodo' => 'balancoMassas',
                'titulo' => 'Balanço de Massas',
                'icone' => 'bi bi-arrow-left-right',
                'descricao' => 'RF11 — Entrada x saída por ordem de produção, divergência e status de auditoria.',
                'filtros' => ['periodo', 'status', 'associado'],
            ],
            'trilha-auditoria' => [
                'metodo' => 'trilhaAuditoria',
                'titulo' => 'Trilha de Auditoria',
                'icone' => 'bi bi-journal-check',
                'descricao' => 'NFR03 — Logs de ações e acessos registrados no sistema.',
                'filtros' => ['periodo'],
            ],
            'rastreabilidade' => [
                'metodo' => 'rastreabilidadeConsolidada',
                'titulo' => 'Rastreabilidade Consolidada',
                'icone' => 'bi bi-graph-up-arrow',
                'descricao' => 'RF12 — Cadeia do lote final até os lotes primários de origem.',
                'filtros' => ['periodo', 'associado'],
            ],
            'validacao-insumos' => [
                'metodo' => 'validacaoInsumos',
                'titulo' => 'Validação de Insumos',
                'icone' => 'bi bi-shield-check',
                'descricao' => 'RF06 — Status orgânico dos insumos frente à Portaria 52/2021.',
                'filtros' => ['tipo', 'associado'],
            ],
            'historico-conversao' => [
                'metodo' => 'historicoConversao',
                'titulo' => 'Histórico de Conversão',
                'icone' => 'bi bi-clock-history',
                'descricao' => 'RF03 — Conversão das propriedades para produção orgânica.',
                'filtros' => ['periodo', 'status', 'associado'],
            ],
            'manejo-animal' => [
                'metodo' => 'manejoAnimal',
                'titulo' => 'Manejo Animal',
                'icone' => 'bi bi-egg-fill',
                'descricao' => 'RF05 — Rebanho, origem de conversão e registros de manejo.',
                'filtros' => ['periodo', 'status', 'associado'],
            ],
            'agroindustria' => [
                'metodo' => 'agroindustriaConsolidado',
                'titulo' => 'Agroindústria Consolidado',
                'icone' => 'bi bi-buildings',
                'descricao' => 'RF07–RF10/RF12 — Receitas, ordens de produção e selos autorizados.',
                'filtros' => ['periodo', 'status', 'associado'],
            ],
            'higienizacao' => [
                'metodo' => 'higienizacao',
                'titulo' => 'Livro de Higienização',
                'icone' => 'bi bi-droplet',
                'descricao' => 'RF08 — Registro de higienização de equipamentos e linhas.',
                'filtros' => ['periodo', 'associado'],
            ],
            'producao-animal' => [
                'metodo' => 'producaoAnimalConsolidado',
                'titulo' => 'Produção Animal',
                'icone' => 'bi bi-egg-fill',
                'descricao' => 'Portaria 52/2021 — Lotes, alimentação, sanidade e reprodução do rebanho.',
                'filtros' => ['periodo', 'associado'],
            ],
        ];
    }
}
