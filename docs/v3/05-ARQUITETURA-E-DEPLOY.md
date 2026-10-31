# 05 — Arquitetura-Alvo e Estratégia de Deploy

> **Documento irmão de:** `01-PRD-SINPORG-V3.md` · **Base:** `PLANO_MIGRACAO_NODE.md`,
> `RELATORIO_MODULARIZACAO.md`, inspeção do monorepo `sinporg/` (já iniciado).
> **Objetivo deste documento:** definir a stack, a topologia de módulos e o caminho de
> deploy que tornam o SINPORG *moderno, seguro e trivialmente implantável*.

---

## 1. Princípios de projeto

| # | Princípio | Consequência prática |
|---|---|---|
| P1 | **Segurança por padrão** | Nenhuma rota existe sem guarda. Autenticação e autorização são *middleware*, não responsabilidade da página. |
| P2 | **Um contrato, três consumidores** | Um tipo/schema em `packages/contracts` vale para API, Web e Mobile. Erro de contrato = erro de compilação. |
| P3 | **Fronteira verificada por máquina** | Testes de arquitetura quebram o build se um módulo importar outro. Fronteira sem enforcement não sobrevive. |
| P4 | **Deploy reproduzível e imutável** | A mesma imagem roda em dev/staging/prod. Configuração só por variável de ambiente. |
| P5 | **Migração incremental (strangler fig)** | PHP e Node coexistem; nenhum big-bang. Uma única fonte de escrita por tabela em cada momento. |
| P6 | **Offline-first no campo** | O agricultor registra sem rede; a sincronização é idempotente. |
| P7 | **Retenção regulatória** | Dados de diário de campo, insumos e balanço de massas: *soft delete*, nunca `DELETE` físico; retenção mínima de 3 anos (Lei 10.831/2003 + Portaria 52/2021). |

---

## 2. Escolha da linguagem: por que TypeScript

| Critério | TypeScript / Node 24 | Go | Rust | PHP 8.4 modernizado |
|---|---|---|---|---|
| **Compartilhar tipos entre API, Web e Mobile** | ✅ nativo (Zod → 3 consumidores) | ⚠️ codegen | ⚠️ codegen | ❌ |
| **App mobile real (Android/iOS)** | ✅ Expo/React Native | ❌ | ❌ | ❌ |
| **Ecossistema de PDF/Excel/Fila** | ✅ Puppeteer, ExcelJS, BullMQ | ⚠️ | ⚠️ | ⚠️ |
| **Compatibilidade de senha legada (bcrypt do PHP)** | ✅ `bcryptjs`/`argon2` | ✅ | ✅ | ✅ |
| **Contratação de mão de obra** | ✅ ampla | ⚠️ média | ❌ escassa | ✅ ampla |
| **Deploy fácil** | ✅ Docker + 1 binário JS | ✅ binário único | ✅ binário único | ⚠️ |

**Decisão:** **TypeScript** em monorepo (Turborepo + pnpm). O ativo decisivo **não** é a
linguagem em si — Rust ou Go seriam mais seguros por construção — e sim
`packages/contracts`: **uma única definição de `Associado`, `Ocorrencia`, `PlanoManejo`
consumida por API, Web e Mobile**. Sem esse compartilhamento, trocar de linguagem só
troca o problema de lugar.

> **Registro de decisão (ADR-001).** Alternativas descartadas: (a) permanecer em PHP
> modernizado — resolve manutenibilidade, não resolve app nativo; (b) Go/Rust na API —
> melhor segurança, porém perde o compartilhamento de contrato e encarece a contratação.
> Reavaliar Go/Rust **apenas** para serviços de alto throughput (relatórios, PDF) se
> houver gargalo medido.

---

## 3. Stack fixada

| Camada | Tecnologia | Versão | Justificativa |
|---|---|---|---|
| Runtime | Node.js | **24 LTS** | `--env-file`, `node:test`, fetch nativo |
| API | **NestJS** | 12.x (ESM) | DI, guards, módulos, OpenTelemetry nativo |
| HTTP adapter | `@nestjs/platform-fastify` | 12.x | ~2–3× o throughput do Express |
| ORM | **Prisma** | 7.3.x | Rust-free; exige driver adapter |
| Driver | `@prisma/adapter-mariadb` | 7.x | **obrigatório** no Prisma 7 para MariaDB |
| Banco | **MariaDB** | 11.4 LTS | 10.4 está EOL |
| Cache/Fila | **Redis 7** + **BullMQ 5** | — | relatórios pesados assíncronos, rate-limit, outbox |
| Validação | **Zod 4** + `nestjs-zod` | 4.x | um schema → DTO + tipo + OpenAPI |
| Auth | `@nestjs/jwt` + `passport-jwt` | — | access 15 min + refresh rotativo 30 d |
| Hash | `bcryptjs` (compat) → `argon2id` (rehash) | — | ninguém troca de senha |
| Web | **Next.js** 16 (App Router) + React 19 | 16.3.x | RSC, streaming |
| UI | **Tailwind 4** + **shadcn/ui** + **TanStack Table/Query** | — | **aposenta Bootstrap/jQuery/DataTables** |
| Mobile | **Expo SDK 57** + `expo-router` | 57 | `expo-sqlite` offline, `expo-secure-store` |
| PDF | **Puppeteer** (HTML→PDF) sobre o HTML atual | — | preserva o layout já aprovado |
| Excel | **ExcelJS** | — | substitui PhpSpreadsheet |
| E-mail | **Resend** (SDK Node) | — | já é o provedor atual |
| Observabilidade | **OpenTelemetry** + Pino + `/metrics` | — | sem métricas a coexistência é adivinhação |
| Testes | **Vitest** (unit) + **Playwright** (E2E) + **Testcontainers** | — | Playwright já está no `package.json` legado |
| CI/CD | **GitHub Actions** → imagens OCI | — | `sinporg/.github/workflows/ci.yml` já existe |
| Empacotamento | **Docker multi-stage** + Compose | — | dev idêntico a prod |
| Proxy | **Caddy** ou **Traefik** (TLS automático) | — | substitui o Apache legado |

---

## 4. Topologia do monorepo

```
sinporg/
├── apps/
│   ├── api/                     # NestJS 12 + Fastify  → :3001
│   │   └── src/
│   │       ├── kernel/          # ★ identidade, acesso, territorialidade, auditoria
│   │       ├── modules/         # 1 diretório por bounded context
│   │       │   ├── associados/
│   │       │   ├── caderno-campo/
│   │       │   ├── certificacao/
│   │       │   ├── fiscalizacao/
│   │       │   ├── financeiro/
│   │       │   ├── producao-animal/
│   │       │   ├── agroindustria/
│   │       │   ├── suprimentos/
│   │       │   ├── governanca/
│   │       │   ├── eventos/
│   │       │   ├── relatorios/
│   │       │   └── sync/
│   │       ├── common/          # guards, interceptors, filters, pipes
│   │       └── main.ts
│   ├── web/                     # Next.js 16 (App Router) → :3000
│   └── mobile/                  # Expo SDK 57 → lojas
├── packages/
│   ├── contracts/               # ★ schemas Zod compartilhados
│   ├── sdk/                     # cliente HTTP tipado + fila offline
│   ├── ui/                      # design system compartilhado (web)
│   └── config/                  # tsconfig / eslint / prettier / tailwind
├── prisma/                      # schema.prisma + migrations/
├── infra/                       # docker-compose, Caddyfile, IaC
├── legacy/                      # PHP congelado (somente correções de segurança)
└── docs/                        # estes artefatos + ADRs + runbooks
```

### 4.1 Estrutura interna de cada módulo (obrigatória)

```
modules/<nome>/
├── <nome>.module.ts             # wiring NestJS
├── <nome>.controller.ts         # HTTP: só traduz entrada/saída
├── <nome>.service.ts            # regras de negócio (transações)
├── <nome>.repository.ts         # acesso a dados (único ponto que fala Prisma)
├── dto/                         # schemas Zod derivados de packages/contracts
├── policies/                    # autorização do módulo (pode(recurso, ação))
├── events/                      # eventos publicados/consumidos
├── <nome>.service.spec.ts       # testes unitários (regras)
└── <nome>.e2e-spec.ts           # testes de integração (HTTP + banco)
```

**Regra de ouro:** o `repository` é o **único** arquivo autorizado a importar
`PrismaService`. `controller` não contém regra de negócio. `service` não conhece HTTP.

---

## 5. Kernel — o "dado comum" formalizado

O maior defeito estrutural do legado é que `associados` é **ao mesmo tempo cadastro e
tabela de login** (~17 campos de autenticação misturados a dados civis). O kernel resolve
isso e é pré-requisito de **todos** os módulos.

```ts
// packages/contracts/src/kernel.ts
export interface ContextoUsuario {
  usuarioId: number;
  associadoId: number | null;      // resolvido UMA vez, de forma canônica
  origem: 'staff' | 'associado';
  papeis: Papel[];                 // admin | gestor | tecnico | agricultor | agroindustria | diretoria
  certificadoraId?: number;        // escopo multi-tenant
  areasProducao: ('animal'|'vegetal'|'agroindustria')[];
  associacoesVisiveis: number[];
  pode(recurso: string, acao: Acao): boolean;
}

export interface AssociadoRef { id: number; uuid: string; nome: string;
  cpf: string | null; registroCnpo: string | null; status: StatusAssociado;
  tipoAssociado: string | null; associacaoPrincipalId: number | null;
  microrregiaoId: number | null; }

export interface UnidadeProdutivaRef { tipo: 'propriedade'|'agroindustria'|'ambiente_criacao';
  id: number; uuid: string; associadoId: number; rotulo: string; }
```

### 5.1 As cinco leis da modularização (enforcement automatizado)

1. Um módulo **nunca** faz `JOIN` em tabela de outro módulo — pede um `*Ref` ao kernel.
2. Um módulo **nunca** faz `UPDATE` em tabela de outro módulo — publica um evento.
3. `senha`, `nivel`, `papeis` **não** vivem em `associados`; vivem em `kernel_usuarios`.
4. Todo dado comum é lido **por referência**, nunca por coluna alheia.
5. Comunicação entre módulos é **por interface pública ou evento**, nunca por SQL.

> **Verificação:** `dependency-cruiser` (regra `no-cross-module`) roda no CI e **falha o
> build** em violação. Complementado por `eslint-plugin-boundaries`.

---

## 6. Deploy: de 40 passos manuais para 1 comando

### 6.1 Situação atual (custo operacional alto)

- Apache + XAMPP, `.htaccess` com `RewriteBase /app/` fixo.
- Credenciais `root` sem senha dentro do código; `config/secrets.php` no disco.
- ~36 scripts destrutivos soltos no webroot (`run_sql.php`, `fix_codes.php`, `debug_seed.php`).
- Migrações como `sql/*.sql` + ~20 `dev-utils/migrate_*.php` executados à mão.
- Sem CI/CD, sem staging, sem rollback, sem healthcheck.
- ~7 cópias do sistema convivendo em `htdocs`.

### 6.2 Alvo

```bash
# Desenvolvimento — um comando
pnpm install && pnpm infra:up && pnpm db:migrate && pnpm dev

# Produção — um comando, imutável
docker compose -f infra/docker-compose.prod.yml up -d --build
```

**Doze requisitos de um deploy "fácil":**

| # | Requisito | Como |
|---|---|---|
| D1 | Ambiente idêntico em dev/staging/prod | Docker multi-stage; `node:24-alpine`, usuário não-root, `tini` |
| D2 | Zero configuração no código | `.env` validado na inicialização por Zod (`env.schema.ts`); falha rápido se faltar variável |
| D3 | Migrações automáticas e versionadas | `prisma migrate deploy` como *init container*/job pré-deploy |
| D4 | Sem downtime | 2 réplicas atrás do proxy + healthcheck `/api/v2/health` + rolling restart |
| D5 | Rollback | Imagens tagueadas por SHA; `docker compose up` da tag anterior |
| D6 | TLS e domínio automáticos | Caddy/Traefik com Let's Encrypt |
| D7 | Banco gerenciado | MariaDB 11.4 gerenciado (backup point-in-time) ou container com volume + `mariadb-backup` |
| D8 | Segredos fora do repositório | `docker secret` / gestor de segredos; `.env.example` versionado só com placeholders |
| D9 | Backup verificado | Job diário + **teste de restauração semanal automatizado** |
| D10 | Observabilidade | OpenTelemetry → coletor; logs JSON (Pino); `/metrics` Prometheus; alerta de erro 5xx |
| D11 | Filas separadas do processo web | Worker BullMQ como serviço próprio (relatórios, e-mails, webhooks) |
| D12 | Superfície mínima exposta | Só o proxy publica porta; o banco e o Redis ficam em rede interna |

### 6.3 `infra/docker-compose.prod.yml` (esboço)

```yaml
services:
  proxy:
    image: caddy:2-alpine
    ports: ["80:80", "443:443"]
    volumes: ["./Caddyfile:/etc/caddy/Caddyfile:ro", "caddy_data:/data"]
    depends_on: [api, web]
  api:
    build: { context: .., dockerfile: apps/api/Dockerfile }
    env_file: [../.env]
    deploy: { replicas: 2 }
    healthcheck:
      test: ["CMD", "node", "-e", "fetch('http://127.0.0.1:3001/api/v2/health').then(r=>process.exit(r.ok?0:1))"]
      interval: 15s
    depends_on: [db, redis]
  worker:
    build: { context: .., dockerfile: apps/api/Dockerfile }
    command: ["node", "dist/worker.js"]
    env_file: [../.env]
    depends_on: [db, redis]
  web:
    build: { context: .., dockerfile: apps/web/Dockerfile }
    env_file: [../.env]
  db:
    image: mariadb:11.4
    volumes: ["db_data:/var/lib/mysql", "./backup:/backup"]
    environment:
      MARIADB_DATABASE: ${DB_NAME}
      MARIADB_USER: ${DB_USER}
      MARIADB_PASSWORD: ${DB_PASSWORD}
      MARIADB_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    # sem "ports:" — inacessível de fora
  redis:
    image: redis:7-alpine
    command: ["redis-server", "--appendonly", "yes"]
volumes: { db_data: {}, caddy_data: {} }
```

### 6.4 Pipeline de CI/CD

```
push/PR
  ├─ lint + typecheck + dependency-cruiser (fronteiras de módulo)
  ├─ unit (Vitest) + integração (Testcontainers MariaDB)
  ├─ testes de autorização cruzada entre certificadoras   ← não-negociável
  ├─ E2E (Playwright) contra stack efêmera
  ├─ build das imagens (multi-stage) → registry por SHA
  └─ deploy staging (automático) → smoke → deploy prod (aprovação manual)
```

### 6.5 Ambientes

| Ambiente | Banco | Dados | Deploy |
|---|---|---|---|
| `local` | container MariaDB + Redis | seeds anonimizados | `pnpm dev` |
| `staging` | cópia restaurada do dump (anonimizada) | real sem PII | automático por merge em `main` |
| `production` | MariaDB 11.4 gerenciado | real | tag semântica + aprovação |

---

## 7. Coexistência PHP ↔ Node (durante a migração)

```apache
# Apache legado na frente, roteando por prefixo (proxy reverso incremental)
ProxyPass        /app/api/v2   http://127.0.0.1:3001/api/v2
ProxyPassReverse /app/api/v2   http://127.0.0.1:3001/api/v2
ProxyPass        /app/api/docs http://127.0.0.1:3001/api/docs
ProxyPass        /app/novo     http://127.0.0.1:3000
ProxyPassReverse /app/novo     http://127.0.0.1:3000
# default: tudo o mais continua no PHP
ProxyPass        /app          http://127.0.0.1:8080
```

**Regras de convivência:**

1. **Uma única fonte de escrita por tabela** em cada momento.
2. Migrações **aditivas e retrocompatíveis** enquanto o PHP escrever. Nunca renomear/dropar coluna que o PHP lê.
3. `feature flag` por módulo (tabela `feature_flags`) para ligar/desligar rota nova sem deploy.
4. `/api/legacy/session-bridge` emite JWT curto a partir de `$_SESSION` — **dívida temporária, removida na Fase 7**.
5. Toda rota migrada devolve `301` da URL antiga por 30 dias antes de ser desligada.

---

## 8. Decisões arquiteturais registradas (ADRs)

| ADR | Decisão | Status |
|---|---|---|
| ADR-001 | TypeScript em monorepo como linguagem-alvo | Aceita |
| ADR-002 | Prisma 7 com `@prisma/adapter-mariadb` (Rust-free) | Aceita |
| ADR-003 | Kernel de identidade separado do domínio Associados | Aceita |
| ADR-004 | JWT (access 15 min + refresh rotativo) substitui sessão PHP | Aceita |
| ADR-005 | `CertificadoraScopeGuard` como *tenant guard* obrigatório | Aceita |
| ADR-006 | Puppeteer sobre o HTML atual para PDF (não reescrever layout) | Aceita |
| ADR-007 | Relatórios pesados em BullMQ, não na requisição | Aceita |
| ADR-008 | Offline com `expo-sqlite` + outbox + `Idempotency-Key` | Aceita |
| ADR-009 | Soft delete + retenção de 3 anos (regulatória) | Aceita |
| ADR-010 | Strangler fig com proxy por prefixo; nenhum big-bang | Aceita |
| ADR-011 | `view_caderno_campo_consolidado`: não migrar para Prisma; recriar como query testável | Aceita |
| ADR-012 | Tailwind + shadcn substituem Bootstrap/jQuery/DataTables (elimina CDN que quebra o offline) | Aceita |
