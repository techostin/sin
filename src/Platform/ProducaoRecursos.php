<?php

declare(strict_types=1);

namespace App\Platform;

/**
 * Catálogo dos recursos do painel Produção do Agricultor.
 *
 * Cobre os módulos legados de Produção Vegetal, Produção Animal e Agroindústria,
 * mantendo-os separados do painel associativo (sem certificações/visitas/NCs,
 * que pertencem ao Controle Social) e do módulo de gestão de associados.
 */
final class ProducaoRecursos
{
    /** @var array<string, array<string, mixed>>|null */
    private static ?array $defs = null;

    /** @return array<string, array<string, mixed>> */
    public static function todos(): array
    {
        if (self::$defs !== null) {
            return self::$defs;
        }

        self::$defs = [
            // ===== Produção Vegetal =====
            'atividades' => [
                'grupo' => 'Produção Vegetal',
                'titulo' => 'Atividades diárias',
                'singular' => 'Atividade',
                'tabela' => 'atividades_diarias',
                'chave' => 'caderno.listar',
                'colunas' => ['nome', 'data_atividade', 'tipo_atividade', 'status', 'propriedade_id', 'lavoura_id'],
                'relacoes' => [
                    'propriedade_id' => ['recurso' => 'propriedades'],
                    'lavoura_id' => ['recurso' => 'lavouras'],
                    'categoria_id' => ['recurso' => 'categorias'],
                    'lote_id' => ['recurso' => 'lotes'],
                ],
                'ordem' => 'data_atividade DESC',
                'busca' => ['nome', 'tipo_atividade'],
                'excluir_campos' => ['categotia_id', 'observações'],
            ],
            'propriedades' => [
                'grupo' => 'Produção Vegetal',
                'titulo' => 'Propriedades',
                'singular' => 'Propriedade',
                'tabela' => 'propriedades',
                'chave' => 'producao.propriedades',
                'colunas' => ['propriedade', 'cidade', 'estado', 'area_total', 'status'],
                'relacoes' => [],
                'ordem' => 'propriedade ASC',
                'busca' => ['propriedade', 'cidade'],
                'sem_exclusao' => true,
                // Colunas legadas sem uso no formulário atual (nome/localização/tamanho/status/recurso).
                'excluir_campos' => ['nome', 'localizacao', 'tamanho', 'status', 'recursos_naturais'],
                'rotulos' => [
                    'propriedade' => 'Nome da propriedade',
                    'area_total' => 'Área total (ha)',
                    'endereco' => 'Endereço',
                    'cep' => 'CEP',
                    'bairro' => 'Bairro',
                    'cidade' => 'Cidade',
                    'estado' => 'Estado (UF)',
                    'latitude' => 'Latitude',
                    'longitude' => 'Longitude',
                    'imagem_principal' => 'Imagem principal',
                    'imagem_secundaria' => 'Imagem secundária',
                    'zona_amortecimento' => 'Zona de amortecimento (RF02)',
                ],
                'ajudas' => [
                    'propriedade' => 'Nome pelo qual a propriedade é conhecida.',
                    'area_total' => 'Área total da propriedade em hectares.',
                    'cep' => 'Somente números ou no formato 00000-000.',
                    'cidade' => 'Selecione primeiro o estado; as cidades serão carregadas automaticamente.',
                    'estado' => 'UF da propriedade; define as cidades disponíveis.',
                    'latitude' => 'Coordenada em graus decimais, ex.: -30,0345.',
                    'longitude' => 'Coordenada em graus decimais, ex.: -51,2178.',
                    'imagem_principal' => 'Envie uma foto JPG, PNG ou WebP (máx. 5 MB). A imagem atual é mantida se nenhum arquivo for selecionado.',
                    'imagem_secundaria' => 'Segunda foto opcional (JPG, PNG ou WebP, máx. 5 MB).',
                    'zona_amortecimento' => 'Descreva as barreiras físicas que protegem a área orgânica de contaminação externa (cercas vivas, mata ciliar, quebra-ventos etc.).',
                ],
            ],
            'lavouras' => [
                'grupo' => 'Produção Vegetal',
                'titulo' => 'Lavouras',
                'singular' => 'Lavoura',
                'tabela' => 'lavouras',
                'chave' => 'producao.lavouras',
                'colunas' => ['nome', 'tipo_cultura', 'area', 'data_plantio', 'status', 'propriedade_id'],
                'relacoes' => ['propriedade_id' => ['recurso' => 'propriedades']],
                'ordem' => 'nome ASC',
                'busca' => ['nome', 'tipo_cultura'],
                'escopo' => 'propriedade',
                'sem_exclusao' => true,
            ],
            'insumos' => [
                'grupo' => 'Produção Vegetal',
                'titulo' => 'Insumos',
                'singular' => 'Insumo',
                'tabela' => 'insumos',
                'chave' => 'insumos.listar',
                'colunas' => ['nome', 'tipo_insumo', 'estoq_anterior', 'quant_recebida', 'quantidade_estoque', 'fornecedor_id'],
                'relacoes' => ['fornecedor_id' => ['recurso' => 'fornecedores']],
                'ordem' => 'nome ASC',
                'busca' => ['nome', 'descricao'],
            ],
            'produtos' => [
                'grupo' => 'Produção Vegetal',
                'titulo' => 'Produtos',
                'singular' => 'Produto',
                'tabela' => 'produtos',
                'chave' => 'producao.produtos',
                'colunas' => ['nome', 'tipo', 'unidade_medida', 'preco_unitario', 'quantidade_produzida', 'data_colheita'],
                'relacoes' => ['lavoura_id' => ['recurso' => 'lavouras']],
                'ordem' => 'nome ASC',
                'busca' => ['nome', 'tipo'],
                'sem_exclusao' => true,
            ],
            'lotes' => [
                'grupo' => 'Produção Vegetal',
                'titulo' => 'Lotes de produção',
                'singular' => 'Lote',
                'tabela' => 'lotes_producao',
                'chave' => 'lotes.listar',
                'colunas' => ['nome_lote', 'produto_id', 'data_producao', 'quantidade', 'unidade_medida'],
                'relacoes' => ['produto_id' => ['recurso' => 'produtos']],
                'ordem' => 'data_producao DESC',
                'busca' => ['nome_lote'],
                'soft_delete' => true,
            ],
            'fornecedores' => [
                'grupo' => 'Produção Vegetal',
                'titulo' => 'Fornecedores',
                'singular' => 'Fornecedor',
                'tabela' => 'fornecedores',
                'chave' => 'producao.fornecedores',
                'colunas' => ['nome', 'cnpj', 'telefone', 'cidade', 'estado', 'status'],
                'relacoes' => [],
                'ordem' => 'nome ASC',
                'busca' => ['nome', 'cnpj'],
                'escopo' => 'global',
                'sem_exclusao' => true,
                'somente_gestor' => true,
            ],
            'ocorrencias' => [
                'grupo' => 'Produção Vegetal',
                'titulo' => 'Ocorrências',
                'singular' => 'Ocorrência',
                'tabela' => 'ocorrencias_manejo',
                'chave' => 'producao.ocorrencias',
                'colunas' => ['nome_comum', 'tipo', 'severidade', 'status', 'data_deteccao'],
                'relacoes' => [
                    'propriedade_id' => ['recurso' => 'propriedades'],
                    'lavoura_id' => ['recurso' => 'lavouras'],
                    'insumo_utilizado_id' => ['recurso' => 'insumos'],
                ],
                'ordem' => 'data_deteccao DESC',
                'busca' => ['nome_comum', 'descricao'],
                'soft_delete' => true,
                'rotulos' => [
                    'foto_url' => 'Foto da ocorrência',
                ],
                'ajudas' => [
                    'foto_url' => 'Envie JPG, PNG ou WebP (máx. 5 MB) com a imagem da ocorrência.',
                ],
            ],
            'categorias' => [
                'grupo' => 'Produção Vegetal',
                'titulo' => 'Categorias de atividades',
                'singular' => 'Categoria',
                'tabela' => 'categorias_atividades',
                'chave' => 'caderno.listar',
                'colunas' => ['nome', 'cor'],
                'relacoes' => [],
                'ordem' => 'nome ASC',
                'busca' => ['nome'],
                'sem_exclusao' => true,
            ],

            // ===== Produção Animal =====
            'animais' => [
                'grupo' => 'Produção Animal',
                'titulo' => 'Rebanho (animais)',
                'singular' => 'Animal',
                'tabela' => 'animais',
                'chave' => 'animal.animais',
                'colunas' => ['nome_animal', 'especie', 'raca', 'sexo', 'status', 'peso_kg'],
                'relacoes' => ['lote_id' => ['recurso' => 'animal_lotes']],
                'ordem' => 'nome_animal ASC',
                'busca' => ['nome_animal', 'identificacao', 'raca'],
                'soft_delete' => true,
            ],
            'animal_lotes' => [
                'grupo' => 'Produção Animal',
                'titulo' => 'Lotes de animais',
                'singular' => 'Lote animal',
                'tabela' => 'animal_lotes',
                'chave' => 'animal.lotes',
                'colunas' => ['nome_lote', 'especie', 'categoria', 'quantidade'],
                'relacoes' => [],
                'ordem' => 'nome_lote ASC',
                'busca' => ['nome_lote', 'categoria'],
                'soft_delete' => true,
            ],
            'alimentacao' => [
                'grupo' => 'Produção Animal',
                'titulo' => 'Alimentação animal',
                'singular' => 'Registro de alimentação',
                'tabela' => 'controle_alimentacao_animal',
                'chave' => 'animal.alimentacao',
                'colunas' => ['data_alimentacao', 'tipo_alimento', 'produto_utilizado', 'quantidade_kg', 'origem'],
                'relacoes' => [
                    'animal_id' => ['recurso' => 'animais'],
                    'lote_id' => ['recurso' => 'animal_lotes'],
                ],
                'ordem' => 'data_alimentacao DESC',
                'busca' => ['produto_utilizado'],
                'soft_delete' => true,
            ],
            'sanidade' => [
                'grupo' => 'Produção Animal',
                'titulo' => 'Sanidade animal',
                'singular' => 'Tratamento/ocorrência',
                'tabela' => 'controle_sanidade_animal',
                'chave' => 'animal.sanidade',
                'colunas' => ['data_tratamento', 'tipo_tratamento', 'produto_utilizado', 'responsavel', 'produto_normatizado'],
                'relacoes' => [
                    'animal_id' => ['recurso' => 'animais'],
                    'lote_id' => ['recurso' => 'animal_lotes'],
                ],
                'ordem' => 'data_tratamento DESC',
                'busca' => ['produto_utilizado', 'motivo'],
                'soft_delete' => true,
            ],
            'reproducao' => [
                'grupo' => 'Produção Animal',
                'titulo' => 'Reprodução animal',
                'singular' => 'Evento reprodutivo',
                'tabela' => 'controle_reproducao_animal',
                'chave' => 'animal.reproducao',
                'colunas' => ['data_evento', 'tipo_evento', 'grupo_reprodutivo', 'nascidos_vivos'],
                'relacoes' => [
                    'animal_id' => ['recurso' => 'animais'],
                    'macho_reprodutor_id' => ['recurso' => 'animais'],
                ],
                'ordem' => 'data_evento DESC',
                'busca' => ['grupo_reprodutivo'],
                'soft_delete' => true,
            ],
            'ambientes' => [
                'grupo' => 'Produção Animal',
                'titulo' => 'Ambientes de criação',
                'singular' => 'Ambiente',
                'tabela' => 'ambiente_criacao_animal',
                'chave' => 'animal.ambientes',
                'colunas' => ['nome_instalacao', 'especie', 'sistema', 'quantidade_animais', 'area_total_m2'],
                'relacoes' => [],
                'ordem' => 'nome_instalacao ASC',
                'busca' => ['nome_instalacao', 'tipo_instalacao'],
                'soft_delete' => true,
            ],

            // ===== Agroindústria =====
            'agroindustrias' => [
                'grupo' => 'Agroindústria',
                'titulo' => 'Minhas agroindústrias',
                'singular' => 'Agroindústria',
                'tabela' => 'agroindustrias',
                'chave' => 'agroindustria.minhas',
                'colunas' => ['razao_social', 'nome_fantasia', 'cnpj', 'cidade', 'estado', 'status'],
                'relacoes' => [],
                'ordem' => 'razao_social ASC',
                'busca' => ['razao_social', 'nome_fantasia'],
                'soft_delete' => true,
            ],
            'agro_setores' => [
                'grupo' => 'Agroindústria',
                'titulo' => 'Setores',
                'singular' => 'Setor',
                'tabela' => 'agro_setores',
                'chave' => 'agroindustria.setores',
                'colunas' => ['nome', 'agroindustria_id'],
                'relacoes' => ['agroindustria_id' => ['recurso' => 'agroindustrias']],
                'ordem' => 'nome ASC',
                'busca' => ['nome'],
                'soft_delete' => true,
            ],
            'agro_categorias' => [
                'grupo' => 'Agroindústria',
                'titulo' => 'Categorias',
                'singular' => 'Categoria',
                'tabela' => 'agro_categorias_atividades',
                'chave' => 'agroindustria.categorias',
                'colunas' => ['nome', 'cor'],
                'relacoes' => [],
                'ordem' => 'nome ASC',
                'busca' => ['nome'],
                'soft_delete' => true,
            ],
            'agro_atividades' => [
                'grupo' => 'Agroindústria',
                'titulo' => 'Atividades diárias',
                'singular' => 'Atividade',
                'tabela' => 'agro_atividades_diarias',
                'chave' => 'agroindustria.atividades',
                'colunas' => ['nome', 'data_atividade', 'tipo_atividade', 'status', 'setor_id'],
                'relacoes' => [
                    'setor_id' => ['recurso' => 'agro_setores'],
                    'categoria_id' => ['recurso' => 'agro_categorias'],
                ],
                'ordem' => 'data_atividade DESC',
                'busca' => ['nome', 'tipo_atividade'],
                'soft_delete' => true,
            ],
            'agro_ocorrencias' => [
                'grupo' => 'Agroindústria',
                'titulo' => 'Ocorrências',
                'singular' => 'Ocorrência',
                'tabela' => 'agro_ocorrencias',
                'chave' => 'agroindustria.ocorrencias',
                'colunas' => ['titulo', 'tipo', 'severidade', 'status', 'data_ocorrencia'],
                'relacoes' => ['setor_id' => ['recurso' => 'agro_setores']],
                'ordem' => 'data_ocorrencia DESC',
                'busca' => ['titulo', 'descricao'],
                'soft_delete' => true,
            ],
            'agro_receitas' => [
                'grupo' => 'Agroindústria',
                'titulo' => 'Receitas',
                'singular' => 'Receita',
                'tabela' => 'receitas_agroindustria',
                'chave' => 'agroindustria.receitas',
                'colunas' => ['nome_produto_final', 'status', 'percentual_organico_calculado', 'selo_autorizado'],
                'relacoes' => [],
                'ordem' => 'nome_produto_final ASC',
                'busca' => ['nome_produto_final', 'descricao'],
                'soft_delete' => true,
            ],
            'agro_higienizacao' => [
                'grupo' => 'Agroindústria',
                'titulo' => 'Higienização',
                'singular' => 'Higienização',
                'tabela' => 'controle_higienizacao',
                'chave' => 'agroindustria.higienizacao',
                'colunas' => ['data_hora_inicio', 'equipamento_linha', 'sanitizante_utilizado', 'responsavel_nome'],
                'relacoes' => [],
                'ordem' => 'data_hora_inicio DESC',
                'busca' => ['equipamento_linha', 'sanitizante_utilizado'],
                'soft_delete' => true,
            ],
            'agro_ordens' => [
                'grupo' => 'Agroindústria',
                'titulo' => 'Ordens de produção',
                'singular' => 'Ordem de produção',
                'tabela' => 'ordens_producao',
                'chave' => 'agroindustria.ordens',
                'colunas' => ['lote_processado_final', 'receita_id', 'data_processamento', 'quantidade_final_obtida', 'status_auditoria'],
                'relacoes' => [
                    'receita_id' => ['recurso' => 'agro_receitas'],
                    'higienizacao_previa_id' => ['recurso' => 'agro_higienizacao'],
                ],
                'ordem' => 'data_processamento DESC',
                'busca' => ['lote_processado_final'],
                'soft_delete' => true,
            ],
        ];

        return self::$defs;
    }

    /** @return array<string, mixed>|null */
    public static function definicao(string $recurso): ?array
    {
        return self::todos()[$recurso] ?? null;
    }

    /** @return array<string, array{rotulo: string, campo: string}> */
    public static function relacionamentos(): array
    {
        return [
            'propriedades' => ['rotulo' => 'Propriedades', 'campo' => 'propriedade'],
            'lavouras' => ['rotulo' => 'Lavouras', 'campo' => 'nome'],
            'insumos' => ['rotulo' => 'Insumos', 'campo' => 'nome'],
            'produtos' => ['rotulo' => 'Produtos', 'campo' => 'nome'],
            'lotes' => ['rotulo' => 'Lotes', 'campo' => 'nome_lote'],
            'fornecedores' => ['rotulo' => 'Fornecedores', 'campo' => 'nome'],
            'categorias' => ['rotulo' => 'Categorias', 'campo' => 'nome'],
            'animais' => ['rotulo' => 'Animais', 'campo' => 'nome_animal'],
            'animal_lotes' => ['rotulo' => 'Lotes animais', 'campo' => 'nome_lote'],
            'agroindustrias' => ['rotulo' => 'Agroindústrias', 'campo' => 'nome_fantasia'],
            'agro_setores' => ['rotulo' => 'Setores', 'campo' => 'nome'],
            'agro_categorias' => ['rotulo' => 'Categorias', 'campo' => 'nome'],
            'agro_receitas' => ['rotulo' => 'Receitas', 'campo' => 'nome_produto_final'],
            'agro_higienizacao' => ['rotulo' => 'Higienizações', 'campo' => 'equipamento_linha'],
        ];
    }

    /** @return list<string> */
    public static function grupos(): array
    {
        return ['Produção Vegetal', 'Produção Animal', 'Agroindústria'];
    }
}
