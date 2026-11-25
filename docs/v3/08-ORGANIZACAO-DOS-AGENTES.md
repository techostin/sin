# 08 — Organização dos Agentes: Construtores e Fiscais

> **Como ler este documento.** Ele define *quem* executa a replicação, *como* cada módulo
> tem um dono e um fiscal, e *quando* o ciclo de um módulo pode ser declarado encerrado.
> O prompt operacional que instancia esses agentes está em `07-PROMPT-MESTRE-MULTIAGENTE.md`.

---

## 1. Modelo de operação

O requisito central é: **cada módulo tem um agente dedicado que constrói e outro que
fiscaliza, em ciclo fechado, até que os objetivos do módulo sejam comprovadamente
atingidos.** Isso se traduz em três regras não-negociáveis:

> **R1 — Paridade obrigatória.** Nenhum módulo é declarado pronto sem um **Fiscal de
> Paridade** que compare comportamento novo × legado, ação por ação.
>
> **R2 — Separação de funções.** O fiscal **nunca** é o mesmo agente que construiu, e
> **não** aceita a declaração do construtor como evidência: ele reproduz a verificação.
>
> **R3 — Ciclo com teto.** O ciclo constrói → fiscaliza → corrige repete até passar
> **ou** até 3 rodadas. Na 3ª falha o módulo é **bloqueado** e escalado — nunca "aprovado
> por cansaço".

```
                        ┌──────────────────────┐
                        │  ORQUESTRADOR (A0)   │  dono do plano, do backlog e dos gates
                        └───────────┬──────────┘
                                    │
        ┌───────────────────────────┼───────────────────────────┐
        │                           │                           │
┌───────▼────────┐        ┌─────────▼─────────┐        ┌────────▼────────┐
│ PLATAFORMA     │        │ MÓDULOS (x12)     │        │ FISCAIS         │
│ (transversal)  │        │ pares Dev+Fiscal  │        │ TRANSVERSAIS    │
│ A3..A9         │        │ M1..M12           │        │ F1..F4          │
└────────────────┘        └─────────┬─────────┘        └────────┬────────┘
                                    │                           │
                          ┌─────────▼─────────┐                 │
                          │  LOOP DO MÓDULO   │◄────────────────┘
                          │  1. Dev constrói  │
                          │  2. Fiscal audita │
                          │  3. Dev corrige   │
                          │  4. re-audita     │
                          │  → GATE ou BLOQUEIO│
                          └───────────────────┘
```

---

## 2. Catálogo de agentes

### 2.1 Governança

| ID | Agente | Missão | Entregável central |
|---|---|---|---|
| **A0** | `orquestrador` | Decompor, sequenciar ondas, arbitrar conflitos, manter o backlog, **decidir os gates** | `docs/v3/STATUS.md` + backlog vivo |
| **A1** | `arquiteto-kernel` | Definir e congelar o contrato do "dado comum" (`AssociadoRef`, `UnidadeProdutivaRef`, `ContextoUsuario`) | `packages/contracts/src/kernel.ts` + ADRs |
| **A2** | `arquiteto-dados` | Schema Prisma, migrações **aditivas**, ownership de tabelas, seeds anonimizados | `prisma/schema.prisma` + `docs/v3/03-*` |

### 2.2 Plataforma (transversal — roda antes dos módulos)

| ID | Agente | Missão | Entregável central |
|---|---|---|---|
| **A3** | `plataforma-api` | Bootstrap NestJS 12 + Fastify + Prisma 7 + Zod + OpenAPI + filtros/erros | `apps/api` sobe, `/api/docs`, `/api/v2/health` |
| **A4** | `plataforma-auth` | Kernel de identidade: JWT, refresh rotativo, guards, rate-limit, ponte de sessão | `apps/api/src/kernel` completo |
| **A5** | `plataforma-contratos` | `packages/contracts` + `packages/sdk` (com fila offline) | contratos versionados |
| **A6** | `plataforma-web` | Next.js 16 App Router, layout dual-mode, design system Tailwind/shadcn | `apps/web` navegável |
| **A7** | `plataforma-sync` | Protocolo offline: outbox, `Idempotency-Key`, deltas, resolução de conflito | `apps/api/src/modules/sync` |
| **A8** | `plataforma-relatorios` | Pipeline HTML→PDF (Puppeteer), ExcelJS, BullMQ, templates | `modules/relatorios` + worker |
| **A9** | `plataforma-devops` | Docker multi-stage, Compose, CI/CD, observabilidade, backups | `infra/` + pipeline verde |
| **A10** | `plataforma-mobile` | Expo SDK 57, `expo-router`, SQLite, secure-store, câmera/GPS/QR | `apps/mobile` |

### 2.3 Módulos — cada linha é um **PAR** (Construtor + Fiscal)

| ID | Módulo | Construtor | Fiscal | Tabelas sob responsabilidade |
|---|---|---|---|---|
| **M1** | Associados & Quadro Associativo | `dev-associados` | `fiscal-associados` | `associados, associacoes, associado_associacao, ficha_adesao, ficha_adesao_produtos, carteirinhas` |
| **M2** | Caderno de Campo (Produção Vegetal) | `dev-caderno-campo` | `fiscal-caderno-campo` | `propriedades, propriedade_membros, lavouras, atividades_diarias, categorias_atividades, produtos, lotes_producao, lote_atividade, lote_insumo, ocorrencias_manejo, historico_conversao` |
| **M3** | Fiscalização & Conformidade | `dev-fiscalizacao` | `fiscal-fiscalizacao` | `roteiros_inspecao, visitas_tecnicas, nao_conformidades, plano_manejo, plano_manejo_cultivos, certificacao_participativa, certificadoras` |
| **M4** | Financeiro da Associação | `dev-financeiro` | `fiscal-financeiro` | `planos_mensalidades, mensalidades, pagamentos, caixa, contas` |
| **M5** | Produção Animal | `dev-producao-animal` | `fiscal-producao-animal` | `animais, animal_lotes, manejo_animal_registros, controle_{alimentacao,sanidade,reproducao}_animal, ambiente_criacao_{animal,membros}, densidades_maximas_animais` |
| **M6** | Agroindústria | `dev-agroindustria` | `fiscal-agroindustria` | `agroindustrias, agroindustria_membros, agro_setores, agro_categorias_atividades, agro_atividades_diarias, agro_ocorrencias, receitas_agroindustria, ingredientes_receita, ordens_producao, controle_higienizacao, rastreabilidade_insumos_processo` |
| **M7** | Suprimentos & Insumos | `dev-suprimentos` | `fiscal-suprimentos` | `insumos, insumos_organicos_aprovados, fornecedores, fornecedor_unidades` |
| **M8** | Governança, Eventos & Comunicação | `dev-governanca` | `fiscal-governanca` | `eventos, evento_presenca, assembleias, assembleia_pautas, assembleia_votos, notificacoes, participacoes` |
| **M9** | Territorialidade & CNPO | `dev-territorial` | `fiscal-territorial` | `estados, cidades, mesorregioes_rs, microrregioes_rs, cnpo, cnpo_atualizacoes` |
| **M10** | Relatórios & Analytics | `dev-relatorios` | `fiscal-relatorios` | *(somente leitura — nenhuma tabela própria)* |
| **M11** | Portal & Dashboards (BFF) | `dev-portal` | `fiscal-portal` | *(nenhuma — consome APIs)* |
| **M12** | Auditoria, Configuração & Administração | `dev-admin` | `fiscal-admin` | `audit_log, kernel_usuarios, password_resets, auth_refresh_tokens, feature_flags` |

### 2.4 Fiscais transversais

| ID | Agente | Missão | Quando atua |
|---|---|---|---|
| **F1** | `fiscal-paridade` | **Comparar comportamento novo × legado PHP**, tela a tela e campo a campo. É o fiscal mais importante do projeto. | Em **todo** gate de módulo |
| **F2** | `fiscal-seguranca` | OWASP, authz quebrada, IDOR, multi-tenant, webhooks, segredos, uploads | Em todo gate + antes de cada deploy |
| **F3** | `fiscal-qualidade` | Cobertura de testes, lint, tipos, fronteiras de módulo, complexidade, DDL | Em todo gate |
| **F4** | `fiscal-regulatorio` | Conformidade legal (Lei 10.831/2003, Decreto 6.323/2007, Portaria 52/2021, IN 50/2009), retenção, rastreabilidade | M2, M3, M6 + auditoria final |

---

## 3. Protocolo Construtor ↔ Fiscal (o coração do método)

### 3.1 Contrato de evidência

O construtor **não** entrega a frase "está pronto". Ele entrega um **dossiê** com
evidência reproduzível. O fiscal **não** aceita nada que não possa reproduzir.

```
modules/<modulo>/
├── README.md                     # o que o módulo faz, rotas, eventos, dependências
├── EVIDENCIAS.md                 # ★ dossiê do construtor (índice de provas)
├── evidencias/
│   ├── unit-<modulo>.txt         # saída de `pnpm test` (verde, com contagem)
│   ├── e2e-<modulo>.txt          # saída de Playwright
│   ├── openapi.json              # contrato exportado (rota a rota)
│   ├── paridade-<tela>.md        # tabela legado × novo, campo a campo
│   ├── cobertura.txt             # relatório de cobertura
│   └── screenshots/              # capturas das telas equivalentes
└── PARECER-FISCAL.md             # ★ veredito do fiscal (ver 3.4)
```

### 3.2 As 6 perguntas que o fiscal responde (sempre as mesmas)

| # | Pergunta | Como o fiscal verifica |
|---|---|---|
| 1 | **Paridade** — toda ação do legado existe na nova versão? | Percorre a matriz de paridade; abre as duas versões; compara campo a campo |
| 2 | **Autorização** — cada rota valida identidade, papel e escopo (*tenant*)? | Tenta acessar com outro papel e com o ID de outro associado/certificadora (**teste de IDOR real**) |
| 3 | **Validação** — entrada inválida é rejeitada com erro claro? | Envia `null`, string onde é número, campos a mais, payload gigante, SQLi/XSS |
| 4 | **Regra de negócio** — as regras do legado foram preservadas? | Recria os casos de borda: aprovações, estados, cálculos, prazos |
| 5 | **Fronteira** — o módulo toca dados de outro módulo? | Roda `dependency-cruiser`; audita os repositórios |
| 6 | **Operação** — funciona sem rede (quando aplicável), com falha de terceiro, e é observável? | Desliga a rede; derruba o webhook; inspeciona logs/métricas |

### 3.3 Loop de correção

```
RODADA 1
  dev-<modulo>      → implementa, escreve EVIDENCIAS.md, abre o gate
  fiscal-<modulo>   → audita (6 perguntas) + fiscais transversais aplicáveis
                    → emite PARECER-FISCAL.md: APROVADO | REPROVADO (com lista de defeitos)
  se REPROVADO:
RODADA 2
  dev-<modulo>      → corrige **apenas** os defeitos listados, atualiza evidências
  fiscal-<modulo>   → re-audita **apenas** os defeitos + suite completa (regressão)
  se REPROVADO:
RODADA 3
  dev-<modulo>      → correção final
  fiscal-<modulo>   → auditoria final
  se REPROVADO → BLOQUEIO
```

**Em caso de bloqueio:** o módulo é marcado `BLOQUEADO` em `STATUS.md`, a onda **não
avança**, e o `orquestrador` escala para decisão humana com um relatório de 1 página
(3 defeitos mais graves + o que já foi tentado). *Nunca* se aprova um módulo por
exaustão.

### 3.4 Formato obrigatório do `PARECER-FISCAL.md`

```markdown
# Parecer Fiscal — <Módulo> — Rodada <N>
**Fiscal:** fiscal-<modulo>   **Data:** AAAA-MM-DD   **Veredito:** APROVADO | REPROVADO

## 1. Paridade (F1)
| # | Ação no legado (arquivo:linha) | Existe no novo? | Evidência | Defeito |
|---|---|---|---|---|
| 1 | modulos/x/listar.php:42 — filtro por status | ✅ | e2e/listar.spec.ts:88 | — |
| 2 | modulos/x/editar.php:120 — recalcula total | ❌ | — | **D-01** |

## 2. Defeitos encontrados
| ID | Severidade | Descrição | Reprodução | Arquivo sugerido |
|---|---|---|---|---|
| D-01 | ALTA | Total não é recalculado ao editar | POST /x/1 {...} → total inalterado | service.ts:78 |
| D-02 | MÉDIA | Campos "importadoEm" ausente no DTO | GET /x/1 → falta campo | dto/x.ts |

## 3. O que foi verificado e passou
- Autorização: 14 rotas testadas com papel trocado; 3 esperadas 403 — todas 403 ✅
- IDOR: tentativa de acesso com associadoId de terceiro → 403 ✅
- Frontières: dependency-cruiser sem violação ✅
- Cobertura: 87% linhas / 79% branches (mínimo 80/70) ✅

## 4. Comandos reproduzidos
```
pnpm --filter api test modules/x        # 41 passed
pnpm --filter api test:e2e modules/x    # 12 passed
npx dependency-cruiser --config .depcruiser.js src  # 0 violations
```
```

---

## 4. Regras anti-cola (anti-collusion)

Um par Dev+Fiscal degenera em "carimbo" se não houver regras explícitas. São estas:

| # | Regra | Por quê |
|---|---|---|
| AC1 | O fiscal **não** lê o código do construtor antes de escrever o plano de auditoria | Evita ancoragem no "o que foi feito" em vez do "o que era exigido" |
| AC2 | O plano de auditoria deriva do **legado + PRD**, nunca do PR de implementação | Garante cobertura do *requisito*, não do *código* |
| AC3 | O fiscal **reproduz** todo comando; não copia a saída do dossiê | Elimina evidência fabricada |
| AC4 | O fiscal **escreve o próprio teste** de pelo menos 1 caso de borda por requisito | Cobertura independente |
| AC5 | O fiscal reporta **ao menos 1 achado** ou justifica explicitamente por que não há nenhum | Torna o "tudo ok" custoso e auditável |
| AC6 | Defeitos são registrados com **reprodução mínima** e severidade | Impede vago "melhorar tratamento de erro" |
| AC7 | O fiscal **não** corrige o código; ele descreve o defeito | Mantém a separação exigida pelo requisito |
| AC8 | Um fiscal que aprova 3 módulos seguidos sem nenhum achado é **rotacionado** | Anti-vício |

---

## 5. Gates de qualidade (aplicados antes de qualquer aprovação)

| Gate | Critério | Quem verifica |
|---|---|---|
| **G1 — Compila e sobe** | `pnpm build` e `pnpm dev` sem erro; healthcheck verde | F3 |
| **G2 — Cobertura** | ≥ 80% linhas / ≥ 70% branches no módulo; 100% nos cálculos de negócio | F3 |
| **G3 — Fronteira** | `dependency-cruiser` sem violação `no-cross-module` | F3 |
| **G4 — Contrato** | OpenAPI gerado, sem rota não documentada; `packages/contracts` sem `any` | F3 |
| **G5 — Autorização** | 100% das rotas com guarda; teste de IDOR e de tenant cruzado por rota | F2 |
| **G6 — Paridade** | Matriz de paridade 100% das ações do legado cobertas | F1 |
| **G7 — Regulatório** | Retenção, rastreabilidade, rotulagem, insumos permitidos | F4 |
| **G8 — Migração** | Migração Prisma aditiva, reversível e testada em cópia do dump | A2 |
| **G9 — Observabilidade** | Logs estruturados + métricas + traces no módulo | A9 |
| **G10 — Documentação** | `README.md` do módulo + runbook + ADRs atualizados | F3 |

**Regra de ouro dos gates:** *nenhum módulo é desligado do PHP sem G1–G10 verdes.* O PHP
antigo só é redirecionado depois da aprovação formal.

---

## 6. Escalação

| Situação | Ação |
|---|---|
| Módulo reprovado 3× | `orquestrador` marca `BLOQUEADO`, onda congelada, relatório de 1 página para decisão humana |
| Conflito entre fiscais (ex.: paridade × segurança) | `orquestrador` arbitra; **segurança sempre vence** |
| Defeito que exige mudar o contrato do kernel | `arquiteto-kernel` decide; mudança versionada; re-auditar **todos** os módulos que consomem o campo |
| Divergência de entendimento da regra de negócio | `fiscal-paridade` vai ao código PHP e ao banco; a **fonte de verdade é o comportamento em produção**, não a documentação |
| Descoberta de dado duplicado/legado | `arquiteto-dados` decide a cópia canônica; registra em ADR; nunca decide sozinho o construtor |

---

## 7. Formato do arquivo de agente (compatível com `.agent/`)

Cada agente é materializado como um arquivo Markdown com frontmatter, no padrão já usado
em `.agent/agents/*.md`. Exemplo do par do módulo M4:

```markdown
---
name: dev-financeiro
description: Construir o módulo Financeiro (mensalidades, PIX, caixa, contas).
tools: Read, Grep, Glob, Write, Edit, Bash
skills: nestjs-expert, prisma-expert, api-patterns, tdd-workflow, clean-code
model: inherit
---

# dev-financeiro — Construtor do Módulo Financeiro

## Missão
Implementar M4 (Financeiro da Associação) com paridade funcional ao legado
(`modulos/mensalidades/`, `modulos/caixa/`, `modulos/contas/`,
`api/pix/webhook/*`), respeitando o contrato do kernel e sem tocar tabela de terceiros.

## Entradas obrigatórias
- `docs/v3/02-CATALOGO-DE-MODULOS-E-TELAS.md` §Financeiro
- `docs/v3/03-MODELO-DE-DADOS-E-OWNERSHIP.md` (tabelas M4)
- `docs/v3/04-CONTRATOS-DE-API.md` §Financeiro
- Código legado: `modulos/mensalidades/*`, `classes/Mensalidade.php`, `classes/PixProvider.php`

## Entregáveis
- `apps/api/src/modules/financeiro/**` (module, controller, service, repository, dto, policies, events)
- `packages/contracts/src/financeiro.ts`
- Testes unitários e E2E
- `EVIDENCIAS.md` + `evidencias/`

## Definição de pronto (auto-verificada antes de abrir o gate)
- [ ] Todas as ações do legado implementadas e listadas na matriz de paridade
- [ ] Webhooks PIX validam assinatura e são idempotentes
- [ ] Nenhum `UPDATE` em tabela de outro módulo
- [ ] Cobertura ≥ 80% / 70%
- [ ] `EVIDENCIAS.md` completo (ver `08-*` §3.1)

## Proibido
- Alterar `packages/contracts/src/kernel.ts` (peça ao `arquiteto-kernel`)
- Editar arquivos de outro módulo
- Declarar pronto sem dossiê
```

---

## 8. Matriz de alocação por onda

| Onda | Módulos | Pares ativos | Fiscais transversais |
|---|---|---|---|
| **0 — Fundação** | A1–A5, A9, M12 | M12 | F2, F3 |
| **1 — Núcleo** | M1, M9, M11 | M1, M9, M11 | F1, F2, F3 |
| **2 — Campo** | M2, M7 | M2, M7 | F1, F2, F3, F4 |
| **3 — Fiscalização** | M3 | M3 | F1, F2, F3, F4 |
| **4 — Financeiro** | M4 | M4 | F1, F2, F3 |
| **5 — Produção** | M5, M6 | M5, M6 | F1, F2, F3, F4 |
| **6 — Governança** | M8, M10 | M8, M10 | F1, F2, F3 |
| **7 — Desligamento** | A6, A10 (consolidação) | — | F1, F2, F3, F4 |

> **Regra de paralelismo:** no máximo **2 pares** ativos simultaneamente, e apenas se
> **não compartilharem tabela**. Conflito de escrita é a causa nº 1 de retrabalho.
