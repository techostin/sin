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

**Fase 0 — Preparação.** A F1 ainda **não começou**.

| # | Item da Fase 0 | Status |
|---|---|---|
| 1 | Congelar features e comunicar | ✅ **concluído em 30/10/2026** |
| 2 | Commit/tag do estado atual + branch `feat/f1-kernel` | ✅ **30/10/2026** — commit `796d11d`, tag `pre-modularizacao`, branch `feat/f1-kernel` ([relatório](docs/v5/EXECUCAO-FASE0-ITEM2-GIT.md)) |
| 3 | Staging com banco anonimizado + feature flags | ✅ **30/10/2026** — `http://localhost/app-staging` + `sisagro_staging`; flags em `feature_flags` ([relatório](docs/v5/FASE0-ITEM3-STAGING.md)) |
| 4 | P0 residual: Resend, logs de produção, decisão LGPD | ⛔ **bloqueado externo** — chave Resend ainda ativa; logs de produção e assinatura LGPD pendentes ([relatório](docs/v5/FASE0-ITEM4-P0-RESIDUAL.md)) |
| 5 | Paridade de K2 e D1 (`arquivo:linha`) | ✅ **30/10/2026** — inventário concluído ([relatório](docs/v5/FASE0-ITEM5-PARIDADE-K2-D1.md)) |
| 6 | Fiscais e especialista de domínio designados | ⚠️ **proposta pronta; faltam nomes/aceite** ([documento](docs/v5/FASE0-ITEM6-FISCAIS-ESPECIALISTA.md)) |

> A F1 só inicia quando os 6 itens estiverem verdes, conforme
> [KICKOFF-IMPLEMENTACAO-MODULAR.md](docs/v5/KICKOFF-IMPLEMENTACAO-MODULAR.md).

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

1. Aprovar/realizar o **commit/tag** e a branch `feat/f1-kernel` (item 2).
2. Preparar **staging + feature flags** (item 3).
3. Gerar **paridade K2/D1** (item 5).
4. Fechar **Resend/logs/LGPD** e designar **fiscais/especialista** (itens 4 e 6).
5. Iniciar **F1**: PSR-4, Kernel, `/api/v1/health`, testes e CI.

## Regras de ouro durante o congelamento

- Nenhuma feature nova sem exceção registrada.
- Segurança/LGPD têm prioridade e não esperam o fim do congelamento.
- Toda exceção: branch `hotfix/*`, teste, rollback e registro.
- Atualizar este arquivo a cada mudança de fase/gate.
