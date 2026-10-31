# 02 — Catálogo de Módulos, Telas e Ações

> **Como foi produzido.** Este catálogo foi gerado a partir de: (a) extração dos **194
> ramos de rota** do roteador `index.php` (+227 `require`), (b) inventário dos 441
> arquivos PHP, (c) introspecção do banco `sisagro` e (d) os documentos de domínio
> (`RELATORIO_MODULARIZACAO.md`, `REPORT.md`, `modulos/TODO_Agroind.md`,
> `PLANO_DASHBOARDS_POR_PERFIL.md`). O detalhamento **campo a campo** de cada tela é
> entregável da **Fase -1** (`anexos/legado-<modulo>.md`) e é a base da matriz de paridade.

**Legenda das colunas:** *Rota legada* = URL amigável que o roteador aceita · *Arquivo* =
PHP que responde · *Propósito* = o que a tela faz.

---

## 0. Visão geral da aplicação legada

| Aspecto | Situação |
|---|---|
| Entrada | Apache `.htaccess` reescreve para `index.php?url=...` **apenas quando o arquivo não existe** — portanto **todo arquivo .php é acessível diretamente**, contornando o roteador |
| Roteador | `index.php` — 1.484 linhas, 194 ramos `if ($parts[0] === '...')`, 227 `require` |
| Autenticação | 3 implementações distintas: `login.php` (staff, 2 etapas para gestor), `classes/User.php` (nunca chamado), `modulos/portal/login.php` (associado) |
| Autorização | Nível numérico lido cru de `$_SESSION['user_nivel']`; nenhum middleware |
| Shell de UI | `includes/header.php` + `includes/menu.php` (84 links) + `includes/footer.php` |
| Persistência | PDO direto; 2.102 comandos SQL em 309 arquivos; 168 das 220 páginas de módulo com SQL próprio |
| Offline | PWA: `manifest.json` + `sw.js` + `assets/js/db_sync.js` (IndexedDB) |
| Relatórios | 3 pilhas: `relatorios.php`, `relatorios/index.php` + `api/relatorios/**`, `relatorios_router.php` (**código morto**) |

---

## M1 — Associados & Quadro Associativo

**Objetivo:** gerir o agricultor associado — do pré-cadastro à aprovação, vínculo
associativo, ficha de adesão, certificação e carteirinha.

**Tabelas (dono):** `associados` (god table: identidade + login + vínculo +
certificação + ficha + territorialidade + notificações), `associacoes`,
`associado_associacao`, `ficha_adesao`, `ficha_adesao_produtos`, `carteirinhas`,
`entidades`.

### Rotas

| Rota legada | Arquivo | Propósito |
|---|---|---|
| `/associados` | `modulos/associados/listar.php` | Listagem com filtros (status, cidade, microrregião, associação) |
| `/associados/pendentes` | `modulos/associados/pendentes.php` | Associados aguardando aprovação |
| `/cadastros-pendentes` | `modulos/associados/cadastros_pendentes.php` | Fila de pré-cadastros e confirmação de acesso |
| `/associados/carteirinhas` | `modulos/associados/carteirinha_listar.php` | Lista de carteirinhas |
| `/associado/novo` | `modulos/associados/cadastrar.php` | Cadastro de associado |
| `/associado/{id}/editar` | `modulos/associados/editar.php` | Edição completa do associado |
| `/associado/{id}/excluir` | `modulos/associados/excluir.php` | Exclusão (**deve virar soft delete** + anonimização) |
| `/meu-perfil` | `modulos/associados/meu_perfil.php` | Autoatendimento do associado (tela de 35 KB) |
| `/associados/relatorio` | `modulos/associados/form_relatorio.php` | Formulário de relatório de associados |
| `/fichas-completas` | `modulos/associados/fichas_completas.php` | Fichas completas preenchidas |
| `/cadastro-ficha/visualizar` | `modulos/associados/ficha_adesao_visualizar.php` | Visualização e impressão da ficha (43 KB) |
| `/associado/{id}/carteirinha-gerar` | `modulos/associados/carteirinha_gerar.php` | Gera a carteirinha |
| `/associado/{id}/carteirinha-impressa` | `modulos/associados/carteirinha_impressa.php` | Registra a impressão |
| `/associado/{id}/carteirinha-pdf` | `modulos/associados/carteirinha_pdf.php` | PDF da carteirinha |
| `/associado/{id}/carteirinha-enviar` | `modulos/associados/carteirinha_enviar.php` | Envio por e-mail |
| — | `modulos/associados/carteirinhas_enviar_massa.php` | **Envio em massa** |
| `/carteirinha/{hash}` | `carteirinha_publica.php` | **Rota pública** de validação da carteirinha |
| `/associacoes` | `modulos/associacoes/listar.php` | Lista de associações |
| `/associacao/novo` | `modulos/associacoes/cadastrar.php` | Cadastro de associação |
| `/associacao/{id}/editar` | `modulos/associacoes/editar.php` | Edição de associação |
| `/associacao/{id}/associados` | `modulos/associacoes/associados.php` | Associados vinculados |
| — | `modulos/associacoes/excluir.php` | Exclusão de associação |
| — | `includes/ficha_adesao_modal.php` (49 KB) | Modal global de ficha de adesão, injetado em **todas** as páginas |
| — | `api/ficha_adesao.php`, `api/pre_cadastro.php` | APIs de ficha e pré-cadastro |

### Ações e regras de negócio
- **Máquina de estados do associado:** `pendente` → `ativo` → `inativo`, com
  `status_associado` de vínculo separado (`ativo`, `desligado` etc.).
- **Confirmação de acesso:** pré-cadastro → análise → aprovação (`ficha_enviada`,
  `ficha_aprovada`, `ficha_aprovada_por`, `ficha_aprovada_em`, `ficha_observacao`).
- **Validação de CPF** (`check_cpf.php` legado) e unicidade.
- **Ficha de adesão** com assinatura, produtos declarados, situação fundiária, zona, tipo
  de certificação e de associado.
- **Carteirinha:** geração, QR e hash de validação pública, PDF, impressão e envio por e-mail.
- **A DERIVAR na Fase -1:** `meu_perfil.php` (35 KB) e `ficha_adesao_visualizar.php`
  (43 KB) concentram a maior parte das regras de edição do produtor — são as telas de maior
  risco de regressão e devem ter anexo de legado detalhado.

### Permissões
- Admin e Gestor: tudo. Associado: apenas `meu-perfil` e a própria ficha e carteirinha.
- **Risco:** a rota `/associado/{id}/excluir` e as de carteirinha não validam ownership
  de forma consistente — o fiscal deve testar IDOR em cada uma.

---

## M2 — Caderno de Campo (Produção Vegetal)

**Objetivo:** o coração operacional — registrar o dia a dia da propriedade (atividades,
insumos, ocorrências, produtos, lotes) e produzir rastreabilidade. **Precisa funcionar
offline.**

**Tabelas (dono):** `propriedades`, `propriedade_membros`, `lavouras`,
`atividades_diarias`, `categorias_atividades`, `produtos`, `lotes_producao`,
`lote_atividade`, `lote_insumo`, `ocorrencias_manejo`, `historico_conversao`,
`culturas`.

### Rotas

| Rota legada | Arquivo | Propósito |
|---|---|---|
| `/caderno-campo/dashboard` | `dashboard.php` | Painel do caderno (visão gestor) |
| `/caderno-campo/dashboard-agricultor` | `dashboard_agricultor.php` (33 KB) | Painel do agricultor — indicadores pessoais |
| `/caderno-campo/propriedades` | `propriedades/listar.php` (22 KB) | Lista de propriedades |
| `/caderno-campo/propriedade/novo` | `propriedades/cadastrar.php` (17 KB) | Cadastro (área, coordenadas, barreiras, membros) |
| `/caderno-campo/propriedade/{id}/editar` | `propriedades/editar.php` | Edição |
| `/caderno-campo/propriedade/{id}/imagens` | `propriedades/visualizar_imagens.php` | Galeria de evidências |
| — | `propriedades/historico_conversao.php` | Histórico de conversão para orgânico |
| `/caderno-campo/lavouras` | `lavouras/listar.php` | Lista de lavouras |
| `/caderno-campo/lavoura/novo` | `lavouras/cadastrar.php` | Cadastro de lavoura |
| `/caderno-campo/lavoura/{id}/editar` | `lavouras/editar.php` | Edição |
| `/caderno-campo/insumos` | `insumos/listar.php` | Lista de insumos |
| `/caderno-campo/insumo/novo` | `insumos/cadastrar.php` (25 KB) | Cadastro de insumo (composição, fabricante, lote, validade) |
| `/caderno-campo/insumo/{id}/editar` | `insumos/editar.php` | Edição |
| `/caderno-campo/insumos/validacao` | `insumos/validacao.php` | **Validação contra a lista de permitidos** |
| `/caderno-campo/produtos` | `produtos/listar.php` | Lista de produtos |
| `/caderno-campo/produto/novo` | `produtos/cadastrar.php` | Cadastro de produto |
| `/caderno-campo/produto/{id}/editar` | `produtos/editar.php` | Edição |
| `/caderno-campo/lotes` | `lotes_producao/listar.php` (16 KB) | Lista de lotes de produção |
| `/caderno-campo/lote/novo` | `lotes_producao/cadastrar.php` | Cadastro de lote |
| `/caderno-campo/lote/{id}/editar` | `lotes_producao/editar.php` | Edição |
| `/caderno-campo/lote/rastrear` | `lotes_producao/rastrear.php` | **Rastreabilidade por QR Code** |
| `/caderno-campo/ocorrencias` | `ocorrencias/listar.php` (21 KB) | Lista de ocorrências de manejo |
| `/caderno-campo/ocorrencia/novo` | `ocorrencias/cadastrar.php` | Cadastro (com evidência fotográfica) |
| `/caderno-campo/ocorrencia/{id}/editar` | `ocorrencias/editar.php` | Edição |
| `/caderno-campo/categorias-atividades` | `categorias_atividades/listar.php` | Categorias de atividade |
| `/caderno-campo/categoria-atividade/novo` | `categorias_atividades/cadastrar.php` | Cadastro |
| `/caderno-campo/categoria-atividade/{id}/editar` | `categorias_atividades/editar.php` | Edição |
| `/atividades-diarias` | `atividade_diaria/listar.php` | Lista do diário de campo |
| `/atividade-diaria/novo` | `atividade_diaria/cadastrar.php` (19 KB) | Registro de atividade diária |
| — | `atividade_diaria/editar.php` (24 KB) | Edição de atividade |
| `/caderno-campo/atividade/{id}/imagens` | `atividade_diaria/visualizar_imagens.php` | Galeria |
| — | `atividade_diaria/excluir.php` | Exclusão (**deve virar soft delete**) |
| `/caderno-campo/sincronizacao` | `sincronizacao.php` (14 KB) | **Tela de sincronização offline** |
| `/relatorios`, `/relatorios-admin` | `relatorios_tabs.php`, `relatorios_admin.php` | Hub de relatórios do caderno |
| `/caderno-campo/relatorios/{geral,atividades,insumos,producao,propriedade,lotes}` | `relatorios/*.php` | Relatórios específicos |
| `/relatorios/geral/exportar` | `relatorios/exportar_relatorio.php` | Exportação |
| — | `relatorios/*_excel.php` (7 arquivos) | Exportações Excel |
| — | `validar_insumo.php` | Endpoint de validação de insumo |
| — | `api/ocorrencias.php` | API de ocorrências |
| — | `assets/js/db_sync.js` (12,8 KB) | **Motor de sincronização offline (IndexedDB)** |
| — | `sw.js`, `manifest.json` | Service Worker e manifesto PWA |

### Regras de negócio e riscos
- **Offline-first:** `db_sync.js` intercepta os formulários de atividade, insumo e
  ocorrência, grava na fila `sync_queue` do IndexedDB e sincroniza ao voltar a conexão.
  **Não há chave de idempotência** — é o risco R5 do roadmap (duplicação de registros no campo).
- **Validação de insumos** contra `insumos_organicos_aprovados` (Portaria 52/2021) —
  requisito **REG-2** do checklist de aceite.
- A classificação é bloqueante no legado: Orgânico, Em conversão, Convencional, Não permitido.
- **Rastreabilidade:** lote → atividades → insumos → produtos, com QR Code — requisito **REG-3**.
- **Soft delete obrigatório** em atividades, insumos e lotes — requisito **REG-1**.
- **Duplicações a resolver:** `atividades_diarias` e `agro_atividades_diarias`;
  `categorias_atividades` e `agro_categorias_atividades`; `ocorrencias_manejo` e
  `agro_ocorrencias`; `lotes_producao` e `animal_lotes`.
- **Arquivos indevidos no diretório de produção:** `.bak`, `debug_qr.php`,
  `teste_qr.php`, `teste_qrcodejs.php` — remover.

---

## M3 — Fiscalização & Conformidade

**Objetivo:** inspeção, certificação participativa (SPG/OCS), não conformidades, plano de
manejo e certificadoras.

**Tabelas (dono):** `roteiros_inspecao`, `visitas_tecnicas`, `nao_conformidades`,
`plano_manejo`, `plano_manejo_cultivos`, `certificacao_participativa`,
`certificadoras`.

### Rotas

| Rota legada | Arquivo | Propósito |
|---|---|---|
| `/roteiros-inspecao` | `templates/listar.php` | Templates de roteiro (checklist) |
| `/roteiro-inspecao/novo` | `templates/cadastrar.php` (19 KB) | Cadastro de template (itens, pesos, críticos) |
| — | `templates/certificado.php`, `declaracao_compromisso.php`, `ficha_pdf.php` | Documentos gerados |
| `/visitas-tecnicas` | `visitas/listar.php` (18 KB) | Lista de visitas |
| `/visita-tecnica/agendar` | `visitas/agendar.php` | Agendamento |
| `/visita-tecnica/{id}/realizar` (id ou hashid) | `visitas/realizar.php` (32 KB) | **Execução do checklist** com evidências e assinatura |
| `/visita-tecnica/{id}/relatorio` | `visitas/relatorio.php` | Relatório da visita |
| `/nao-conformidades` | `nao_conformidades/listar.php` (17 KB) | Lista de NCs |
| `/nao-conformidade/{id}` | `nao_conformidades/detalhe.php` | Detalhe da NC |
| `/nao-conformidade/acompanhar` | `nao_conformidades/acompanhar.php` | Acompanhamento da correção |
| `/certificacoes`, `/certificacao/listar` | `certificacao/listar.php` (21 KB) | Lista de certificações |
| `/certificacao/novo` | `certificacao/cadastrar.php` | Nova certificação (SPG/OCS/Ecovida) |
| `/certificacao/{id}/editar` | `certificacao/editar.php` | Edição |
| `/certificacao/{id}/renovar` | `certificacao/renovar.php` | Renovação |
| `/certificacao/historico/{id}` | `certificacao/historico.php` | Histórico do associado |
| `/certificacao/{id}/certificado` | `certificacao/certificado.php` | **Certificado PDF** |
| `/insumos-aprovados` | `certificacao/insumos.php` | Lista oficial de insumos permitidos |
| `/planos-manejo` | `plano_manejo/listar.php` | Lista de planos de manejo |
| `/plano-manejo/novo` | `plano_manejo/cadastrar.php` (32 KB) | Cadastro do plano (áreas, práticas, cultivos) |
| `/plano-manejo/{id}/visualizar` | `plano_manejo/visualizar.php` | Visualização |
| `/plano-manejo/{id}/editar` | `plano_manejo/editar.php` (18 KB) | Edição |
| `/plano-manejo/{id}/aprovar` | `plano_manejo/aprovar.php` | Aprovação e reprovação |
| `/plano-manejo/{id}/upload_croqui` | `plano_manejo/upload_croqui.php` | Upload do croqui (imagem ou PDF) |
| — | `certificadoras_list.php`, `certificadoras_cadastrar.php`, `certificadoras_editar.php` | CRUD de certificadoras |
| — | `api/certificadoras.php`, `api/certificadoras/controller.php` | API REST de certificadoras |
| — | `api/certificacao/controller.php` (23 KB), `api/certificacao/gerar_pdf.php` | API de certificação e PDF |
| — | `api/roteiros_inspecao.php` (30 KB), `api/nao_conformidades.php`, `api/plano_manejo.php` | APIs |
| — | `api/insumos_aprovados.php` | API de insumos permitidos |
| `/relatorios-conformidade` | `modulos/conformidade/relatorios.php`, `conformidade/relatorios/gerar.php` | Relatórios de conformidade |
| `/consulta-cnpo` | `consulta_cnpo/index.php`, `api/cnpo_consulta.php` | Consulta CNPO |

### Regras de negócio
- **Template de roteiro:** `checklist` JSON `[{id, categoria, item, obrigatorio, peso}]`,
  `pontuacao_minima` e `itens_criticos` (reprovam automaticamente).
- **Tipos de visita:** Inicial, Regular, Renovação, Surpresa.
- **Resultado da visita:** `resultado` JSON por item, `pontuacao` calculada,
  `conformidade` (Conforme, Parcialmente, Não Conforme); **NC em item crítico reprova sempre**.
- **NC:** gravidade (Baixa, Média, Alta, Crítica), `acao_corretiva`, `prazo_correcao`,
  status (Aberta, Em Correção, Resolvida), `evidencia_correcao_url`.
- **Certificação:** tipo (SPG, OCS, Rede Ecovida), número, emissão, validade, status
  (Ativa, Vencida, Suspensa, Em Conversão), `dados_adicionais` JSON.
- **Plano de manejo:** ano-safra, áreas (total, orgânica, conversão), práticas, croqui,
  status (Rascunho, Em Análise, Aprovado, Reprovado).

### Defeitos do legado a NÃO reproduzir (achados verificados na análise)

| # | Defeito | Impacto |
|---|---|---|
| D1 | `certificacao/cadastrar.php` grava em colunas **inexistentes** (`data_vencimento`, `escopo`) | Todo INSERT/UPDATE pela UI falha — o cadastro de certificação pela tela está morto |
| D2 | Assinaturas de visita (dataURL base64) gravadas em `VARCHAR(255)` | **Todas as 4 assinaturas no banco estão truncadas** em exatamente 255 caracteres |
| D3 | **IDOR** em `api/plano_manejo.php` (GET, PUT e DELETE sem checagem de dono) | Qualquer usuário lê, altera e apaga plano de outro produtor |
| D4 | Escopo multi-tenant **inexistente** no PHP (`certificadora_id` só resolve logo) | Vazamento entre certificadoras |
| D5 | `MODIFY COLUMN ... ENUM` sobre rótulo em mojibake | **Perda silenciosa de dados**: 11/11 `plano_manejo_cultivos.tipo_semente` vazios e 2 de 5 NCs com `status` vazio |
| D6 | `verificado_por` (INT) recebe nome do usuário | Grava `0` em todas as NCs verificadas |
| D7 | `gravidade` fora da whitelist do PUT | O select de gravidade não tem efeito |
| D8 | `peso` do checklist é exibido mas **nunca entra na fórmula** de pontuação | Pontuação ignora pesos (contagem simples) |
| D9 | `createVisita()` não gera `hashid` | 7 de 12 visitas sem hashid; a rota por hashid é inutilizável |
| D10 | `createVisita()` grava `user_id` de `usuarios` em FK para `associados` | Inconsistência referencial |
| D11 | Renovação **sobrescreve** o registro em vez de criar novo | Histórico de certificação não é preservado |
| D12 | Não existe job que promova o status a "Vencida" | Status fica obsoleto |
| D13 | DELETE físico de certificação apesar de existir `excluido` | *Soft delete* morto |
| D14 | Rotas 404: `/roteiro-inspecao/editar/{id}` e `/roteiro-inspecao/ficha/{id}` | Links quebrados em `templates/listar.php` e `cadastrar.php` |
| D15 | `RoteiroInspecao::delete()` físico contra FK `RESTRICT` | Erro 500 ao excluir roteiro com visitas |
| D16 | Template de certificado é **mock** (dados e logo fixos no código) | Documento inválido |
| D17 | Não há **assinatura digital criptográfica** (sem ICP-Brasil, hash ou timestamp) | Exigência regulatória não atendida |
| D18 | `api/cnpo_consulta.php` usa `REPLACE` encadeado sobre 5 colunas | *Full scan* de 23.232 linhas por consulta |
| D19 | `associados.registro_cnpo` é NULL em 100% dos registros | Vínculo produtor com CNPO inexistente |
| D20 | Duas APIs de certificadoras concorrentes, uma **sem CSRF**, com diretórios de upload diferentes | Superfície de ataque e divergência |
| D21 | XSS sem escaping em `certificacao/historico.php` | Execução de script armazenado |

> **Correções de premissa importantes:** o script `sql/schema_organico_sprint1.sql` está
> **desatualizado** — o schema real é a baseline
> `sinporg/prisma/migrations/0_baseline/migration.sql`. **Não existe classe
> `PlanoManejo`** (o domínio é SQL direto em `api/plano_manejo.php`). **Não existe
> "score de conformidade"**: `ReportConformidade.php` é um **catálogo estático** de 9
> fontes de dados; as métricas reais são a pontuação da visita, o `percentual_organico`
> e a divergência do balanço de massas — e a tolerância de 10% citada na descrição
> **não é aplicada no código**. A sigla **PEAF não existe** em nenhum arquivo do workspace.

---

## M4 — Financeiro da Associação

**Objetivo:** mensalidades, cobrança PIX, caixa e contas.

**Tabelas (dono):** `planos_mensalidades`, `mensalidades`, `pagamentos`,
`caixa`, `contas`.

### Rotas

| Rota legada | Arquivo | Propósito |
|---|---|---|
| `/mensalidades` | `mensalidades/listar.php` | Lista de mensalidades |
| `/mensalidade/novo` | `mensalidades/cadastrar.php` | Lançamento manual |
| `/mensalidade/gerar` | `mensalidades/gerar.php` | **Geração em lote do mês** |
| `/mensalidade/{id}/baixa` | `mensalidades/baixa.php` | Baixa de pagamento |
| `/mensalidade/{id}/estornar` | `mensalidades/estornar.php` | Estorno |
| `/mensalidade/{id}/excluir` | `mensalidades/excluir.php` | Exclusão |
| `/planos` | `mensalidades/planos.php` | Planos de mensalidade |
| `/plano/novo`, `/plano/{id}/editar`, `/plano/{id}/excluir` | `mensalidades/plano_*.php` | CRUD de planos |
| `/caixa` | `caixa/listar.php` | Movimentações de caixa |
| `/caixa/lancamento` | `caixa/cadastrar.php` | Novo lançamento |
| `/caixa/cobranca` | `caixa/cobranca.php` (14 KB) | **Cobrança PIX** |
| `/caixa/relatorio` | `caixa/relatorio.php` | Relatório de caixa |
| `/caixa/{id}/excluir` | `caixa/excluir.php` | Exclusão |
| `/contas` | `contas/listar.php` | Contas a pagar e a receber |
| `/conta/novo`, `/conta/{id}/editar` | `contas/*.php` | CRUD de contas |
| — | `api/pix/webhook/{asaas,gerencianet,mercadopago}.php` | **Webhooks PIX** |
| `/comunicacao` | `modulos/comunicacao/index.php` (20 KB) | Central de comunicação |

### Regras de negócio e riscos
- **Ciclo:** plano → geração de mensalidades → cobrança PIX → webhook de confirmação →
  baixa → caixa → relatórios.
- **Transação cruzada interna:** `classes/Mensalidade.php` faz INSERT e DELETE em
  `pagamentos` e `caixa` (linhas 266, 319, 324, 423) — o estorno precisa ser
  **transacional e idempotente**.
- **Domínio sem nenhuma FK:** `mensalidades`, `pagamentos`, `caixa`,
  `planos_mensalidades` e `contas` não têm chave estrangeira. Pior:
  `mensalidades.associado_id` e `pagamentos.associado_id` são `bigint unsigned`
  enquanto `associados.id` é `int(11)` — **a FK não pode sequer ser criada** sem
  alinhar o tipo (achado do mapeamento de schema).
- **Segurança crítica:** os webhooks PIX **não validam assinatura nem idempotência** no
  legado. É o risco A-12 e exige os testes **S-09** e **S-10**.
- **Caminho quebrado:** `api/comunicacao/whatsapp_links.php` usa `../../../config`
  (fora do app) — corrigir.

---

## M5 — Produção Animal

**Objetivo:** manejo animal — lotes, ambientes, alimentação, sanidade, reprodução e
rastreabilidade.

**Tabelas (dono):** `animais`, `animal_lotes`, `manejo_animal_registros`,
`controle_alimentacao_animal`, `controle_sanidade_animal`,
`controle_reproducao_animal`, `ambiente_criacao_animal`,
`ambiente_criacao_membros`, `densidades_maximas_animais`.

### Rotas

| Rota legada | Arquivo | Propósito |
|---|---|---|
| `/producao-animal` | `producao_animal/painel.php` | Painel de produção animal |
| `/producao-animal/lotes` | `lotes_listar.php` | Lotes |
| `/producao-animal/lote/novo`, `/lote/{id}/editar` | `lotes_*.php` | CRUD de lote |
| `/producao-animal/alimentacao` | `alimentacao_listar.php` | Alimentação |
| `/producao-animal/alimentacao/novo`, `/{id}/editar` | `alimentacao_*.php` | CRUD |
| `/producao-animal/sanidade` | `sanidade_listar.php` | Sanidade |
| `/producao-animal/sanidade/novo`, `/{id}/editar` | `sanidade_*.php` | CRUD |
| `/producao-animal/reproducao` | `reproducao_listar.php` | Reprodução |
| `/producao-animal/reproducao/novo`, `/{id}/editar` | `reproducao_*.php` | CRUD |
| `/producao-animal/ambientes` | `ambientes_listar.php` | Ambientes de criação |
| `/producao-animal/ambiente/novo`, `/{id}/editar` | `ambientes_*.php` | CRUD |
| `/producao-animal/rastreabilidade` | `rastreabilidade.php` (20 KB) | Rastreabilidade animal |
| `/manejo-animal` | `manejo_animal/animais_listar.php` | Animais |
| `/manejo-animal/novo`, `/{id}/editar`, `/{id}` | `animais_*.php` | CRUD e visualização |

### Regras de negócio
- **Densidade máxima por ambiente** (`densidades_maximas_animais`) — validar o limite ao
  alocar animais.
- **Conversão para orgânico** (`animais.origem_conversao`, `tipo_entrada`) — regra
  regulatória de período de conversão.
- **Sanidade:** tipo de tratamento e carência — um animal tratado não pode ser certificado
  dentro do período de carência.
- **Módulo de MENOR acoplamento** do sistema (depende apenas de `associado_id`) — é o
  **primeiro a migrar** e o melhor candidato a serviço independente.

---

## M6 — Agroindústria

**Objetivo:** processamento, receitas, ordens de produção, balanço de massas,
higienização e rotulagem.

**Tabelas (dono):** `agroindustrias`, `agroindustria_membros`,
`agroindustria_existentes`, `agroindustria_atualizacoes`, `agro_setores`,
`agro_categorias_atividades`, `agro_atividades_diarias`, `agro_ocorrencias`,
`receitas_agroindustria`, `ingredientes_receita`, `ordens_producao`,
`controle_higienizacao`, `rastreabilidade_insumos_processo`.

### Rotas

| Rota legada | Arquivo | Propósito |
|---|---|---|
| `/agroindustria/minhas` | `minhas_listar.php` | Unidades de agroindústria do produtor |
| `/agroindustria/minha/nova`, `/minha/{id}/editar` | `minhas_*.php` (10 e 15 KB) | CRUD da unidade |
| `/agroindustria/setores`, `/setor/novo`, `/setor/{id}/editar` | `setores_*.php` | Setores de processamento |
| `/agroindustria/categorias`, `/categoria/novo`, `/{id}/editar` | `categorias_*.php` | Categorias de atividade |
| `/agroindustria/atividades`, `/atividade/novo`, `/{id}/editar` | `atividades_*.php` | Atividades diárias da agroindústria |
| `/agroindustria/ocorrencias`, `/ocorrencia/novo`, `/{id}/editar` | `ocorrencias_*.php` | Ocorrências |
| `/agroindustria/receitas` | `receitas_listar.php` | Receitas e formulações |
| `/agroindustria/receitas/nova`, `/receitas/{id}/editar` | `receitas_*.php` (12 e 15 KB) | CRUD de receita e ingredientes |
| `/agroindustria/higienizacao`, `/higienizacao/nova` | `higienizacao_*.php` | **Livro de higienização** |
| `/agroindustria/ordens`, `/ordens/nova` | `ordens_listar.php`, `ordens_cadastrar.php` (20 KB) | Ordens de produção |
| `/agroindustria/ordens/{id}` | `ordens_visualizar.php` (13 KB) | Visualização da ordem e balanço de massas |
| `/agroindustria/rastreabilidade` | `rastreabilidade.php` (14 KB) | Rastreabilidade do lote final |
| — | `TODO_Agroind.md` | PRD regulatório do módulo |

### Regras de negócio (regulatórias — críticas)
- **RF07 — Fluxograma e receita:** ingredientes com quantidade e unidade.
- **RF08 — Segregação espaço-temporal:** exige registro de higienização prévia quando a
  mesma planta processa convencional e orgânico.
- **RF09 — Percentual orgânico e rotulagem:** maior ou igual a 95 por cento permite o selo
  *Produto Orgânico Brasil*; entre 70 e 95 por cento permite apenas "Produto com
  ingredientes orgânicos"; **abaixo de 70 por cento bloqueia qualquer menção a orgânico**.
  Implementar como **serviço testável** (ver `06-SEGURANCA-E-CONFORMIDADE.md` seção 3.3).
- **RF10 — Link campo para agroindústria:** a ordem de produção consome lote do caderno de campo.
- **RF11 — Balanço de massas:** saída processada maior que a entrada de matéria-prima
  dispara **alerta** (indício de mistura ou fraude).
- **RF12 — QR Code do lote final** para consulta pública.
- **NFR01 — Retenção de 3 anos** (*soft delete*) em diário, notas de insumo e balanço.
- **Acoplamento medido:** `classes/Agroindustria.php:258` faz
  `UPDATE associados SET tem_agroindustria=1` — **violação da Lei L2**; na versão nova
  vira evento mais projeção no kernel.
- **`agroindustria_existentes` (133 linhas)** e `agroindustria_atualizacoes` parecem
  tabelas de importação da base externa PEAF/SDR-RS, coexistem com `agroindustrias` sem
  vínculo — **decidir canonicidade** (duplicação conceitual).
- Todo o domínio `agro_*` (9 tabelas) está **sem nenhuma FK**.

---

## M7 — Suprimentos & Insumos

**Objetivo:** insumos, lista oficial de permitidos e fornecedores.

**Tabelas (dono):** `insumos`, `insumos_organicos_aprovados`, `fornecedores`,
`fornecedor_unidades`.

### Rotas

| Rota legada | Arquivo | Propósito |
|---|---|---|
| `/fornecedor` | `fornecedor/listar.php` | Lista de fornecedores |
| `/fornecedor/novo` | `fornecedor/cadastrar.php` (14 KB) | Cadastro |
| `/fornecedor/{id}/editar` | `fornecedor/editar.php` (21 KB) | Edição |
| — | `fornecedor/excluir.php` | Exclusão |
| `/insumos-aprovados` | `certificacao/insumos.php` | Lista oficial (Portaria 52/2021) |
| — | `api/insumos_aprovados.php` | API |
| `/caderno-campo/insumos` e subrotas | `caderno_campo/insumos/*.php` | Caderno de insumos do produtor (ver M2) |

### Regras de negócio
- **`insumos_organicos_aprovados`:** 250 linhas; categoria, tipo, `permitido` (booleano),
  `restricoes`, `normativa`. Seeds em três arquivos (vegetal, animal e processamento,
  ~48 KB no total).
- **`Produto` e `Fornecedor` agregam 3 domínios:** `Fornecedor::getUnidades` une
  `propriedades`, `agroindustrias` e `ambiente_criacao_animal` — violação de fronteira
  a resolver com o *registry* de unidades produtivas do kernel.
- **Tipos de insumo:** Orgânico, Em conversão, Convencional, Não permitido.
- **Dados suspeitos no schema:** `fornecedores.cidade` e `fornecedores.estado` são
  `int(11)` enquanto `endereco` e `bairro` são texto; `propriedades.latitude` e
  `longitude` são `varchar(20)` em vez de decimal. **Corrigir na Fase 0.**
- **Tabela `culturas` está vazia (0 linhas)** — verificar se deve existir ou ser removida.

---

## M8 — Governança, Eventos & Comunicação

**Objetivo:** assembleias com votação, eventos com presença, notificações e comunicação.

**Tabelas (dono):** `eventos`, `evento_presenca`, `assembleias`,
`assembleia_pautas`, `assembleia_votos`, `participacoes` (**tabela morta**);
`notificacoes` (compartilhada com o kernel).

### Rotas

| Rota legada | Arquivo | Propósito |
|---|---|---|
| `/assembleia` | `assembleia/listar.php` | Lista de assembleias |
| `/assembleia/cadastrar` | `assembleia/cadastrar.php` | Convocação |
| `/assembleia/{id}/editar` | `assembleia/editar.php` | Edição |
| `/assembleia/{id}/pautas` | `assembleia/pautas.php` (14 KB) | Pautas da assembleia |
| `/assembleia/{id}/publicar` | `assembleia/publicar.php` | Publicação (notifica associados) |
| `/assembleia/{id}/visualizar` | `assembleia/visualizar.php` (19 KB) | Visualização e votação |
| `/assembleia/{id}/encerrar` | `assembleia/encerrar.php` | Encerramento |
| `/assembleia/{id}/resultado` | `assembleia/resultado.php` (14 KB) | Apuração do resultado |
| `/eventos` | `eventos/listar.php` (18 KB) | Lista de eventos |
| `/evento/novo` | `eventos/cadastrar.php` | Cadastro |
| `/evento/{id}` | `eventos/visualizar.php` | Visualização |
| `/evento/{id}/editar` | `eventos/editar.php` | Edição |
| `/eventos/presencas`, `/evento/presencas` | `eventos/presencas.php` | Confirmação de presença |
| — | `eventos/acompanhar_presenca.php`, `relatorio_presenca.php` | Acompanhamento e relatório |
| — | `api/get_eventos_pendentes.php`, `get_eventos_simples.php`, `get_presenca_evento.php`, `toggle_presenca.php`, `limpar_modal_eventos.php` | APIs de eventos |
| `/comunicacao` | `comunicacao/index.php` (20 KB) | Central de comunicação |
| — | `includes/lembretes.php`, `classes/Lembrete.php` | Lembretes (injetados no header global) |
| — | `includes/widget_eventos.php`, `assets/js/eventos*.js` | Widget e modais de evento |
| — | `modulos/admin/clusters.php` | Clusters de comunicação (**UPDATE em associados** — viola L2) |

### Regras de negócio
- **Ciclo da assembleia:** rascunho → publicada (notifica) → em votação → encerrada →
  resultado apurado. **Resultado imutável após o encerramento.**
- **Tipos de votação** (`assembleias.tipo_votacao`) — maioria simples, qualificada etc.
- **1 voto por associado** por pauta; presença registrada em `participacoes` — que é
  **tabela morta** (0 linhas, 0 FKs, nenhuma referência no código): foi substituída por
  `evento_presenca`. **Remover.**
- **Eventos:** status, presença confirmada, lista de presença e relatório.
- **Notificações:** `notificacoes` com 364 linhas (a tabela mais usada depois de
  `cnpo`), `tipo_usuario`; lembretes automáticos com `dias_antecedencia_evento`.
  **Defeito de schema:** a coluna `dados_adicional` tem typo no nome.
- **Acoplamento do shell:** `header.php` injeta `lembretes.php` e
  `ficha_adesao_modal.php` em **todas** as páginas, e `footer.php` injeta os modais de
  visita — o shell conhece domínios (a resolver no BFF).

---

## M9 — Territorialidade & CNPO

**Objetivo:** dados de referência geográfica e a base CNPO.

**Tabelas (dono):** `estados`, `cidades`, `mesorregioes_rs`, `microrregioes_rs`,
`cnpo`, `cnpo_atualizacoes`.

### Rotas

| Rota legada | Arquivo | Propósito |
|---|---|---|
| `/regionalizacao` | `regionalizacao/index.php` | Painel de regionalização |
| — | `regionalizacao/mapa.php` (14 KB) | Mapa por microrregião |
| — | `api/get_cidades.php`, `get_estado_cidade.php`, `get_associacao_detalhes.php` | APIs de apoio |
| — | `api/regionalizacao/dados.php`, `microrregioes.php` | APIs de regionalização |
| `/consulta-cnpo` | `consulta_cnpo/index.php`, `api/cnpo_consulta.php` | Consulta à base CNPO |
| — | `map_cidades_micro.php`, `municipio_micro_map.php`, `update_associados_regiao.php` | Scripts de carga e mapa (**remover do webroot**) |
| — | `dev-utils/atualizar_cnpo.php`, `atualizar_peaf.php`, `migrate_peaf_cnpo.php` | Rotinas de atualização externa |
| — | `scripts/sincronizar_externos.sh`, `scripts/CRON_PRO.md` | Sincronização agendada |

### Regras de negócio e riscos
- **`cnpo` tem 23.232 linhas** (a maior tabela do sistema) e **`cidades` tem 5.595** —
  juntas, 95% de todos os registros do banco. O sistema operacional tem apenas 1.505 registros.
- **Baixo acoplamento e somente leitura** para os demais módulos: **candidato ideal a
  serviço de referência cacheável**.
- **`cidades.estado` é `int(11)` sem FK**, então a tabela `estados` (28 linhas) está
  **desconectada** do resto do schema. **Corrigir na Fase 0.**
- **`associados.registro_cnpo`** é `UNIQUE int` que aponta semanticamente para
  `cnpo` **sem FK** — e está NULL em 100% dos associados: **não há vínculo produtor ↔ CNPO**.
- **Importação sem cron versionado:** `dev-utils/atualizar_cnpo.php` faz *scraping* da
  página do MAPA, baixa o `cnpo-mapa-DD-MM-AAAA.xlsx`, recarrega via `RENAME TABLE` e
  registra em `cnpo_atualizacoes` (idempotente por nome de arquivo). Precisa virar job
  versionado e observável.
- **Dados externos:** planilhas PEAF do MAPA em `recicla/*.xlsx` (~1,2 MB e 3,9 MB).

---

## M10 — Relatórios & Analytics

**Objetivo:** **camada somente-leitura** que atravessa todos os domínios. **Não é um
módulo com tabelas próprias** — é uma camada de consulta.

**Escopo:** `relatorios.php` (57 KB), `relatorios/index.php` (45 KB),
`classes/ReportManager.php` (26 KB), `classes/ReportConformidade.php` (49 KB),
`classes/Relatorio.php`, `config/relatorios_config.php` (11 KB), `relatorios/*.php`
(33 arquivos), `api/relatorios/**` (22 arquivos), `modulos/relatorios_diretoria/**`.

### Rotas

| Rota legada | Arquivo | Propósito |
|---|---|---|
| `/relatorios` | `relatorios.php` | Hub de relatórios |
| `/relatorios/{categoria}/{tipo}` | `relatorios.php` | Relatório específico |
| `/relatorios/{nome}/pdf` e `/relatorios/{nome}/excel` | `relatorios/{nome}.php?tipo=` | Exportação (regra do `.htaccess`) |
| — | `relatorios/{associados,atividades,dashboard,eventos,financeiro,insumos,lavouras,produtores,propriedades}.php` | Relatórios por domínio |
| — | `relatorios/form_{atividades,financeiro,insumos,personalizado,propriedades}.php` | Formulários de filtro |
| — | `relatorios/templates/*.php` mais `relatorio_estilo.css` (11 KB) | **Templates HTML dos relatórios** |
| — | `relatorios/gerar_pdf.php`, `gerar_certificado.php` | Geração de PDF |
| — | `api/relatorios/admin_export.php` (17 KB) | Exportação administrativa |
| — | `api/relatorios/{visao-geral,produtividade,inadimplencia,fluxo-caixa,contas-pagar,contas-receber,estoque-atual,movimentacoes,pagamentos-por-forma,previsao-receita,status-associados,lista-associados,rapido,gerar,atividades-diarias,fluxo-caixa-realizado-previsto,relatorio_associados,relatorio_atividades_usuario}.php` | Endpoints de relatório |
| — | `api/relatorios_controller.php`, `api/relatorios_admin.php` | Controllers |
| `/dashboard/...` | `modulos/dashboard/dashboard_{admin,gestor,agroindustria,diretoria}.php` | Dashboards por perfil |
| — | `modulos/relatorios_diretoria/{ativos_municipio,entradas_saidas,inadimplentes_micro}.php` | Relatórios da diretoria |
| — | `relatorios_router.php` | **Código morto — remover** |

### Regras e riscos
- **Maior agregador cross-domain** do sistema: lê tabelas de 6 domínios por SQL direto. Na
  versão nova: **views e endpoints autenticados**, nunca SQL em tabela de módulo (Lei L1).
- **4 bibliotecas de PDF no legado:** mPDF, TCPDF e Dompdf — mais PhpSpreadsheet para Excel.
  **Decisão ADR-006:** Puppeteer sobre o HTML existente, o que preserva o layout já aprovado.
- **Hoje os relatórios travam a requisição web** — na versão nova vão para **BullMQ**
  (ADR-007), com entrega por notificação e download.
- **`relatorios/eventos.php` chama `ReportManager::getEventosReport` inexistente** —
  página quebrada (corrigir ou remover).
- **`api/relatorios/visao-geral.php` responde 200 sem autenticação** e quebra em coluna
  inexistente — risco A-01.
- **`relatorios/*.php` redirecionam 302** em parte — consolidar.

---

## M11 — Portal & Dashboards (BFF)

**Objetivo:** interface própria do associado e os dashboards por perfil.

| Rota legada | Arquivo | Propósito |
|---|---|---|
| `/portal/login` | `modulos/portal/login.php` (30 KB) | **Login do associado** (autentica em `associados`) |
| `/portal/area` | `modulos/portal/area.php` (51 KB) | Área do associado (**maior arquivo de módulo do sistema**) |
| `/portal/logout` | `modulos/portal/logout.php` | Logout |
| — | `classes/PortalAccess.php` (18 KB) | Controle de acesso ao portal |
| `/dashboard/...` | `modulos/dashboard/dashboard_*.php` | 4 dashboards |
| — | `modulos/caderno_campo/dashboard_agricultor.php` (33 KB) | Dashboard do agricultor |
| — | `includes/perfil.php` | Helper de perfil e redirecionamento |

### Regras de negócio
- **Perfis e dispatch** (ver `PLANO_DASHBOARDS_POR_PERFIL.md`): nível 1 Admin; nível 2
  Gestor e Técnico; nível 3 Associado, subdividido por `tipo_usuario` em `agricultor` e
  `produtor_agroindustria` — **este último não tem dashboard próprio** no legado.
- **Acesso ao portal** é controlado por `PortalAccess` — verificar regras de janela de
  tempo, IP e bloqueio.
- **Violação de fronteira:** `portal/area.php` faz `UPDATE associados` direto.
- **Bug conhecido:** `includes/header.php` lê `$_SESSION['nivel']` (a chave correta é
  `user_nivel`), então `window.USER_NIVEL` é sempre vazio.
- **`includes/perfil.php`** é o helper de dispatch, listado como arquivo-chave do plano de
  dashboards por perfil.

---

## M12 — Auditoria, Configuração & Administração

**Objetivo:** trilha de auditoria, gestão de usuários do sistema, configurações e
*feature flags*.

**Tabelas:** `audit_log` (247 linhas), `usuarios` (12), `password_resets`,
`auth_refresh_tokens` (7), mais `kernel_usuarios` e `feature_flags` (novas).

### Rotas

| Rota legada | Arquivo | Propósito |
|---|---|---|
| `/usuarios` | `modulos/usuarios/listar.php` | Lista de usuários do sistema |
| `/usuario/novo` | `modulos/usuarios/cadastrar.php` | Cadastro |
| `/usuario/{id}/editar` | `modulos/usuarios/editar.php` (14 KB) | Edição |
| — | `modulos/usuarios/excluir.php` | Exclusão |
| `/usuario/perfil` | `modulos/usuarios/perfil.php` | Meu perfil |
| `/usuario/configuracoes` | `modulos/usuarios/configuracoes.php` | Configurações |
| `/usuario/salvar_notificacoes` | `modulos/usuarios/salvar_notificacoes.php` | Preferências (**UPDATE em associados** — viola L2) |
| `/login` | `login.php` (48 KB) | Login do staff, com **2 etapas para gestor** |
| `/logout` | `logout.php` | Logout |
| `/recuperar-senha`, `/redefinir-senha/{token}` | `recuperar_senha.php`, `redefinir_senha.php` | Recuperação de senha |
| `/completar-perfil` | `completar_perfil.php` | Complemento obrigatório de perfil |
| `/admin/...` | `modulos/admin/clusters.php` | Clusters de comunicação |
| — | `classes/AuditLog.php` (56 chamadas) | Trilha de auditoria |
| — | `tools/listar_usuarios.php`, `tools/gerar_manual.php`, `tools/capturar_screenshots.js` | Ferramentas (**fora do webroot**) |

### Regras de negócio
- **Login em 2 etapas do gestor:** etapa 1 credenciais; etapa 2 seleção de órgão,
  especialidade e setor. Busca unificada em `associados` **ou** `usuarios`, com
  `password_verify`. Exige `session_regenerate_id` — **ausente no legado**.
- **Redirecionamento por perfil** após o login.
- **Recuperação de senha:** duas tabelas com esquemas divergentes (`token_hash` e
  `used` contra `token` e `usado`) — **unificar na Fase 0**. O código de hoje já usa
  apenas `classes/PasswordReset.php`, então o alerta antigo está resolvido no código, mas o
  legado de colunas permanece.
- **Nível e tipo correlacionados:** `NIVEL_ADMIN=1`, `NIVEL_GESTOR=2`,
  `NIVEL_USUARIO=3` e `usuarios.tipo` em (admin, gestor, usuario).
  **Substituir por papéis mais `pode()`.**
- **Bug de schema a corrigir:** `auth_refresh_tokens.expires_at` está definida como
  `timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()` — a
  coluna de **expiração é reescrita para NOW() a cada UPDATE**, então os tokens **nunca
  expiram** na prática, e sem valor no INSERT o token nasce expirado. **Corrigir antes de
  usar.**
- **`audit_log`** existe e é usado — oficializar como serviço do kernel em modo
  *append-only*.

---

## Anexo A — Rotas de debug a REMOVER

| Rota | Arquivo | Motivo |
|---|---|---|
| `/teste` | `teste_evento.php` | Script de teste no webroot |
| `/teste-db` | `teste_db.php` | idem |
| `/teste-ajax` | `teste_ajax.php` | idem |
| — | `check_*.php`, `run_*.php`, `fix_*.php`, `debug_*.php`, `_tmp_*.php` (36 arquivos na raiz) | Ferramentas destrutivas expostas |
| — | `dev-utils/**` (33 arquivos) | Migrações e diagnósticos manuais |
| — | `index.html` (45 KB) | Cópia antiga do roteador — **vaza código-fonte** |
| — | `relatorios_router.php` | Código morto |

---

## Anexo B — Caminhos conhecidamente quebrados (corrigir, não replicar)

| Arquivo | Defeito |
|---|---|
| `api/relatorios/rapido.php` | Usava `../../../config` (fora do app) — já corrigido no legado |
| `api/comunicacao/whatsapp_links.php` | `../../../config` — **pendente** |
| `api/regionalizacao/microrregioes.php` | `../../../config` — **pendente** |
| `includes/header.php` | Lê `$_SESSION['nivel']` em vez de `user_nivel` |
| `api/relatorios/visao-geral.php` | Responde 200 sem autenticação e quebra em coluna inexistente |
| `relatorios/eventos.php` | Chama método inexistente `ReportManager::getEventosReport` |
| `recicla/cadastrar.php`, `recicla/listar.php` | Arquivos vazios (0 bytes) |
| `certificacao/cadastrar.php` | Grava em colunas inexistentes (`data_vencimento`, `escopo`) |
| `api/plano_manejo.php` | IDOR em GET, PUT e DELETE |
| `visitas_tecnicas` | Assinaturas truncadas em 255 caracteres |

---

## Anexo C — Tabelas mortas a remover (evidência verificada)

| Tabela | Linhas | FKs | Referências no código | Situação |
|---|---:|---:|---|---|
| `caderno_campo` | 0 | 0 | 0 | Substituída por `atividades_diarias` (a **tabela** é morta; o **módulo** `modulos/caderno_campo/` é ativo) |
| `participacoes` | 0 | 0 | 0 | Substituída por `evento_presenca`; contém colunas resquício |
| `lote_atividade` | 0 | 0 | 0 | Nunca usada |
| `entidades` | 0 | 0 | 0 | Nunca usada; PK sem `AUTO_INCREMENT` |
| `certificacao_produtos` | 0 | — | 1 leitura | Órfã (lida em `certificacao/cadastrar.php`) |

---

## Anexo D — Volumetria de telas por módulo (referência de esforço)

| Módulo | Arquivos PHP | Maior arquivo |
|---|---:|---|
| M2 Caderno de Campo | 64 | `dashboard_agricultor.php` (33 KB) |
| M10 Relatórios | 33 | `relatorios/index.php` (45 KB) |
| M6 Agroindústria | 24 | `ordens_cadastrar.php` (20 KB) |
| M1 Associados | 17 | `ficha_adesao_visualizar.php` (43 KB) |
| M5 Produção Animal | 17 | `rastreabilidade.php` (20 KB) |
| M3 Fiscalização | 12 | `visitas/realizar.php` (32 KB) |
| M4 Financeiro | 10 | `caixa/cobranca.php` (14 KB) |
| M8 Governança e Eventos | 8 | `assembleia/visualizar.php` (19 KB) |
| M12 Usuários e Admin | 8 | `usuarios/editar.php` (14 KB) |
| M11 Portal e Dashboards | 6 | `portal/area.php` (51 KB) |
| M9 Territorial | 4 | `municipio_micro_map.php` (14 KB) |
| M7 Suprimentos | 4 | `fornecedor/editar.php` (21 KB) |

**Total de arquivos PHP do sistema:** 441 · **84.734 linhas** (excluindo `vendor/`,
`node_modules/`, `recicla/` e `producao_bkp/`).
