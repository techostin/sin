# Análise do menu "Fiscalização" do sistema atual → novo módulo social

O menu lateral atual (`includes/menu.php`, bloco `#fiscalizacaoSubmenu`) tem **12 itens**. O novo
módulo `social` manteve os 12 e acrescentou o **Painel do Gestor / Técnico** (equivalente ao
`/dashboard/gestor` legado), totalizando **13 itens**. Todos agora abrem telas próprias — não são
mais apenas DataTables sem rota — e as ações principais (planos, visitas, NCs e validação de
insumos) podem ser executadas pelo perfil autorizado.

| # | Item no sistema atual | Rota atual | Arquivo atual | Chave nova (`social`) | Permissão | Situação na nova plataforma |
|---|---|---|---|---|---|---|
| 0 | Painel do gestor/técnico | `/dashboard/gestor` | `modulos/dashboard/dashboard_gestor.php` | `painel.gestor` | `fiscalizacao.read` | **Painel ativo** em `social/painel.php`, com os mesmos KPIs, próximas visitas e atalhos |
| 1 | Certificações (SPG/OCS) | `/certificacoes` | `modulos/certificacao/listar.php` | `certificacoes.listar` | `fiscalizacao.read` | **Módulo completo** (filtros, criar/editar/renovar/histórico/detalhes/certificado/excluir) — ver `ANALISE-CERTIFICACOES.md` |
| 2 | Certificadoras | `/certificadoras_list.php` | `certificadoras_list.php` | `certificadoras.listar` | `fiscalizacao.read` | **Módulo completo**: lista com busca/UF, nova certificadora, edição, logo (URL/upload) e membros vinculados |
| 3 | Planos de Manejo — Análise | `/planos-manejo` | `modulos/plano_manejo/listar.php` | `planos.analise` | `fiscalizacao.read` | Listagem real (4) com safra, tipo, propriedade, agricultor, status, versão e data |
| 4 | Insumos Aprovados | `/insumos-aprovados` | `modulos/certificacao/insumos.php` | `insumos.aprovados` | `fiscalizacao.read` | Lista nacional (250) com categoria, nome comercial, princípio ativo, fabricante e status |
| 5 | Visitas e Inspeções | `/visitas-tecnicas` | `modulos/roteiros_inspecao/visitas/listar.php` | `visitas.inspecoes` | `fiscalizacao.read` | Listagem real (12) com data, propriedade, agricultor, roteiro, ciência, status e conformidade |
| 6 | Agendar Visita | `/visita-tecnica/agendar` | `modulos/roteiros_inspecao/visitas/agendar.php` | `agendar.visita` | `fiscalizacao.write` | **Fluxo completo**: formulário (`social/agendar-visita.php`) grava em `visitas_tecnicas` com `hashid`, auditoria `VISITA_AGENDADA` e confirmação |
| 7 | Roteiros (Templates) | `/roteiros-inspecao` | `modulos/roteiros_inspecao/templates/listar.php` | `roteiros.templates` | `fiscalizacao.read` | Listagem real (3) com código, nome, tipo, versão e nº de itens do checklist |
| 8 | Não Conformidades | `/nao-conformidades` | `modulos/roteiros_inspecao/nao_conformidades/listar.php` | `nao.conformidades` | `fiscalizacao.read` | Listagem real (5) com data da visita, produtor, propriedade, descrição, gravidade, prazo e status |
| 9 | Validação de Insumos | `/caderno-campo/insumos/validacao` | `modulos/caderno_campo/insumos/validacao.php` | `validacao.insumos` | `fiscalizacao.write` | Listagem real (14) com insumo, fornecedor, estoque, validade e conformidade |
| 10 | Relatórios Administrativos | `/relatorios-admin` | `modulos/caderno_campo/relatorios_admin.php` | `relatorios.admin` | `relatorios.read` | Catálogo de relatórios (associados, inadimplência, caixa, visitas, NCs, certificações) |
| 11 | Relatórios de Conformidade | `/relatorios-conformidade` | `modulos/conformidade/relatorios.php` | `relatorios.conformidade` | `relatorios.read` | Catálogo de relatórios (visitas, NCs, certificações, insumos, trilha de auditoria) |
| 12 | Consulta CNPO | `/consulta-cnpo` | `modulos/consulta_cnpo/index.php` | `consulta.cnpo` | `fiscalizacao.read` | Listagem real (500 primeiros do CNPO) com produtor, entidade, tipo, município, UF, escopo e situação |

## Regras preservadas

- **Visibilidade**: cada item só aparece se o perfil tem a permissão correspondente
  (`Autorizacao::funcoesVisiveis`). O fiscal/técnico vê **13 de 13**; o certificador vê os itens de
  análise/certificação; perfis sem `fiscalizacao.read` não veem o menu.
- **Colunas**: extraídas das telas atuais (cabeçalhos `<th>`), com os mesmos join e filtros
  (`excluido = 0`, status, ordenação).
- **Agendar visita**: valida associado, propriedade (precisa pertencer ao associado), roteiro e
  data (hoje ou futura), grava `status = 'Agendada'`, gera `hashid` e registra auditoria.
- **Permissões de ação**: agendar/validar exigem `fiscalizacao.write`; relatórios exigem
  `relatorios.read`; revisão/certificação continuam reservadas a `fiscalizacao.review` e
  `fiscalizacao.certificate`.

## Modal de Confirmações de Visitas Agendadas

O modal do legado (`includes/visita_gestor_modal.php`) foi portado para `social/painel.php`. Quando
existem visitas `Agendada` com data hoje/futura, ele abre automaticamente e mostra:

- agricultor, propriedade, roteiro, técnico, data/hora;
- status de confirmação do agricultor (**Confirmado**, **Recusou** com motivo ou **Pendente**);
- **Ciente** por visita, gravando `gestor_ciente`, `gestor_ciente_em`, usuário e tabela;
- **Reagendar** com nova data/hora (a data deve ser hoje/futura), limpando as confirmações
  anteriores para o agricultor confirmar novamente;
- barra de progresso da ciência do gestor; o modal só fecha quando todas as visitas tiverem
  ciência registrada (exceto perfis sem permissão de escrita).

Endpoints nativos novos: `SocialModulos::visitasConfirmacao()`, `registrarCienteVisita()` e
`reagendarVisita()`; a auditoria registra `VISITA_GESTOR_CIENTE`/`VISITA_REAGENDADA_GESTOR`.

## Planos de Manejo — consulta e sugestões técnicas

A listagem de planos (`social/modulo.php?modulo=planos`) agora tem **Ver detalhes** para
gestores/técnicos e admin. A tela mostra caracterização da unidade, práticas de manejo, cultivos,
croqui, status/versão e permite:

- registrar **parecer técnico** com decisão (Aprovar, Revisão Necessária, Reprovar ou Sugestão);
- registrar **sugestões de ajuste/alteração** sem alterar o status quando a decisão for “Sugestão”;
- consultar o **histórico de pareceres** (quem, quando, decisão, parecer e sugestões);
- imprimir a ficha do plano.

O histórico fica em `plano_manejo_pareceres`; a migração está em
`scripts/migrar-plano-pareceres.sql` (executar como root, pois o usuário da aplicação não tem CREATE).

## Ações ativas e próximas fatias

Já estão ativas as telas de painel, planos, insumos aprovados, visitas, roteiros, não
conformidades, validação de insumos, relatórios e CNPO, com filtros, busca, CSV e ações de status
para os perfis com `fiscalizacao.write`/`fiscalizacao.review`. Ainda faltam os fluxos profundos do
legado:

1. Executar visita com checklist completo e geração automática de NCs;
2. Formulário completo de NC (novo/edição/anexo de evidência);
3. Aprovar/reprovar plano com parecer detalhado e histórico de versões;
4. Emitir/renovar/revogar certificação (já há módulo de certificações);
5. Validar insumos com vínculo à lista nacional;
6. Exportações PDF/Excel dos relatórios administrativos e de conformidade.

Cada fatia reaproveita a classe legada correspondente (`VisitaTecnica`, `RoteiroInspecao`,
`PlanoManejo`/`CertificadoOrganico`, `InsumoValidador`, `ReportManager`) para não perder a lógica
que já funciona no sistema atual.
