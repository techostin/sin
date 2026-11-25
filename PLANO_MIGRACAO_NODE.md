# Plano de Migração — SINPORG → TypeScript
## NestJS 12 + Fastify + Prisma 7 + Next.js 16 + Expo SDK 57

> **Objetivo:** implementação **completa e funcional** do SINPORG em TypeScript, cobrindo
> **Web** (desktop: gestores/admin) e **Mobile** (app nativo Android/iOS: agricultores),
> com API única e contrato de tipos compartilhado.
>
> **Estratégia:** migração incremental por estrangulamento (*strangler fig*), com PHP e
> Node coexistindo em produção. **Nunca big-bang.**
>
> Documento gerado em 11/09/2026 a partir de auditoria do código e do banco `sisagro`.

---

## 1. Sumário executivo

O SINPORG é hoje um monólito PHP de **~79.900 linhas em 492 arquivos**, com **86 tabelas**
e 12+ domínios de negócio. A migração para TypeScript é **viável** e **recomendada apenas
se** o objetivo incluir app nativo nas lojas e uma base de longo prazo — não para "funcionar
em mobile", que o PWA já resolve.

**Três achados da auditoria que mudam o planejamento:**

1. **Já houve uma tentativa de reescrita em Laravel.** O banco contém `migrations`,
   `users`, `sessions`, `cache`, `jobs`, `failed_jobs`, `personal_access_tokens` e
   migrações registradas de `mensalidades`, `pagamentos`, `propriedades`, `lavouras`,
   `atividades_diarias`. **Isso é a evidência de que big-bang falhou aqui.** O plano abaixo
   existe para não repetir o erro.

2. **O banco tem esquema duplicado e legado** (`usuarios` vs `users`, `associados` vs
   `produtores`, `atividades_diarias` vs `agro_atividades_diarias`,
   `associacoes` vs `associacoes_base`). **Nada disso pode ser migrado para Prisma antes
   de ser reconciliado.**

3. **Há 3 tabelas sem PRIMARY KEY e uma tabela MyISAM** — bloqueadores diretos da
   introspecção do Prisma. Detalhes e correções na Fase 0.

**Duração estimada:** **12 a 18 meses** com equipe de 2–3 devs TypeScript.
**Alternativa:** se o objetivo for apenas mobile+web, o PWA atual termina em **2–3 semanas**.

---

## 2. Definição de "Pronto" (Mobile + Web)

A migração só é considerada completa quando **todos** os critérios abaixo forem atendidos:

| # | Critério | Verificação |
|---|---|---|
| 1 | **Zero arquivos PHP em produção** | `Get-ChildItem -Recurse -Filter *.php` no deploy retorna vazio |
| 2 | **Paridade funcional** nos 12+ domínios | Checklist de telas/fluxos assinado pelo SINPORG |
| 3 | **Web** completa em Next.js 16 | Todos os 24 módulos de `modulos/` acessíveis |
| 4 | **App mobile** publicado (Play Store + App Store) | Builds assinados em produção |
| 5 | **Offline funcional** no campo | Registrar caderno de campo sem internet e sincronizar |
| 6 | **Relatórios PDF/Excel** em paridade | Todos os relatórios de `relatorios/` reproduzidos |
| 7 | **API documentada** (OpenAPI) | Swagger em `/api/docs` |
| 8 | **Sem sessão PHP** — auth por JWT | Sessões nativas eliminadas |
| 9 | **Sem segredos em repositório** | `config.php` sanitizado, secrets em .env |
| 10 | **Migrações versionadas** | Prisma Migrate é a única fonte de verdade do schema |

---

## 3. Arquitetura-alvo

```
sinporg/
├── apps/
│   ├── api/                 # NestJS 12 + Fastify  → :3001
│   │   └── src/
│   │       ├── modules/     # 1 módulo por domínio (ver §7)
│   │       ├── common/      # guards, interceptors, filters, pipes
│   │       └── main.ts
│   ├── web/                 # Next.js 16 (App Router) → :3000
│   │   └── app/             # rotas espelhando modulos/
│   └── mobile/              # Expo SDK 57 (React Native) → lojas
│       └── app/             # expo-router
├── packages/
│   ├── contracts/           # ★ tipos + schemas Zod compartilhados
│   ├── sdk/                 # cliente HTTP tipado (gerado do OpenAPI)
│   └── config/              # tsconfig / eslint / prettier compartilhados
├── prisma/
│   ├── schema.prisma        # modelo único do banco
│   └── migrations/          # versionadas
├── infra/
│   ├── docker-compose.yml   # MariaDB + Redis + api + web
│   └── apache-proxy.conf    # coexistência PHP ↔ Node
├── legacy/                  # código PHP congelado (não evolui)
├── turbo.json
├── pnpm-workspace.yaml
└── .env.example
```

**O ativo mais valioso desta arquitetura é `packages/contracts`:** um único tipo
`Associado`, `Ocorrencia`, `PlanoManejo` consumido por API, Web e Mobile. Erro de
contrato vira erro de compilação, não bug em produção. **É isso que justifica TypeScript
aqui** — sem isso, a stack é só troca de linguagem.

### 3.1 Versões fixadas (verificadas em 11/09/2026)

| Pacote | Versão | Observação |
|---|---|---|
| Node.js | **24 LTS** | já instalado (v24.13.0) |
| NestJS | **12.x** | ESM-first, Vitest/oxlint, Rspack |
| `@nestjs/platform-fastify` | 12.x | adapter HTTP |
| Prisma | **7.3.x** | *Rust-free*, requer driver adapter |
| `@prisma/adapter-mariadb` | 7.x | ★ obrigatório no Prisma 7 para MariaDB |
| Next.js | **16.3.x** | App Router |
| Expo SDK | **57** | com expo-router |
| React Native | conforme SDK 57 | |
| MariaDB | **10.11 LTS** ou **11.4 LTS** | 10.4 está EOL |
| Redis | 7.x | cache + filas (BullMQ) |

> ⚠️ **Não iniciar em NestJS 11 / Prisma 6.** Ambos estão 1 major atrás. Prisma 6 → 7 é uma
> reescrita do engine (de Rust para JS); começar em 6 garante uma migração forçada em breve.

---

## 4. FASE 0 — Saneamento do banco (bloqueador de tudo)

**Duração:** 2–3 semanas · **Pré-requisito para qualquer linha de código Node.**

Sem esta fase, `prisma db pull` produz um schema inutilizável. Execute **em ambiente de
staging com backup verificado** antes.

### 4.0 Backup e baseline

```powershell
& "C:\xampp\mysql\bin\mysqldump.exe" -u root --single-transaction --routines --triggers sisagro > backup_sisagro_pre_migracao.sql
```

### 4.1 Tabelas sem PRIMARY KEY (3)

`caderno_campo`, `cidades` e `produtores` têm coluna `id NOT NULL` mas **sem constraint
de PK**. Prisma exige identificador único — sem isso o modelo é gerado com `@@ignore` e
fica inutilizável, mascarando também uma falha real de integridade.

**Antes de corrigir, valide se `id` é de fato único:**

```sql
SELECT 'caderno_campo' t, COUNT(*) total, COUNT(DISTINCT id) distintos FROM caderno_campo
UNION ALL SELECT 'cidades', COUNT(*), COUNT(DISTINCT id) FROM cidades
UNION ALL SELECT 'produtores', COUNT(*), COUNT(DISTINCT id) FROM produtores;
```

**Se `total = distintos`** (esperado), aplicar:

```sql
ALTER TABLE caderno_campo MODIFY id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id);
ALTER TABLE cidades      MODIFY id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id);
ALTER TABLE produtores   MODIFY id INT NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (id);
```

**Se houver duplicatas:** corrigir os dados antes (decisão de negócio do SINPORG).

### 4.2 `cidades` é MyISAM → converter para InnoDB

MyISAM não suporta FK nem transação. É a **única** tabela fora do padrão.

```sql
ALTER TABLE cidades ENGINE=InnoDB;
```

> Verificado: `cidades` usa `utf8_general_ci` (não utf8mb4). A conversão de charset abaixo
> resolve engine e collation de uma vez.

### 4.3 Padronizar collation (3 collations misturadas)

Encontrado: `utf8mb4_general_ci`, `utf8mb4_unicode_ci` e `utf8_general_ci`.
`classes/Database.php` já força `utf8mb4_unicode_ci` na conexão, mas as **colunas** não
seguem — risco de erro em joins e de comparação incorreta de acentos.

```sql
-- Inventário
SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES
WHERE TABLE_SCHEMA='sisagro' AND TABLE_TYPE='BASE TABLE'
GROUP BY TABLE_NAME, TABLE_COLLATION;

-- Aplicar por tabela (script gerado do inventário)
ALTER TABLE <tabela> CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4.4 Reconciliar tabelas duplicadas/legadas

Decisão obrigatória **antes** de gerar o Prisma Client:

| Legado | Canônico | Ação |
|---|---|---|
| `users` (Laravel) | `usuarios` | **Dropar** `users` após confirmar que nenhum PHP usa |
| `produtores` (sem PK) | `associados` | Migrar dados remanescentes, depois **dropar** |
| `atividades_diarias` | `agro_atividades_diarias` | Determinar qual está em uso; consolidar |
| `associacoes_base` | `associacoes` | Consolidar |
| `password_resets` | `password_reset_tokens` | Ambas legadas — substituir por tabela nova de auth |

**Verificação antes de dropar qualquer coisa:**

```powershell
Select-String -Path (Get-ChildItem -Recurse -Include *.php |
  Where-Object { $_.FullName -notmatch 'vendor|node_modules|producao_bkp|recicla' }) -Pattern "FROM\s+users\b|JOIN\s+users\b|FROM\s+produtores\b"
```

### 4.5 Remover resíduos de Laravel

Tabelas presentes **sem uso pela aplicação PHP**:
`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`,
`password_reset_tokens`, `migrations`.

> `sessions` e `users` são do Laravel. O PHP usa sessões nativas em arquivo
> (`$_SESSION`), **não** a tabela `sessions`. Confirmar e dropar.

**Registros de migração órfãos:** `personal_access_tokens` e `associado_evento` estão
na tabela `migrations` mas **as tabelas não existem**. Limpar junto.

```sql
DROP TABLE cache, cache_locks, jobs, job_batches, failed_jobs,
           password_reset_tokens, password_resets, migrations;
-- 'users' e 'sessions' só após a verificação acima
```

### 4.6 Tratar a view

`view_caderno_campo_consolidado` — 1 view. Prisma a introspecta como model
somente-leitura. **Decisão recomendada:** não migrar a view para Prisma; recriar a
consulta no repositório NestJS, onde é testável. Manter a view durante a coexistência.

### 4.7 Upgrade do MariaDB

**MariaDB 10.4.32 está EOL desde junho/2024** — sem patches de segurança.
Prisma 7 suporta 10.0+, então não é bloqueador técnico, mas é risco de segurança.

**Recomendado:** subir para **10.11 LTS** ou **11.4 LTS** nesta fase, antes de introduzir
Prisma Migrate (evita repetir o upgrade em banco já gerenciado por migrations).

### 4.8 Corrigir segredos (obrigatório antes de expor API)

`config/config.php` contém em texto puro, versionado no Git:
- `RESEND_API_KEY` (`re_...`) — **chave real de produção**
- `ID_ENCODE_KEY` — usada para codificar IDs em URLs

**Ações:** rotacionar a chave Resend, mover para `.env`, remover do histórico Git
(`git filter-repo`) e documentar em `.env.example`.

### 4.9 Legado a limpar no código

- `caderno_campo.cerated_at` — **typo** de `created_at`. Renomear agora; a introspecção
  do Prisma carrega o nome errado para dentro do novo código.
- `password_resets` + `password_reset_tokens` — duas tabelas de reset; substituir por uma.

### ✅ Critérios de aceite da Fase 0

- [ ] Backup verificado e restauração testada
- [ ] 0 tabelas sem PRIMARY KEY
- [ ] 0 tabelas MyISAM
- [ ] 1 collation única (`utf8mb4_unicode_ci`)
- [ ] Tabelas duplicadas reconciliadas e legadas dropadas
- [ ] MariaDB em LTS suportada
- [ ] Segredos fora do Git e rotacionados
- [ ] `prisma db pull` executa **sem warnings de PK** e gera schema revisável

---

## 5. FASE 1 — Fundação da API (4–6 semanas)

### 5.1 Bootstrap do monorepo

```powershell
pnpm dlx create-turbo@latest sinporg --package-manager pnpm
cd sinporg
pnpm add -w -D turbo typescript @types/node
```

`apps/api/package.json` (essencial):

```json
{
  "name": "@sinporg/api",
  "type": "module",
  "scripts": {
    "dev": "nest start --watch",
    "build": "nest build",
    "test": "vitest run",
    "prisma:pull": "prisma db pull",
    "prisma:generate": "prisma generate",
    "prisma:migrate": "prisma migrate dev"
  },
  "dependencies": {
    "@nestjs/common": "^12.0.0",
    "@nestjs/core": "^12.0.0",
    "@nestjs/platform-fastify": "^12.0.0",
    "@nestjs/config": "^4.0.0",
    "@nestjs/jwt": "^11.0.0",
    "@nestjs/passport": "^11.0.0",
    "@nestjs/swagger": "^8.0.0",
    "@prisma/client": "^7.3.0",
    "@prisma/adapter-mariadb": "^7.3.0",
    "zod": "^4.0.0",
    "nestjs-zod": "^4.0.0",
    "argon2": "^0.44.0",
    "bullmq": "^5.0.0",
    "ioredis": "^5.0.0"
  }
}
```

### 5.2 Prisma: introspecção e driver adapter (Prisma 7)

Prisma 7 é **Rust-free**: exige driver adapter explícito.

`prisma/schema.prisma`:

```prisma
generator client {
  provider = "prisma-client"
  output   = "../apps/api/src/generated/prisma"
}

datasource db {
  provider = "mysql"
  url      = env("DATABASE_URL")
}

model usuarios {
  id               Int              @id @default(autoincrement())
  nome             String           @db.VarChar(100)
  sobrenome        String?          @db.VarChar(100)
  email            String           @unique @db.VarChar(100)
  senha            String           @db.VarChar(255)
  nivel            Int              @default(1)
  status           usuarios_status  @default(ativo)
  tipo             usuarios_tipo    @default(usuario)
  associado_id     Int?             @unique
  certificadora_id Int?
  telefone         String?          @db.VarChar(15)
  created_at       DateTime         @default(now()) @db.DateTime(0)
  ultimo_login     DateTime?        @db.DateTime(0)

  associado        associados?      @relation(fields: [associado_id], references: [id])
  certificadora    certificadoras?  @relation(fields: [certificadora_id], references: [id])

  @@map("usuarios")
}

enum usuarios_status {
  ativo
  inativo
  pendente
}

enum usuarios_tipo {
  admin
  gestor
  usuario
}
```

`apps/api/src/prisma/prisma.service.ts`:

```ts
import { Injectable, OnModuleInit, OnModuleDestroy } from '@nestjs/common';
import { PrismaClient } from '../generated/prisma/client';
import { PrismaMariaDb } from '@prisma/adapter-mariadb';

@Injectable()
export class PrismaService extends PrismaClient implements OnModuleInit, OnModuleDestroy {
  constructor() {
    const adapter = new PrismaMariaDb({
      host: process.env.DB_HOST ?? 'localhost',
      user: process.env.DB_USER!,
      password: process.env.DB_PASS!,
      database: process.env.DB_NAME ?? 'sisagro',
      connectionLimit: 10,
    });
    super({ adapter });
  }
  async onModuleInit()    { await this.$connect(); }
  async onModuleDestroy() { await this.$disconnect(); }
}
```

**Fluxo de adoção do Prisma (crítico):**

1. `prisma db pull` → gera schema a partir do banco **já saneado** (Fase 0).
2. **Revisar manualmente** os ~86 models. Renomear para camelCase **não** é automático —
   decidir se mantém `snake_case` com `@@map` ou renomeia.
3. Marcar em `@@ignore` tudo que não pertence ao domínio (durante a coexistência).
4. `prisma migrate diff` para gerar a baseline **sem** alterar o banco.
5. A partir daí, **só** Prisma Migrate altera o schema.

> ⚠️ **Regra de ouro da coexistência:** enquanto o PHP escrever no mesmo banco, toda
> alteração de schema precisa ser **aditiva e retrocompatível**. Nunca renomear ou dropar
> coluna que o PHP ainda lê.

### 5.3 Estrutura base da API

`apps/api/src/main.ts`:

```ts
import { NestFactory } from '@nestjs/core';
import { FastifyAdapter, NestFastifyApplication } from '@nestjs/platform-fastify';
import { DocumentBuilder, SwaggerModule } from '@nestjs/swagger';
import { ZodValidationPipe } from 'nestjs-zod';
import { AppModule } from './app.module';

async function bootstrap() {
  const app = await NestFactory.create<NestFastifyApplication>(
    AppModule, new FastifyAdapter({ trustProxy: true }),
  );
  app.setGlobalPrefix('api/v2');
  app.useGlobalPipes(new ZodValidationPipe());
  app.enableCors({ origin: process.env.CORS_ORIGINS?.split(',') ?? [], credentials: true });

  const config = new DocumentBuilder()
    .setTitle('SINPORG API').setVersion('2.0')
    .addBearerAuth().build();
  SwaggerModule.setup('api/docs', app, SwaggerModule.createDocument(app, config));

  await app.listen(3001, '0.0.0.0');
}
bootstrap();
```

### 5.4 Contratos compartilhados

`packages/contracts/src/associado.ts`:

```ts
import { z } from 'zod';

export const AssociadoSchema = z.object({
  id: z.number().int().positive(),
  nome: z.string().min(3).max(255),
  cpf: z.string().regex(/^\d{3}\.\d{3}\.\d{3}-\d{2}$/),
  email: z.string().email().nullable(),
  telefone: z.string().max(20).nullable(),
  status: z.enum(['ativo', 'inativo', 'pendente']),
});
export type Associado = z.infer<typeof AssociadoSchema>;

export const CriarAssociadoSchema = AssociadoSchema.omit({ id: true });
export type CriarAssociado = z.infer<typeof CriarAssociadoSchema>;
```

Uso: `apps/api` valida entrada com `CriarAssociadoSchema`; `apps/web` e `apps/mobile`
reutilizam o mesmo schema em formulários. **Uma definição, três consumidores.**

### ✅ Critérios de aceite da Fase 1

- [ ] `pnpm dev` sobe api + web; Swagger acessível em `/api/docs`
- [ ] Prisma conecta ao `sisagro` com `@prisma/adapter-mariadb`
- [ ] Primeiro endpoint (`GET /api/v2/health`) responde 200
- [ ] CI roda lint + testes + build em PR
- [ ] Baseline do Prisma Migrate gerada e versionada

---

## 6. FASE 2 — Autenticação e autorização (2–3 semanas)

### 6.1 O que existe hoje (auditado)

O sistema tem **duas identidades distintas** — isto é decisivo para o desenho:

| Público | Tabela | Campo senha | Onde autentica |
|---|---|---|---|
| Gestores / admin / técnicos | `usuarios` | `senha` | `login.php:99`, `classes/User.php:62,79` |
| Agricultores (associados) | `associados` | `senha` | `modulos/portal/login.php:100` |

Autorização por `usuarios.nivel` (int) **e** `usuarios.tipo` (enum `admin|gestor|usuario`),
correlacionados com `NIVEL_ADMIN=1`, `NIVEL_GESTOR=2`, `NIVEL_USUARIO=3` em
`config/config.php`. Há ainda `certificadora_id` em `usuarios`, indicando **escopo
multi-tenant**.

**Bônus de compatibilidade:** as senhas usam `password_hash()` do PHP, que gera
**bcrypt** — verificável em Node com `bcryptjs`. **Nenhum usuário precisa redefinir senha.**

### 6.2 Desenho do JWT

```ts
interface JwtPayload {
  sub: number;
  origem: 'staff' | 'associado';   // qual tabela autenticou
  nivel: 1 | 2 | 3;
  tipo: 'admin' | 'gestor' | 'usuario';
  certificadoraId?: number;        // escopo multi-tenant
  associadoId?: number;            // vínculo do agricultor
}
```

- **Access token:** 15 min · **Refresh token:** 30 dias, rotativo, armazenado com hash
- **Web:** refresh em cookie `httpOnly` + `SameSite=Lax` + `Secure`
- **Mobile:** refresh no `expo-secure-store` (Keychain/Keystore)
- **Migração de hash:** validar com bcrypt; se o custo for baixo, **rehash** para argon2id
  no primeiro login bem-sucedido

### 6.3 Guards

```ts
@Injectable()
export class JwtAuthGuard extends AuthGuard('jwt') {}

@Injectable()
export class NivelGuard implements CanActivate {
  constructor(private reflector: Reflector) {}
  canActivate(ctx: ExecutionContext): boolean {
    const niveis = this.reflector.getAllAndOverride<number[]>('niveis', [
      ctx.getHandler(), ctx.getClass(),
    ]);
    if (!niveis) return true;
    const { user } = ctx.switchToHttp().getRequest();
    return niveis.includes(user.nivel);
  }
}
```

Além de `NivelGuard`, é obrigatório um **`CertificadoraScopeGuard`** que injeta
`certificadoraId` em toda query — sem ele há vazamento de dados entre certificadoras.

### 6.4 Ponte de sessão (kickoff da coexistência)

Enquanto o PHP roda, uma sessão PHP não conhece o JWT. Endpoint de ponte temporário:

```
POST /legacy/session-bridge   (rota PHP, valida $_SESSION existente)
  → emite JWT curto
  → permite que páginas Next.js já autentiquem o usuário PHP logado
```

**Vida útil:** apenas durante a migração. **Deve ser removido na Fase 7.**

### ✅ Critérios de aceite da Fase 2

- [ ] Login de `usuarios` e de `associados` via `POST /api/v2/auth/login`
- [ ] Usuário PHP existente loga no Node **sem trocar senha**
- [ ] Refresh token rotativo, com revogação
- [ ] `CertificadoraScopeGuard` bloqueia acesso cruzado (teste automatizado)
- [ ] Rate limiting no login (proteção a força bruta)

---

## 7. FASE 3 — Mapa de migração de domínios

### 7.1 Mapa PHP → NestJS

Baseado nos 24 diretórios de `modulos/` e nas tabelas reais do `sisagro`:

| # | Módulo NestJS | Origem PHP | Tabelas | Onda |
|---|---|---|---|---|
| 1 | `auth` | `login.php`, `logout.php`, `modulos/portal/login.php` | `usuarios`, `associados`, `password_reset_tokens` | **1** |
| 2 | `usuarios` | `modulos/usuarios/` (7) | `usuarios`, `audit_log` | **1** |
| 3 | `associados` | `modulos/associados/` (18) | `associados`, `associado_associacao`, `entidades` | **1** |
| 4 | `propriedades` | `propriedades` | `propriedades`, `propriedade_membros` | **1** |
| 5 | `associacoes` | `modulos/associacoes/` (5) | `associacoes`, `associacoes_base` | **1** |
| 6 | `regionalizacao` | `modulos/regionalizacao/`, `api/regionalizacao/` | `cidades`, `estados`, `mesorregioes_rs`, `microrregioes_rs` | **1** |
| 7 | `caderno-campo` | `modulos/caderno_campo/` (65) | `caderno_campo`, `agro_atividades_diarias`, `atividades_diarias`, `categorias_atividades` | **2** |
| 8 | `lavouras` | `lavouras` | `lavouras`, `culturas`, `plano_manejo_cultivos` | **2** |
| 9 | `ocorrencias` | `api/ocorrencias.php`, `api/nao_conformidades.php` | `agro_ocorrencias`, `ocorrencias_manejo`, `nao_conformidades` | **2** |
| 10 | `insumos` | `api/insumos_aprovados.php` | `insumos`, `insumos_organicos_aprovados`, `lote_insumo` | **2** |
| 11 | `plano-manejo` | `modulos/plano_manejo/` (6), `api/plano_manejo.php` | `plano_manejo`, `plano_manejo_cultivos` | **2** |
| 12 | `roteiros-inspecao` | `modulos/roteiros_inspecao/` (12), `api/roteiros_inspecao.php` | `roteiros_inspecao`, `visitas_tecnicas` | **2** |
| 13 | `certificadoras` | `certificadoras_*.php`, `api/certificadoras/` | `certificadoras` | **3** |
| 14 | `certificacao` | `modulos/certificacao/` (7), `api/certificacao/` | `certificacao_participativa`, `certificacao_produtos` | **3** |
| 15 | `carteirinhas` | `carteirinha_publica.php`, `classes/Carteirinha*` | `carteirinhas` | **3** |
| 16 | `ficha-adesao` | `api/ficha_adesao.php`, `includes/ficha_adesao_modal.php` | `ficha_adesao`, `ficha_adesao_produtos` | **3** |
| 17 | `financeiro` | `modulos/caixa/`, `contas/`, `mensalidades/` | `mensalidades`, `pagamentos`, `planos_mensalidades`, `caixa`, `contas` | **4** |
| 18 | `pix` | `api/pix/webhook/*` (3 gateways) | `pagamentos` | **4** |
| 19 | `producao-animal` | `modulos/producao_animal/` (17), `manejo_animal/` (4) | `animais`, `animal_lotes`, `controle_*`, `manejo_animal_registros` | **5** |
| 20 | `agroindustria` | `modulos/agroindustria/` (24) | `agroindustrias`, `receitas_agroindustria`, `lotes_producao`, `ordens_producao` | **5** |
| 21 | `governanca` | `modulos/assembleia/` (8) | `assembleias`, `assembleia_pautas`, `assembleia_votos`, `participacoes` | **6** |
| 22 | `eventos` | `modulos/eventos/` (8), `api/get_eventos_*` | `eventos`, `evento_presenca` | **6** |
| 23 | `notificacoes` | `includes/lembretes.php`, `classes/Lembrete.php` | `notificacoes` | **6** |
| 24 | `rastreabilidade` | `rastreabilidade.php`, `classes/HistoricoConversao.php` | `rastreabilidade_insumos_processo`, `historico_conversao`, `cnpo` | **6** |

### 7.2 Estratégia por onda

Cada onda é um **ciclo completo vertical** — banco → API → Web → Mobile — e termina com
o módulo PHP correspondente **desligado** (rota redirecionada). Não se avança de onda com
a anterior incompleta.

```
Onda 1 (núcleo + auth) ........ 6–8 semanas    ← habilita todas as outras
Onda 2 (campo) ................ 10–12 semanas  ← coração do app mobile
Onda 3 (certificação) ......... 8–10 semanas
Onda 4 (financeiro) ........... 6–8 semanas
Onda 5 (animal/agroindústria) . 8–10 semanas
Onda 6 (governança/eventos) ... 6–8 semanas
Onda 7 (relatórios) ........... 6–8 semanas
```

**Regra de convivência por onda:** o módulo migrado atende `/api/v2/<modulo>`; o PHP
antigo continua em `/app/<modulo>` até o front novo assumir. Só então a rota PHP é
redirecionada.

---

## 8. FASE 4 — Relatórios, PDF e Excel (6–8 semanas)

**Esta é a área de maior risco.** O PHP gera documentos com **4 bibliotecas diferentes**
(mPDF, TCPDF, Dompdf, PhpSpreadsheet), concentradas em arquivos gigantes:

| Arquivo | Tamanho | Papel |
|---|---|---|
| `classes/ReportConformidade.php` | 50 KB | relatórios de conformidade |
| `relatorios.php` | 57 KB | hub de relatórios |
| `classes/ReportManager.php` | 26 KB | orquestração |
| `relatorios/index.php` | 45 KB | UI de relatórios |
| `relatorios/` | 28 arquivos | ~250 KB de lógica |

### 8.1 Estratégia recomendada

**Não reescrever a camada de PDF em paralelo à migração de dados.** Ordem:

1. **Reaproveitar o HTML/CSS existente.** Os relatórios PHP já produzem HTML com
   `relatorios/relatorio_estilo.css` (11 KB). Renderizar esse HTML no **Puppeteer**
   (headless Chrome) → PDF preserva **fielmente** o layout atual, com custo baixo.
   Alternativa para casos simples: `pdfkit` ou `@react-pdf/renderer`.
2. **Excel:** `ExcelJS` substitui PhpSpreadsheet.
3. **Fase de transição:** manter um **PHP report microservice** isolado atrás do proxy,
   consumido pelo Node via HTTP, até cada relatório ser portado. Isso evita bloquear a
   migração de dados por causa de relatórios.
4. **BullMQ + Redis** para gerar relatórios pesados em background (hoje eles travam a
   requisição web) e entregar por e-mail/notificação.

> **Ganho colateral importante:** relatórios assíncronos com fila resolvem um problema
> atual do sistema — requisições longas de PDF. Vale antecipar essa melhoria para o PHP,
> se possível.

### ✅ Critérios de aceite da Fase 4

- [ ] Cada relatório de `relatorios/` tem equivalente Node com diff visual aceito
- [ ] Geração assíncrona com fila e notificação ao usuário
- [ ] Exportação Excel via ExcelJS em paridade
- [ ] Microserviço PHP de relatórios **desligado**

---

## 9. FASE 5 — Web (Next.js 16)

**Duração:** 2–3 meses (em paralelo às ondas)

### 9.1 Estrutura de rotas

Espelhar `modulos/` em `app/` (App Router, React Server Components):

```
apps/web/app/
├── (public)/login/           # login staff + agricultor
├── (app)/
│   ├── layout.tsx            # sidebar (desktop) + bottom nav (mobile)
│   ├── page.tsx              # dashboard por perfil (nível)
│   ├── associados/           # listar, cadastrar, editar, ficha
│   ├── caderno-campo/        # ocorrências, atividades
│   ├── certificacao/
│   ├── financeiro/
│   ├── relatorios/
│   └── ...
└── api/                      # apenas BFF leve; regra fica na API Nest
```

### 9.2 Decisões técnicas

- **UI:** Tailwind CSS + shadcn/ui. **Aposentar Bootstrap 5.3 e jQuery/DataTables**
  (CDN) — usar TanStack Table. Isso também elimina a dependência de CDN que hoje
  **quebra o modo offline do PWA**.
- **Estado/Dados:** TanStack Query (cache, retry, otimista).
- **Auth:** JWT em cookie `httpOnly`, refresh silencioso via middleware.
- **Design responsivo:** **reaproveitar o trabalho já feito** — `agricultura-familiar.css`,
  bottom nav, FAB e safe-area do `mobile-pwa-plan.md` são um ativo real. O layout
  dual-mode (sidebar >=992px / bottom nav <992px) já está desenhado; portar as decisões,
  não o CSS.
- **PWA:** manifest + service worker (Serwist) **com assets locais**, não CDN.

---

## 10. FASE 6 — Mobile nativo (Expo SDK 57)

**Duração:** 2–3 meses (após Onda 2, quando o núcleo de campo já existe na API)

### 10.1 Stack mobile

| Necessidade | Pacote |
|---|---|
| Navegação | `expo-router` |
| Dados/API | TanStack Query + `packages/sdk` |
| **Offline** | `expo-sqlite` + fila de sincronização |
| Token seguro | `expo-secure-store` |
| Câmera (fotos de campo) | `expo-camera` |
| GPS (georreferenciamento) | `expo-location` |
| Assinatura (ficha de adesão) | `react-native-signature-canvas` |
| QR Code (carteirinha) | `expo-barcode-scanner` |
| Push | `expo-notifications` |

### 10.2 Offline-first (o requisito mais importante do campo)

O `assets/js/db_sync.js` (13 KB, IndexedDB) já provou o conceito no PWA. O app nativo
deve implementar o mesmo modelo, de forma mais robusta:

```
┌── App mobile ─────────────────────────────────┐
│ 1. Usuário registra ocorrência (offline)      │
│ 2. Grava em SQLite local + enfileira outbox   │
│ 3. UI responde imediatamente (optimistic)     │
└───────────────────────────────────────────────┘
                    ↓ (volta a rede)
┌── Sync Engine ────────────────────────────────┐
│ 4. Drena a outbox em ordem                    │
│ 5. Envia com Idempotency-Key (evita duplicar) │
│ 6. Servidor é autoritativo em conflito        │
│ 7. Baixa deltas desde last_sync_at            │
└───────────────────────────────────────────────┘
```

**Requisitos não-negociáveis:**
- **Idempotency-Key** em todo POST de sincronização (senão o agricultor duplica registros
  ao reconectar)
- **Resolução de conflito documentada** por entidade (recomendado: *last-write-wins* com
  trilha em `audit_log`)
- **`updated_at` confiável** em todas as tabelas — hoje nem todas têm
- **Testes de sincronização** com rede intermitente em CI

### 10.3 Perfis no app

- **Agricultor** (`associados`): caderno de campo, eventos, carteirinha, perfil
- **Técnico/Gestor** (`usuarios`, nível 2): visitas, roteiros, aprovações
- **Admin** (nível 1): mantém-se melhor no **Web** (telas densas de tabela)

---

## 11. FASE 7 — Desligamento do PHP

Ordem obrigatória para evitar janela de indisponibilidade:

1. **Congelar** o código PHP em `legacy/` (somente correções críticas de segurança).
2. **Redirecionar** rota a rota para o equivalente Next.js/Nest (301 + aviso por 30 dias).
3. **Remover a ponte de sessão** (`/legacy/session-bridge`).
4. **Parar de escrever** no banco pelo PHP → remover a restrição "aditivo".
5. **Aposentar** o microserviço de relatórios PHP.
6. **Arquivar** backup final e o dump do banco pré-migração.
7. **Remover** credenciais e variáveis do PHP do servidor.
8. **Confirmar critério #1** da Definição de Pronto: zero PHP em produção.

---

## 12. Coexistência — infraestrutura

`infra/apache-proxy.conf` (Apache na frente, roteando por prefixo):

```apache
# API nova → NestJS
ProxyPass        /app/api/v2   http://127.0.0.1:3001/api/v2
ProxyPassReverse /app/api/v2   http://127.0.0.1:3001/api/v2
ProxyPass        /app/api/docs http://127.0.0.1:3001/api/docs
ProxyPassReverse /app/api/docs http://127.0.0.1:3001/api/docs

# Web nova → Next.js (rotas já migradas)
ProxyPass        /app/novo   http://127.0.0.1:3000
ProxyPassReverse /app/novo   http://127.0.0.1:3000

# Todo o resto continua no PHP legado (default)
ProxyPass        /app        http://127.0.0.1:8080
ProxyPassReverse /app        http://127.0.0.1:8080
```

**Regras de convivência:**
- **Uma única fonte de escrita por tabela** em cada momento da migração. Se o PHP é dono de
  `associados`, o Node **não** escreve nela até a Onda 1 terminar.
- **Migrations sempre aditivas** enquanto ambos escrevem.
- **Feature flag por módulo** para ligar/desligar a rota nova sem deploy.
- **Observabilidade desde o dia 1:** OpenTelemetry na API (NestJS 12 tem suporte nativo a
  observabilidade). Sem métricas, a coexistência vira adivinhação.

---

## 13. Cronograma e equipe

### 13.1 Esforço estimado

| Fase | Duração | Paralelizável com |
|---|---|---|
| 0 — Saneamento do banco | 2–3 semanas | — |
| 1 — Fundação (monorepo, Prisma, CI) | 4–6 semanas | — |
| 2 — Auth (JWT, guards, ponte) | 2–3 semanas | Fase 1 final |
| 3 — Ondas 1–6 (domínios) | 44–56 semanas | Fase 5 (Web) |
| 4 — Relatórios | 6–8 semanas | Onda 5–6 |
| 5 — Web Next.js | 8–12 semanas | Fase 3 |
| 6 — Mobile Expo | 8–12 semanas | após Onda 2 |
| 7 — Desligamento PHP | 2–3 semanas | — |
| **Total (2–3 devs)** | **≈ 12–18 meses** | |

### 13.2 Equipe mínima

| Papel | Alocação | Responsabilidade |
|---|---|---|
| Tech lead TypeScript | 1 | arquitetura, Prisma, auth, revisão |
| Dev full-stack (Nest + Next) | 1 | módulos e Web |
| Dev mobile (Expo) | 0,5–1 | app + sync offline |
| DBA / backend PHP | 0,5 | Fase 0 e manutenção do legado |
| QA | 0,5 | paridade funcional por onda |

**Sem um tech lead que domine NestJS + Prisma + React, o projeto para no meio.** Este é o
risco nº 1 — mais que qualquer questão técnica.

---

## 14. Riscos e mitigações

| # | Risco | Prob. | Impacto | Mitigação |
|---|---|---|---|---|
| 1 | **Big-bang repetindo o Laravel abandonado** | Alta | Fatal | Ondas verticais obrigatórias; nenhuma onda avança incompleta |
| 2 | **Falta de domínio TypeScript na equipe** | Alta | Alto | Tech lead dedicado; treinamento antes da Fase 1 |
| 3 | **Relatórios PDF divergirem** | Média | Alto | Puppeteer sobre o HTML atual; microserviço PHP de transição |
| 4 | **Duplicação de dados no sync offline** | Média | Alto | Idempotency-Key + testes de rede intermitente |
| 5 | **Dados legados duplicados** | Alta | Médio | Fase 0 explícita, com decisão de negócio documentada |
| 6 | **Dois sistemas indefinidamente** | Média | Alto | Prazo por onda + critério de desligamento na Definição de Pronto |
| 7 | **Regressão de segurança na troca de auth** | Média | Alto | ScopeGuard testado; rate limit; auditoria em `audit_log` |
| 8 | **Bloqueio por causa do MariaDB EOL** | Média | Médio | Upgrade na Fase 0, antes do Prisma Migrate |
| 9 | **Prisma 7 Rust-free com comportamento novo** | Média | Médio | Validar `db pull` em staging antes de codificar; fixar versão |
| 10 | **Migração de schema quebrando o PHP** | Alta | Alto | Migrations **aditivas** até o desligamento; nunca renomear/dropar |

---

## 15. Primeiros 14 dias (ações concretas)

**Semana 1 — Decisão e saneamento**

1. Aprovar formalmente este plano e o orçamento de 12–18 meses.
2. **Backup completo** e restaurar em staging para provar que o backup funciona.
3. Rodar as validações de unicidade de `caderno_campo`, `cidades`, `produtores`.
4. **Rotacionar a `RESEND_API_KEY`** e removê-la do Git (`config/config.php`).
5. Levantar, com o SINPORG, a decisão de negócio: `produtores` vs `associados`.

**Semana 2 — Fundação**

6. Corrigir PKs, `cidades`→InnoDB, padronizar collation em staging.
7. Subir o monorepo (Turborepo + pnpm) com `apps/api` vazio.
8. Rodar `prisma db pull` **em staging saneado** e revisar o schema gerado.
9. Gerar baseline do Prisma Migrate.
10. Entregar `GET /api/v2/health` respondendo 200 com Swagger publicado.

**Entregável do dia 14:** Prisma Client gerado do banco real, API respondendo, CI ativo, e
nenhuma linha de PHP alterada. **A partir daí, o risco deixa de ser técnico e passa a ser
de disciplina de execução.**

---

## 16. Recomendação final

**Faça**, se e somente se:
- houver intenção real de publicar app nas lojas; **e**
- houver (ou for contratado) um tech lead TypeScript; **e**
- houver compromisso de 12–18 meses com migração incremental.

**Não faça**, se o objetivo for apenas "usar no celular e no computador": o PWA atual
resolve isso em 2–3 semanas através do `mobile-pwa-plan.md`, que já está ~70% concluído.

**O maior aprendizado desta auditoria:** o banco `sisagro` **já contém os escombros de uma
reescrita em Laravel** que não terminou. O plano acima existe para que a segunda tentativa
termine — e a diferença entre as duas não é a linguagem escolhida, é o **método
incremental**.
