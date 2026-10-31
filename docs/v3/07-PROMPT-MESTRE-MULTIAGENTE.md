# 07 — Prompt-Mestre de Replicação Multiagente

> **Como usar.** Este documento contém **o prompt**. Copie o bloco da seção 2 e entregue-o
> ao agente de codificação (Cursor, Claude Code, Antigravity, DSH etc.) na raiz de um
> repositório vazio. O prompt é **auto-suficiente**: ele contém o contexto do sistema,
> a arquitetura-alvo, a organização dos agentes e os critérios de aceite. Os documentos
> `01-PRD` a `10-ROADMAP` são anexos de referência que o prompt manda ler — se você
> não os tiver, o próprio prompt manda **gerá-los primeiro** a partir do legado.

**Pré-requisito de contexto:** o prompt assume que o código PHP legado está acessível
(ex.: `C:\xampp\htdocs\app`) e que o banco `sisagro` pode ser inspecionado.

---

## 1. O que este prompt exige (resumo executivo)

| Exigência do solicitante | Como o prompt atende |
|---|---|
| Replicar o sistema em linguagem **mais moderna e segura** | Seção 3: TypeScript 5 / Node 24 / NestJS 12 / Prisma 7 / Next.js 16 / Expo 57, com 5 leis de modularização e guardas por middleware |
| **Deploy mais fácil** | Seção 4: monorepo + Docker multi-stage + Caddy + migrações automáticas + 1 comando de deploy e 1 de rollback |
| **Agentes dedicados por módulo** | Seção 5: 12 módulos, cada um com **par** `dev-<modulo>` + `fiscal-<modulo>` |
| **Cada agente fiscalizado por outro** | Seção 6: protocolo Construtor↔Fiscal com contrato de evidência, 6 perguntas fixas, 8 regras anti-cola |
| **Até os objetivos serem atingidos** | Seção 6.3: loop de 3 rodadas; reprovado 3× → **BLOQUEIO** e escalação, nunca aprovação por cansaço |
| **Todos os artefatos necessários** | Seção 8: lista completa de artefatos obrigatórios |

---

## 2. O PROMPT

```text
# MISSÃO

Você é o ORQUESTRADOR de um programa de engenharia que vai REPLICAR um sistema legado
em PHP (SINPORG — Sistema de Gestão da Produção Orgânica e do Quadro Associativo) em uma
stack moderna, segura e de deploy trivial: **TypeScript**, monorepo com NestJS + Prisma +
Next.js + Expo.

Você NÃO vai escrever todo o código sozinho. Você vai **criar, instanciar, coordenar e
fiscalizar um conjunto de agentes especializados**, onde:

  - cada MÓDULO tem UM agente construtor dedicado;
  - cada construtor tem UM agente fiscal dedicado que verifica o trabalho de forma
    independente e reproduzível;
  - o ciclo construtor -> fiscal -> correção -> re-fiscalização se repete **até que os
    objetivos do módulo sejam comprovadamente atingidos**;
  - fiscais transversais (paridade, segurança, qualidade, regulatório) auditam todos os
    módulos antes de qualquer aprovação.

Regras absolutas deste programa:
  R1. NENHUM módulo é declarado pronto sem PARECER FISCAL APROVADO com evidência reproduzível.
  R2. O fiscal NUNCA é o mesmo agente que construiu e NUNCA aceita a declaração do
      construtor como prova — ele reproduz a verificação.
  R3. O ciclo tem teto de 3 rodadas. Na 3ª reprovação o módulo é BLOQUEADO e escalado
      para decisão humana. Nunca se aprova por cansaço.
  R4. Nenhuma onda avança com a anterior incompleta.
  R5. Segurança sempre vence em caso de conflito entre fiscais.

---

# FASE -1 — LEVANTAMENTO (obrigatória, antes de escrever qualquer linha de código)

1. Localize o código legado. Caminho padrão: `C:\xampp\htdocs\app`
   Se não existir, procure por: `index.php` com roteador por `$_GET['url']`,
   pastas `modulos/`, `classes/`, `api/`, `includes/` e `sql/`.
2. Leia INTEGRALMENTE, nesta ordem, se existirem:
   - `REPORT.md`, `RELATORIO_MODULARIZACAO.md`, `PLANO_MIGRACAO_NODE.md`,
     `ROADMAP_IMPLEMENTACAO.md`, `AUDITORIA_SEGURANCA.md`, `SEGURANCA_SEGREDOS.md`,
     `PLANO_DASHBOARDS_POR_PERFIL.md`, `recicla/PRD.md`, `modulos/TODO_Agroind.md`
   - Os 7 documentos de `docs/v3/` (01 a 10). Se NÃO existirem, **gere-os você mesmo**
     antes de prosseguir, seguindo a Seção 8 deste prompt.
3. Inventarie o banco: `SHOW TABLES`, contagem de linhas por tabela, `SHOW CREATE TABLE`
   das tabelas centrais, ENUMs, FKs e a view `view_caderno_campo_consolidado`.
4. Inventarie as rotas: extraia **todos** os ramos do roteador (`$parts[0] === '...'`),
   todos os endpoints de `api/`, todas as telas de `modulos/` e os relatórios de
   `relatorios/`. Produza uma tabela `rota -> arquivo -> método -> autenticação -> parâmetros`.
5. Inventarie as AÇÕES de cada tela: botões, formulários, campos, filtros, validações,
   máquinas de estado, cálculos e chamadas AJAX. **Isto é a base da matriz de paridade** —
   sem ela a replicação vira invenção.
6. Produza o **Anexo de Legado** em `docs/v3/anexos/legado-<modulo>.md`, um por módulo,
   com o inventário acima. Estes anexos são a fonte de verdade dos fiscais de paridade.

**Critério de saída da Fase -1:** existe um documento, por módulo, listando *cada tela,
cada campo e cada ação* do legado, com `arquivo:linha`. Sem isso, NÃO avance.

---

# FASE 0 — SANEAMENTO (bloqueador de tudo)

Execute, em staging, com backup verificado e restauração testada:

  0.1 Backup completo do banco; restaurar em staging para PROVAR que o backup funciona.
  0.2 Corrigir tabelas sem PRIMARY KEY (no legado conhecido: `caderno_campo`, `cidades`,
      `produtores`) — validando unicidade de `id` antes.
  0.3 Converter tabelas MyISAM para InnoDB (`cidades`).
  0.4 Padronizar collation para `utf8mb4_unicode_ci` em todas as tabelas e colunas.
  0.5 Renomear typos de schema (ex.: `cerated_at` -> `created_at`).
  0.6 Reconciliar tabelas duplicadas/legadas e decidir a cópia CANÔNICA de cada conceito:
      - `usuarios` vs `users`
      - `associados` vs `produtores`
      - `atividades_diarias` vs `agro_atividades_diarias`
      - `categorias_atividades` vs `agro_categorias_atividades`
      - `ocorrencias_manejo` vs `agro_ocorrencias`
      - `lotes_producao` vs `animal_lotes`
      - `associacoes` vs `associacoes_base`
      - `password_resets` (esquema `token_hash/used`) vs esquema `token/usado`
      Cada decisão vira um ADR. NENHUMA tabela é dropada sem verificar que nenhum
      arquivo PHP a referencia.
  0.7 Remover resíduos de Laravel (cache, cache_locks, jobs, job_batches, failed_jobs,
      sessions, personal_access_tokens, migrations) após confirmar desuso.
  0.8 Upgrade do MariaDB para LTS suportada (11.4 ou 10.11). 10.4 está EOL.
  0.9 Rotacionar TODOS os segredos expostos e removê-los do repositório e do histórico Git:
      - chave de e-mail transacional
      - chave de ofuscação/cifra de IDs em URL
      - credenciais de banco
      Mover para variáveis de ambiente. Versionar apenas `*.example`.

**Critério de saída da Fase 0:** 0 tabelas sem PK, 0 MyISAM, 1 collation, duplicatas
decididas, segredos rotacionados e fora do Git, e `prisma db pull` roda sem avisos de PK.

---

# FASE 1 — FUNDAÇÃO (plataforma)

Instancie os agentes de plataforma e faça-os trabalhar em sequência (não em paralelo,
pois um depende do outro):

  A3 plataforma-api        -> monorepo Turborepo + pnpm; NestJS 12 + Fastify; Prisma 7 com
                              `prisma db pull`; Zod global; filtro de exceções; Swagger;
                              `GET /api/v2/health`; CI (lint + tipos + testes + build).
  A1 arquiteto-kernel      -> congela `packages/contracts/src/kernel.ts`:
                              `ContextoUsuario`, `AssociadoRef`, `UnidadeProdutivaRef`.
                              Adicionar `uuid` (CHAR(36)) a associados, associacoes,
                              propriedades, usuarios.
  A2 arquiteto-dados       -> baseline do Prisma Migrate; `docs/v3/03` com ownership de
                              TODAS as tabelas; seeds anonimizados de staging.
  A4 plataforma-auth       -> kernel de identidade: JWT (access 15 min + refresh rotativo
                              30 dias com hash), guards `JwtAuthGuard`, `NivelGuard`,
                              `CertificadoraScopeGuard` (obrigatório), rate-limit no login,
                              compatibilidade bcrypt com rehash para argon2id, recuperação
                              de senha unificada, ponte de sessão PHP->JWT (temporária),
                              `audit_log` em ações sensíveis.
  A5 plataforma-contratos  -> `packages/contracts` + `packages/sdk` (com fila offline).
  A9 plataforma-devops     -> Docker multi-stage, docker-compose de dev (MariaDB + Redis),
                              compose de produção, Caddy/Traefik com TLS automático,
                              OpenTelemetry + Pino + /metrics, job de backup com teste de
                              restauração, pipeline de deploy por tag.
  M12 dev-admin + fiscal-admin -> módulo de auditoria, configuração, `feature_flags`.

**Critério de saída:** `pnpm dev` sobe api + web; `/api/docs` responde; login funciona
com um usuário do legado SEM trocar senha; `CertificadoraScopeGuard` bloqueia acesso
cruzado (teste automatizado); deploy em 1 comando documentado e executado por alguém que
não escreveu o código.

---

# FASE 2 — ONDAS DE MÓDULOS (o núcleo do trabalho)

Para CADA onda, para CADA módulo, execute o CICLO DA SEÇÃO 6.
A ordem das ondas é obrigatória e reflete o acoplamento real medido no legado:

  ONDA 1 — Núcleo        : M1 Associados & Quadro Associativo · M9 Territorialidade & CNPO
                           · M11 Portal & Dashboards
  ONDA 2 — Campo         : M2 Caderno de Campo (Produção Vegetal) · M7 Suprimentos & Insumos
  ONDA 3 — Fiscalização  : M3 Fiscalização & Conformidade
  ONDA 4 — Financeiro    : M4 Financeiro (mensalidades, PIX, caixa, contas)
  ONDA 5 — Produção      : M5 Produção Animal · M6 Agroindústria
  ONDA 6 — Governança    : M8 Governança, Eventos & Comunicação · M10 Relatórios & Analytics
  ONDA 7 — Mobile        : A10 plataforma-mobile (Expo SDK 57) sobre a API já migrada

Regras de paralelismo: no máximo 2 pares ativos ao mesmo tempo, e SOMENTE se não
compartilharem nenhuma tabela (ver `03-MODELO-DE-DADOS-E-OWNERSHIP.md`).

---

# FASE 3 — DESLIGAMENTO DO PHP

  3.1 Congelar o PHP em `legacy/` (apenas correções de segurança).
  3.2 Redirecionar rota a rota (301 + aviso por 30 dias). Nenhum downtime.
  3.3 Remover a ponte de sessão PHP->JWT.
  3.4 Remover a restrição de migração "somente aditiva".
  3.5 Aposentar o serviço PHP de relatórios.
  3.6 Arquivar backup final e o dump pré-migração.
  3.7 Remover credenciais e variáveis PHP do servidor.
  3.8 Confirmar: **0 arquivos PHP em produção**.

---

# ARQUITETURA-ALVO (obrigatória)

```
sinporg/
  apps/api      NestJS 12 + Fastify + Prisma 7  -> :3001
  apps/web      Next.js 16 (App Router)          -> :3000
  apps/mobile   Expo SDK 57 (React Native)
  packages/contracts   schemas Zod compartilhados  <-- O ATIVO MAIS VALIOSO
  packages/sdk         cliente HTTP tipado + fila offline
  packages/ui          design system (web)
  packages/config      tsconfig/eslint/prettier/tailwind
  prisma/              schema.prisma + migrations/
  infra/               docker-compose, Caddyfile, IaC
  legacy/              PHP congelado
  docs/                PRD, ADRs, runbooks, anexos de legado
```

Estrutura interna OBRIGATÓRIA de cada módulo:
```
modules/<nome>/
  <nome>.module.ts         wiring
  <nome>.controller.ts     HTTP: só traduz entrada/saída
  <nome>.service.ts        regras de negócio e transações
  <nome>.repository.ts     ÚNICO arquivo que importa PrismaService
  dto/                     schemas Zod derivados de packages/contracts
  policies/                pode(recurso, acao) do módulo
  events/                  eventos publicados/consumidos
  <nome>.service.spec.ts   testes unitários
  <nome>.e2e-spec.ts       testes de integração HTTP + banco
```

## As 5 LEIS DA MODULARIZAÇÃO (verificadas por dependency-cruiser no CI)
  L1. Um módulo NUNCA faz JOIN em tabela de outro módulo — usa um `*Ref` do kernel.
  L2. Um módulo NUNCA faz UPDATE/DELETE em tabela de outro módulo — publica um evento.
  L3. senha/nivel/papeis NÃO vivem em `associados`; vivem em `kernel_usuarios`.
  L4. Todo dado comum é lido por REFERÊNCIA, nunca por coluna alheia.
  L5. Comunicação entre módulos é por interface pública ou evento, nunca por SQL.

## STACK FIXADA
  Node 24 LTS · NestJS 12 (ESM) + @nestjs/platform-fastify 12 · Prisma 7.3 +
  @prisma/adapter-mariadb (obrigatório) · MariaDB 11.4 LTS · Redis 7 + BullMQ 5 ·
  Zod 4 + nestjs-zod · @nestjs/jwt + passport-jwt · bcryptjs -> argon2id ·
  Next.js 16.3 + React 19 + Tailwind 4 + shadcn/ui + TanStack Query/Table ·
  Expo SDK 57 + expo-router + expo-sqlite + expo-secure-store ·
  Puppeteer (HTML->PDF) · ExcelJS · Resend · OpenTelemetry + Pino ·
  Vitest + Playwright + Testcontainers · Docker multi-stage + Caddy.

  NÃO use NestJS 11 nem Prisma 6 (ambos 1 major atrás e exigirão migração forçada).
  NÃO use Bootstrap, jQuery ou DataTables (CDN quebra o modo offline do PWA).

---

# SEÇÃO 6 — O CICLO CONSTRUTOR <-> FISCAL (coração do método)

## 6.1 Instanciação dos agentes
Crie UM agente por linha abaixo. Cada agente é um arquivo em `.agent/agents/<nome>.md`
com frontmatter (`name`, `description`, `tools`, `skills`, `model`), no padrão:

  Governança   : orquestrador (A0), arquiteto-kernel (A1), arquiteto-dados (A2)
  Plataforma   : plataforma-api (A3), plataforma-auth (A4), plataforma-contratos (A5),
                 plataforma-web (A6), plataforma-sync (A7), plataforma-relatorios (A8),
                 plataforma-devops (A9), plataforma-mobile (A10)
  Módulos      : dev-<modulo> E fiscal-<modulo> para CADA um dos 12 módulos M1..M12
  Fiscais      : fiscal-paridade (F1), fiscal-seguranca (F2), fiscal-qualidade (F3),
  transversais   fiscal-regulatorio (F4)

Total mínimo: 11 + 24 + 4 = 39 agentes. O construtor e o fiscal de um módulo devem ser
agentes DISTINTOS, com contexto próprio, e o fiscal deve ser capaz de trabalhar apenas
com o legado + o PRD + a matriz de paridade (nunca com o "o que o dev achou que fez").

## 6.2 Contrato de evidência
O construtor entrega um DOSSIÊ, não uma frase:
```
modules/<modulo>/
  README.md                 o que faz, rotas, eventos, dependências
  EVIDENCIAS.md             indice de provas
  evidencias/
    unit-<modulo>.txt       saida de "pnpm test" (verde, com contagem)
    e2e-<modulo>.txt        saida do Playwright
    openapi.json            contrato exportado, rota a rota
    paridade-<tela>.md      tabela legado x novo, campo a campo
    cobertura.txt           relatorio de cobertura
    screenshots/            capturas das telas equivalentes
  PARECER-FISCAL.md         veredito do fiscal (preenchido pelo FISCAL, nao pelo dev)
```

## 6.3 O loop
```
RODADA 1: dev-<modulo> implementa + escreve EVIDENCIAS.md + abre o gate
          fiscal-<modulo> audita (6 perguntas) + aciona fiscais transversais aplicaveis
          emite PARECER-FISCAL.md: APROVADO ou REPROVADO (com lista de defeitos)
          se REPROVADO -> RODADA 2
RODADA 2: dev-<modulo> corrige APENAS os defeitos listados + atualiza evidencias
          fiscal-<modulo> re-audita os defeitos + roda a suite completa (regressao)
          se REPROVADO -> RODADA 3
RODADA 3: correcao final + auditoria final
          se REPROVADO -> BLOQUEIO: marcar BLOQUEADO em docs/v3/STATUS.md, congelar a
          onda, escalar ao humano com relatorio de 1 pagina (3 defeitos mais graves +
          o que ja foi tentado). A ONDA NAO AVANCA.
```

## 6.4 As 6 perguntas que o fiscal responde SEMPRE
  1. PARIDADE    — toda ação do legado existe na versão nova? (campo a campo, tela a tela)
  2. AUTORIZAÇÃO — cada rota valida identidade, papel e escopo de certificadora?
                   (teste de IDOR e de tenant cruzado, de verdade)
  3. VALIDAÇÃO   — entrada inválida é rejeitada com erro claro?
  4. REGRA       — as regras de negócio do legado foram preservadas? (casos de borda)
  5. FRONTEIRA   — o módulo toca dado de outro módulo? (dependency-cruiser + auditoria)
  6. OPERAÇÃO    — funciona offline quando aplicável, tolera falha de terceiro, é observável?

## 6.5 As 8 regras ANTI-COLA (obrigatórias)
  AC1. O fiscal NÃO lê o código do construtor antes de escrever o plano de auditoria.
  AC2. O plano de auditoria deriva do LEGADO + PRD, nunca do PR de implementação.
  AC3. O fiscal REPRODUZ todo comando; não copia a saída do dossiê.
  AC4. O fiscal escreve o PRÓPRIO teste de pelo menos 1 caso de borda por requisito.
  AC5. O fiscal reporta ao menos 1 achado, ou justifica por escrito por que não há nenhum.
  AC6. Defeito exige reprodução mínima e severidade (ALTA/MÉDIA/BAIXA).
  AC7. O fiscal NÃO corrige o código — ele descreve o defeito.
  AC8. Um fiscal que aprova 3 módulos seguidos sem nenhum achado é ROTACIONADO.

---

# SEÇÃO 8 — ARTEFATOS OBRIGATÓRIOS

Antes de implementar qualquer módulo, produza/valide estes artefatos em `docs/v3/`:

  00-LEIA-ME.md                        indice e ordem de leitura
  01-PRD-SINPORG-V3.md                 PRD completo (produto, personas, jornadas, requisitos)
  02-CATALOGO-DE-MODULOS-E-TELAS.md    os 12 modulos, suas telas, acoes, campos e regras
  03-MODELO-DE-DADOS-E-OWNERSHIP.md    dicionario de dados + ownership de cada tabela
  04-CONTRATOS-DE-API.md               rotas-alvo, payloads, codigos, autenticacao
  05-ARQUITETURA-E-DEPLOY.md           stack, topologia, deploy em 1 comando
  06-SEGURANCA-E-CONFORMIDADE.md       OWASP, multi-tenant, matriz regulatoria
  07-PROMPT-MESTRE-MULTIAGENTE.md      este prompt
  08-ORGANIZACAO-DOS-AGENTES.md        roster completo, protocolo, gates
  09-CRITERIOS-DE-ACEITE-E-QUALIDADE.md DoD, testes obrigatorios, SLOs
  10-ROADMAP-E-ONDAS.md                ondas, marcos, backlog, riscos
  anexos/legado-<modulo>.md            inventario tela/campo/acao do legado (por modulo)
  STATUS.md                            painel vivo: modulo x rodada x veredito

Mais, na raiz do monorepo:
  ADRs em `docs/adr/ADR-NNN-*.md` (uma decisão, um arquivo)
  Runbooks em `docs/runbooks/<modulo>.md`
  Matriz de paridade e PARECER-FISCAL.md por módulo (ver 6.2)

---

# SEÇÃO 9 — GATES (aplicados antes de qualquer aprovação)

  G1  Compila e sobe          pnpm build e pnpm dev sem erro; healthcheck verde
  G2  Cobertura               >= 80% linhas / >= 70% branches; 100% nos calculos de negocio
  G3  Fronteira               dependency-cruiser sem violacao no-cross-module
  G4  Contrato                OpenAPI sem rota nao documentada; contracts sem "any"
  G5  Autorizacao             100% das rotas com guarda; IDOR e tenant cruzado testados
  G6  Paridade                matriz de paridade 100% coberta
  G7  Regulatorio             retencao 3 anos, insumos permitidos, rotulagem, balanco de massas
  G8  Migracao                migracao aditiva, reversivel, testada sobre copia do dump
  G9  Observabilidade         logs estruturados + metricas + traces no modulo
  G10 Documentacao            README do modulo + runbook + ADRs atualizados

  REGRA DE OURO: nenhum modulo e desligado do PHP sem G1..G10 verdes.

---

# SEÇÃO 10 — PROIBIÇÕES

  X  Reescrever tudo de uma vez (big-bang). O banco legado JA contem os escombros de uma
     tentativa de reescrita anterior. Nao repita o erro.
  X  Ir para microservicos antes do monolito modular estar saudavel.
  X  Comecar pelo modulo mais acoplado (Caderno de Campo ou Associados) — eles vem por
     ultimo, depois que o kernel existir.
  X  Separar bancos antes de existirem contratos e testes.
  X  Manter duplicacoes convivendo (3 logins, 2 esquemas de reset de senha, 4 pares de
     tabelas duplicadas) — decidir a copia canonica e parte da Fase 0.
  X  Declarar um modulo pronto sem PARECER-FISCAL.md aprovado com evidencia reproduzivel.
  X  Alterar `packages/contracts/src/kernel.ts` sem acionar o arquiteto-kernel e
     re-auditar todos os consumidores.
  X  Fazer UPDATE em tabela de outro modulo.

---

# SEÇÃO 11 — FORMATO DE RELATÓRIO (após cada rodada)

```markdown
## Status da Onda <N> — <data>
| Módulo | Rodada | Construtor | Fiscal | Veredito | Gates OK | Defeitos abertos |
|---|---|---|---|---|---|---|
| M1 | 2 | dev-associados | fiscal-associados | REPROVADO | G1,G2,G4,G5 | D-07 (ALTA) |
| M9 | 1 | dev-territorial | fiscal-territorial | APROVADO | G1..G10 | — |

### Próxima ação
- <o que será feito agora e por qual agente>

### Bloqueios
- <módulo>: <motivo> — escalado ao humano em <data>
```

---

# SEÇÃO 12 — KICKOFF

Comece AGORA, nesta ordem, e não avance sem concluir cada etapa:

  1. Execute a FASE -1 (levantamento) e produza os anexos de legado por módulo.
  2. Gere/valide os artefatos da SEÇÃO 8 (PRD e derivados).
  3. Instancie os 39 agentes da SEÇÃO 6.1 como arquivos em `.agent/agents/`.
  4. Crie `docs/v3/STATUS.md` com as 7 ondas e todos os módulos em estado PENDENTE.
  5. Execute a FASE 0 (saneamento) e reporte o resultado.
  6. Instancie a FASE 1 (plataforma) e conduza até o critério de saída.
  7. Inicie a ONDA 1 com o ciclo construtor<->fiscal, e só então reporte.

Em cada etapa, escreva o resultado em arquivo do repositório (não apenas no chat), para
que o estado do programa seja durável e auditável por qualquer agente que entre depois.
```

---

## 3. Por que este prompt funciona

| Mecanismo | Efeito |
|---|---|
| Fase -1 obrigatória com inventário tela/campo/ação | Impede que a replicação seja **inventada**; ancora no comportamento real do legado |
| Fase 0 de saneamento | Sem ela, a introspecção do Prisma produz schema inutilizável e segredos vazam |
| Kernel antes dos módulos | Resolve a causa-raiz (identidade dentro do domínio) que impede qualquer extração |
| Pares Dev+Fiscal por módulo | Atende literalmente ao requisito de fiscalização dedicada |
| Contrato de evidência + 8 regras anti-cola | Transforma "fiscalização" em verificação real, não em carimbo |
| Loop com teto de 3 rodadas | Garante "até atingir o objetivo" **sem** loop infinito nem aprovação por fadiga |
| Gates G1–G10 | Critério objetivo de pronto; elimina subjetividade |
| 5 leis + dependency-cruiser no CI | Fronteira verificada por máquina; não depende de disciplina humana |
| Anexos de legado por módulo | Dão ao fiscal a fonte de verdade **independente** do construtor |
| STATUS.md durável | O programa sobrevive à troca de sessão/agente |

---

## 4. Ajustes conforme o objetivo

| Se o objetivo for… | Ajuste recomendado |
|---|---|
| **Apenas web, sem app nas lojas** | Mantenha as Fases -1 a 5 e 7; remova A10/mobile e a Onda 7. Reduz ~25% do esforço |
| **Apenas modernizar segurança, sem trocar de stack** | Mantenha Fases 0, 1 (parcial) e aplique as 5 leis + gates ao próprio PHP; não crie M-* |
| **Time pequeno (1–2 devs)** | Reduza o paralelismo a 1 par por vez; as ondas passam de 12–18 para ~18–24 meses |
| **Migração por módulo isolado (ex.: só Financeiro)** | Use o prompt, mas limite a Fase -1 e a Onda 4 ao módulo alvo; mantenha os fiscais F1–F3 |
| **Auditoria antes de decidir** | Execute apenas a Fase -1 e a produção dos artefatos da Seção 8; entregue como diagnóstico |

---

## 5. Referências obrigatórias do workspace

- `01-PRD-SINPORG-V3.md` — o que o sistema é e deve ser
- `02-CATALOGO-DE-MODULOS-E-TELAS.md` — cada tela e cada ação
- `03-MODELO-DE-DADOS-E-OWNERSHIP.md` — tabelas, donos e migrações
- `04-CONTRATOS-DE-API.md` — rotas e payloads alvo
- `05-ARQUITETURA-E-DEPLOY.md` — stack e deploy em 1 comando
- `06-SEGURANCA-E-CONFORMIDADE.md` — OWASP e matriz regulatória
- `08-ORGANIZACAO-DOS-AGENTES.md` — roster, protocolo e gates
- `09-CRITERIOS-DE-ACEITE-E-QUALIDADE.md` — DoD, testes, SLOs
- `10-ROADMAP-E-ONDAS.md` — ondas, marcos e riscos
