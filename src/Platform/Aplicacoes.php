<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Registro dos sistemas (apps) e das funções de cada um.
 *
 * O framework é dirigido a PERFIS: cada função exige uma permissão; o perfil
 * do usuário (papéis em `usuario_papel`/`papeis`) determina o conjunto de
 * permissões e, portanto, quais funções aparecem e quais sistemas ele pode
 * acessar. O perfil `admin` tem a permissão curinga `*` e enxerga tudo.
 */
final class Aplicacoes
{
    /** @var array<string, array<string, mixed>> */
    private const DEFS = [
        'assoc' => [
            'slug' => 'assoc',
            'nome' => 'Gestão Associativa',
            'curto' => 'Associados',
            'descricao' => 'Quadro de associados, adesões, vida financeira, assembleias e documentos da associação.',
            'cor' => 'emerald',
            'icone' => '🏛️',
            'permissoes' => [
                'associados.read',
                'financeiro.read',
                'governanca.read',
                'portal.read',
                'documentos.read',
            ],
            'papeis' => ['admin', 'diretor', 'gestor_associacao', 'tesoureiro', 'secretario', 'diretoria', 'produtor', 'consumidor'],
            'funcoes' => [
                ['chave' => 'painel.assoc', 'titulo' => 'Painel', 'descricao' => 'Visão geral da associação.', 'permissao' => 'portal.read', 'icone' => '📊', 'rota' => 'painel.php'],
                ['chave' => 'associados.quadro', 'titulo' => 'Quadro de associados', 'descricao' => 'Cadastro, vínculo e histórico do associado.', 'permissao' => 'associados.read', 'icone' => '👥'],
                ['chave' => 'associados.novo', 'titulo' => 'Novo associado', 'descricao' => 'Incluir pessoa e iniciar o vínculo.', 'permissao' => 'associados.write', 'icone' => '➕'],
                ['chave' => 'adesoes.aprovar', 'titulo' => 'Adesões e fichas', 'descricao' => 'Analisar, aprovar ou recusar adesões.', 'permissao' => 'associados.approve', 'icone' => '✅'],
                ['chave' => 'aprovacoes.pendencias', 'titulo' => 'Liberar acessos e fichas', 'descricao' => 'Confirmar cadastros pendentes e aprovar fichas de adesão.', 'permissao' => 'associados.approve', 'icone' => '🔓', 'rota' => 'pendencias.php'],
                ['chave' => 'financeiro.mensalidades', 'titulo' => 'Mensalidades e caixa', 'descricao' => 'Planos, pagamentos e prestação de contas.', 'permissao' => 'financeiro.read', 'icone' => '💰'],
                ['chave' => 'governanca.assembleias', 'titulo' => 'Assembleias e eventos', 'descricao' => 'Convocações, presença e deliberações.', 'permissao' => 'governanca.read', 'icone' => '🏛️'],
                ['chave' => 'documentos.consultar', 'titulo' => 'Documentos', 'descricao' => 'Atas, carteirinhas e documentos da associação.', 'permissao' => 'documentos.read', 'icone' => '📄'],
                ['chave' => 'documentos.gerir', 'titulo' => 'Gestão de documentos', 'descricao' => 'Emitir, registrar e arquivar documentos.', 'permissao' => 'documentos.write', 'icone' => '📁'],
                ['chave' => 'relatorios.assoc', 'titulo' => 'Relatórios da associação', 'descricao' => 'Indicadores e exportações.', 'permissao' => 'relatorios.read', 'icone' => '📊'],
                ['chave' => 'papeis.atribuir', 'titulo' => 'Papéis e permissões', 'descricao' => 'Definir o papel do associado (diretor, tesoureiro, secretário, produtor).', 'permissao' => 'papeis.gerenciar', 'icone' => '🔑', 'rota' => 'papeis.php'],
                ['chave' => 'portal.meu-cadastro', 'titulo' => 'Meu cadastro', 'descricao' => 'Seus dados e documentos.', 'permissao' => 'portal.read', 'icone' => '👤', 'rota' => 'meu-perfil.php'],
                // Autoatendimento do membro associado (somente os próprios dados).
                ['chave' => 'membro.minha-ficha', 'titulo' => 'Minha ficha', 'descricao' => 'Situação e dados completos da sua ficha de adesão.', 'permissao' => 'associados.self', 'icone' => '📝', 'rota' => 'minha-ficha.php'],
                ['chave' => 'membro.meus-documentos', 'titulo' => 'Meus documentos', 'descricao' => 'Carteirinha de associado e documentos emitidos.', 'permissao' => 'documentos.self', 'icone' => '🪪'],
                ['chave' => 'membro.minhas-mensalidades', 'titulo' => 'Minhas mensalidades', 'descricao' => 'Seus vencimentos e pagamentos.', 'permissao' => 'financeiro.self', 'icone' => '💳'],
                ['chave' => 'membro.meus-certificados', 'titulo' => 'Meus certificados', 'descricao' => 'Suas certificações e validades.', 'permissao' => 'documentos.self', 'icone' => '🏅'],
                ['chave' => 'membro.meus-relatorios', 'titulo' => 'Meus relatórios', 'descricao' => 'Histórico consolidado da sua trajetória como associado.', 'permissao' => 'relatorios.self', 'icone' => '📊'],
            ],
        ],
        'social' => [
            'slug' => 'social',
            'nome' => 'Controle Social e Certificação',
            'curto' => 'Social',
            'descricao' => 'Unidades de produção, OPACs e OCSs, visitas técnicas e conformidade orgânica.',
            'cor' => 'teal',
            'icone' => '🛡️',
            'permissoes' => [
                'fiscalizacao.read',
            ],
            'papeis' => ['admin', 'gestor_fiscalizacao', 'fiscal_campo', 'certificador', 'diretoria'],
            'funcoes' => [
                // Painel do gestor/técnico (equivalente ao /dashboard/gestor legado).
                ['chave' => 'painel.gestor', 'titulo' => 'Painel do Gestor / Técnico', 'descricao' => 'Indicadores de fiscalização, visitas e conformidade.', 'permissao' => 'fiscalizacao.read', 'icone' => '📊', 'rota' => 'painel.php'],
                // Espelho do menu "Fiscalização" do sistema atual.
                ['chave' => 'certificacoes.listar', 'titulo' => 'Certificações (SPG/OCS)', 'descricao' => 'Certificações participativas, organismo e validade.', 'permissao' => 'fiscalizacao.read', 'icone' => '🏅', 'rota' => 'certificacoes.php'],
                ['chave' => 'certificadoras.listar', 'titulo' => 'Certificadoras', 'descricao' => 'OPACs, OCSs e certificadoras cadastradas.', 'permissao' => 'fiscalizacao.read', 'icone' => '🏢', 'rota' => 'certificadoras.php'],
                ['chave' => 'planos.analise', 'titulo' => 'Planos de Manejo — Análise', 'descricao' => 'Planos por safra, versão e situação.', 'permissao' => 'fiscalizacao.read', 'icone' => '📔', 'rota' => 'modulo.php?modulo=planos'],
                ['chave' => 'insumos.aprovados', 'titulo' => 'Insumos Aprovados', 'descricao' => 'Lista nacional de insumos permitidos (Portaria 52/2021).', 'permissao' => 'fiscalizacao.read', 'icone' => '✅', 'rota' => 'modulo.php?modulo=insumos_aprovados'],
                ['chave' => 'visitas.inspecoes', 'titulo' => 'Visitas e Inspeções', 'descricao' => 'Agenda, roteiros, ciência e conformidade.', 'permissao' => 'fiscalizacao.read', 'icone' => '📋', 'rota' => 'modulo.php?modulo=visitas'],
                ['chave' => 'agendar.visita', 'titulo' => 'Agendar Visita', 'descricao' => 'Novo agendamento de visita técnica.', 'permissao' => 'fiscalizacao.write', 'icone' => '📅', 'rota' => 'agendar-visita.php'],
                ['chave' => 'roteiros.templates', 'titulo' => 'Roteiros (Templates)', 'descricao' => 'Modelos de roteiro, versão e itens de checklist.', 'permissao' => 'fiscalizacao.read', 'icone' => '🗒️', 'rota' => 'modulo.php?modulo=roteiros'],
                ['chave' => 'nao.conformidades', 'titulo' => 'Não Conformidades', 'descricao' => 'NCs por visita, gravidade, prazo e situação.', 'permissao' => 'fiscalizacao.read', 'icone' => '⚠️', 'rota' => 'modulo.php?modulo=ncs'],
                ['chave' => 'validacao.insumos', 'titulo' => 'Validação de Insumos', 'descricao' => 'Conferência do status orgânico dos insumos em estoque.', 'permissao' => 'fiscalizacao.write', 'icone' => '🧪', 'rota' => 'modulo.php?modulo=validacao'],
                ['chave' => 'relatorios.admin', 'titulo' => 'Relatórios Administrativos', 'descricao' => 'Relatórios gerenciais da fiscalização.', 'permissao' => 'relatorios.read', 'icone' => '📊', 'rota' => 'relatorios.php'],
                ['chave' => 'relatorios.conformidade', 'titulo' => 'Relatórios de Conformidade', 'descricao' => 'Visitas, NCs, certificações e auditoria.', 'permissao' => 'relatorios.read', 'icone' => '📈', 'rota' => 'relatorios.php'],
                ['chave' => 'consulta.cnpo', 'titulo' => 'Consulta CNPO', 'descricao' => 'Consulta pública do Cadastro Nacional de Produtores Orgânicos.', 'permissao' => 'fiscalizacao.read', 'icone' => '🔎', 'rota' => 'modulo.php?modulo=cnpo'],
            ],
        ],
        'producao' => [
            'slug' => 'producao',
            'nome' => 'Produção do Agricultor',
            'curto' => 'Produção',
            'descricao' => 'Caderno de campo, lotes, colheita, insumos, materiais e equipamentos.',
            'cor' => 'amber',
            'icone' => '🌱',
            'permissoes' => [
                'caderno.read',
                'agroindustria.read',
            ],
            'papeis' => ['admin', 'produtor', 'agroindustria', 'gestor_associacao', 'diretoria'],
            'funcoes' => [
                ['chave' => 'painel.producao', 'titulo' => 'Painel', 'descricao' => 'Visão geral da produção.', 'permissao' => 'caderno.read', 'icone' => '📊', 'rota' => 'painel.php'],
                ['chave' => 'producao.meu-plano', 'titulo' => 'Meu plano de manejo', 'descricao' => 'Plano obrigatório para toda produção (vegetal, animal e agroindústria).', 'permissao' => 'caderno.read', 'icone' => '📔', 'rota' => 'meu-plano.php'],
                // ===== Produção Vegetal =====
                ['chave' => 'caderno.registrar', 'titulo' => 'Registrar atividade', 'descricao' => 'Anotar o que foi feito no campo hoje.', 'permissao' => 'caderno.write', 'icone' => '✍️', 'grupo' => 'Produção Vegetal', 'rota' => 'registro.php?recurso=atividades&acao=novo'],
                ['chave' => 'caderno.listar', 'titulo' => 'Caderno de campo', 'descricao' => 'Histórico de atividades e insumos.', 'permissao' => 'caderno.read', 'icone' => '📓', 'grupo' => 'Produção Vegetal', 'rota' => 'registro.php?recurso=atividades'],
                ['chave' => 'producao.propriedades', 'titulo' => 'Propriedades', 'descricao' => 'Cadastro das propriedades e áreas.', 'permissao' => 'caderno.read', 'icone' => '🏡', 'grupo' => 'Produção Vegetal', 'rota' => 'registro.php?recurso=propriedades'],
                ['chave' => 'producao.lavouras', 'titulo' => 'Lavouras', 'descricao' => 'Culturas, áreas e plantios.', 'permissao' => 'caderno.read', 'icone' => '🌾', 'grupo' => 'Produção Vegetal', 'rota' => 'registro.php?recurso=lavouras'],
                ['chave' => 'insumos.listar', 'titulo' => 'Insumos', 'descricao' => 'Estoque, fornecedor e aplicações.', 'permissao' => 'caderno.read', 'icone' => '🧪', 'grupo' => 'Produção Vegetal', 'rota' => 'registro.php?recurso=insumos'],
                ['chave' => 'producao.produtos', 'titulo' => 'Produtos', 'descricao' => 'Produtos, preços e colheita.', 'permissao' => 'caderno.read', 'icone' => '📦', 'grupo' => 'Produção Vegetal', 'rota' => 'registro.php?recurso=produtos'],
                ['chave' => 'lotes.listar', 'titulo' => 'Lotes de produção', 'descricao' => 'Produção, colheita e destinação.', 'permissao' => 'caderno.read', 'icone' => '🏷️', 'grupo' => 'Produção Vegetal', 'rota' => 'registro.php?recurso=lotes'],
                ['chave' => 'producao.fornecedores', 'titulo' => 'Fornecedores', 'descricao' => 'Fornecedores de insumos e serviços.', 'permissao' => 'caderno.read', 'icone' => '🚚', 'grupo' => 'Produção Vegetal', 'rota' => 'registro.php?recurso=fornecedores'],
                ['chave' => 'producao.ocorrencias', 'titulo' => 'Ocorrências', 'descricao' => 'Pragas, doenças e ações de manejo.', 'permissao' => 'caderno.read', 'icone' => '⚠️', 'grupo' => 'Produção Vegetal', 'rota' => 'registro.php?recurso=ocorrencias'],
                ['chave' => 'producao.relatorios', 'titulo' => 'Relatórios de produção', 'descricao' => 'Resumo e exportações da produção.', 'permissao' => 'caderno.read', 'icone' => '📊', 'grupo' => 'Produção Vegetal', 'rota' => 'relatorios.php'],
                // ===== Produção Animal =====
                ['chave' => 'animal.animais', 'titulo' => 'Rebanho (animais)', 'descricao' => 'Animais, identificação e situação.', 'permissao' => 'caderno.read', 'icone' => '🐄', 'grupo' => 'Produção Animal', 'rota' => 'registro.php?recurso=animais'],
                ['chave' => 'animal.lotes', 'titulo' => 'Lotes de animais', 'descricao' => 'Lotes, espécies e quantidades.', 'permissao' => 'caderno.read', 'icone' => '🐑', 'grupo' => 'Produção Animal', 'rota' => 'registro.php?recurso=animal_lotes'],
                ['chave' => 'animal.alimentacao', 'titulo' => 'Alimentação', 'descricao' => 'Dieta, quantidade e origem.', 'permissao' => 'caderno.read', 'icone' => '🌽', 'grupo' => 'Produção Animal', 'rota' => 'registro.php?recurso=alimentacao'],
                ['chave' => 'animal.sanidade', 'titulo' => 'Sanidade', 'descricao' => 'Tratamentos, vacinas e carências.', 'permissao' => 'caderno.read', 'icone' => '💉', 'grupo' => 'Produção Animal', 'rota' => 'registro.php?recurso=sanidade'],
                ['chave' => 'animal.reproducao', 'titulo' => 'Reprodução', 'descricao' => 'Coberturas, partos e nascimentos.', 'permissao' => 'caderno.read', 'icone' => '🐣', 'grupo' => 'Produção Animal', 'rota' => 'registro.php?recurso=reproducao'],
                ['chave' => 'animal.ambientes', 'titulo' => 'Ambientes', 'descricao' => 'Instalações, sistemas e capacidade.', 'permissao' => 'caderno.read', 'icone' => '🏠', 'grupo' => 'Produção Animal', 'rota' => 'registro.php?recurso=ambientes'],
                // ===== Agroindústria =====
                ['chave' => 'agroindustria.minhas', 'titulo' => 'Minhas agroindústrias', 'descricao' => 'Unidades de processamento.', 'permissao' => 'caderno.read', 'icone' => '🏭', 'grupo' => 'Agroindústria', 'rota' => 'registro.php?recurso=agroindustrias'],
                ['chave' => 'agroindustria.setores', 'titulo' => 'Setores', 'descricao' => 'Setores da agroindústria.', 'permissao' => 'caderno.read', 'icone' => '🧩', 'grupo' => 'Agroindústria', 'rota' => 'registro.php?recurso=agro_setores'],
                ['chave' => 'agroindustria.categorias', 'titulo' => 'Categorias', 'descricao' => 'Categorias de atividades.', 'permissao' => 'caderno.read', 'icone' => '🏷️', 'grupo' => 'Agroindústria', 'rota' => 'registro.php?recurso=agro_categorias'],
                ['chave' => 'agroindustria.atividades', 'titulo' => 'Atividades diárias', 'descricao' => 'Registro diário da agroindústria.', 'permissao' => 'caderno.read', 'icone' => '📋', 'grupo' => 'Agroindústria', 'rota' => 'registro.php?recurso=agro_atividades'],
                ['chave' => 'agroindustria.ocorrencias', 'titulo' => 'Ocorrências', 'descricao' => 'Ocorrências e ações corretivas.', 'permissao' => 'caderno.read', 'icone' => '⚠️', 'grupo' => 'Agroindústria', 'rota' => 'registro.php?recurso=agro_ocorrencias'],
                ['chave' => 'agroindustria.receitas', 'titulo' => 'Receitas', 'descricao' => 'Receitas e percentual orgânico.', 'permissao' => 'caderno.read', 'icone' => '📖', 'grupo' => 'Agroindústria', 'rota' => 'registro.php?recurso=agro_receitas'],
                ['chave' => 'agroindustria.higienizacao', 'titulo' => 'Higienização', 'descricao' => 'Controle de higienização.', 'permissao' => 'caderno.read', 'icone' => '🧼', 'grupo' => 'Agroindústria', 'rota' => 'registro.php?recurso=agro_higienizacao'],
                ['chave' => 'agroindustria.ordens', 'titulo' => 'Ordens de produção', 'descricao' => 'Transformação e receitas.', 'permissao' => 'agroindustria.read', 'icone' => '🏭', 'grupo' => 'Agroindústria', 'rota' => 'registro.php?recurso=agro_ordens'],
                ['chave' => 'agroindustria.rastreabilidade', 'titulo' => 'Rastreabilidade', 'descricao' => 'Consulta de lotes e ordens da agroindústria.', 'permissao' => 'caderno.read', 'icone' => '🔎', 'grupo' => 'Agroindústria', 'rota' => 'rastreabilidade.php'],
                // ===== Ferramentas =====
                ['chave' => 'producao.rastreabilidade', 'titulo' => 'Rastreabilidade', 'descricao' => 'Consulta de lotes e códigos públicos.', 'permissao' => 'caderno.read', 'icone' => '🔎', 'grupo' => 'Ferramentas', 'rota' => 'rastreabilidade.php'],
                ['chave' => 'portal.meu-cadastro', 'titulo' => 'Meu cadastro', 'descricao' => 'Seus dados e da propriedade.', 'permissao' => 'portal.read', 'icone' => '👤', 'grupo' => 'Ferramentas', 'rota' => 'meu-perfil.php'],
            ],
        ],
    ];

    /** Rótulos de perfil para exibição. */
    private const ROTULOS = [
        'admin' => 'Administrador',
        'diretor' => 'Diretor(a)',
        'tesoureiro' => 'Tesoureiro(a)',
        'secretario' => 'Secretário(a)',
        'gestor_associacao' => 'Gestor de associação',
        'gestor_fiscalizacao' => 'Fiscal / Técnico',
        'fiscal_campo' => 'Fiscal de campo',
        'certificador' => 'Certificador',
        'produtor' => 'Membro associado',
        'consumidor' => 'Membro consumidor',
        'diretoria' => 'Diretoria',
        'agroindustria' => 'Agroindústria',
    ];

    /**
     * Permissões por perfil — fonte de verdade quando a conta não tem linhas
     * em `usuario_papel` (legado) e complemento das permissões do banco.
     *
     * @var array<string, list<string>>
     */
    private const PERMISSOES_POR_PAPEL = [
        'admin' => ['*', 'papeis.gerenciar'],
        'diretor' => [
            // Acesso total ao módulo de associação (diretor/vice-diretor/presidente).
            'associados.read', 'associados.write', 'associados.approve',
            'financeiro.read', 'financeiro.write',
            'governanca.read', 'governanca.write',
            'relatorios.read', 'portal.read', 'config.read',
            'documentos.read', 'documentos.write',
            // Autoatendimento comum do associado.
            'associados.self', 'documentos.self', 'financeiro.self', 'relatorios.self',
        ],
        'tesoureiro' => [
            // Área financeira do módulo de associação.
            'associados.read',
            'financeiro.read', 'financeiro.write',
            'governanca.read',
            'relatorios.read', 'portal.read',
            'associados.self', 'documentos.self', 'financeiro.self', 'relatorios.self',
        ],
        'secretario' => [
            // Gestão de documentos do módulo de associação.
            'associados.read',
            'documentos.read', 'documentos.write',
            'governanca.read',
            'relatorios.read', 'portal.read',
            'associados.self', 'documentos.self', 'financeiro.self', 'relatorios.self',
        ],
        'gestor_associacao' => [
            'associados.read', 'associados.write', 'associados.approve',
            'financeiro.read', 'financeiro.write',
            'governanca.read', 'governanca.write',
            'relatorios.read', 'portal.read', 'caderno.read',
            'associados.self', 'documentos.self', 'financeiro.self', 'relatorios.self',
        ],
        'gestor_fiscalizacao' => [
            'fiscalizacao.read', 'fiscalizacao.write', 'fiscalizacao.review', 'fiscalizacao.certificate',
            'caderno.read', 'relatorios.read', 'config.read',
        ],
        'fiscal_campo' => ['fiscalizacao.read', 'fiscalizacao.write', 'relatorios.read'],
        'certificador' => ['fiscalizacao.read', 'fiscalizacao.review', 'fiscalizacao.certificate'],
        'diretoria' => [
            'associados.read', 'financeiro.read', 'governanca.read', 'relatorios.read',
            'fiscalizacao.read', 'caderno.read', 'portal.read',
            'associados.self', 'documentos.self', 'financeiro.self', 'relatorios.self',
        ],
        'produtor' => [
            'caderno.read', 'caderno.write', 'portal.read', 'agroindustria.read',
            // Autoatendimento do membro associado: somente os próprios dados.
            'associados.self', 'documentos.self', 'financeiro.self', 'relatorios.self',
        ],
        'consumidor' => [
            // Membro consumidor/cliente: somente autoatendimento, sem produção.
            'portal.read', 'associados.self', 'documentos.self', 'financeiro.self', 'relatorios.self',
        ],
        'agroindustria' => ['agroindustria.read', 'agroindustria.write', 'caderno.read', 'portal.read'],
    ];

    /** @return array<string, array<string, mixed>> */
    public static function todos(): array
    {
        return self::DEFS;
    }

    /** @return array<string, mixed> */
    public static function obter(string $slug): array
    {
        if (!isset(self::DEFS[$slug])) {
            throw new \InvalidArgumentException('Sistema desconhecido: ' . $slug);
        }

        return self::DEFS[$slug];
    }

    public static function existe(string $slug): bool
    {
        return isset(self::DEFS[$slug]);
    }

    public static function rotuloPapel(string $codigo): string
    {
        return self::ROTULOS[$codigo] ?? $codigo;
    }

    /** @return list<string> */
    public static function permissoesDoPapel(string $papel): array
    {
        return self::PERMISSOES_POR_PAPEL[$papel] ?? [];
    }

    /** @return list<string> */
    public static function todasPermissoes(): array
    {
        $uniq = [];
        foreach (self::PERMISSOES_POR_PAPEL as $lista) {
            foreach ($lista as $p) {
                if ($p !== '*') {
                    $uniq[$p] = true;
                }
            }
        }

        return array_keys($uniq);
    }
}
