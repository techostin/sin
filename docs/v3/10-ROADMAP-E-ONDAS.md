# 10 — Roadmap, Ondas e Backlog de Execução

> **Como usar:** este é o cronograma vivo. Cada linha vira uma tarefa no
> `docs/v3/STATUS.md`. O `orquestrador` (A0) é o dono deste documento e o atualiza a
> cada rodada concluída.

---

## 1. Visão de fases

```
Fase -1  Levantamento do legado .......... 2-3 semanas   (bloqueador)
Fase  0  Saneamento de banco e segredos .. 2-3 semanas   (bloqueador)
Fase  1  Fundacao da plataforma .......... 4-6 semanas
Fase  2  Ondas de modulos ................ 40-52 semanas (o corpo do trabalho)
Fase  3  Mobile nativo ................... 8-12 semanas  (apos Onda 2)
Fase  4  Relatorios em paridade .......... 6-8 semanas   (paralela as ondas 5-6)
Fase  5  Desligamento do PHP ............. 2-3 semanas
--------------------------------------------------------------------
TOTAL com 2-3 devs + agentes ............. 12-18 meses
```

**Caminho crítico:** Fase 0 -> Fase 1 (kernel) -> Onda 1 (Associados). Qualquer atraso
nessas três empurra todo o resto. As demais ondas são paralelizáveis em pares.

---

## 2. Fases detalhadas

### Fase -1 — Levantamento (obrigatória)

| # | Entrega | Critério de aceite |
|---|---|---|
| -1.1 | Anexos de legado por módulo (`anexos/legado-<modulo>.md`) | Cada tela, campo e ação com `arquivo:linha` |
| -1.2 | Mapa de rotas completo (roteador + `api/` + `relatorios/`) | Tabela rota -> arquivo -> método -> auth -> parâmetros |
| -1.3 | Inventário do banco (77 tabelas, 48 FKs, 1 view) | Dicionário de dados + contagens |
| -1.4 | Decisão das duplicatas (8 pares) | Um ADR por decisão |
| -1.5 | Artefatos `01`–`10` de `docs/v3/` | Revisados pelo humano |

### Fase 0 — Saneamento (bloqueia tudo)

| # | Entrega | Critério de aceite |
|---|---|---|
| 0.1 | Backup verificado + restauração testada | Restauração executada em staging com sucesso |
| 0.2 | 0 tabelas sem PRIMARY KEY | `information_schema` confirma |
| 0.3 | 0 tabelas MyISAM | idem |
| 0.4 | 1 collation única (`utf8mb4_unicode_ci`) | 77/77 tabelas |
| 0.5 | Typos de schema corrigidos | `cerated_at` -> `created_at` |
| 0.6 | Duplicatas reconciliadas | Cópia canônica definida e migrada |
| 0.7 | Resíduos de Laravel removidos | `cache, jobs, failed_jobs, sessions, users`... |
| 0.8 | MariaDB em LTS (11.4) | `SELECT VERSION()` |
| 0.9 | Segredos rotacionados e fora do Git | `gitleaks` limpo; app sobe com env |
| 0.10 | `prisma db pull` sem avisos | schema revisável gerado |

### Fase 1 — Fundação

| # | Entrega | Agente | Critério de aceite |
|---|---|---|---|
| 1.1 | Monorepo Turborepo + pnpm | A3 | `pnpm install && pnpm build` verde |
| 1.2 | NestJS 12 + Fastify + Zod + Swagger | A3 | `/api/docs` e `/api/v2/health` respondem |
| 1.3 | Prisma 7 + adapter MariaDB + baseline | A2 | `prisma migrate status` = up to date |
| 1.4 | `packages/contracts` + `packages/sdk` | A5 | Kernel versionado; 1 tipo, 3 consumidores |
| 1.5 | Kernel de identidade + JWT + guards | A4 | Login do legado funciona sem trocar senha |
| 1.6 | `CertificadoraScopeGuard` + teste cruzado | A4 | Teste de tenant cruzado **bloqueia o merge** |
| 1.7 | Docker dev + prod + Caddy + observabilidade | A9 | Deploy em 1 comando, por terceiro |
| 1.8 | CI/CD com gates | A9 | Todo gate G1–G10 roda no PR |
| 1.9 | Módulo de auditoria/config/`feature_flags` | M12 | `audit_log` gravando |

---

## 3. As 7 ondas de módulos

Para cada módulo, o ciclo é sempre: **inventário -> contrato -> API -> testes -> Web ->
(mobile) -> gate -> desligamento do PHP**.

### Onda 1 — Núcleo (habilita todas as outras)

| Módulo | Escopo | Esforço | Depende de |
|---|---|---|---|
| **M1 Associados & Quadro Associativo** | CRUD de associado, ficha de adesão, vínculo associativo, carteirinhas, aprovação de cadastro, meu perfil | 5–6 sem | Kernel, M9 |
| **M9 Territorialidade & CNPO** | estados, cidades, mesorregiões/microrregiões RS, consulta CNPO/PEAF | 2–3 sem | — |
| **M11 Portal & Dashboards** | portal do associado, 4 dashboards por perfil, BFF | 3–4 sem | M1 |

### Onda 2 — Campo (coração do app mobile)

| Módulo | Escopo | Esforço | Depende de |
|---|---|---|---|
| **M2 Caderno de Campo** | propriedades, lavouras, atividades diárias, categorias, produtos, lotes, ocorrências, histórico de conversão, rastreabilidade, relatórios de campo, **sync offline** | 8–10 sem | M1, M7, A7 |
| **M7 Suprimentos & Insumos** | insumos, insumos orgânicos aprovados (Portaria 52/2021), fornecedores, unidades | 3–4 sem | M9 |

### Onda 3 — Fiscalização

| Módulo | Escopo | Esforço | Depende de |
|---|---|---|---|
| **M3 Fiscalização & Conformidade** | roteiros de inspeção, visitas técnicas, não conformidades, plano de manejo, certificação participativa, certificadoras, certificados PDF, conformidade/score | 7–8 sem | M1, M2 |

### Onda 4 — Financeiro

| Módulo | Escopo | Esforço | Depende de |
|---|---|---|---|
| **M4 Financeiro** | planos de mensalidade, geração, cobrança PIX (3 provedores), baixa, estorno, caixa, contas a pagar/receber, relatórios financeiros | 5–6 sem | M1 |

### Onda 5 — Produção

| Módulo | Escopo | Esforço | Depende de |
|---|---|---|---|
| **M5 Produção Animal** | animais, lotes, ambientes, alimentação, reprodução, sanidade, rastreabilidade animal | 4–5 sem | M1 |
| **M6 Agroindústria** | setores, atividades, categorias, receitas/ingredientes, ordens de produção, balanço de massas, higienização, rastreabilidade, **rotulagem** | 5–6 sem | M2, M7 |

### Onda 6 — Governança e Leitura

| Módulo | Escopo | Esforço | Depende de |
|---|---|---|---|
| **M8 Governança, Eventos & Comunicação** | assembleias (pautas, votação, encerramento, resultado), eventos e presença, notificações/lembretes, WhatsApp, e-mail | 5–6 sem | M1 |
| **M10 Relatórios & Analytics** | camada somente-leitura: relatórios administrativos, de gestor e diretoria, PDF/Excel assíncronos | 5–6 sem | ondas 1–5 |

### Onda 7 — Mobile e desligamento

| # | Entrega | Esforço |
|---|---|---|
| 7.1 | App Expo SDK 57 (auth, caderno de campo offline, câmera, GPS, QR, push) | 8–12 sem |
| 7.2 | Publicação Play Store + App Store | (latência externa) |
| 7.3 | Redirecionamento rota a rota do PHP legado (301, aviso 30 dias) | 1–2 sem |
| 7.4 | Remoção da ponte de sessão e do serviço PHP de relatórios | 1 sem |
| 7.5 | **0 arquivos PHP em produção** | marco final |

---

## 4. Backlog inicial (épicos e histórias)

### Épico E1 — Kernel de Identidade
| ID | História | Aceite |
|---|---|---|
| E1.1 | Como usuário do legado, quero entrar com minha senha atual | Login funciona sem redefinir senha (bcrypt compatível) |
| E1.2 | Como sistema, quero que uma senha antiga seja migrada para argon2id | Rehash silencioso no 1º login válido |
| E1.3 | Como sistema, quero que um refresh token seja de uso único | Reutilização revoga a família de tokens |
| E1.4 | Como gestor de certificadora, quero ver apenas meus produtores | Tenant cruzado devolve 403 (teste automatizado) |
| E1.5 | Como admin, quero suspender um usuário | Suspensão invalida tokens ativos em < 60 s |

### Épico E2 — Associados
| ID | História | Aceite |
|---|---|---|
| E2.1 | Como gestor, quero cadastrar um associado com validação de CPF | CPF inválido/duplicado rejeitado com mensagem clara |
| E2.2 | Como agricultor, quero pré-cadastrar-me pelo site | Vai para fila de aprovação; sem acesso até aprovar |
| E2.3 | Como gestor, quero aprovar/reprovar cadastros pendentes | Transição registrada em `audit_log` |
| E2.4 | Como agricultor, quero minha carteirinha em PDF com QR | PDF gerado; QR resolve para validação pública |
| E2.5 | Como agricultor, quero preencher minha ficha de adesão e assinar | Assinatura registrada; PDF arquivado |
| E2.6 | Como gestor, quero excluir um associado | **Soft delete** + anonimização; auditoria preservada |

### Épico E3 — Caderno de Campo (offline-first)
| ID | História | Aceite |
|---|---|---|
| E3.1 | Como agricultor, quero registrar atividade **sem internet** | Registro local imediato; sincroniza ao reconectar |
| E3.2 | Como agricultor, quero registrar insumo e ser avisado se for proibido | Alerta impeditivo conforme Portaria 52/2021 |
| E3.3 | Como agricultor, quero fotografar a evidência | Upload validado por conteúdo; re-encode |
| E3.4 | Como agricultor, quero rastrear meu lote por QR | Consulta devolve a cadeia completa |
| E3.5 | Como técnico, quero ver as atividades de todos os produtores do meu escopo | Filtros + exportação Excel |
| E3.6 | Como sistema, quero nunca duplicar um registro sincronizado | `Idempotency-Key` testado com 500 itens na fila |

### Épico E4 — Fiscalização
| ID | História | Aceite |
|---|---|---|
| E4.1 | Como técnico, quero criar templates de roteiro de inspeção | Checklist versionado; itens críticos reprovam automaticamente |
| E4.2 | Como técnico, quero agendar e realizar uma visita | Pontuação calculada; conformidade derivada |
| E4.3 | Como técnico, quero registrar não conformidade com prazo | Status: aberta -> em correção -> resolvida |
| E4.4 | Como produtor, quero dar ciência da visita | Registro de ciência ou recusa com justificativa |
| E4.5 | Como auditor, quero o certificado em PDF assinado | Geração assíncrona; nome do arquivo forçado a `.pdf` |

### Épico E5 — Financeiro
| ID | História | Aceite |
|---|---|---|
| E5.1 | Como gestor, quero gerar mensalidades do mês em lote | Idempotente: rodar 2× não duplica |
| E5.2 | Como associado, quero pagar por PIX | QR/copia-e-cola gerado; webhook confirma |
| E5.3 | Como sistema, quero processar webhook de forma idempotente | Mesmo `event_id` processado 1× |
| E5.4 | Como gestor, quero dar baixa manual e estornar | Estorno reverte caixa e pagamento em transação única |
| E5.5 | Como diretoria, quero fluxo de caixa realizado × previsto | Relatório em paridade com o legado |

### Épico E6 — Produção e Agroindústria
| ID | História | Aceite |
|---|---|---|
| E6.1 | Como produtor, quero registrar manejo animal (alimentação, sanidade, reprodução) | Rastreabilidade por animal/lote |
| E6.2 | Como agroindústria, quero cadastrar receita e ingredientes | Percentual orgânico calculado automaticamente |
| E6.3 | Como agroindústria, quero emitir ordem de produção | Consome lote de matéria-prima do caderno |
| E6.4 | Como sistema, quero alertar quebra de balanço de massas | Saída > entrada -> alerta |
| E6.5 | Como agroindústria, quero registrar higienização pré-processamento | Obrigatório para lote orgânico |
| E6.6 | Como sistema, quero bloquear rótulo indevido | <70% orgânico proíbe menção ao termo |

### Épico E7 — Governança
| ID | História | Aceite |
|---|---|---|
| E7.1 | Como diretoria, quero convocar assembleia com pautas | Publicação notifica associados |
| E7.2 | Como associado, quero votar | 1 voto por associado; voto auditável |
| E7.3 | Como diretoria, quero encerrar e apurar o resultado | Resultado imutável após encerramento |
| E7.4 | Como associado, quero confirmar presença em evento | Lista de presença + relatório |
| E7.5 | Como sistema, quero lembrar o associado de um evento | Notificação com antecedência configurável |

### Épico E8 — Plataforma e Operação
| ID | História | Aceite |
|---|---|---|
| E8.1 | Como dev, quero subir tudo com 1 comando | `pnpm dev` sobe api+web+db+redis |
| E8.2 | Como operador, quero implantar com 1 comando | `docker compose up -d --build` |
| E8.3 | Como operador, quero reverter em 1 comando | Tag anterior sobe; smoke test verde |
| E8.4 | Como operador, quero saber que algo quebrou antes do usuário | Alerta de 5xx e de p95 |
| E8.5 | Como auditor, quero rastrear quem mudou o quê | `audit_log` consultável por período e entidade |

---

## 5. Riscos e mitigação

| # | Risco | Prob. | Impacto | Mitigação |
|---|---|---|---|---|
| R1 | **Repetir o big-bang** (já houve uma tentativa em Laravel, cujas tabelas ainda estão no banco) | Alta | Fatal | Ondas verticais obrigatórias; nenhuma onda avança incompleta |
| R2 | **Regressão funcional** em 84 mil linhas sem testes | Alta | Alto | Anexos de legado + matriz de paridade + fiscal-paridade em todo gate |
| R3 | **Falta de domínio TypeScript na equipe** | Alta | Alto | Tech lead dedicado; treinamento antes da Fase 1 |
| R4 | **Relatórios PDF divergirem** | Média | Alto | Puppeteer sobre o HTML atual; serviço PHP de transição |
| R5 | **Duplicação de dados no sync offline** | Média | Alto | `Idempotency-Key` + testes de rede intermitente |
| R6 | **Dados legados duplicados** | Alta | Médio | Fase 0 explícita com decisão de negócio documentada |
| R7 | **Dois sistemas indefinidamente** | Média | Alto | Prazo por onda + critério de desligamento |
| R8 | **Regressão de segurança na troca de auth** | Média | Alto | ScopeGuard testado + IDOR por rota + rate limit + auditoria |
| R9 | **Bloqueio por MariaDB EOL** | Média | Médio | Upgrade na Fase 0, antes do Prisma Migrate |
| R10 | **Prisma 7 (Rust-free) com comportamento novo** | Média | Médio | Validar `db pull` em staging; versão fixada |
| R11 | **Migração de schema quebrar o PHP** | Alta | Alto | Migrações aditivas até o desligamento; nunca renomear/dropar |
| R12 | **Agentes/paralelismo gerando conflito de arquivos** | Média | Médio | Máx. 2 pares ativos; nunca com tabela compartilhada; uma *worktree* por frente |
| R13 | **Fadiga de fiscalização** (fiscal vira carimbo) | Média | Alto | 8 regras anti-cola + rotação a cada 3 aprovações sem achado |
| R14 | **Conhecimento concentrado em uma pessoa** | Alta | Alto | Anexos, ADRs, runbooks e ownership de tabelas produzidos durante as Fases -1/0 |

---

## 6. Primeiros 30 dias (ações concretas)

**Semana 1 — Decisão e levantamento**
1. Aprovar o plano e o orçamento.
2. Backup completo + **restauração testada** em staging.
3. Executar a Fase -1: anexos de legado por módulo.
4. Levantar com o SINPORG as decisões de negócio (duplicatas, retenção, perfis).

**Semana 2 — Saneamento**
5. Corrigir PKs, MyISAM, collation em staging.
6. Rotacionar segredos e limpá-los do Git.
7. Subir o MariaDB para LTS.
8. Definir ownership das 77 tabelas.

**Semana 3 — Fundação**
9. Criar o monorepo (Turborepo + pnpm).
10. Rodar `prisma db pull` em staging saneado; revisar o schema.
11. Gerar a baseline do Prisma Migrate.
12. Entregar `GET /api/v2/health` + Swagger.

**Semana 4 — Kernel**
13. Congelar `packages/contracts/src/kernel.ts`.
14. Implementar JWT + guards + rate limit.
15. Implementar `CertificadoraScopeGuard` **com teste de tenant cruzado**.
16. Deploy em 1 comando executado por alguém que não escreveu o código.

**Entregável do dia 30:** API respondendo, login do legado funcionando, CI com gates
ativo, banco saneado, **e nenhuma linha do PHP alterada**. A partir daí o risco deixa de
ser técnico e passa a ser disciplina de execução.
