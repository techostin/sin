# ROADMAP DE IMPLEMENTAÇÃO — SINPORG
## NestJS 12 + Fastify + Prisma 7 + Next.js 16 + Expo SDK 57

> Checklist-mestre da migração. Cada passo é verificável e tem critério de aceite.
> Detalhamento técnico completo em `PLANO_MIGRACAO_NODE.md`.

**Legenda:** ✅ concluído · ⏳ pendente · 👤 ação sua (manual/externa, fora do meu alcance)

**Esforço total estimado:** 12–18 meses · **Caminho crítico:** Fase 3 (ondas de domínio)

---

## FASE 0 — Saneamento do banco  ✅ CONCLUÍDA (11/09/2026)

| # | Passo | Status |
|---|---|---|
| 0.1 | Backup verificado do `sisagro` (12,23 MB, 86 CREATE TABLE) e protegido no Git | ✅ |
| 0.2 | Diagnóstico: PKs, engines, collations, referências de código às tabelas legadas | ✅ |
| 0.3 | PRIMARY KEY em `caderno_campo` e `cidades` | ✅ |
| 0.4 | `cidades` MyISAM → InnoDB + utf8mb4 | ✅ |
| 0.5 | Padronizar collation de 63 tabelas → `utf8mb4_unicode_ci` (75/75) | ✅ |
| 0.6 | Renomear typo `cerated_at` → `created_at` | ✅ |
| 0.7 | Migrar conta órfã de Joselaine de `users` → `usuarios` (id 58) | ✅ |
| 0.8 | Remover 11 tabelas legadas (86 → 75) | ✅ |
| 0.9 | Smoke test do app PHP (contagens, joins, ENUM acentuado) | ✅ |
| 0.10 | 👤 Upgrade MariaDB 10.4 (EOL) → 10.11/11.4 LTS | ⏳ |
| 0.11 | 👤 Rotacionar `RESEND_API_KEY` e remover do histórico do Git | ⏳ |
| 0.12 | 👤 Validar login e recuperação de senha do app PHP em staging | ⏳ |

**Alerta latente registrado:** `PasswordReset.php` e `portal/login.php` usam colunas diferentes da mesma tabela (`token_hash/used` vs `token/usado`). Investigar.

---

## FASE 1 — Fundação da API  (4–6 semanas)

| # | Passo | Entregável |
|---|---|---|
| 1.1 | Criar monorepo Turborepo + pnpm workspaces | `apps/`, `packages/`, `turbo.json` |
| 1.2 | `apps/api`: NestJS 12 + `@nestjs/platform-fastify` + configuração | API sobe local |
| 1.3 | Prisma 7 + `@prisma/adapter-mariadb` + `prisma.config.ts` (URL fora do schema) | conexão OK |
| 1.4 | `prisma db pull` no schema saneado | ≈75 models |
| 1.5 | Revisar schema: nomes, 42 `@map`, `@@ignore`, enums acentuados | schema legível |
| 1.6 | Tratar `view_caderno_campo_consolidado` (não introspectada) | decisão documentada |
| 1.7 | Gerar baseline do Prisma Migrate (sem alterar banco) | `prisma/migrations/` |
| 1.8 | `packages/contracts` — schemas Zod compartilhados | 1 tipo, 3 consumidores |
| 1.9 | `packages/sdk` — cliente HTTP tipado | consumo tipado |
| 1.10 | Validação global (`ZodValidationPipe`) + filtro de exceções | erros padronizados |
| 1.11 | Swagger/OpenAPI + `GET /api/v2/health` | primeiro endpoint |
| 1.12 | CI (lint + testes + build) e observabilidade (OpenTelemetry) | pipeline verde |
| 1.13 | `docker-compose` de dev (MariaDB + Redis) | ambiente reproduzível |

**Risco operacional conhecido:** o CLI do Prisma exige aprovação de sandbox neste ambiente (spawn do `schema-engine` com stdio capturado → `EPERM`). Preferir terminal fora do sandbox, container ou CI.

---

## FASE 2 — Autenticação e autorização  (2–3 semanas)

| # | Passo | Nota |
|---|---|---|
| 2.1 | Modelar as **duas identidades**: `staff` (`usuarios`) e `associado` (`associados`) | decisão central |
| 2.2 | JWT: access 15 min + refresh rotativo 30 dias | refresh hasheado |
| 2.3 | Guards: `JwtAuthGuard`, `NivelGuard`, `CertificadoraScopeGuard` | multi-tenant |
| 2.4 | Compatibilidade bcrypt (`password_hash` do PHP) + rehash para argon2id | ninguém troca senha |
| 2.5 | Rate limiting e lockout no login | anti força bruta |
| 2.6 | Recuperação de senha (migrar `password_resets` — **tabela em uso**) | 8 refs ativas |
| 2.7 | Ponte de sessão PHP→JWT (temporária, remover na Fase 7) | coexistência |
| 2.8 | Testes automatizados de autorização cruzada entre certificadoras | segurança |

---

## FASE 3 — Migração de domínios  (7 ondas · 44–56 semanas)

**Padrão por módulo:** repositório → service → controller → contrato Zod → testes → tela Web → tela Mobile (se aplicável) → **desligar o módulo PHP**. Não se avança de onda com a anterior incompleta.

### Onda 1 — Núcleo (6–8 sem) — *habilita todas as outras*
| # | Módulo | Origem |
|---|---|---|
| 3.1 | `auth` | `login.php`, `logout.php`, `modulos/portal/login.php` |
| 3.2 | `usuarios` | `modulos/usuarios/` |
| 3.3 | `associados` | `modulos/associados/` |
| 3.4 | `propriedades` | `propriedades` |
| 3.5 | `associacoes` | `modulos/associacoes/` |
| 3.6 | `regionalizacao` | `modulos/regionalizacao/`, `api/regionalizacao/` |

### Onda 2 — Campo (10–12 sem) — *coração do app mobile*
| # | Módulo | Origem |
|---|---|---|
| 3.7 | `caderno-campo` | `modulos/caderno_campo/` (**65 arquivos**) |
| 3.8 | `lavouras` | `lavouras`, `culturas` |
| 3.9 | `ocorrencias` | `api/ocorrencias.php`, `api/nao_conformidades.php` |
| 3.10 | `insumos` | `api/insumos_aprovados.php` |
| 3.11 | `plano-manejo` | `modulos/plano_manejo/`, `api/plano_manejo.php` |
| 3.12 | `roteiros-inspecao` | `modulos/roteiros_inspecao/`, `api/roteiros_inspecao.php` |

### Onda 3 — Certificação (8–10 sem)
| # | Módulo | Origem |
|---|---|---|
| 3.13 | `certificadoras` | `certificadoras_*.php`, `api/certificadoras/` |
| 3.14 | `certificacao` | `modulos/certificacao/`, `api/certificacao/` |
| 3.15 | `carteirinhas` | `carteirinha_publica.php` |
| 3.16 | `ficha-adesao` | `api/ficha_adesao.php`, `includes/ficha_adesao_modal.php` |

### Onda 4 — Financeiro (6–8 sem)
| # | Módulo | Origem |
|---|---|---|
| 3.17 | `financeiro` | `modulos/caixa/`, `contas/`, `mensalidades/` |
| 3.18 | `pix` | `api/pix/webhook/*` (Asaas, Gerencianet, Mercado Pago) |

### Onda 5 — Produção (8–10 sem)
| # | Módulo | Origem |
|---|---|---|
| 3.19 | `producao-animal` | `modulos/producao_animal/`, `manejo_animal/` |
| 3.20 | `agroindustria` | `modulos/agroindustria/` |

### Onda 6 — Governança (6–8 sem)
| # | Módulo | Origem |
|---|---|---|
| 3.21 | `governanca` | `modulos/assembleia/` |
| 3.22 | `eventos` | `modulos/eventos/`, `api/get_eventos_*` |
| 3.23 | `notificacoes` | `includes/lembretes.php` |
| 3.24 | `rastreabilidade` | `rastreabilidade.php`, `classes/HistoricoConversao.php` |

---

## FASE 4 — Relatórios, PDF e Excel  (6–8 semanas)

| # | Passo | Nota |
|---|---|---|
| 4.1 | Extrair templates HTML dos relatórios (reusar `relatorio_estilo.css`) | preserva layout |
| 4.2 | `Puppeteer` (HTML → PDF) em paridade com mPDF/TCPDF/Dompdf | área de maior risco |
| 4.3 | `ExcelJS` substituindo PhpSpreadsheet | exportação |
| 4.4 | Microserviço PHP de relatórios (transição, atrás do proxy) | não bloqueia dados |
| 4.5 | Fila BullMQ + Redis para relatórios pesados | hoje travam a requisição |
| 4.6 | Paridade visual dos 33 arquivos de `relatorios/` | aceite do SINPORG |
| 4.7 | Desligar o microserviço PHP | fim da Fase 4 |

---

## FASE 5 — Web  (8–12 semanas · paralela às ondas)

| # | Passo | Nota |
|---|---|---|
| 5.1 | `apps/web`: Next.js 16 App Router + layout dual-mode | sidebar / bottom nav |
| 5.2 | Design system: Tailwind + shadcn; **aposentar Bootstrap/jQuery/DataTables** | elimina CDN |
| 5.3 | Auth no web: cookie `httpOnly` + refresh silencioso | segurança |
| 5.4 | TanStack Query + `packages/sdk` | cache e otimista |
| 5.5 | Telas por módulo (acompanha as ondas 1–6) | paridade funcional |
| 5.6 | PWA com **assets locais** | corrige offline quebrado por CDN |
| 5.7 | Testes E2E com Playwright (já disponível no `package.json`) | regressão |

---

## FASE 6 — Mobile nativo  (8–12 semanas · após a Onda 2)

| # | Passo | Nota |
|---|---|---|
| 6.1 | `apps/mobile`: Expo SDK 57 + expo-router | base |
| 6.2 | Auth + `expo-secure-store` | token seguro |
| 6.3 | Offline: `expo-sqlite` + outbox local | requisito de campo |
| 6.4 | Sync engine + `Idempotency-Key` | evita duplicar registros |
| 6.5 | Resolução de conflitos + `updated_at` confiável | last-write-wins |
| 6.6 | Câmera, GPS, assinatura, QR Code | `expo-camera`, `expo-location` |
| 6.7 | Push com `expo-notifications` | lembretes de evento |
| 6.8 | Testes de sincronização com rede intermitente | CI |
| 6.9 | 👤 Publicação na Play Store + App Store (contas, certificados, revisão) | latência externa |

---

## FASE 7 — Desligamento do PHP

| # | Passo | Nota |
|---|---|---|
| 7.1 | Congelar código PHP em `legacy/` | só correções de segurança |
| 7.2 | Redirecionar rota a rota (301 + aviso por 30 dias) | sem downtime |
| 7.3 | Remover a ponte de sessão PHP→JWT | dívida temporária |
| 7.4 | Remover a restrição de schema "somente aditivo" | PHP deixa de escrever |
| 7.5 | Aposentar o microserviço de relatórios PHP | — |
| 7.6 | Arquivar backup final e dump pré-migração | — |
| 7.7 | Remover credenciais e variáveis PHP do servidor | segurança |
| 7.8 | **Confirmar: 0 arquivos PHP em produção** | critério final |

---

## Passos transversais (ao longo de todas as fases)

| # | Tema | Passo |
|---|---|---|
| T.1 | Coexistência | Proxy Apache (`/api/v2` → Node) + feature flag por módulo |
| T.2 | Coexistência | **Uma única fonte de escrita por tabela** em cada momento |
| T.3 | Banco | Migrations sempre **aditivas** enquanto o PHP escrever |
| T.4 | Segurança | Auditar CSRF → JWT; registrar em `audit_log` |
| T.5 | Observabilidade | OpenTelemetry + métricas antes da coexistência |
| T.6 | Qualidade | Revisão humana obrigatória de auth e multi-tenant |
| T.7 | Operação | Backup e plano de rollback **por onda** |
| T.8 | Documentação | Runbook de cada módulo migrado |

---

## Definição de Pronto (critérios finais)

1. Zero arquivos PHP em produção
2. Paridade funcional nos 12+ domínios, assinada pelo SINPORG
3. Web completa em Next.js 16
4. App publicado na Play Store **e** App Store
5. Offline funcional no campo (registrar sem internet e sincronizar)
6. Todos os relatórios de `relatorios/` em paridade
7. API documentada (Swagger em `/api/docs`)
8. Auth 100% JWT, sem sessão PHP
9. Zero segredos no repositório
10. Prisma Migrate como única fonte de verdade do schema
