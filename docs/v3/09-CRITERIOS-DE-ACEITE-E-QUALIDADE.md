# 09 — Critérios de Aceite, Qualidade e Definição de Pronto

> Este documento é o **árbitro contratual** entre construtores e fiscais. Se um critério
> aqui não está satisfeito, o módulo **não** está pronto — independentemente de opinião.

---

## 1. Definição de Pronto do projeto (visão final)

O SINPORG v3 está concluído quando **todos** os itens abaixo forem verdadeiros e
verificáveis por comando:

| # | Critério | Verificação executável |
|---|---|---|
| DP1 | **Zero arquivos PHP em produção** | `Get-ChildItem -Recurse -Filter *.php` no deploy retorna vazio |
| DP2 | **Paridade funcional nos 12 módulos**, assinada pelo SINPORG | 12 × `PARECER-FISCAL.md` com veredito APROVADO |
| DP3 | Web completa em Next.js 16 | Todas as rotas do `02-CATALOGO` respondem 200 autenticadas |
| DP4 | App publicado na **Play Store** e **App Store** | builds assinados em produção |
| DP5 | **Offline funcional no campo** | registrar caderno de campo sem rede → reconectar → dado único no banco |
| DP6 | **Paridade de relatórios** PDF/Excel | diff visual aprovado para os relatórios do inventário |
| DP7 | **API documentada** (OpenAPI 3.1) | `/api/docs` acessível e sem rota não documentada |
| DP8 | Auth **100% JWT**, sem sessão PHP | `grep -r "session_start" legacy/` → só na ponte, removida |
| DP9 | **Zero segredos no repositório** | `gitleaks detect` limpo; `config/secrets.php` inexistente |
| DP10 | **Prisma Migrate é a única fonte de verdade** | `prisma migrate status` = up to date |
| DP11 | **Deploy em 1 comando** e rollback em 1 comando | runbook executado por pessoa que não escreveu o código |
| DP12 | **Observabilidade ativa** | dashboards + alerta de 5xx + traces de ponta a ponta |
| DP13 | **Backup com restauração testada** | restauração semanal automatizada, verde por 4 semanas |
| DP14 | **Retenção regulatória de 3 anos** | nenhum `DELETE` físico em diário de campo, insumos ou balanço de massas |

---

## 2. Definição de Pronto por módulo (o checklist do fiscal)

Todo módulo precisa satisfazer **os itens abaixo**. O fiscal marca cada um com evidência.

### 2.1 Funcional

- [ ] **FUNC-1** Toda ação do legado inventariada na matriz de paridade, com `arquivo:linha` de origem.
- [ ] **FUNC-2** Toda regra de negócio do legado reproduzida (validações, cálculos, máquinas de estado, prazos).
- [ ] **FUNC-3** Casos de borda cobertos: campo vazio, valor zero, data passada, duplicidade, limite de plano.
- [ ] **FUNC-4** Erros devolvem mensagem útil ao usuário final (não stack trace, não "Erro").

### 2.2 Segurança

- [ ] **SEG-1** 100% das rotas com guarda de autenticação (nenhuma rota "aberta" por esquecimento).
- [ ] **SEG-2** Autorização por **papel + recurso + escopo** (não por nível numérico).
- [ ] **SEG-3** **Teste de IDOR real**: acessar recurso de outro associado → 403/404.
- [ ] **SEG-4** **Teste de tenant cruzado**: certificadora A não enxerga dado da B → 403.
- [ ] **SEG-5** Toda entrada validada por Zod; whitelist de campos (nada de espalhar o body direto no Prisma).
- [ ] **SEG-6** Sem SQL concatenado; Prisma parametrizado em 100% das consultas.
- [ ] **SEG-7** Upload: MIME real (`file-type`), re-encode de imagem, extensão derivada do conteúdo, nome aleatório, fora do webroot, sem execução.
- [ ] **SEG-8** Rate limit no login, na recuperação de senha e em endpoints públicos.
- [ ] **SEG-9** Webhooks: assinatura validada + **idempotência** (`Idempotency-Key` / `event_id`).
- [ ] **SEG-10** Nenhum segredo em código, log ou resposta de erro.
- [ ] **SEG-11** Registro em `audit_log` das ações sensíveis (quem, quando, o quê, antes/depois).

### 2.3 Arquitetura

- [ ] **ARQ-1** Nenhum JOIN em tabela de outro módulo (usa `*Ref` do kernel).
- [ ] **ARQ-2** Nenhum UPDATE/DELETE em tabela de outro módulo (usa evento).
- [ ] **ARQ-3** `repository` é o único que importa `PrismaService`.
- [ ] **ARQ-4** `dependency-cruiser` sem violação.
- [ ] **ARQ-5** Contratos em `packages/contracts` consumidos por API/Web/Mobile (sem redefinição local).
- [ ] **ARQ-6** Migração Prisma **aditiva**, reversível e testada sobre cópia do dump.

### 2.4 Qualidade

- [ ] **QUA-1** Cobertura ≥ **80% linhas / 70% branches** no módulo.
- [ ] **QUA-2** **100%** de cobertura nos cálculos de negócio (pontuação de visita, balanço de massas, percentual orgânico, juros/multa, conversão de área).
- [ ] **QUA-3** `lint` e `typecheck` sem erro e sem `any` novo.
- [ ] **QUA-4** Testes E2E dos fluxos principais (Playwright) verdes.
- [ ] **QUA-5** Testes de regressão do módulo passam após qualquer mudança no kernel.

### 2.5 Operação

- [ ] **OPE-1** Migrações e seeds idempotentes.
- [ ] **OPE-2** Logs estruturados (JSON) com `requestId` / `traceId`, sem PII.
- [ ] **OPE-3** Métricas do módulo expostas (`/metrics`).
- [ ] **OPE-4** Tempo de resposta p95 < 500 ms (leituras) e < 1.500 ms (escritas) em carga nominal.
- [ ] **OPE-5** Consultas sem N+1; índices conferidos para toda consulta nova por FK/status/data.
- [ ] **OPE-6** `README.md` do módulo + runbook de incidente.

### 2.6 Regulatório (módulos M2, M3, M6 e M12)

- [ ] **REG-1** soft delete e retenção de 3 anos em diário de campo, insumos e balanço de massas.
- [ ] **REG-2** Insumo não permitido pela Portaria 52/2021 é **bloqueado** com alerta impeditivo.
- [ ] **REG-3** Rastreabilidade lote → produto → lavoura → atividades → insumos completa e consultável por QR Code.
- [ ] **REG-4** Rotulagem: ≥95% orgânico → selo "Orgânico Brasil"; 70–95% → apenas "com ingredientes orgânicos"; <70% → proibido mencionar orgânico.
- [ ] **REG-5** Balanço de massas: saída > entrada dispara alerta (indício de mistura/fraude).
- [ ] **REG-6** Trilha de auditoria imutável para fiscalização (SisOrg/OAC).

---

## 3. Template da matriz de paridade (obrigatória por módulo)

O construtor preenche; o `fiscal-paridade` **verifica linha a linha**.

```markdown
| # | Tela/Ação no legado | Arquivo:linha | Rota nova | Status | Evidência | Observação |
|---|---|---|---|---|---|---|
| 1 | Listar associados com filtro por status/cidade | modulos/associados/listar.php:1-640 | GET /api/v2/associados | OK | e2e/associados/listar.spec.ts | filtro por microrregião adicionado |
| 2 | Cadastrar associado (validação de CPF) | modulos/associados/cadastrar.php:210 | POST /api/v2/associados | OK | unit/cpf.spec.ts | — |
| 3 | Ficha de adesão (modal + PDF) | includes/ficha_adesao_modal.php | POST /api/v2/ficha-adesao | PENDENTE | — | **D-03**: assinatura digital pendente |
| 4 | Carteirinha PDF + envio por e-mail | modulos/associados/carteirinha_pdf.php | GET /api/v2/carteirinhas/:id/pdf | FALTA | — | depende do pipeline A8 |
```

**Regra:** o status só pode ser "OK" quando há **link de evidência reproduzível**. Qualquer
item PENDENTE ou FALTA impede o gate.

---

## 4. Testes obrigatórios (além dos unitários)

### 4.1 Matriz de autorização (executada pelo `fiscal-seguranca`)

Para **cada rota** do módulo, com **cada** papel:

| Papel de teste | Expectativa |
|---|---|
| Anônimo | 401 |
| Agricultor (nível 3) tentando rota de gestor | 403 |
| Agricultor A acessando recurso do agricultor B | 403 / 404 |
| Gestor da certificadora A acessando dado da B | 403 |
| Token expirado ou refresh revogado | 401 |
| Token com papel adulterado (assinatura inválida) | 401 |
| Papel correto, escopo correto | 200 |

### 4.2 Offline e sincronização (M2 e M10)

| Teste | Critério |
|---|---|
| Registrar 10 atividades offline | 10 registros locais, UI responde imediatamente |
| Reconectar → sincronizar | Exatamente 10 no servidor (**sem duplicatas**) |
| Sincronizar 2× o mesmo lote | Nenhuma duplicata (idempotência) |
| Conflito: editar offline e no servidor | Regra documentada aplicada; nada é perdido silenciosamente |
| Rede cair no meio do envio | Retoma sem duplicar e sem perder |
| Fila com 500 itens | Sincroniza em ordem, sem travar a UI |

### 4.3 Terceiros e falhas (M4 e M8)

| Teste | Critério |
|---|---|
| Webhook PIX com assinatura inválida | 401, nada é gravado |
| Webhook PIX duplicado (mesmo event_id) | Processado **uma** vez |
| Webhook fora de ordem | Estado final consistente |
| Provedor de e-mail fora do ar | Fila retém e reprocessa; usuário não vê erro |
| Geração de PDF falha | Job marcado com erro, visível, reprocessável |

### 4.4 Acessibilidade e responsividade (Web)

| Teste | Critério |
|---|---|
| Navegação por teclado nos fluxos principais | 100% alcançável |
| Contraste | WCAG AA |
| Rótulos em todos os campos | Sem campo sem rótulo acessível |
| Layout 360 px, 768 px, 1280 px | Sem scroll horizontal, sem sobreposição |
| Alvos de toque (mobile) | ≥ 44 × 44 px |

---

## 5. Métricas e SLOs

| Métrica | Alvo | Onde medir |
|---|---|---|
| Disponibilidade da API | 99,9% mensal | OpenTelemetry |
| Latência p95 (leitura) | < 500 ms | métricas HTTP |
| Latência p95 (escrita) | < 1.500 ms | métricas HTTP |
| Taxa de erro 5xx | < 0,1% | métricas HTTP |
| Cobertura global | ≥ 80% | Vitest |
| Vulnerabilidades altas/críticas | **0** | pnpm audit + DAST no CI |
| Duplicação de código | < 5% | SonarQube / jscpd |
| Violações de fronteira | **0** | dependency-cruiser |
| Sincronizações com conflito não resolvido | **0** | tabela sync_conflicts |
| Jobs de relatório com falha | < 1% | BullMQ dashboard |
| MTTR de incidente | < 2 h | runbook + alertas |

---

## 6. Checklist de release (antes de cada deploy em produção)

```
[ ] CI verde na tag exata que vai para produção
[ ] Testes de autorização cruzada passando (bloqueante)
[ ] Migrações Prisma revisadas e testadas sobre cópia do dump real
[ ] Backup recente (< 24 h) e restauração testada (< 7 dias)
[ ] .env validado pelo env.schema.ts (sem variável faltando)
[ ] Nenhum segredo novo no repositório (gitleaks limpo)
[ ] Changelog e ADRs atualizados
[ ] Feature flags do módulo conferidas (rollout gradual: 5% -> 50% -> 100%)
[ ] Plano de rollback escrito e uma pessoa designada para executá-lo
[ ] Painel de observabilidade aberto durante a janela
[ ] SINPORG avisado (se houver mudança visível)
```

---

## 7. Definition of Ready (antes de iniciar um módulo)

Um módulo só **começa** quando:

- [ ] As tabelas dele têm **ownership** definido em `03-MODELO-DE-DADOS-E-OWNERSHIP.md`.
- [ ] O contrato do kernel que ele consome está congelado (`AssociadoRef`, `UnidadeProdutivaRef`, `ContextoUsuario`).
- [ ] A **matriz de paridade em branco** existe, com todas as ações do legado inventariadas.
- [ ] As rotas-alvo estão especificadas em `04-CONTRATOS-DE-API.md`.
- [ ] Há dados de teste anonimizados em staging.
- [ ] Os fiscais (par + transversais) estão designados.

> **Se qualquer item acima faltar, o construtor não deve escrever código.** É a diferença
> entre uma migração que termina e outra que vira escombro — como já aconteceu com a
> tentativa em Laravel cujas tabelas ainda estão dentro do banco `sisagro`.
