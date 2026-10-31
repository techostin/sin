# Congelamento de Features — Início da Modularização

> **Status:** **ATIVO** · **Início:** 30/10/2026 · **Revisão:** 11/12/2026 · **Término previsto:** 18/12/2026 (mínimo)
> **Decisor:** Tech Lead · **Aprovação de exceção:** Tech Lead + Product Owner (SINPORG)
> **Comunicado pronto para envio:** [`COMUNICADO-CONGELAMENTO.txt`](COMUNICADO-CONGELAMENTO.txt)
> **Painel de status:** [`STATUS.md`](../../STATUS.md)

---

## 1. O que está congelado

A partir de **30/10/2026**, ficam **congeladas novas funcionalidades** no sistema atual (PHP legado),
incluindo:

- novas telas, campos, filtros, relatórios e dashboards;
- novas regras de negócio ou alterações de cálculo;
- refatorações amplas fora do escopo da F1/K2/D1;
- mudanças de layout/UX que não sejam correções;
- novas integrações externas (PIX, e-mail, WhatsApp, CNPO) fora do escopo aprovado;
- alterações de schema que não sejam **aditivas e retrocompatíveis**.

## 2. O que NÃO está congelado

| Permitido | Condição |
|---|---|
| Correções de **segurança** (P0/P1) | imediata; registrar no livro de exceções |
| Correções de **LGPD/privacidade** | imediata; registrar |
| Correção de **bug bloqueante** (produção parada, perda de dado, erro 500 generalizado) | aprovação do Tech Lead; registrar |
| **Integridade/corrupção de dados** | imediata; registrar |
| Trabalho da **F1/K2/D1** (fundação e primeiros módulos) | branch `feat/f1-kernel`; não altera comportamento legado |
| Migrações **aditivas** necessárias à F1 | testadas em cópia; nunca destrutivas |
| Atualização de dependência de segurança | com `composer audit` e teste |

## 3. Por que congelar

1. O sistema atual tem **252 pendências no Git** e dívida de segurança; novas features aumentam o
   risco de regressão e dificultam a paridade.
2. A F1 exige **bootstrap, autoload, kernel e CI** estáveis; mudanças concorrentes no legado
   quebram a base de comparação.
3. O congelamento protege a janela de **paridade** de K2 e D1.
4. Evita repetir o padrão das tentativas anteriores (Laravel e monorepo TS), que perderam foco por
   mistura de escopo.

## 4. Processo de exceção

1. Solicitar ao Tech Lead com: **descrição**, **justificativa**, **risco**, **arquivos afetados**,
   **prazo** e **plano de teste/rollback**.
2. Decisão em até **1 dia útil** (segurança/LGPD: imediata, com registro retroativo).
3. Aprovação registrada na tabela abaixo.
4. Implementar em branch `hotfix/*` (nunca direto na `main`).
5. Incluir teste e evidência; atualizar `STATUS.md`.

### Registro de exceções

| # | Data | Solicitante | Motivo | Aprovação | Branch/Commit | Status |
|---|---|---|---|---|---|---|
| 1 | — | — | — | — | — | — |

> Sem linha registrada, a exceção **não existe**. feature não aprovada é revertida.

## 5. Branches e releases

| Branch | Uso |
|---|---|
| `main` | somente merge de correção aprovada ou marco de gate |
| `feat/f1-kernel` | F1 (fundação) |
| `hotfix/*` | exceção aprovada (segurança/bug crítico) |
| `feat/*` | **bloqueada** durante o congelamento |

- Nenhum deploy de feature nova.
- Deploy de correção: tag `hotfix-AAAA.MM.DD` + rollback testado.
- F1/K2/D1: feature flags e deploy separado, sem alterar telas legadas.

## 6. Prazos e critérios de saída

| Marco | Data | Critério |
|---|---|---|
| Início | 30/10/2026 | comunicado e `STATUS.md` publicados |
| Revisão intermediária | 11/12/2026 | G1/G2 (fundação/kernel) aprovados; exceções revisadas |
| Término mínimo | 18/12/2026 | K2 piloto aprovado; primeira fatia de D1 em staging |
| Prorrogação | se necessário | decisão do Tech Lead + PO, registrada |

**Critérios para descongelar:**
- G1 e G2 verdes;
- K2 em produção atrás de flag (ou staging aprovado);
- backlog repriorizado e comunicação de retomada;
- nenhuma exceção pendente sem registro.

## 7. Comunicação

| Canal | Conteúdo | Responsável |
|---|---|---|
| E-mail para equipe/PO | comunicado completo | Tech Lead |
| WhatsApp do time | versão curta | Tech Lead |
| `STATUS.md` no repositório | fonte de verdade | Tech Lead |
| Reunião semanal | exceções e progresso | Tech Lead + PO |
| SINPORG (PO) | impacto em prazos e escopo | Product Owner |

**Mensagem pronta:** [`COMUNICADO-CONGELAMENTO.txt`](COMUNICADO-CONGELAMENTO.txt).

> **Nota operacional:** este ambiente não possui canal de e-mail/WhatsApp configurado; o comunicado
> foi publicado no repositório (`STATUS.md` e este arquivo) e está pronto para envio pelo responsável.

## 8. Métricas do congelamento

| Métrica | Meta |
|---|---|
| Features não aprovadas mergeadas | **0** |
| Exceções registradas | 100% |
| Deploys de feature | 0 |
| Regressões causadas por exceção | 0 |
| Revisão semanal realizada | 100% |

## 9. Riscos do congelamento

| Risco | Mitigação |
|---|---|
| Pressão por features urgentes | processo de exceção com aprovação e registro |
| Acúmulo de backlog | backlog repriorizado; retomada planejada |
| Time ocioso em front-end | trabalho em PWA/UX da F1/K2/D1 |
| Perda de contexto | `STATUS.md` + ADRs + paridade |
| Exceção virar regra | revisão semanal e métrica de features |

---

**Aprovado por:** ______________________ (Tech Lead) · **Data:** 30/10/2026
**Ciência do Product Owner (SINPORG):** ______________________ · **Data:** ____/____/______
