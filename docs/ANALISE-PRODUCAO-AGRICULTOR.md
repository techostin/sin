# Produção do Agricultor — inventário do legado e implementação

Atualizado em 2026-11-16. Este documento registra o que existe no painel
**Produção do Agricultor** (`/app/producao/`) e o mapeamento com os módulos do
sistema legado, para não deixar funcionalidade de fora.

## Separação dos painéis

O painel de produção acessa somente:

- Produção Vegetal: propriedades, lavouras, insumos, produtos, lotes, fornecedores,
  atividades diárias, ocorrências, relatórios e rastreabilidade.
- Produção Animal: animais, lotes de animais, alimentação, sanidade, reprodução e ambientes.
- Agroindústria: agroindústrias, setores, categorias, atividades, ocorrências, receitas,
  higienização, ordens de produção e rastreabilidade.

**Não entram neste painel** (pertencem ao Controle Social ou à Gestão Associativa):
certificações, visitas técnicas, não conformidades, plano de manejo, insumos aprovados,
CNPO, associados, financeiro, mensalidades e documentos da associação. O legado exibia
alguns desses itens no menu do agricultor; aqui eles permanecem em seus próprios painéis.

## Menus por área de produção

O menu e o CRUD são recortados pela área do agricultor (`area_producao` +
`tem_agroindustria`), na mesma lógica do legado (`User::areaProducao()`/
`temAgroindustria()`):

| Perfil | Grupos exibidos |
|---|---|
| Hortaliças/vegetais | Produção Vegetal + Ferramentas |
| Somente agroindústria | Agroindústria + Ferramentas |
| Vegetal + agroindústria | Produção Vegetal + Agroindústria + Ferramentas |
| Criador de animais | Produção Animal + Ferramentas |
| Animal + agroindústria | Produção Animal + Agroindústria + Ferramentas |
| Admin/gestão | Todos os grupos |
| Conta sem área cadastrada | Todos (fallback legado) |

O pré-cadastro agora oferece **Vegetal + Agroindústria** e **Animal + Agroindústria**,
além de Vegetal, Animal, Agroindústria, Mista e Consumidor. A agroindústria também é
detectada automaticamente quando o associado já possui unidade em `agroindustrias`.
Acesso direto a um recurso fora da área é bloqueado no controller e no serviço.

## Formulário de propriedade (paridade com o legado)

O formulário genérico de `propriedades` foi ajustado ao layout do legado
(`modulos/caderno_campo/propriedades/*`):

- colunas antigas sem uso no cadastro atual (`nome`, `localizacao`, `tamanho`, `status`,
  `recursos_naturais`) foram removidas do formulário, eliminando os campos “órfãos” que
  apareciam abaixo de Observações;
- **Estado** é um `<select>` com as UFs da tabela `estados`;
- **Cidade** é um `<select>` dependente: começa desabilitado com “Selecione um estado
  primeiro” e é carregado a partir de `/app/api/get_cidades.php?todos=1`, filtrando pela UF;
- valores antigos gravados como ID de cidade/estado são convertidos para nome/UF na listagem e
  na edição;
- os campos restantes abaixo de Observações têm rótulos e ajuda explícitos:
  **Imagem principal** e **Imagem secundária** são uploads JPG/PNG/WebP de até 5 MB (com
  pré-visualização, otimização e preservação da imagem atual), gravados em
  `uploads/propriedades/`; **Zona de amortecimento (RF02)** descreve as barreiras físicas
  exigidas.

## Menu legado mapeado

| Módulo legado | Itens legados | Implementação nova |
|---|---|---|
| Produção Vegetal | Propriedades, Lavouras, Insumos, Produtos, Lotes de Produção, Fornecedores | CRUD em `producao/registro.php?recurso=...` |
| Produção Vegetal | Atividades Diárias, Ocorrências | CRUD + ocorrências |
| Produção Vegetal | Rastreabilidade, Relatórios | `producao/rastreabilidade.php`, `producao/relatorios.php` |
| Produção Animal | Rebanho (Animais), Lotes, Alimentação, Sanidade, Reprodução, Ambientes | CRUD em `producao/registro.php?recurso=...` |
| Agroindústria | Minhas Agroindústrias, Setores, Categorias, Atividades, Ocorrências, Receitas, Higienização, Ordens | CRUD em `producao/registro.php?recurso=...` |
| Agroindústria | Rastreabilidade | página comum de rastreabilidade |
| Agricultor | Portal/Meu cadastro | `/app/producao/meu-perfil.php` |

## Implementado nesta fatia

- Menu completo, agrupado em **Produção Vegetal / Produção Animal / Agroindústria / Ferramentas**.
- CRUD genérico de **22 recursos** com:
  - listagem com busca, edição e exclusão lógica quando a tabela tem `excluido`;
  - formulário gerado a partir do schema, com enums, datas, decimais e relações (propriedade,
    lavoura, produto, lote, animal, setor, receita etc.);
  - escopo por `associado_id` (ou pela propriedade, no caso de lavouras);
  - administrador/gestão veem tudo; agricultor vê somente o próprio escopo;
  - escrita separada por módulo: `caderno.write` para vegetal/animal e
    `agroindustria.write` para agroindústria (sem misturar os escopos);
  - auditoria `PRODUCAO_CRIADO`, `PRODUCAO_ATUALIZADO` e `PRODUCAO_EXCLUIDO`.
- Exportação CSV por recurso (mesma consulta da listagem, respeitando o escopo).
- Tela de relatórios com contadores por grupo e atalhos para lista/CSV.
- Consulta de rastreabilidade por lote de produção, ordem de produção e código público.
- O formulário e as listas aceitam decimais com vírgula (convertidos para o banco).
- Páginas testadas por HTTP para os 22 recursos, relatórios e rastreabilidade.
- Smoke: criação de propriedade pelo agricultor, listagem e isolamento do painel.

## Ainda não coberto / próximas fatias

1. **Upload físico de imagens** (propriedades, atividades, ocorrências) — hoje os campos de
   imagem são textuais. O legado tem upload/galeria.
2. **QR Code e impressão** de lotes/ordens (legado tem `rastrear.php`, `teste_qr.php`).
3. **Painéis dedicados** de Produção Animal e Agroindústria (visão agregada); o dashboard
   atual lista todas as funcionalidades.
4. **Relatórios em PDF/Excel** com layout do legado (`caderno_campo/relatorios/*`);
   a primeira fatia entrega CSV e contadores.
5. **Tabelas de detalhe**: ingredientes de receita, movimentações de animais, manejo animal,
   fornecedor/unidades e histórico de conversão de propriedade.
6. Regras de área do agricultor (`area_producao`/`tem_agroindustria`) para ocultar grupos
   não aplicáveis — hoje o menu mostra os três grupos para quem tem acesso ao painel.

## Tabelas principais usadas

`propriedades`, `lavouras`, `insumos`, `produtos`, `lotes_producao`, `fornecedores`,
`atividades_diarias`, `categorias_atividades`, `ocorrencias_manejo`, `animais`,
`animal_lotes`, `controle_alimentacao_animal`, `controle_sanidade_animal`,
`controle_reproducao_animal`, `ambiente_criacao_animal`, `agroindustrias`, `agro_setores`,
`agro_categorias_atividades`, `agro_atividades_diarias`, `agro_ocorrencias`,
`receitas_agroindustria`, `controle_higienizacao` e `ordens_producao`.
