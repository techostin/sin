<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * DataTables por funcionalidade.
 *
 * Cada definição espelha a tela de listagem correspondente do sistema atual
 * (`modulos/associados/listar.php`, `modulos/certificacao/listar.php`,
 * `modulos/roteiros_inspecao/visitas/listar.php`, `modulos/mensalidades/listar.php`,
 * `modulos/assembleia/listar.php`, `modulos/associados/carteirinha_listar.php`,
 * `modulos/caderno_campo/...`, `modulos/agroindustria/ordens_listar.php` e
 * `certificadoras_list.php`): mesmas colunas, mesma ordem e ações equivalentes.
 *
 * As consultas são somente leitura (limite 500); busca, ordenação e paginação
 * são do DataTables local.
 */
final class Tabelas
{
    /** @var array<string, array<string, array<string, mixed>>> */
    private const DEFS = [
        'assoc' => [
            'associados.quadro' => [
                'titulo' => 'Quadro de associados',
                'descricao' => 'Mesma listagem do sistema atual: nome, contato, cidade, status e nível.',
                'colunas' => [
                    ['campo' => 'nome', 'rotulo' => 'Nome completo', 'tipo' => 'texto'],
                    ['campo' => 'email', 'rotulo' => 'E-mail', 'tipo' => 'texto'],
                    ['campo' => 'telefone', 'rotulo' => 'Telefone', 'tipo' => 'texto'],
                    ['campo' => 'cidade', 'rotulo' => 'Cidade', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                    ['campo' => 'nivel', 'rotulo' => 'Nível', 'tipo' => 'nivel'],
                ],
                'sql' => "SELECT CONCAT(a.nome, ' ', a.sobrenome) AS nome, a.email, a.telefone,
                                 CONCAT(COALESCE(a.cidade, '—'), '/', COALESCE(a.estado, '—')) AS cidade,
                                 a.status, a.nivel
                            FROM associados a
                           WHERE a.excluido_em IS NULL
                           ORDER BY a.nome LIMIT 500",
                'acoes' => ['Editar', 'Criar ficha de adesão', 'Excluir'],
            ],
            'associados.novo' => [
                'titulo' => 'Novo associado',
                'descricao' => 'Cadastros mais recentes, base para o fluxo de inclusão de associado.',
                'colunas' => [
                    ['campo' => 'nome', 'rotulo' => 'Nome completo', 'tipo' => 'texto'],
                    ['campo' => 'email', 'rotulo' => 'E-mail', 'tipo' => 'texto'],
                    ['campo' => 'telefone', 'rotulo' => 'Telefone', 'tipo' => 'texto'],
                    ['campo' => 'cidade', 'rotulo' => 'Cidade', 'tipo' => 'texto'],
                    ['campo' => 'data_cadastro', 'rotulo' => 'Cadastro', 'tipo' => 'datahora'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                ],
                'sql' => "SELECT CONCAT(nome, ' ', sobrenome) AS nome, email, telefone,
                                 CONCAT(COALESCE(cidade, '—'), '/', COALESCE(estado, '—')) AS cidade,
                                 data_cadastro, status
                            FROM associados
                           WHERE excluido_em IS NULL
                           ORDER BY data_cadastro DESC LIMIT 200",
                'acoes' => ['Novo associado'],
            ],
            'adesoes.aprovar' => [
                'titulo' => 'Adesões e fichas',
                'descricao' => 'Fichas de adesão aguardando análise da Diretoria (pendentes e recusadas).',
                'colunas' => [
                    ['campo' => 'nome', 'rotulo' => 'Associado(a)', 'tipo' => 'texto'],
                    ['campo' => 'tipo_associado', 'rotulo' => 'Tipo de associado', 'tipo' => 'tipo_associado'],
                    ['campo' => 'cpf', 'rotulo' => 'CPF', 'tipo' => 'cpf'],
                    ['campo' => 'municipio', 'rotulo' => 'Município', 'tipo' => 'texto'],
                    ['campo' => 'data_submissao', 'rotulo' => 'Submetida em', 'tipo' => 'datahora'],
                    ['campo' => 'numero_cadastro', 'rotulo' => 'Nº de cadastro', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Resultado', 'tipo' => 'badge'],
                    ['campo' => 'data_analise', 'rotulo' => 'Analisada em', 'tipo' => 'datahora'],
                ],
                'sql' => "SELECT COALESCE(f.nome_completo, '—') AS nome, f.tipo_associado, f.cpf,
                                 CONCAT(COALESCE(f.municipio, '—'), '/', COALESCE(f.estado, '—')) AS municipio,
                                 f.data_submissao, f.numero_cadastro, f.status, f.data_analise
                            FROM ficha_adesao f
                           WHERE f.status IN ('pendente', 'recusada')
                           ORDER BY f.data_submissao ASC LIMIT 200",
                'acoes' => ['Visualizar ficha', 'Aprovar', 'Recusar'],
                'toolbar' => 'pendencias.php',
                'toolbar_rotulo' => 'Abrir tela de aprovação',
            ],
            'aprovacoes.pendencias' => [
                'titulo' => 'Liberar acessos e fichas',
                'descricao' => 'Contas do pré-cadastro aguardando liberação (mesma fila de "Cadastros Pendentes").',
                'colunas' => [
                    ['campo' => 'nome', 'rotulo' => 'Nome', 'tipo' => 'texto'],
                    ['campo' => 'email', 'rotulo' => 'E-mail', 'tipo' => 'texto'],
                    ['campo' => 'telefone', 'rotulo' => 'Telefone', 'tipo' => 'texto'],
                    ['campo' => 'perfil_solicitado', 'rotulo' => 'Perfil solicitado', 'tipo' => 'texto'],
                    ['campo' => 'nivel_solicitado', 'rotulo' => 'Nível solicitado', 'tipo' => 'nivel'],
                    ['campo' => 'created_at', 'rotulo' => 'Recebida em', 'tipo' => 'datahora'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                ],
                'sql' => "SELECT CONCAT(nome, ' ', sobrenome) AS nome, email, telefone,
                                 COALESCE(perfil_solicitado, '—') AS perfil_solicitado,
                                 COALESCE(nivel_solicitado, nivel) AS nivel_solicitado,
                                 created_at, status
                            FROM usuarios
                           WHERE status = 'pendente'
                           ORDER BY created_at ASC LIMIT 200",
                'acoes' => ['Liberar acesso', 'Negar'],
                'toolbar' => 'pendencias.php',
                'toolbar_rotulo' => 'Abrir pendências',
            ],
            'financeiro.mensalidades' => [
                'titulo' => 'Mensalidades e caixa',
                'descricao' => 'Cobranças, planos, vencimentos e pagamentos (mesma listagem atual).',
                'colunas' => [
                    ['campo' => 'associado', 'rotulo' => 'Associado', 'tipo' => 'texto'],
                    ['campo' => 'referencia', 'rotulo' => 'Ref.', 'tipo' => 'texto'],
                    ['campo' => 'associacao', 'rotulo' => 'Associação', 'tipo' => 'texto'],
                    ['campo' => 'plano', 'rotulo' => 'Plano', 'tipo' => 'texto'],
                    ['campo' => 'data_vencimento', 'rotulo' => 'Vencimento', 'tipo' => 'data'],
                    ['campo' => 'valor_cobrado', 'rotulo' => 'Valor', 'tipo' => 'valor'],
                    ['campo' => 'status_mensalidade', 'rotulo' => 'Situação', 'tipo' => 'badge'],
                    ['campo' => 'data_pagamento', 'rotulo' => 'Pagamento', 'tipo' => 'data'],
                ],
                'sql' => "SELECT CONCAT(a.nome, ' ', a.sobrenome) AS associado,
                                 CONCAT(m.ano_referencia, '/', LPAD(m.mes_referencia, 2, '0')) AS referencia,
                                 COALESCE(ass.nome, '—') AS associacao,
                                 COALESCE(p.nome, '—') AS plano,
                                 m.data_vencimento, m.valor_cobrado, m.status_mensalidade, m.data_pagamento
                            FROM mensalidades m
                            JOIN associados a ON a.id = m.associado_id
                            LEFT JOIN associacoes ass ON ass.id = m.associacao_id
                            LEFT JOIN planos_mensalidades p ON p.id = m.plano_id
                           ORDER BY m.ano_referencia DESC, m.mes_referencia DESC LIMIT 500",
                'acoes' => ['Cobrança', 'Estornar pagamento', 'Excluir'],
            ],
            'governanca.assembleias' => [
                'titulo' => 'Assembleias e eventos',
                'descricao' => 'Assembleias, período, pautas e votos (mesma listagem atual).',
                'colunas' => [
                    ['campo' => 'titulo', 'rotulo' => 'Título', 'tipo' => 'texto'],
                    ['campo' => 'associacao', 'rotulo' => 'Associação', 'tipo' => 'texto'],
                    ['campo' => 'periodo', 'rotulo' => 'Período', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                    ['campo' => 'pautas', 'rotulo' => 'Pautas', 'tipo' => 'texto'],
                    ['campo' => 'votos', 'rotulo' => 'Votos', 'tipo' => 'texto'],
                ],
                'sql' => "SELECT a.titulo, COALESCE(ass.nome, '—') AS associacao,
                                 CONCAT(DATE_FORMAT(a.data_inicio, '%d/%m/%Y %H:%i'), ' até ',
                                        DATE_FORMAT(a.data_fim, '%d/%m/%Y %H:%i')) AS periodo,
                                 a.status,
                                 (SELECT COUNT(*) FROM assembleia_pautas p WHERE p.assembleia_id = a.id) AS pautas,
                                 (SELECT COUNT(*) FROM assembleia_votos v WHERE v.assembleia_id = a.id) AS votos
                            FROM assembleias a
                            LEFT JOIN associacoes ass ON ass.id = a.associacao_id
                           ORDER BY a.data_inicio DESC LIMIT 200",
                'acoes' => ['Editar', 'Ver', 'Publicar/Ativar', 'Ver resultado'],
            ],
            'documentos.consultar' => [
                'titulo' => 'Documentos (carteirinhas)',
                'descricao' => 'Carteirinhas emitidas, validade e situação do associado (mesma listagem atual).',
                'colunas' => [
                    ['campo' => 'associado', 'rotulo' => 'Associado', 'tipo' => 'texto'],
                    ['campo' => 'email', 'rotulo' => 'E-mail', 'tipo' => 'texto'],
                    ['campo' => 'categoria', 'rotulo' => 'Categoria', 'tipo' => 'texto'],
                    ['campo' => 'situacao', 'rotulo' => 'Situação', 'tipo' => 'badge'],
                    ['campo' => 'financeiro', 'rotulo' => 'Financeiro', 'tipo' => 'badge'],
                    ['campo' => 'validade', 'rotulo' => 'Validade', 'tipo' => 'data'],
                ],
                'sql' => "SELECT CONCAT(a.nome, ' ', a.sobrenome) AS associado, a.email,
                                 COALESCE(a.tipo_associado, '—') AS categoria,
                                 COALESCE(a.status, '—') AS situacao,
                                 COALESCE(a.status_associado, '—') AS financeiro,
                                 c.validade
                            FROM carteirinhas c
                            JOIN associados a ON a.id = c.associado_id
                           ORDER BY c.gerada_em DESC LIMIT 500",
                'acoes' => ['Gerar carteirinha', 'Renovar', 'Revogar'],
            ],
            'documentos.gerir' => [
                'titulo' => 'Gestão de documentos',
                'descricao' => 'Emissão, registro e revogação de documentos da associação.',
                'colunas' => [
                    ['campo' => 'numero', 'rotulo' => 'Número', 'tipo' => 'texto'],
                    ['campo' => 'tipo', 'rotulo' => 'Tipo', 'tipo' => 'texto'],
                    ['campo' => 'associado', 'rotulo' => 'Associado', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                    ['campo' => 'emitido_em', 'rotulo' => 'Emitido em', 'tipo' => 'datahora'],
                ],
                'sql' => "SELECT d.numero, d.tipo,
                                 CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS associado,
                                 d.status, d.emitido_em
                            FROM documentos_emitidos d
                            LEFT JOIN associados a ON a.id = d.associado_id
                           ORDER BY d.created_at DESC LIMIT 500",
                'acoes' => ['Emitir documento', 'Revogar', 'Ver arquivo'],
            ],
            'relatorios.assoc' => [
                'titulo' => 'Relatórios da associação',
                'descricao' => 'Relatórios do módulo de associação.',
                'colunas' => [
                    ['campo' => 'relatorio', 'rotulo' => 'Relatório', 'tipo' => 'texto'],
                    ['campo' => 'descricao', 'rotulo' => 'Conteúdo', 'tipo' => 'texto'],
                ],
                'linhas' => [
                    ['relatorio' => 'Associados por município', 'descricao' => 'Distribuição territorial do quadro associativo'],
                    ['relatorio' => 'Associados por microrregião', 'descricao' => 'Base para os relatórios da diretoria'],
                    ['relatorio' => 'Mensalidades e inadimplência', 'descricao' => 'Cobranças em aberto e pagamentos'],
                    ['relatorio' => 'Fichas e adesões', 'descricao' => 'Submetidas, aprovadas e recusadas'],
                    ['relatorio' => 'Carteirinhas emitidas', 'descricao' => 'Validade e revogações'],
                ],
                'acoes' => ['Gerar PDF', 'Gerar Excel'],
            ],
            'portal.meu-cadastro' => [
                'titulo' => 'Meu cadastro',
                'descricao' => 'Seus dados de associado e situação do vínculo.',
                'colunas' => [
                    ['campo' => 'nome', 'rotulo' => 'Nome', 'tipo' => 'texto'],
                    ['campo' => 'email', 'rotulo' => 'E-mail', 'tipo' => 'texto'],
                    ['campo' => 'telefone', 'rotulo' => 'Telefone', 'tipo' => 'texto'],
                    ['campo' => 'municipio', 'rotulo' => 'Município', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Situação', 'tipo' => 'badge'],
                ],
                'pessoal' => true,
                'sql_associado' => "SELECT CONCAT(nome, ' ', sobrenome) AS nome, email, telefone,
                                           CONCAT(COALESCE(cidade, '—'), '/', COALESCE(estado, '—')) AS municipio,
                                           COALESCE(status_associado, status) AS status
                                      FROM associados WHERE id = :pessoa_id LIMIT 1",
                'sql_usuario' => "SELECT CONCAT(nome, ' ', sobrenome) AS nome, email, telefone,
                                         '—' AS municipio, status
                                    FROM usuarios WHERE id = :pessoa_id LIMIT 1",
                'acoes' => ['Editar meus dados'],
            ],
            'membro.minha-ficha' => [
                'titulo' => 'Minha ficha',
                'descricao' => 'Situação da sua ficha de adesão ao quadro associativo.',
                'colunas' => [
                    ['campo' => 'numero_cadastro', 'rotulo' => 'Nº de cadastro', 'tipo' => 'texto'],
                    ['campo' => 'tipo_associado', 'rotulo' => 'Tipo', 'tipo' => 'tipo_associado'],
                    ['campo' => 'status', 'rotulo' => 'Situação', 'tipo' => 'badge'],
                    ['campo' => 'data_submissao', 'rotulo' => 'Submetida em', 'tipo' => 'datahora'],
                    ['campo' => 'data_analise', 'rotulo' => 'Analisada em', 'tipo' => 'datahora'],
                ],
                'pessoal' => true,
                'sql_associado' => "SELECT numero_cadastro, tipo_associado, status, data_submissao, data_analise
                                      FROM ficha_adesao WHERE associado_id = :pessoa_id ORDER BY id DESC LIMIT 5",
                'sql_usuario' => 'SELECT 1 AS x WHERE 1 = 0',
                'acoes' => ['Ver ficha completa'],
            ],
            'membro.meus-documentos' => [
                'titulo' => 'Meus documentos',
                'descricao' => 'Carteirinha de associado, validade e situação.',
                'colunas' => [
                    ['campo' => 'codigo', 'rotulo' => 'Carteirinha', 'tipo' => 'texto'],
                    ['campo' => 'validade', 'rotulo' => 'Validade', 'tipo' => 'data'],
                    ['campo' => 'gerada_em', 'rotulo' => 'Emitida em', 'tipo' => 'datahora'],
                    ['campo' => 'situacao', 'rotulo' => 'Situação', 'tipo' => 'badge'],
                ],
                'pessoal' => true,
                'sql_associado' => "SELECT CONCAT('••••', RIGHT(token, 6)) AS codigo, validade, gerada_em,
                                           CASE WHEN revogada_em IS NULL THEN 'Ativa' ELSE 'Revogada' END AS situacao
                                      FROM carteirinhas WHERE associado_id = :pessoa_id ORDER BY gerada_em DESC LIMIT 50",
                'sql_usuario' => 'SELECT 1 AS x WHERE 1 = 0',
                'acoes' => ['Baixar carteirinha'],
            ],
            'membro.minhas-mensalidades' => [
                'titulo' => 'Minhas mensalidades',
                'descricao' => 'Seus vencimentos, pagamentos e situação financeira.',
                'colunas' => [
                    ['campo' => 'referencia', 'rotulo' => 'Referência', 'tipo' => 'texto'],
                    ['campo' => 'valor_cobrado', 'rotulo' => 'Valor', 'tipo' => 'valor'],
                    ['campo' => 'data_vencimento', 'rotulo' => 'Vencimento', 'tipo' => 'data'],
                    ['campo' => 'data_pagamento', 'rotulo' => 'Pagamento', 'tipo' => 'data'],
                    ['campo' => 'status_mensalidade', 'rotulo' => 'Situação', 'tipo' => 'badge'],
                ],
                'pessoal' => true,
                'sql_associado' => "SELECT CONCAT(ano_referencia, '/', LPAD(mes_referencia, 2, '0')) AS referencia,
                                           valor_cobrado, data_vencimento, data_pagamento, status_mensalidade
                                      FROM mensalidades WHERE associado_id = :pessoa_id
                                      ORDER BY ano_referencia DESC, mes_referencia DESC LIMIT 100",
                'sql_usuario' => 'SELECT 1 AS x WHERE 1 = 0',
                'acoes' => ['Ver comprovante'],
            ],
            'membro.meus-certificados' => [
                'titulo' => 'Meus certificados',
                'descricao' => 'Suas certificações participativas, emissão e validade.',
                'colunas' => [
                    ['campo' => 'tipo_certificacao', 'rotulo' => 'Tipo', 'tipo' => 'texto'],
                    ['campo' => 'numero_certificado', 'rotulo' => 'Número', 'tipo' => 'texto'],
                    ['campo' => 'data_emissao', 'rotulo' => 'Emissão', 'tipo' => 'data'],
                    ['campo' => 'data_validade', 'rotulo' => 'Validade', 'tipo' => 'data'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                ],
                'pessoal' => true,
                'sql_associado' => "SELECT tipo_certificacao, numero_certificado, data_emissao, data_validade, status
                                      FROM certificacao_participativa
                                     WHERE associado_id = :pessoa_id AND excluido = 0
                                     ORDER BY data_emissao DESC LIMIT 100",
                'sql_usuario' => 'SELECT 1 AS x WHERE 1 = 0',
                'acoes' => ['Ver certificado'],
            ],
            'membro.meus-relatorios' => [
                'titulo' => 'Meus relatórios',
                'descricao' => 'Histórico consolidado: ficha, mensalidades e certificações.',
                'colunas' => [
                    ['campo' => 'data', 'rotulo' => 'Data', 'tipo' => 'data'],
                    ['campo' => 'tipo', 'rotulo' => 'Tipo', 'tipo' => 'texto'],
                    ['campo' => 'descricao', 'rotulo' => 'Descrição', 'tipo' => 'texto'],
                ],
                'pessoal' => true,
                'sql_associado' => "SELECT data, tipo, descricao FROM (
                        SELECT data_submissao AS data, 'Ficha de adesão' AS tipo,
                               CONCAT('Ficha ', COALESCE(status, '')) AS descricao
                          FROM ficha_adesao WHERE associado_id = :pessoa_id
                        UNION ALL
                        SELECT data_vencimento AS data, 'Mensalidade' AS tipo,
                               CONCAT('Mensalidade ', COALESCE(status_mensalidade, '')) AS descricao
                          FROM mensalidades WHERE associado_id = :pessoa_id
                        UNION ALL
                        SELECT data_emissao AS data, 'Certificado' AS tipo,
                               CONCAT(COALESCE(tipo_certificacao, ''), ' ', COALESCE(numero_certificado, '')) AS descricao
                          FROM certificacao_participativa WHERE associado_id = :pessoa_id AND excluido = 0
                    ) h ORDER BY data DESC LIMIT 100",
                'sql_usuario' => 'SELECT 1 AS x WHERE 1 = 0',
                'acoes' => ['Gerar PDF'],
            ],
        ],

        'social' => [
            'certificacoes.listar' => [
                'titulo' => 'Certificações (SPG/OCS)',
                'descricao' => 'Certificações participativas com emissão, validade e organismo (listagem atual).',
                'colunas' => [
                    ['campo' => 'tipo_certificacao', 'rotulo' => 'Tipo', 'tipo' => 'texto'],
                    ['campo' => 'numero_certificado', 'rotulo' => 'Nº certificado', 'tipo' => 'texto'],
                    ['campo' => 'associado', 'rotulo' => 'Associado', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                    ['campo' => 'data_emissao', 'rotulo' => 'Emissão', 'tipo' => 'data'],
                    ['campo' => 'data_validade', 'rotulo' => 'Validade', 'tipo' => 'data'],
                    ['campo' => 'organismo', 'rotulo' => 'Organismo', 'tipo' => 'texto'],
                ],
                'sql' => "SELECT c.tipo_certificacao, c.numero_certificado,
                                 CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS associado,
                                 c.status, c.data_emissao, c.data_validade,
                                 COALESCE(NULLIF(c.organismo_certificador, ''), cert.nome, '—') AS organismo
                            FROM certificacao_participativa c
                            LEFT JOIN associados a ON a.id = c.associado_id
                            LEFT JOIN certificadoras cert ON cert.id = c.certificadora_id
                           WHERE c.excluido = 0
                           ORDER BY c.data_emissao DESC LIMIT 500",
                'acoes' => ['Editar', 'Renovar', 'Ver histórico', 'Ver detalhes', 'Excluir'],
            ],
            'certificadoras.listar' => [
                'titulo' => 'Certificadoras',
                'descricao' => 'OPACs, OCSs e certificadoras com contato e logo (listagem atual).',
                'colunas' => [
                    ['campo' => 'nome', 'rotulo' => 'Nome', 'tipo' => 'texto'],
                    ['campo' => 'cidade', 'rotulo' => 'Cidade/UF', 'tipo' => 'texto'],
                    ['campo' => 'contato', 'rotulo' => 'Contato', 'tipo' => 'texto'],
                    ['campo' => 'logo', 'rotulo' => 'Logo', 'tipo' => 'texto'],
                ],
                'sql' => "SELECT nome,
                                 CONCAT(COALESCE(cidade, '—'), '/', COALESCE(uf, estado, '—')) AS cidade,
                                 COALESCE(NULLIF(contato, ''), NULLIF(email, ''), NULLIF(telefone, ''), '—') AS contato,
                                 CASE WHEN logo_filename IS NOT NULL AND logo_filename <> '' THEN 'Sim' ELSE 'Não' END AS logo
                            FROM certificadoras ORDER BY nome LIMIT 500",
                'acoes' => ['Editar', 'Ver membros'],
            ],
            'planos.analise' => [
                'titulo' => 'Planos de Manejo — Análise',
                'descricao' => 'Planos por safra, versão e situação (listagem atual).',
                'colunas' => [
                    ['campo' => 'safra', 'rotulo' => 'Safra', 'tipo' => 'texto'],
                    ['campo' => 'tipo', 'rotulo' => 'Tipo', 'tipo' => 'texto'],
                    ['campo' => 'propriedade', 'rotulo' => 'Propriedade', 'tipo' => 'texto'],
                    ['campo' => 'agricultor', 'rotulo' => 'Agricultor', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                    ['campo' => 'versao', 'rotulo' => 'Versão', 'tipo' => 'texto'],
                    ['campo' => 'created_at', 'rotulo' => 'Data criação', 'tipo' => 'datahora'],
                ],
                'sql' => "SELECT p.ano_safra AS safra,
                                 CASE WHEN p.area_convencional_ha > 0 THEN 'Em conversão' ELSE 'Orgânico' END AS tipo,
                                 COALESCE(pr.propriedade, '—') AS propriedade,
                                 CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS agricultor,
                                 p.status, p.versao, p.created_at
                            FROM plano_manejo p
                            LEFT JOIN propriedades pr ON pr.id = p.propriedade_id
                            LEFT JOIN associados a ON a.id = p.associado_id
                           WHERE p.excluido = 0
                           ORDER BY p.ano_safra DESC LIMIT 500",
                'acoes' => ['Editar', 'Aprovar', 'Reprovar', 'Excluir'],
            ],
            'insumos.aprovados' => [
                'titulo' => 'Insumos Aprovados',
                'descricao' => 'Lista de insumos permitidos para produção orgânica (Portaria 52/2021).',
                'colunas' => [
                    ['campo' => 'categoria', 'rotulo' => 'Categoria', 'tipo' => 'texto'],
                    ['campo' => 'nome_comercial', 'rotulo' => 'Nome comercial', 'tipo' => 'texto'],
                    ['campo' => 'principio', 'rotulo' => 'Princípio ativo / composição', 'tipo' => 'texto'],
                    ['campo' => 'fabricante', 'rotulo' => 'Fabricante / marca', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                ],
                'sql' => "SELECT categoria,
                                 COALESCE(NULLIF(nome_comercial, ''), nome) AS nome_comercial,
                                 COALESCE(NULLIF(principio_ativo, ''), NULLIF(composicao, ''), '—') AS principio,
                                 COALESCE(NULLIF(fabricante, ''), '—') AS fabricante,
                                 CASE WHEN permitido IN (1, '1', 'sim', 'Sim', 'SIM', 'permitido')
                                      THEN 'Permitido' ELSE 'Restrito' END AS status
                            FROM insumos_organicos_aprovados
                           ORDER BY nome_comercial LIMIT 500",
                'acoes' => ['Ver detalhes', 'Exportar lista'],
            ],
            'visitas.inspecoes' => [
                'titulo' => 'Visitas e Inspeções',
                'descricao' => 'Agenda de visitas com propriedade, roteiro, ciência e conformidade (listagem atual).',
                'colunas' => [
                    ['campo' => 'data_visita', 'rotulo' => 'Data', 'tipo' => 'data'],
                    ['campo' => 'propriedade', 'rotulo' => 'Propriedade', 'tipo' => 'texto'],
                    ['campo' => 'agricultor', 'rotulo' => 'Agricultor', 'tipo' => 'texto'],
                    ['campo' => 'roteiro', 'rotulo' => 'Roteiro', 'tipo' => 'texto'],
                    ['campo' => 'ciente', 'rotulo' => 'Ciente', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                    ['campo' => 'conformidade', 'rotulo' => 'Conformidade', 'tipo' => 'badge'],
                ],
                'sql' => "SELECT v.data_visita,
                                 COALESCE(p.propriedade, '—') AS propriedade,
                                 CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS agricultor,
                                 COALESCE(r.nome, '—') AS roteiro,
                                 CASE WHEN v.ciente = 1 THEN 'Sim' ELSE 'Não' END AS ciente,
                                 v.status, v.conformidade
                            FROM visitas_tecnicas v
                            LEFT JOIN associados a ON a.id = v.associado_id
                            LEFT JOIN propriedades p ON p.id = v.propriedade_id
                            LEFT JOIN roteiros_inspecao r ON r.id = v.roteiro_id
                           ORDER BY v.data_visita DESC LIMIT 500",
                'acoes' => ['Agendar', 'Ver detalhes', 'Reagendar visita', 'Executar visita'],
                'toolbar' => 'agendar-visita.php',
                'toolbar_rotulo' => 'Agendar visita',
            ],
            'agendar.visita' => [
                'titulo' => 'Agendar Visita',
                'descricao' => 'Novo agendamento e últimos compromissos da agenda.',
                'colunas' => [
                    ['campo' => 'data_visita', 'rotulo' => 'Data', 'tipo' => 'data'],
                    ['campo' => 'propriedade', 'rotulo' => 'Propriedade', 'tipo' => 'texto'],
                    ['campo' => 'agricultor', 'rotulo' => 'Agricultor', 'tipo' => 'texto'],
                    ['campo' => 'roteiro', 'rotulo' => 'Roteiro', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                ],
                'sql' => "SELECT v.data_visita, COALESCE(p.propriedade, '—') AS propriedade,
                                 CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS agricultor,
                                 COALESCE(r.nome, '—') AS roteiro, v.status
                            FROM visitas_tecnicas v
                            LEFT JOIN associados a ON a.id = v.associado_id
                            LEFT JOIN propriedades p ON p.id = v.propriedade_id
                            LEFT JOIN roteiros_inspecao r ON r.id = v.roteiro_id
                           ORDER BY v.data_visita DESC LIMIT 200",
                'acoes' => ['Agendar visita'],
                'toolbar' => 'agendar-visita.php',
                'toolbar_rotulo' => 'Abrir formulário',
            ],
            'roteiros.templates' => [
                'titulo' => 'Roteiros (Templates)',
                'descricao' => 'Modelos de roteiro de inspeção, versão e quantidade de itens (listagem atual).',
                'colunas' => [
                    ['campo' => 'codigo', 'rotulo' => 'Código', 'tipo' => 'texto'],
                    ['campo' => 'nome', 'rotulo' => 'Nome', 'tipo' => 'texto'],
                    ['campo' => 'tipo', 'rotulo' => 'Tipo', 'tipo' => 'texto'],
                    ['campo' => 'versao', 'rotulo' => 'Versão', 'tipo' => 'texto'],
                    ['campo' => 'itens', 'rotulo' => 'Itens', 'tipo' => 'texto'],
                ],
                'sql' => "SELECT codigo, nome, tipo, versao,
                                 CASE WHEN checklist IS NOT NULL AND JSON_VALID(checklist)
                                      THEN JSON_LENGTH(checklist) ELSE 0 END AS itens
                            FROM roteiros_inspecao
                           ORDER BY nome LIMIT 500",
                'acoes' => ['Ver checklist', 'Novo roteiro', 'Editar'],
            ],
            'nao.conformidades' => [
                'titulo' => 'Não Conformidades',
                'descricao' => 'NCs por visita, gravidade, prazo e situação (listagem atual).',
                'colunas' => [
                    ['campo' => 'data_visita', 'rotulo' => 'Data visita', 'tipo' => 'data'],
                    ['campo' => 'produtor', 'rotulo' => 'Produtor', 'tipo' => 'texto'],
                    ['campo' => 'propriedade', 'rotulo' => 'Propriedade', 'tipo' => 'texto'],
                    ['campo' => 'descricao', 'rotulo' => 'Descrição', 'tipo' => 'texto'],
                    ['campo' => 'gravidade', 'rotulo' => 'Gravidade', 'tipo' => 'badge'],
                    ['campo' => 'prazo_correcao', 'rotulo' => 'Prazo', 'tipo' => 'data'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                ],
                'sql' => "SELECT v.data_visita,
                                 CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS produtor,
                                 COALESCE(p.propriedade, '—') AS propriedade,
                                 nc.descricao, nc.gravidade, nc.prazo_correcao, nc.status
                            FROM nao_conformidades nc
                            LEFT JOIN visitas_tecnicas v ON v.id = nc.visita_tecnica_id
                            LEFT JOIN associados a ON a.id = v.associado_id
                            LEFT JOIN propriedades p ON p.id = v.propriedade_id
                           ORDER BY v.data_visita DESC LIMIT 500",
                'acoes' => ['Registrar NC', 'Acompanhar', 'Ver detalhes'],
            ],
            'validacao.insumos' => [
                'titulo' => 'Validação de Insumos',
                'descricao' => 'Status orgânico dos insumos em estoque (listagem atual).',
                'colunas' => [
                    ['campo' => 'nome', 'rotulo' => 'Insumo', 'tipo' => 'texto'],
                    ['campo' => 'fornecedor', 'rotulo' => 'Fornecedor', 'tipo' => 'texto'],
                    ['campo' => 'quantidade_estoque', 'rotulo' => 'Estoque', 'tipo' => 'decimal'],
                    ['campo' => 'data_validade', 'rotulo' => 'Validade', 'tipo' => 'data'],
                    ['campo' => 'conformidade', 'rotulo' => 'Conformidade', 'tipo' => 'badge'],
                ],
                'sql' => "SELECT i.nome, COALESCE(f.nome, '—') AS fornecedor,
                                 i.quantidade_estoque, i.data_validade,
                                 CASE WHEN i.aprovado_organico = 1 THEN 'Conforme'
                                      WHEN i.aprovado_organico = 0 THEN 'Não conforme'
                                      ELSE 'Pendente' END AS conformidade
                            FROM insumos i
                            LEFT JOIN fornecedores f ON f.id = i.fornecedor_id
                           WHERE i.excluido = 0
                           ORDER BY i.nome LIMIT 500",
                'acoes' => ['Aprovar', 'Reprovar', 'Ver detalhes'],
            ],
            'relatorios.admin' => [
                'titulo' => 'Relatórios Administrativos',
                'descricao' => 'Relatórios gerenciais da fiscalização.',
                'colunas' => [
                    ['campo' => 'relatorio', 'rotulo' => 'Relatório', 'tipo' => 'texto'],
                    ['campo' => 'descricao', 'rotulo' => 'Conteúdo', 'tipo' => 'texto'],
                ],
                'linhas' => [
                    ['relatorio' => 'Associados por município', 'descricao' => 'Distribuição territorial dos associados ativos'],
                    ['relatorio' => 'Inadimplentes por microrregião', 'descricao' => 'Mensalidades em aberto por região'],
                    ['relatorio' => 'Fluxo de caixa', 'descricao' => 'Entradas e saídas financeiras'],
                    ['relatorio' => 'Visitas por período', 'descricao' => 'Agendadas, realizadas e revisadas'],
                    ['relatorio' => 'Não conformidades abertas', 'descricao' => 'Por gravidade e prazo'],
                    ['relatorio' => 'Certificações por validade', 'descricao' => 'Vencendo e vencidas'],
                ],
                'acoes' => ['Gerar PDF', 'Gerar Excel', 'Gerar CSV'],
            ],
            'relatorios.conformidade' => [
                'titulo' => 'Relatórios de Conformidade',
                'descricao' => 'Relatórios de conformidade orgânica e auditoria.',
                'colunas' => [
                    ['campo' => 'relatorio', 'rotulo' => 'Relatório', 'tipo' => 'texto'],
                    ['campo' => 'descricao', 'rotulo' => 'Conteúdo', 'tipo' => 'texto'],
                ],
                'linhas' => [
                    ['relatorio' => 'Visitas e inspeções', 'descricao' => 'Executadas, revisadas e pendentes'],
                    ['relatorio' => 'Não conformidades', 'descricao' => 'Abertas, resolvidas e reincidências'],
                    ['relatorio' => 'Certificações SPG/OCS', 'descricao' => 'Emitidas, renovadas e vencidas'],
                    ['relatorio' => 'Validação de insumos', 'descricao' => 'Conformidade frente à Portaria 52/2021'],
                    ['relatorio' => 'Trilha de auditoria', 'descricao' => 'Logs de ações e acessos do sistema'],
                ],
                'acoes' => ['Gerar PDF', 'Gerar Excel', 'Gerar CSV'],
            ],
            'consulta.cnpo' => [
                'titulo' => 'Consulta CNPO',
                'descricao' => 'Cadastro Nacional de Produtores Orgânicos: produtor, entidade e situação.',
                'colunas' => [
                    ['campo' => 'nome_produtor', 'rotulo' => 'Produtor', 'tipo' => 'texto'],
                    ['campo' => 'entidade', 'rotulo' => 'Entidade', 'tipo' => 'texto'],
                    ['campo' => 'tipo_entidade', 'rotulo' => 'Tipo', 'tipo' => 'texto'],
                    ['campo' => 'cidade', 'rotulo' => 'Município', 'tipo' => 'texto'],
                    ['campo' => 'uf', 'rotulo' => 'UF', 'tipo' => 'texto'],
                    ['campo' => 'escopo', 'rotulo' => 'Escopo', 'tipo' => 'texto'],
                    ['campo' => 'situacao', 'rotulo' => 'Situação', 'tipo' => 'badge'],
                ],
                'sql' => "SELECT nome_produtor, entidade, tipo_entidade, cidade, uf, escopo, situacao
                            FROM cnpo
                           WHERE nome_produtor IS NOT NULL AND nome_produtor <> ''
                           ORDER BY nome_produtor LIMIT 500",
                'acoes' => ['Ver detalhes'],
            ],
        ],

        'producao' => [
            'caderno.registrar' => [
                'titulo' => 'Registrar atividade',
                'descricao' => 'Atividades do caderno de campo (mesmas colunas da listagem atual).',
                'colunas' => [
                    ['campo' => 'id', 'rotulo' => '#', 'tipo' => 'texto'],
                    ['campo' => 'nome', 'rotulo' => 'Nome', 'tipo' => 'texto'],
                    ['campo' => 'data_atividade', 'rotulo' => 'Data', 'tipo' => 'data'],
                    ['campo' => 'horario', 'rotulo' => 'Horário', 'tipo' => 'texto'],
                    ['campo' => 'tipo_atividade', 'rotulo' => 'Tipo', 'tipo' => 'texto'],
                    ['campo' => 'categoria', 'rotulo' => 'Categoria', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                    ['campo' => 'propriedade', 'rotulo' => 'Propriedade', 'tipo' => 'texto'],
                    ['campo' => 'lavoura', 'rotulo' => 'Lavoura', 'tipo' => 'texto'],
                    ['campo' => 'associado', 'rotulo' => 'Associado', 'tipo' => 'texto'],
                ],
                'sql' => "SELECT ad.id, ad.nome, ad.data_atividade,
                                 CONCAT(COALESCE(ad.hora_inicio, ''), CASE WHEN ad.hora_fim IS NOT NULL AND ad.hora_fim <> '' THEN CONCAT('–', ad.hora_fim) ELSE '' END) AS horario,
                                 ad.tipo_atividade, COALESCE(cat.nome, '—') AS categoria, ad.status,
                                 COALESCE(prop.propriedade, '—') AS propriedade,
                                 COALESCE(lav.nome, '—') AS lavoura,
                                 CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS associado
                            FROM atividades_diarias ad
                            LEFT JOIN categorias_atividades cat ON cat.id = ad.categoria_id
                            LEFT JOIN propriedades prop ON prop.id = ad.propriedade_id
                            LEFT JOIN lavouras lav ON lav.id = ad.lavoura_id
                            LEFT JOIN associados a ON a.id = ad.associado_id
                           WHERE ad.excluido = 0
                           ORDER BY ad.data_atividade DESC LIMIT 500",
                'acoes' => ['Nova atividade', 'Editar', 'Excluir'],
            ],
            'caderno.listar' => [
                'titulo' => 'Caderno de campo',
                'descricao' => 'Histórico de atividades registradas (mesmas colunas da listagem atual).',
                'colunas' => [
                    ['campo' => 'id', 'rotulo' => '#', 'tipo' => 'texto'],
                    ['campo' => 'nome', 'rotulo' => 'Nome', 'tipo' => 'texto'],
                    ['campo' => 'data_atividade', 'rotulo' => 'Data', 'tipo' => 'data'],
                    ['campo' => 'horario', 'rotulo' => 'Horário', 'tipo' => 'texto'],
                    ['campo' => 'tipo_atividade', 'rotulo' => 'Tipo', 'tipo' => 'texto'],
                    ['campo' => 'categoria', 'rotulo' => 'Categoria', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Status', 'tipo' => 'badge'],
                    ['campo' => 'propriedade', 'rotulo' => 'Propriedade', 'tipo' => 'texto'],
                    ['campo' => 'lavoura', 'rotulo' => 'Lavoura', 'tipo' => 'texto'],
                    ['campo' => 'associado', 'rotulo' => 'Associado', 'tipo' => 'texto'],
                ],
                'sql' => "SELECT ad.id, ad.nome, ad.data_atividade,
                                 CONCAT(COALESCE(ad.hora_inicio, ''), CASE WHEN ad.hora_fim IS NOT NULL AND ad.hora_fim <> '' THEN CONCAT('–', ad.hora_fim) ELSE '' END) AS horario,
                                 ad.tipo_atividade, COALESCE(cat.nome, '—') AS categoria, ad.status,
                                 COALESCE(prop.propriedade, '—') AS propriedade,
                                 COALESCE(lav.nome, '—') AS lavoura,
                                 CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS associado
                            FROM atividades_diarias ad
                            LEFT JOIN categorias_atividades cat ON cat.id = ad.categoria_id
                            LEFT JOIN propriedades prop ON prop.id = ad.propriedade_id
                            LEFT JOIN lavouras lav ON lav.id = ad.lavoura_id
                            LEFT JOIN associados a ON a.id = ad.associado_id
                           WHERE ad.excluido = 0
                           ORDER BY ad.data_atividade DESC LIMIT 500",
                'acoes' => ['Editar', 'Excluir', 'Gerar PDF', 'Gerar Excel'],
            ],
            'lotes.listar' => [
                'titulo' => 'Lotes e colheita',
                'descricao' => 'Lotes de produção com produto, data e quantidade (listagem atual).',
                'colunas' => [
                    ['campo' => 'nome_lote', 'rotulo' => 'Nome do lote', 'tipo' => 'texto'],
                    ['campo' => 'produto', 'rotulo' => 'Produto', 'tipo' => 'texto'],
                    ['campo' => 'data_producao', 'rotulo' => 'Data produção', 'tipo' => 'data'],
                    ['campo' => 'quantidade', 'rotulo' => 'Quantidade', 'tipo' => 'decimal'],
                    ['campo' => 'unidade_medida', 'rotulo' => 'Unidade', 'tipo' => 'texto'],
                ],
                'sql' => "SELECT l.nome_lote, COALESCE(pr.nome, '—') AS produto,
                                 l.data_producao, l.quantidade, l.unidade_medida
                            FROM lotes_producao l
                            LEFT JOIN produtos pr ON pr.id = l.produto_id
                           WHERE l.excluido = 0
                           ORDER BY l.data_producao DESC LIMIT 500",
                'acoes' => ['Editar', 'Excluir', 'Ver QR'],
            ],
            'insumos.listar' => [
                'titulo' => 'Insumos e equipamentos',
                'descricao' => 'Estoque de insumos, fornecedor e quantidades (listagem atual).',
                'colunas' => [
                    ['campo' => 'nome', 'rotulo' => 'Nome', 'tipo' => 'texto'],
                    ['campo' => 'descricao', 'rotulo' => 'Descrição', 'tipo' => 'texto'],
                    ['campo' => 'associado', 'rotulo' => 'Associado', 'tipo' => 'texto'],
                    ['campo' => 'fornecedor', 'rotulo' => 'Fornecedor', 'tipo' => 'texto'],
                    ['campo' => 'estoq_anterior', 'rotulo' => 'Est. anterior', 'tipo' => 'decimal'],
                    ['campo' => 'quant_recebida', 'rotulo' => 'Quant. recebida', 'tipo' => 'decimal'],
                    ['campo' => 'quantidade_estoque', 'rotulo' => 'Quant. estoque', 'tipo' => 'decimal'],
                ],
                'sql' => "SELECT i.nome, i.descricao,
                                 CONCAT(COALESCE(a.nome, ''), ' ', COALESCE(a.sobrenome, '')) AS associado,
                                 COALESCE(f.nome, '—') AS fornecedor,
                                 i.estoq_anterior, i.quant_recebida, i.quantidade_estoque
                            FROM insumos i
                            LEFT JOIN associados a ON a.id = i.associado_id
                            LEFT JOIN fornecedores f ON f.id = i.fornecedor_id
                           WHERE i.excluido = 0
                           ORDER BY i.nome LIMIT 500",
                'acoes' => ['Editar', 'Excluir'],
            ],
            'agroindustria.ordens' => [
                'titulo' => 'Ordens de produção',
                'descricao' => 'Lote processado, produto final e auditoria (listagem atual).',
                'colunas' => [
                    ['campo' => 'lote_processado_final', 'rotulo' => 'Lote processado', 'tipo' => 'texto'],
                    ['campo' => 'produto_final', 'rotulo' => 'Produto final', 'tipo' => 'texto'],
                    ['campo' => 'data_processamento', 'rotulo' => 'Processamento', 'tipo' => 'data'],
                    ['campo' => 'quantidade_final_obtida', 'rotulo' => 'Qtde final', 'tipo' => 'decimal'],
                    ['campo' => 'percentual_organico', 'rotulo' => '% orgânico', 'tipo' => 'decimal'],
                    ['campo' => 'status_auditoria', 'rotulo' => 'Status auditoria', 'tipo' => 'badge'],
                ],
                'sql' => "SELECT o.lote_processado_final, COALESCE(r.nome_produto_final, '—') AS produto_final,
                                 o.data_processamento, o.quantidade_final_obtida,
                                 o.percentual_organico, o.status_auditoria
                            FROM ordens_producao o
                            LEFT JOIN receitas_agroindustria r ON r.id = o.receita_id
                           WHERE o.excluido = 0
                           ORDER BY o.data_processamento DESC LIMIT 500",
                'acoes' => ['Ver ordem', 'Nova ordem'],
            ],
            'portal.meu-cadastro' => [
                'titulo' => 'Meu cadastro',
                'descricao' => 'Seus dados e o da propriedade vinculada.',
                'colunas' => [
                    ['campo' => 'nome', 'rotulo' => 'Nome', 'tipo' => 'texto'],
                    ['campo' => 'email', 'rotulo' => 'E-mail', 'tipo' => 'texto'],
                    ['campo' => 'telefone', 'rotulo' => 'Telefone', 'tipo' => 'texto'],
                    ['campo' => 'municipio', 'rotulo' => 'Município', 'tipo' => 'texto'],
                    ['campo' => 'status', 'rotulo' => 'Situação', 'tipo' => 'badge'],
                ],
                'pessoal' => true,
                'sql_associado' => "SELECT CONCAT(nome, ' ', sobrenome) AS nome, email, telefone,
                                           CONCAT(COALESCE(cidade, '—'), '/', COALESCE(estado, '—')) AS municipio,
                                           COALESCE(status_associado, status) AS status
                                      FROM associados WHERE id = :pessoa_id LIMIT 1",
                'sql_usuario' => "SELECT CONCAT(nome, ' ', sobrenome) AS nome, email, telefone,
                                         '—' AS municipio, status
                                    FROM usuarios WHERE id = :pessoa_id LIMIT 1",
                'acoes' => ['Editar meus dados'],
            ],
        ],
    ];

    /** @return array<string, mixed>|null */
    public static function definicao(string $app, string $chave): ?array
    {
        return self::DEFS[$app][$chave] ?? null;
    }

    /**
     * Executa a consulta da funcionalidade (somente leitura, limite 500).
     *
     * @param array<string, mixed> $identidade
     * @return list<array<string, mixed>>
     */
    public static function linhas(string $app, string $chave, array $identidade): array
    {
        $def = self::definicao($app, $chave);
        if ($def === null) {
            return [];
        }

        if (isset($def['linhas']) && is_array($def['linhas'])) {
            return $def['linhas'];
        }

        try {
            $pdo = Identidades::pdo();

            if (!empty($def['pessoal'])) {
                $associadoId = ($identidade['origem'] ?? '') === 'associados'
                    ? (int) ($identidade['id'] ?? 0)
                    : (int) ($identidade['associado_id'] ?? 0);

                if ($associadoId > 0) {
                    $stmt = $pdo->prepare((string) ($def['sql_associado'] ?? ''));
                    $stmt->execute(['pessoa_id' => $associadoId]);
                } elseif (($identidade['origem'] ?? '') === 'usuarios') {
                    $stmt = $pdo->prepare((string) ($def['sql_usuario'] ?? ''));
                    $stmt->execute(['pessoa_id' => (int) ($identidade['id'] ?? 0)]);
                } else {
                    return [];
                }
            } else {
                $stmt = $pdo->prepare((string) ($def['sql'] ?? ''));
                $stmt->execute();
            }

            return $stmt->fetchAll();
        } catch (\Throwable $e) {
            error_log('SINPORG Tabelas::linhas - ' . $e->getMessage());
            return [];
        }
    }

    /** @return list<string> */
    public static function acoes(string $app, string $chave): array
    {
        $def = self::definicao($app, $chave);

        return is_array($def['acoes'] ?? null) ? array_values($def['acoes']) : [];
    }
}
