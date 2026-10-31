# 00 — LEIA-ME: Kit de Replicação do SINPORG v3

> **O que é este pacote.** Um conjunto de artefatos prontos para executar a replicação do
> SINPORG (sistema PHP legado de gestão da produção orgânica) em uma plataforma moderna,
> segura e de deploy simples — com **agentes dedicados por módulo, cada um fiscalizado por
> outro**, em ciclo fechado até o objetivo de cada módulo ser comprovadamente atingido.
>
> **Para onde ir primeiro:** se você quer **executar**, comece pelo
> [`07-PROMPT-MESTRE-MULTIAGENTE.md`](07-PROMPT-MESTRE-MULTIAGENTE.md). Se você quer
> **entender**, comece pelo [`01-PRD-SINPORG-V3.md`](01-PRD-SINPORG-V3.md).

---

## 1. Ordem de leitura

| # | Documento | Para quem | O que responde |
|---|---|---|---|
| **01** | [PRD — SINPORG v3](01-PRD-SINPORG-V3.md) | Todos | O que o sistema é, o que deve ser, personas, jornadas, requisitos, riscos e métricas |
| **02** | [Catálogo de Módulos e Telas](02-CATALOGO-DE-MODULOS-E-TELAS.md) | Produto, dev, QA | Os 12 módulos, cada tela, cada ação, cada defeito por módulo |
| **03** | [Modelo de Dados e Ownership](03-MODELO-DE-DADOS-E-OWNERSHIP.md) | Dados, backend | 77 tabelas, 1.025 colunas, 48 FKs, dono de cada tabela, regras de migração |
| **04** | [Contratos de API](04-CONTRATOS-DE-API.md) | Backend, frontend, mobile | Contrato HTTP alvo, convenções, erros, sync offline, matriz de rotas |
| **05** | [Arquitetura e Deploy](05-ARQUITETURA-E-DEPLOY.md) | Tech lead, DevOps | Stack, topologia, 12 requisitos de deploy, ADRs |
| **06** | [Segurança e Conformidade](06-SEGURANCA-E-CONFORMIDADE.md) | Segurança, jurídico | OWASP, multi-tenant, LGPD, matriz regulatória, 18 testes de segurança |
| **07** | ★ [**Prompt-Mestre Multiagente**](07-PROMPT-MESTRE-MULTIAGENTE.md) | Quem vai executar | **O prompt completo, auto-suficiente e copiável** |
| **08** | [Organização dos Agentes](08-ORGANIZACAO-DOS-AGENTES.md) | Orquestrador | Os 39 agentes, o protocolo construtor↔fiscal, gates, anti-cola |
| **09** | [Critérios de Aceite e Qualidade](09-CRITERIOS-DE-ACEITE-E-QUALIDADE.md) | Fiscais, QA | Definição de Pronto, checklist por módulo, testes obrigatórios, SLOs |
| **10** | [Roadmap e Ondas](10-ROADMAP-E-ONDAS.md) | Gestão | Fases, ondas, backlog de épicos e histórias, riscos |
| **A1** | [Mapa de Telas Reais](anexos/mapa-de-telas-reais.md) | Todos | **230 rotas navegadas na aplicação em execução**, com campos e ações |

---

## 2. Como usar em três cenários

### Cenário A — Vou executar a replicação
1. Abra [`07-PROMPT-MESTRE-MULTIAGENTE.md`](07-PROMPT-MESTRE-MULTIAGENTE.md) e copie o bloco da seção 2.
2. Entregue-o ao agente de codificação na raiz do repositório de destino.
3. O prompt determina a leitura dos documentos `01` a `10` (este pacote) e, se eles não
   existirem no destino, a **geração deles a partir do legado**.
4. Acompanhe o andamento em `STATUS.md`, que o orquestrador mantém.

### Cenário B — Vou decidir se vale a pena
1. Leia o [`01-PRD-SINPORG-V3.md`](01-PRD-SINPORG-V3.md), seções **1** (diagnóstico),
   **8** (defeitos do legado) e **16** (riscos).
2. Leia o [`10-ROADMAP-E-ONDAS.md`](10-ROADMAP-E-ONDAS.md), seções **1** (fases) e **5** (riscos).
3. A seção 4 do prompt-mestre traz **ajustes por objetivo** (só Web, só segurança, time
   pequeno, apenas um módulo).

### Cenário C — Vou auditar antes de decidir
Execute apenas a **Fase −1** (levantamento) e a produção dos artefatos da seção 8 do
prompt-mestre. O resultado é um diagnóstico entregável, sem escrever código de produção.

---

## 3. O que foi analisado para produzir este pacote

| Fonte | Cobertura |
|---|---|
| **Código PHP legado** | 441 arquivos / 84.734 linhas — roteador de 1.484 linhas (194 ramos de rota, 227 `require`), 220 páginas de módulo, 52 classes, 56 arquivos de `api/`, 33 de `relatorios/` |
| **Banco `sisagro`** | Introspecção ao vivo: 77 tabelas, 1.025 colunas, 48 FKs, 229 índices, 71 ENUMs, 1 view, contagens por tabela, histórico dos dumps pré e pós-saneamento |
| **Aplicação em execução** | Navegação autenticada real: **230 rotas** visitadas, **226 com conteúdo autenticado**, com extração de formulários, campos, ações e colunas |
| **Documentação existente** | `REPORT.md`, `RELATORIO_MODULARIZACAO.md`, `PLANO_MIGRACAO_NODE.md`, `ROADMAP_IMPLEMENTACAO.md`, `AUDITORIA_SEGURANCA.md`, `SEGURANCA_SEGREDOS.md`, `PLANO_DASHBOARDS_POR_PERFIL.md`, `recicla/PRD.md`, `modulos/TODO_Agroind.md`, `doc/MODELO_IDENTIDADE.md` |
| **Monorepo de destino** | `sinporg/` (NestJS 12 + Prisma 7 + Next.js 16 + Expo 57) já iniciado: schema com 76 modelos e 67 enums, módulos `auth`, `associados`, `caderno-campo`, `relatorios`, `sync`, CI e Docker |

**Relatórios de análise aprofundada** gerados no processo (na raiz do workspace):
`doc/SCHEMA_SISAGRO_COMPLETO.md` · `RELATORIO_NUCLEO_AUTH_SEGURANCA.md` ·
`RELATORIO_MODULO_ASSOCIADOS_GOVERNANCA.md` · `docs/RELATORIO_TECNICO_CADERNO_CAMPO.md` ·
`doc/RELATORIO_CERTIFICACAO_INSPECAO_PLANO_MANEJO.md` · `RELATORIO_FINANCEIRO_PRODUCAO.md`.

---

## 4. Os cinco números que resumem a situação

| Número | Significado |
|---|---|
| **84.734** | linhas de PHP em 441 arquivos — o ativo de regra de negócio a preservar |
| **47 de 86** | handlers POST **sem CSRF** (55%) — a maior dívida de segurança |
| **45 de 77** | tabelas **sem nenhuma chave estrangeira** — a maior dívida de dados |
| **0** | filtros por certificadora nas consultas — o multi-tenant não existe como fronteira |
| **1.505** | registros operacionais reais — o gargalo é manutenibilidade, **não escala** |

---

## 5. Os doze módulos e seus pares de agentes

| ID | Módulo | Construtor | Fiscal | Onda |
|---|---|---|---|---|
| M1 | Associados & Quadro Associativo | `dev-associados` | `fiscal-associados` | 1 |
| M2 | Caderno de Campo (Produção Vegetal) | `dev-caderno-campo` | `fiscal-caderno-campo` | 2 |
| M3 | Fiscalização & Conformidade | `dev-fiscalizacao` | `fiscal-fiscalizacao` | 3 |
| M4 | Financeiro da Associação | `dev-financeiro` | `fiscal-financeiro` | 4 |
| M5 | Produção Animal | `dev-producao-animal` | `fiscal-producao-animal` | 5 |
| M6 | Agroindústria | `dev-agroindustria` | `fiscal-agroindustria` | 5 |
| M7 | Suprimentos & Insumos | `dev-suprimentos` | `fiscal-suprimentos` | 2 |
| M8 | Governança, Eventos & Comunicação | `dev-governanca` | `fiscal-governanca` | 6 |
| M9 | Territorialidade & CNPO | `dev-territorial` | `fiscal-territorial` | 1 |
| M10 | Relatórios & Analytics | `dev-relatorios` | `fiscal-relatorios` | 6 |
| M11 | Portal & Dashboards | `dev-portal` | `fiscal-portal` | 1 |
| M12 | Auditoria, Configuração & Administração | `dev-admin` | `fiscal-admin` | 0 |

Mais **11 agentes de plataforma** (API, auth, contratos, Web, sync, relatórios, DevOps,
mobile) e **3 de governança** (orquestrador, arquiteto-kernel, arquiteto-dados), e
**4 fiscais transversais** (paridade, segurança, qualidade, regulatório). **Total: 39.**

---

## 6. A regra que define este método

> Cada módulo tem **um construtor** e **um fiscal independente**. O fiscal audita por
> seis perguntas fixas, reproduz toda a evidência e emite parecer. Reprovado, o módulo
> volta ao construtor. Depois de **três rodadas**, o módulo é **bloqueado** e escalado —
> **nunca aprovado por cansaço**.

O detalhamento do protocolo, das oito regras anti-cola e dos dez gates está em
[`08-ORGANIZACAO-DOS-AGENTES.md`](08-ORGANIZACAO-DOS-AGENTES.md).

---

## 7. Aviso importante

A análise identificou **vulnerabilidades exploráveis e funcionalidades quebradas** no
sistema em execução — incluindo um endpoint que serve dados pessoais de todos os
associados sem autenticação e uma coluna ausente que derruba todo o fluxo de cobrança PIX.
Os itens estão catalogados na **seção 8 do PRD**. **Eles são diagnosticados, não
explorados**: nenhuma rota destrutiva foi acionada e nenhum formulário de domínio foi
submetido durante a análise da aplicação em execução.

Recomenda-se tratar a **seção 8.1 (segurança crítica)** como correção emergencial no
legado, independentemente da decisão sobre a migração.
