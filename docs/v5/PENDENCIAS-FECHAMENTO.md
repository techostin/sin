# Fechamento de Pendências — Estado em 01/11/2026

## 1. O que foi fechado

| Item | Evidência |
|---|---|
| Baseline congelado | commit `796d11d`, tag `pre-modularizacao` |
| Staging anonimizado | `http://localhost/app-staging`, `sisagro_staging` |
| P0 técnico local | PHP 8.4.27, hardening, WAF, `TRACE=405` |
| Fundação F1 | PSR-4, Kernel, API v1, health, testes, CI |
| K1/K3/L2 | papéis, capabilities, auditoria, numeração, manifesto |
| T1 | lotes/eventos/elos/QR, vegetal/animal/insumo/agroindústria |
| D1/D2/D3/D5/D6/D7/D8/D4 | fatias core com E2E e testes |
| L1/L2 | resumo, dashboard por capability, menu filtrado |
| E2E admin | login, certificado `CERT-2026-000009`, PDF, evidência |
| Quality gate | **74 testes / 174 asserções**, PHPStan/Deptrac/PHPCS verdes |

## 2. Pendências externas — não executáveis pelo agente

| # | Pendência | Situação atual | Quem fecha |
|---|---|---|---|
| 1 | Rotação da chave Resend | chave antiga **ainda válida** (HTTP 422); API de keys **401** | responsável pelo painel Resend |
| 2 | Revogar chave Resend antiga | depende da nova chave | responsável pelo painel |
| 3 | Logs de produção 20–27/10 | não obtidos; locais preservados | hospedagem/provedor |
| 4 | Decisão LGPD formal | parecer A8 preliminar; sem assinatura | controlador/DPO |
| 5 | Fiscais e especialista | proposta pronta; sem nomes/aceite | SINPORG |
| 6 | Deploy de produção | runbook pronto; execução manual | infraestrutura |
| 7 | Push ao remoto | **credencial inválida**: `git push --dry-run` retornou `Invalid username or token` | renovar GCM/PAT e autorizar |
| 8 | Push das tags | depende do item 7 | responsável |

## 3. Pendências técnicas internas (backlog, não bloqueiam a fundação)

- D7: listagem e vínculo explícito insumo → produção.
- D8: pautas, votos, presença e quórum.
- D4: planos, pagamentos detalhados, caixa e conciliação.
- L1: exportações PDF/Excel.
- L2: telas novas por persona; hoje o menu é filtrado, não redesenhado.
- D2: offline completo.
- D5/D6: perdas finas, rótulo e relatórios específicos.

## 4. Critério para declarar o programa “sem pendências”

1. Nova chave Resend criada e antiga revogada.
2. Logs de produção recebidos e anexados ao dossiê A7.
3. Decisão LGPD assinada e registrada por 5 anos.
4. Fiscais e especialista nomeados com aceite.
5. Push e deploy de produção aprovados/executados; token GitHub renovado.

> **Evidência do push:** em 01/11/2026 o `git push --dry-run app feat/f1-kernel` retornou
> `Authentication failed` / `Invalid username or token`. O GCM lista a conta `techostin`, mas o
> token armazenado está inválido/expirado. Nenhum push foi realizado.
6. Backlog técnico priorizado (não precisa estar 100% implementado para a fundação).

**Conclusão:** as pendências **técnicas executáveis** foram fechadas até a Onda 5. As pendências
restantes são **externas/humanas** ou backlog de evolução; nenhuma delas foi maquiada como concluída.
