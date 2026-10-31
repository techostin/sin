# Kickoff da Implementação Modular — Próximos Passos e Primeiro Módulo

> **Data:** 2026-10-30 · **Status:** plano de arranque (nada implementado) · **Base:** plano v5
> **Estado verificado hoje:** PHP **8.4.27**, php.ini endurecido, WAF ativo, `TRACE=405`,
> segredos/sessões rotacionados; **sem** `src/`, `routes/`, testes, autoload PSR-4 ou CI;
> **252 pendências no Git**.
> **P0 residual:** chave Resend, logs de produção e decisão LGPD (não bloqueiam o código, mas
> precisam de dono e prazo).

---

## 1. Resposta curta

| Pergunta | Resposta |
|---|---|
| **Por onde começar?** | Pela **fundação (F1)**: autoload PSR-4, bootstrap único, Kernel de contratos, roteador por módulo, camadas Controller→Service→Repository, testes e CI |
| **Qual a primeira entrega técnica?** | **K2 — Território & Referências**, como *fatia vertical de fumaça* (leitura, sem PII), para provar o padrão ponta a ponta |
| **Qual o primeiro módulo de negócio?** | **D1 — Quadro Associativo & Adesão** |
| **O que NÃO começar primeiro?** | D2 (Caderno de Campo, o maior e mais acoplado), D4 (Financeiro) ou uma reescrita geral |

**Justificativa em uma frase:** D1 é o **dado comum** (o agricultor/associado) que todos os outros
módulos consomem; implementá-lo primeiro estabiliza o `AssociadoRef`, o login e as permissões — e
destrava D2, D3, D4 e D8. K2 entra antes como piloto porque é pequeno, somente leitura e é
dependência direta de D1.

---

## 2. Critérios de entrada (não começar sem isso)

| # | Critério | Evidência |
|---|---|---|
| 1 | P0 técnico ativo | PHP ≥ 8.4.27; `TRACE=405`; WAF/headers; segredos rotacionados |
| 2 | Resend rotacionado | chave nova no painel; e-mail de teste |
| 3 | Logs de produção triados | versão/data do PHP em produção + logs 20–27/10 |
| 4 | Decisão LGPD registrada | parecer A8 assinado pelo controlador/DPO |
| 5 | Git limpo e versionado | commit das 252 pendências; branch/tag `pre-modularizacao` |
| 6 | Staging | cópia de código + base anonimizada + feature flags |
| 7 | Congelamento | 6–8 semanas sem feature nova no legado |
| 8 | Equipe e domínio | tech lead, 2 devs, QA, especialista SINPORG; matriz de paridade de D1/K2 |
| 9 | Backup | dump verificado + restauração testada na última semana |

> **Exceção controlada:** F1 (fundação) pode começar em paralelo ao fechamento dos itens 2–4, desde
> que não haja deploy de módulo de negócio antes dos gates.

---

## 3. Sequência de implementação

```
Semana 1        Semanas 2–6              Semanas 6–8        Semanas 8–14
Congelar/       F1 Fundação             K2 piloto          D1 Associados
versionar       (kernel, HTTP, dados,   (fatia vertical)   (fatias A→D)
                 testes, CI, deploy)
   │                  │                      │                  │
   └──── G0.1 ────────┴────── G1/G2 ─────────┴────── G3 ────────┴── G4/G6
```

### Fase 0 — Preparação (semana 1)

1. Congelar features e comunicar. ✅ **30/10/2026** — ver
   [`CONGELAMENTO-FEATURES.md`](CONGELAMENTO-FEATURES.md) e [`STATUS.md`](../../STATUS.md).
2. Commit/tag do estado atual; branch `feat/f1-kernel`.
3. Criar staging com cópia do banco (anonimizada) e feature flags.
4. Fechar P0 residual (Resend, logs de produção, decisão LGPD).
5. Inventariar paridade de **K2** e **D1** (`arquivo:linha`).
6. Designar fiscais e especialista de domínio.

### Fase 1 — Fundação F1 (4–6 semanas)

| # | Entrega | Critério de aceite |
|---|---|---|
| 1.1 | `composer.json` com PSR-4 `App\\` → `src/`; autoload | `composer dump-autoload` e classes carregam |
| 1.2 | Bootstrap único + config por ambiente/secrets | nenhum `DB_*` hardcoded; CLI e web sobem |
| 1.3 | Kernel de contratos | `ContextoUsuario`, `AssociadoRef`, `UnidadeProdutivaRef`, `AuditLogger`, `EventBus` |
| 1.4 | HTTP: front controller, router registry, middleware | rota nova responde `/api/v1/health`; rotas antigas intactas |
| 1.5 | Dados: conexão única, UnitOfWork, Repository base | nenhum SQL em controller; prepared statements |
| 1.6 | Segurança: authz, CSRF, upload, escopo/tenant | matriz de autorização e IDOR no CI |
| 1.7 | Qualidade: PHPUnit, PHPStan, deptrac, PHPCS | `composer test` e `deptrac` verdes |
| 1.8 | CI/CD + staging + feature flags + rollback | pipeline roda em PR; deploy 1 comando |
| 1.9 | Observabilidade mínima | request id, log JSON, health, alerta 5xx |

**Gate G1:** fundação verde; nenhuma página legada alterada de comportamento.
**Gate G2:** contratos do Kernel congelados (`kernel@1`).

### Fase 2 — K2 piloto (1–2 semanas, dentro/logo após F1)

Escopo mínimo, somente leitura, sem PII:

- `GET /api/v1/territorios/estados`
- `GET /api/v1/territorios/cidades?uf=RS`
- `GET /api/v1/territorios/microrregioes`
- `GET /api/v1/health`
- Uma tela existente (ex.: filtro de cidades) consumindo o novo endpoint **atrás de flag**
- Testes unitários + integração + E2E do caminho

**Por que K2:** é dependência de D1, não tem dados pessoais, tem baixo acoplamento e valida o
caminho completo (router → controller → service → repository → view/API → teste → deploy) sem
risco de negócio.

**Gate G3:** K2 em produção atrás de flag, com paridade e rollback testados.

### Fase 3 — D1 Quadro Associativo (6–8 semanas, em fatias)

| Fatia | Escopo | Entrega | Critério |
|---|---|---|---|
| **A** | Leitura | `GET /api/v1/associados` (filtros/paginação), `GET /api/v1/associados/{uuid}`, `GET /api/v1/associados/pendentes`; página de listagem atrás de flag | paridade de filtros/colunas; 0 SQL cruzado |
| **B** | Escrita | `POST/PATCH /api/v1/associados`; validação de CPF; soft delete; auditoria | CPF inválido/duplicado rejeitado; auditoria antes/depois |
| **C** | Fluxo | aprovação/reprovação, transições de status, notificações, pré-cadastro | máquina de estados testada; autor registrado |
| **D** | Documentos | ficha de adesão + carteirinha PDF/QR + validação pública | PDF/QR válidos; token opaco invalidável |
| **E** | Desligamento | migrar telas restantes, 301 das rotas antigas, remover flag | matriz de paridade 100%; legado desligado |

**Gate G4/G6 por fatia:** contrato de API, autorização, paridade web e cobertura ≥80%.

> **Cuidado com o login:** na fatia A, o Kernel apenas **resolve** o `associadoId` a partir da
> sessão/DB atual. A migração de credenciais para `auth_credenciais` só ocorre na fatia C/D, para não
> misturar refatoração de cadastro com mudança de autenticação.

---

## 4. Por que D1 primeiro (e não D2/D4)

| Critério | D1 Associados | D2 Caderno | D4 Financeiro |
|---|---|---|---|
| É o dado comum do sistema | **sim** | consome | consome |
| Destrava outros módulos | **todos** | D3/D6 | D8/L1 |
| Tamanho/risco inicial | médio (fatiável) | **alto** (14k linhas, offline) | médio (PIX/webhooks) |
| Dependências | K1/K2 | D1/D7 | D1 |
| Valor imediato | aprovação/cadastro/carteirinha | campo | cobrança |
| Recomendação | **primeiro** | segundo (com D7) | depois de D1 |

D2 é o coração do mobile, mas é o **maior** e mais acoplado; começá-lo antes do Kernel e de D1
repetiria o erro de big-bang. D4 depende de D1 para saber quem cobrar.

---

## 5. Primeiros 10 dias (checklist executável)

| Dia | Ação | Evidência |
|---|---|---|
| 1 | Congelamento + commit/tag `pre-modularizacao` | `git status` limpo; tag criada |
| 1 | Branch `feat/f1-kernel`; staging com base anonimizada | ambiente acessível |
| 2 | `composer.json` PSR-4 + `src/Kernel` esqueleto | `composer dump-autoload` ok |
| 2 | `/api/v1/health` via router registry | HTTP 200 JSON |
| 3 | Contratos `ContextoUsuario`/`AssociadoRef`/`UnidadeProdutivaRef` + testes | PHPUnit verde |
| 3 | PHPStan/deptrac/PHPCS + pipeline CI | pipeline no PR |
| 4 | Repository base + UnitOfWork + conexão única | teste de integração com DB |
| 5 | Middleware de auth/CSRF/policy reaproveitando o existente | teste de autorização |
| 6 | Inventário de paridade K2 + D1 | planilha `arquivo:linha` |
| 7 | K2: contrato + endpoints de leitura | OpenAPI + testes |
| 8 | K2 atrás de flag em staging | E2E do caminho |
| 9 | D1 fatia A: contrato + listagem | endpoint + página em staging |
| 10 | Revisão G1/G2 e ajuste do backlog | parecer do fiscal |

---

## 6. Equipe mínima e papéis

| Papel | Dedicação | Responsabilidade |
|---|---|---|
| Tech lead | 1 | fronteiras, contratos, revisão final |
| Dev backend sênior | 1–2 | Kernel, K2, D1 |
| Dev frontend/PWA | 1 | páginas finas, acessibilidade |
| QA automação | 1 | PHPUnit/E2E, matriz de autorização |
| Especialista SINPORG | 0,5 | paridade de regra de negócio |
| Fiscal independente | rotativo | evidência e parecer |
| DevOps/segurança | 0,5 | CI, staging, WAF |

**Esforço estimado:** F1 4–6 semanas; K2 1–2; D1 6–8 (fatias). Primeiro valor em produção:
**8–10 semanas** após o início.

---

## 7. Riscos específicos do arranque

| Risco | Mitigação |
|---|---|
| Kernel grande demais (“framework próprio”) | começar com 5 interfaces e 3 middlewares; nada especulativo |
| Refatorar login junto com cadastro | separar: credenciais só na fatia C/D |
| Quebrar páginas legadas | novas rotas atrás de flag; 301 só no fim |
| Escopo de D1 crescer (ficha/carteirinha) | fatias A–E com gate por fatia |
| Git com 252 pendências | commit/tag antes de qualquer código novo |
| Agentes em paralelo conflitarem | 1 frente de código por vez no início; worktree por tarefa |
| P0 residual virar bloqueio | dono e prazo para Resend/logs/LGPD na semana 1 |

---

## 8. Decisões necessárias

| # | Decisão | Recomendação |
|---|---|---|
| K-01 | Congelamento de 6–8 semanas | **sim** |
| K-02 | Primeiro módulo de negócio | **D1**, com K2 como piloto |
| K-03 | Estratégia de branch | `main` + `feat/f1-kernel` + feature flags |
| K-04 | Staging com base anonimizada | **sim, antes da fatia A** |
| K-05 | Dono do P0 residual (Resend/logs/LGPD) | definir na semana 1 |
| K-06 | Especialista de domínio | 0,5 FTE durante D1 |
| K-07 | Critério de “pronto” da fatia A | paridade + autorização + E2E + flag |

---

## 9. O que não fazer

1. Começar por D2/D4 ou por uma reescrita geral.
2. Criar “framework” antes de ter uma rota real funcionando.
3. Migrar login e cadastro no mesmo incremento.
4. Tocar em várias tabelas compartilhadas ao mesmo tempo.
5. Deployar módulo novo sem feature flag e rollback.
6. Avançar sem matriz de paridade e sem fiscal.

---

## 10. Definição de pronto do primeiro ciclo

- [ ] Fundação F1 verde (testes, análise, deptrac, CI).
- [ ] K2 em produção atrás de flag, com paridade e rollback.
- [ ] D1 fatia A (leitura) em produção atrás de flag.
- [ ] Nenhuma página legada quebrada; `login` 200; rotas antigas intactas.
- [ ] Matriz de autorização (anônimo, papéis, outro associado, outro tenant) verde.
- [ ] 0 violação de fronteira; `repository` único com PDO.
- [ ] Runbook + rollback testado + observabilidade.

---

## Anexo — Estrutura-alvo mínima

```
src/
├── Kernel/
│   ├── Contracts/   ContextoUsuario, AssociadoRef, UnidadeProdutivaRef, AuditLogger, EventBus
│   ├── Http/        Request, Response, Router, Middleware
│   ├── Auth/        SessionAuth, Policy, Scope
│   └── Persistence/ Connection, UnitOfWork, Repository
├── Modules/
│   ├── Territorio/  Controller, Service, Repository
│   └── Associados/  Controller, Service, Repository, Policy, dto/
routes/              territorio.php, associados.php
views/               por módulo
tests/               Unit, Integration, E2E
```

**Regra:** página antiga vira *thin page* que chama o controller do módulo; nada de SQL na página.
