# STATUS — SINPORG / Modularização

> **Atualizado em:** 30/10/2026 · **Fonte de verdade do andamento** · **Não é página pública** (`.md` bloqueado pelo Apache)

## ⛔ Congelamento de features ATIVO

| Item | Valor |
|---|---|
| Início | **30/10/2026** |
| Revisão | **11/12/2026** |
| Término mínimo | **18/12/2026** |
| Escopo | novas features no PHP legado (telas, campos, regras, relatórios, integrações) |
| Exceções | segurança, LGPD, bug bloqueante, integridade de dados — com registro e aprovação |
| Regras completas | [docs/v5/CONGELAMENTO-FEATURES.md](docs/v5/CONGELAMENTO-FEATURES.md) |
| Comunicado | [docs/v5/COMUNICADO-CONGELAMENTO.txt](docs/v5/COMUNICADO-CONGELAMENTO.txt) |

## Fase atual

**Fase 0 quase concluída + F1 iniciada em 30/10/2026** (autorização explícita, com itens 4 e 6 pendentes).

| # | Item da Fase 0 | Status |
|---|---|---|
| 1 | Congelar features e comunicar | ✅ **concluído em 30/10/2026** |
| 2 | Commit/tag do estado atual + branch `feat/f1-kernel` | ✅ **30/10/2026** — commit `796d11d`, tag `pre-modularizacao`, branch `feat/f1-kernel` ([relatório](docs/v5/EXECUCAO-FASE0-ITEM2-GIT.md)) |
| 3 | Staging com banco anonimizado + feature flags | ✅ **30/10/2026** — `http://localhost/app-staging` + `sisagro_staging`; flags em `feature_flags` ([relatório](docs/v5/FASE0-ITEM3-STAGING.md)) |
| 4 | P0 residual: Resend, logs de produção, decisão LGPD | ⛔ **bloqueado externo** — chave Resend ainda ativa; logs de produção e assinatura LGPD pendentes ([relatório](docs/v5/FASE0-ITEM4-P0-RESIDUAL.md)) |
| 5 | Paridade de K2 e D1 (`arquivo:linha`) | ✅ **30/10/2026** — inventário concluído ([relatório](docs/v5/FASE0-ITEM5-PARIDADE-K2-D1.md)) |
| 6 | Fiscais e especialista de domínio designados | ⚠️ **proposta pronta; faltam nomes/aceite** ([documento](docs/v5/FASE0-ITEM6-FISCAIS-ESPECIALISTA.md)) |

> **Exceção de gate:** a F1 foi iniciada por autorização do responsável com os itens 4 e 6 pendentes;
> nenhum módulo de negócio foi iniciado. Ver [relatório da fundação](docs/v5/EXECUCAO-F1-FUNDACAO.md).

## F1 — Fundação (em andamento)

| # | Entrega | Status |
|---|---|---|
| 1.1 | PSR-4 `App\` → `src/`, autoload e scripts | ✅ |
| 1.2 | Bootstrap único + API sem sessão | ✅ |
| 1.3 | Kernel de contratos (6 interfaces) | ✅ |
| 1.4 | HTTP, router, pipeline, middleware e `/api/v1/health` | ✅ |
| 1.5 | Connection, UnitOfWork, Repository base | ✅ |
| 1.6 | Authz/CSRF/tenant/upload no Kernel | ⚠️ parcial (F2) |
| 1.7 | PHPUnit, PHPStan, Deptrac, PHPCS | ✅ 11 testes, 0 erros |
| 1.8 | CI, staging, feature flags; deploy/rollback | ⚠️ parcial |
| 1.9 | Health, `X-Request-Id`, log, envelope JSON | ✅ |

- Tag **`f1-fundacao`** → commit `2979360`; branch `feat/f1-kernel`.
- Health: **`GET /app/api/v1/health` → 200**; legacy `login.php` → 200.

## P0 — segurança (resumo)

| Item | Status |
|---|---|
| PHP 8.4.27 / hardening / `TRACE=405` | ✅ ativo |
| WAF local (Apache + aplicação + cabeçalhos) | ✅ ativo |
| Segredos do banco/ID/sessões rotacionados | ✅ executado |
| `RESEND_API_KEY` | ⏳ rotação manual pendente |
| Logs de produção | ⏳ aguardando provedor |
| Decisão LGPD | ⚠️ incidente potencial em avaliação; falta assinatura/DO |

Relatórios: [A4](docs/v5/EXECUCAO-A4-HARDENING-PHP.md) · [A5](docs/v5/EXECUCAO-A5-WAF.md) ·
[A6](docs/v5/EXECUCAO-A6-SEGREDOS-SESSOES.md) · [A7](docs/v5/EXECUCAO-A7-LOGS-INCIDENTE.md) ·
[A8](docs/v5/EXECUCAO-A8-AVALIACAO-LGPD.md)

## Próximo passo

1. Fechar **F1.6** (authz/CSRF/tenant/upload no Kernel) e **F1.8** (deploy/rollback/sync staging).
2. Fechar **Resend/logs/LGPD** e designar **fiscais/especialista** (itens 4 e 6).
3. Iniciar o piloto **K2** (endpoints de território) usando o padrão da F1.
4. Somente depois iniciar **D1** (associados).

## Regras de ouro durante o congelamento

- Nenhuma feature nova sem exceção registrada.
- Segurança/LGPD têm prioridade e não esperam o fim do congelamento.
- Toda exceção: branch `hotfix/*`, teste, rollback e registro.
- Atualizar este arquivo a cada mudança de fase/gate.
