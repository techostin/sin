# SINPORG — Plano de Desmembramento em Módulos e Execução Multicanal

> ⚠️ **REVISÃO SUPERADA (2026-10-21).** A pasta `sinporg/` foi removida do projeto e este
> documento assumia aquele monorepo como fundação da plataforma nova. O plano vigente é
> [`docs/v5/PLANO-DE-MODULOS-E-EXECUCAO.md`](../v5/PLANO-DE-MODULOS-E-EXECUCAO.md). Mantido
> apenas como histórico da Revisão 1.

> **Natureza deste documento:** proposta técnica e plano de execução. **Nada foi executado.**
> Não houve alteração de código de produção, migração de banco, deploy, instalação de dependência
> nem mudança de configuração. O único artefato produzido é este arquivo.
>
> **Versão:** 1.0 · **Data:** 2026-10-21 · **Status:** aguardando decisão (G0)
> **Escopo analisado:** legado PHP (465 arquivos, 82.349 linhas), banco `sisagro`, documentação
> existente em `docs/v3/`, `RELATORIO_MODULARIZACAO.md`, `PLANO_MIGRACAO_NODE.md` e o monorepo
> iniciado em `sinporg/` (86 arquivos TypeScript, 5.653 linhas, 76 modelos Prisma).

---

## 0. Sobre a exigência de "100% de eficácia, segurança e usabilidade"

Nenhum engenheiro honesto assina 100% de garantia para uma replataforma de 82 mil linhas sem
testes, com dívida de segurança ativa e dados duplicados. O que este plano garante é diferente e
mais útil: **cada afirmação de qualidade tem uma evidência executável e um portão (gate) que
impede o avanço quando a evidência não existe.**

| O que não se promete | Como o plano controla |
|---|---|
| "Não vai haver regressão" | Matriz de paridade linha a linha + fiscal independente + E2E por fluxo + rollout gradual por rota |
| "Não vai haver incidente de segurança" | Correção emergencial do legado antes de expor a API + testes de autorização cruzada obrigatórios + DAST |
| "Todo agricultor vai adotar" | Piloto com 1 associação e 1 microrregião antes do rollout; métricas de adoção e tempo de tarefa |
| "Vai ficar pronto no prazo" | Ondas curtas com escopo congelado, caminho crítico explícito e critério de corte (MVP) |
| "Sem custo extra" | Coexistência custa mais durante 12–18 meses; o plano mostra o custo e o critério de desligamento |

**Compromisso verificável:** ao final deste documento existe uma lista de gates (G0–G10) e uma
Definição de Pronto (seção 9). Se um gate não passa, o módulo **não avança** — independentemente
de opinião, prazo ou cansaço da equipe.

---

## 1. Resposta direta

### 1.1 Se eu fosse separar este projeto, como faria?

Em **sete passos**, nesta ordem — a ordem é parte da resposta, porque metade do risco está em
fazer a coisa certa na hora errada:

1. **Blindar e congelar o legado antes de modularizar.** Hoje há *Local File Inclusion* ativo
   (`index.php:49-51` monta caminho com `$parts[2]`; `includes/rota_segura.php` existe mas **não é
   incluído por nenhum arquivo**), guarda de API em apenas 25 de 56 endpoints, 23 scripts de
   diagnóstico/seed na raiz e 250 pendências no Git. Modularizar sobre isso é construir sobre areia.
2. **Transformar o "dado comum" em contrato, não em banco compartilhado.** O agricultor/associado
   já é o nó central do grafo (a tabela `associados` é referenciada por 34 áreas do código), mas
   hoje isso é acoplamento por SQL. Passa a ser um **Kernel** com contrato explícito
   (`AssociadoRef`, `ContextoUsuario`, `UnidadeProdutivaRef`) e eventos.
3. **Recortar por capacidade de negócio, não por pasta, tabela ou tela.** Cada módulo responde a
   uma pergunta de negócio e tem um único dono dos seus dados.
4. **Colocar cada tabela sob exatamente um dono** (ownership). Nenhum módulo faz `JOIN` ou `UPDATE`
   em tabela de outro; usa referência do kernel ou evento.
5. **Definir quatro mecanismos de comunicação** e proibir o quinto (SQL direto entre módulos).
6. **Tratar Relatórios e Experiência (Web/Mobile) como camadas**, não como módulos de domínio.
   Relatório é produto de leitura; portal/dashboard é BFF multicanal.
7. **Implantar como monólito modular: um deploy, fronteiras verificadas no CI.** Microserviços
   agora multiplicariam custo operacional sem resolver o problema real, que é manutenibilidade.

### 1.2 Quais seriam os módulos?

São **13 unidades lógicas**: 3 de núcleo, 8 de negócio e 2 de leitura/experiência.

| ID | Módulo | Missão em uma frase | Dono dos dados | Canais |
|---|---|---|---|---|
| **K1** | Identidade & Acesso | Quem é o usuário, o que ele pode, em qual escopo (associação/certificadora) | `auth_credenciais`, `auth_refresh_tokens`, `password_resets`, `usuarios` | Web + Mobile |
| **K2** | Território & Referências | Onde o agricultor está e quais referências oficiais valem (CNPO, cidades, microrregiões) | `estados`, `cidades`, `mesorregioes_rs`, `microrregioes_rs`, `cnpo`, `cnpo_atualizacoes` | Web + Mobile (leitura) |
| **K3** | Plataforma, Auditoria & Administração | Serviços transversais: auditoria, arquivos, notificações, eventos, sync, feature flags, config | `audit_log`, `notificacoes`, `arquivos`, `eventos_outbox`, `eventos_consumidos`, `feature_flags`, `sync_idempotencia` | API + Admin Web |
| **D1** | Quadro Associativo & Adesão | Ciclo de vida do associado: pré-cadastro, aprovação, ficha, vínculo, carteirinha | `associados`, `associacoes`, `associado_associacao`, `ficha_adesao`, `ficha_adesao_produtos`, `carteirinhas`, `entidades` | Web (gestão) + Mobile (agricultor) |
| **D2** | Caderno de Campo (Vegetal) | Registrar e comprovar a produção vegetal: propriedades, lavouras, atividades, lotes, ocorrências | `propriedades`, `propriedade_membros`, `lavouras`, `atividades_diarias`, `categorias_atividades`, `produtos`, `lotes_producao`, `lote_atividade`, `lote_insumo`, `ocorrencias_manejo`, `historico_conversao`, `culturas` | **Mobile offline-first** + Web |
| **D3** | Fiscalização & Conformidade | Provar conformidade orgânica: roteiros, visitas, não conformidades, plano de manejo, certificação | `roteiros_inspecao`, `visitas_tecnicas`, `nao_conformidades`, `plano_manejo`, `plano_manejo_cultivos`, `certificacao_participativa`, `certificadoras` | Web + **Mobile (campo)** |
| **D4** | Financeiro Associativo | Arrecadar e controlar: planos, mensalidades, PIX, caixa, contas a pagar/receber | `planos_mensalidades`, `mensalidades`, `pagamentos`, `caixa`, `contas` | Web (gestão) + Mobile (pagar/consultar) |
| **D5** | Produção Animal | Rastrear o rebanho: animais, lotes, ambientes, alimentação, sanidade, reprodução | `animais`, `animal_lotes`, `manejo_animal_registros`, `controle_alimentacao_animal`, `controle_sanidade_animal`, `controle_reproducao_animal`, `ambiente_criacao_animal`, `ambiente_criacao_membros`, `densidades_maximas_animais` | Mobile + Web |
| **D6** | Agroindústria & Processamento | Transformar matéria-prima com rastreabilidade e rótulo correto | `agroindustrias`, `agroindustria_membros`, `agroindustria_existentes`, `agroindustria_atualizacoes`, `agro_*`, `receitas_agroindustria`, `ingredientes_receita`, `ordens_producao`, `controle_higienizacao`, `rastreabilidade_insumos_processo` | Web + Mobile |
| **D7** | Suprimentos & Insumos | Garantir que só entra insumo permitido (Portaria 52/2021) e rastrear fornecedor | `insumos`, `insumos_organicos_aprovados`, `fornecedores`, `fornecedor_unidades` | Web + Mobile (consulta) |
| **D8** | Governança & Comunicação | Decidir e informar: assembleias, votações, eventos, presença, lembretes | `assembleias`, `assembleia_pautas`, `assembleia_votos`, `eventos`, `evento_presenca`, `participacoes` | Web + Mobile |
| **L1** | Relatórios & Analytics | Responder perguntas gerenciais e regulatórias em PDF/Excel, sem tocar dados transacionais | Nenhum dado transacional; **read models** em `analytics.*` + `relatorio_jobs` | Web (gestão/diretoria) |
| **L2** | Portal, Dashboards & BFF Multicanal | Compor a experiência por perfil e por canal (web do gestor, web do agricultor, app) | Nenhum; agrega via API/SDK | Web + Mobile |

> **Mapa com a documentação existente:** D1..D8 correspondem a M1..M8; K2 a M9; L1 a M10;
> L2 a M11; K3 + partes de M12 viram o núcleo de plataforma. A numeração `docs/v3` continua válida;
> este documento reorganiza em camadas e acrescenta o desenho multicanal e a execução.

### 1.3 Como todos se comunicam se o dado comum é o agricultor/associado?

O ponto comum **não é a tabela `associados`** — é o **contrato do núcleo**. Quatro mecanismos:

| Mecanismo | Quando usar | Exemplo | Regra |
|---|---|---|---|
| **Referência do Kernel** | Exibir ou validar dado de outro módulo | `AssociadoRef`, `UnidadeProdutivaRef` | Leitura por referência; lote de até 100 IDs por chamada |
| **Comando síncrono** | A resposta é necessária para concluir a operação | D2 pede a D7 se um insumo é permitido | Timeout 500 ms, fallback com bloqueio seguro |
| **Evento de domínio** | Efeito colateral que pode ser assíncrono | D1 publica `associado.aprovado` → D3 cria pendência de visita | Outbox transacional; consumidor idempotente |
| **Read model (L1)** | Agregação e relatório | Fluxo de caixa, produtividade, inadimplência | Só lê `analytics.*`; nunca tabela transacional |

E **um mecanismo proibido**: `JOIN`/`UPDATE` direto em tabela de outro módulo. É a diferença entre
"módulos" e "pastas do mesmo código".

### 1.4 Como atender browser e mobile sem duplicar regra?

**Uma API, um contrato, N experiências.** Web e mobile consomem os mesmos endpoints e o mesmo
pacote `packages/contracts` (Zod/OpenAPI). A regra de negócio vive no servidor, uma única vez.
O que muda por canal é **apresentação, offline e autenticação** — detalhado na seção 6 e na
matriz do Anexo C.

### 1.5 O que este plano entrega

- Decomposição justificada com evidências do código atual.
- Contratos do núcleo e catálogo de eventos.
- Estratégia multicanal (web, app offline, PWA de transição).
- Execução em 2 trilhas e 10 fases, com gates, esforço e caminho crítico.
- Riscos, mitigação, definição de pronto, critérios de sucesso e as decisões que dependem de você.

---

## 2. Diagnóstico (com evidências)

### 2.1 Números recontados nesta análise

| Métrica | Valor | Fonte |
|---|---:|---|
| Arquivos PHP do legado | 465 | recontagem própria (exclui `vendor/`, `node_modules/`, cópias `recicla/`, `producao_bkp/`, `sinporg/`) |
| Linhas de PHP | 82.349 | idem |
| Rotas no roteador `index.php` | 224 referências a `$parts[0]` em 1.396 linhas, 248 `require` | idem |
| Páginas de módulo | 220 arquivos em `modulos/` | idem |
| Endpoints em `api/` | 56 arquivos (25 usam `ApiAuth`) | idem |
| Arquivos em `relatorios/` | 26 | idem |
| Comandos SQL no código | 1.215 ocorrências | idem |
| Arquivos que leem `$_SESSION['user_id']` | 109 | idem |
| Arquivos que leem `$_SESSION['user_nivel']` | 79 | idem |
| Arquivos que recebem `$_POST` | 118 | idem |
| Scripts de diagnóstico/seed na raiz | 23 de 36 `.php` da raiz | idem |
| Tabelas / colunas / FKs / views | 77 / 1.025 / 48 / 1 | `docs/v3/03-MODELO-DE-DADOS-E-OWNERSHIP.md` |
| Pendências no Git (modificados + não rastreados) | 250 | `git status` |
| Monorepo novo versionado? | **Não** (`sinporg/` aparece como `??`) | `git status` |
| Plataforma nova já escrita | 86 arquivos TS/TSX, 5.653 linhas, 76 modelos, 67 enums, 6 módulos de API | recontagem própria |

### 2.2 Arquitetura atual (como a requisição chega ao dado)

```
Browser/PWA
   │
   ▼
Apache + .htaccess  ──►  index.php (roteador central: 224 referências a $parts[0])
                            │
                            ├──► modulos/<x>/*.php  (220 páginas, cada uma monta seu bootstrap)
                            ├──► api/*.php          (56 endpoints, guarda irregular)
                            ├──► relatorios/*.php   (26 páginas, SQL próprio)
                            └──► classes/*.php       (52 modelos com SQL dentro)
                                       │
                                       ▼
                              MySQL/MariaDB "sisagro" (77 tabelas, 48 FKs)
                                       ▲
                                       └── mesma tabela lida/escrita por 3+ áreas
```

O sintoma central: **não há fronteira entre "quem decide" e "quem guarda o dado".** Cada página é
autossuficiente: monta bootstrap, valida sessão, consulta o banco, decide regra e renderiza HTML.

### 2.3 Os oito acoplamentos que impedem modularizar hoje

| # | Dimensão | Evidência | Efeito |
|---|---|---|---|
| 1 | Bootstrap | 126 + 92 + 33 inclusões de `config` com profundidades diferentes | cada arquivo é uma ilha |
| 2 | Sessão | 109 arquivos leem `user_id`, 79 leem `user_nivel` | identidade espalhada, sem contexto de request |
| 3 | Banco | `associados` tocada por 34 áreas; `usuarios` por 23; 30 tabelas com 3+ consumidores | impossível extrair sem contrato |
| 4 | Acesso a dados | 1.215 comandos SQL em 309 arquivos; sem repositório | regra e persistência misturadas |
| 5 | Roteamento | `index.php` conhece todas as rotas e todos os arquivos | todo módulo depende do arquivo central |
| 6 | UI/Navegação | `includes/menu.php` tem 84 links e faz 4 queries de negócio por render | shell conhece domínio |
| 7 | Relatórios | 3 pilhas de relatório (`relatorios.php`, `relatorios/index.php`, `api/relatorios/**`) e `ReportManager`/`ReportConformidade` com SQL cruzado | leitura atravessa 6 domínios |
| 8 | Duplicação de conceitos | 3 logins, 2 esquemas de reset, 4 pares de tabelas duplicadas (atividade, categoria, ocorrência, lote) | a mesma regra diverge |

### 2.4 O problema-raiz: uma tabela com cinco papéis

```
associados (52 colunas)
├── identidade civil ............ nome, cpf, rg, nascimento
├── contato/endereço ............ email, telefone, cep
├── AUTENTICAÇÃO ................ senha, nivel, tipo, status   ← deveria ser do Kernel (K1)
├── vínculo associativo ......... associacao_id, vinculo, tipo_associado, status_associado
├── certificação ................ certificadora_id, tipo_certificacao, registro_cnpo
├── ficha de adesão ............. ficha_enviada, ficha_aprovada_por/em
├── territorialidade ............ microrregiao_id, cluster_comunicacao
├── notificações ................ notificacoes_ativas, dias_antecedencia_evento
└── integração .................. user_id, tem_agroindustria, area_producao
```

Consequências diretas:
1. O login novo (`sinporg/apps/api/src/modules/auth/auth.service.ts:73-111`) **lê `associados`
   e depois `usuarios`** — prova que o Kernel ainda depende do domínio, e já introduz uma
   divergência de precedência em relação ao PHP (`login.php:90-111`). Item do gate G2.
2. `tem_agroindustria` é escrita por outro domínio (`classes/Agroindustria.php:258`) — violação
   de fronteira já existente.
3. Relatórios, dashboards e o menu dependem de colunas de 5 domínios na mesma linha.

**Decisão do plano:** não mover as 52 colunas agora. O Kernel passa a **definir o contrato** e,
numa fase posterior, a **possuir as credenciais** (`auth_credenciais`). O cadastro permanece em D1
para evitar um big-bang de dados. Ver 4.3 e ADR-03.

### 2.5 Dívidas que mudam o desenho (não são detalhe)

| Achado | Evidência | Consequência para o plano |
|---|---|---|
| LFI ativo na rota de relatórios | `index.php:49-51`; `rota_segura.php` sem nenhum include | Fase 0 obrigatória antes de qualquer wave |
| 30 endpoints de API sem autenticação | `RELATORIO_MODULARIZACAO.md` §4.5; só 25/56 usam `ApiAuth` | nenhum módulo pode ser exposto antes de guarda central |
| 47 de 86 POST sem CSRF | `docs/v3/00-LEIA-ME.md` §4 | idem |
| Zero escopo por certificadora nas consultas | docs/v3 PRD | multi-tenant será requisito duro do Kernel |
| Segredos e `root` sem senha no código | `config/config.php` (documentado em auditoria) | rotação antes de expor API |
| 23 scripts destrutivos na raiz + 36 em `dev-utils/` | recontagem | remover/bloquear antes de staging público |
| 250 pendências no Git; `sinporg/` não versionado | `git status` | **risco de perda de trabalho**: primeiro item da execução |
| Divergência de paridade no login v2 | `auth.service.ts:76-111` procura `associados` primeiro e só cai em `usuarios` se não achar; o PHP atual valida as **duas** e prefere `usuarios` (`login.php:90-111`) | corrigir antes do gate G2 — senão contas de sistema com mesmo e-mail ficam inacessíveis |
| Sem testes automatizados no legado | 0 suites PHP | paridade só é possível com matriz + E2E |

### 2.6 O que já existe de plataforma nova (e o que isso muda)

O monorepo `sinporg/` não é um plano no papel: já contém fundação real.

| Componente | Estado observado |
|---|---|
| `apps/api` NestJS + Fastify + Prisma 7 | 6 módulos: `auth`, `associados`, `caderno-campo`, `relatorios`, `sync`, `health` |
| Autenticação | JWT 15 min + refresh 30 dias rotativo, hash SHA-256 do refresh, compatibilidade bcrypt do PHP, guards de nível e de certificadora |
| Contratos e SDK | `packages/contracts` (Zod) + `packages/sdk` (HTTP tipado + fila offline) |
| Banco | 76 modelos, 67 enums, baseline + 2 migrations |
| Web | Next.js com login, home, atividades e sincronização (4 telas) |
| Mobile | Expo com login, home e sincronização (3 telas), fila SQLite |
| Testes | 5 arquivos de teste (mapper, bcrypt, contratos, SDK, fila) |
| CI | `.github/workflows/ci.yml` |

**Veredito:** a fundação técnica está validada; a maior parte dos 8 domínios **não existe** em v2.
Isso permite adotar o plano por ondas sem reescrever a fundação — mas exige três correções antes da
Onda 1: versionar o monorepo, fechar as fronteiras (hoje `auth` lê `associados`) e completar os
gates de segurança.

---

## 3. Princípios de decomposição

### 3.1 Critérios usados para recortar (nesta ordem)

1. **Capacidade de negócio**: o módulo responde a uma pergunta que o SINPORG faz ("quem está em
   dia?", "esta produção é conforme?", "quanto entrou no caixa?").
2. **Ownership de dados**: um único dono por tabela; escrita só pelo dono.
3. **Ciclo de mudança**: o que muda junto (regra + tela + dado) fica junto.
4. **Canal e offline**: o que precisa funcionar sem internet no campo tem fronteira própria.
5. **Regulação**: conformidade orgânica, retenção de 3 anos e auditoria exigem rastreabilidade
   isolável.
6. **Time**: cada módulo precisa caber em 1–2 pessoas com um fiscal independente.

### 3.2 Anti-critérios (o que não usar)

- **Não** separar por pasta do legado (`modulos/caderno_campo/` tem 65 arquivos de 3 domínios).
- **Não** separar por tabela (geraria 77 "módulos" com transações distribuídas).
- **Não** separar por camada técnica (um módulo "controllers", outro "models") — isso é organização
  interna, não módulo.
- **Não** separar por perfil de usuário (gestor/agricultor) — perfil é autorização, não domínio.
- **Não** microserviços agora: 1.505 registros operacionais reais e 2–3 devs não justificam rede,
  observabilidade distribuída e consistência eventual em tudo.
- **Não** reescrever do zero: 82 mil linhas de regra não documentada são o ativo; o caminho é
  estrangulamento (strangler) com paridade comprovada.

### 3.3 Como saber se a separação funcionou (métricas de arquitetura)

| Métrica | Alvo | Como medir |
|---|---:|---|
| `JOIN`/`UPDATE` em tabela de outro módulo | 0 | `dependency-cruiser` + regra de Prisma por schema + revisão |
| Import cruzado entre pastas de módulo | 0 | ESLint `boundaries` no CI |
| Endpoints sem autorização declarada | 0 | teste que varre rotas e exige decorator de papel/escopo |
| Duplicação de contrato (tipo redefinido no cliente) | 0 | `packages/contracts` é a única fonte; lint de import |
| Acoplamento aferente (quantos módulos dependem de X) | ≤ 3 por módulo, exceto núcleo | grafo gerado no CI |
| Churn de arquivos compartilhados | queda mês a mês | `git log --stat` por diretório |

---

## 4. Arquitetura-alvo

### 4.1 Visão em quatro camadas

```
┌──────────────────────────────────────────────────────────────────────┐
│ CAMADA 4 — EXPERIÊNCIA                                              │
│  Web de Gestão (Next.js)      Portal do Agricultor (PWA/Web)        │
│  App de Campo (Expo, offline)  PWA legado de transição              │
└───────────────▲──────────────────────────▲──────────────────────────┘
                │ BFF /api/v2  + packages/sdk (contratos tipados)      │
┌───────────────┴──────────────────────────┴──────────────────────────┐
│ CAMADA 3 — CONTRATOS                                                │
│  packages/contracts (Zod) · OpenAPI 3.1 · SDK · eventos versionados │
└───────────────▲──────────────────────────▲──────────────────────────┘
                │ comandos/consultas        │ eventos
┌───────────────┴──────────────────────────┴──────────────────────────┐
│ CAMADA 2 — DOMÍNIOS (8)                                             │
│  D1 Quadro Associativo   D2 Caderno de Campo   D3 Fiscalização      │
│  D4 Financeiro           D5 Produção Animal    D6 Agroindústria     │
│  D7 Suprimentos          D8 Governança                              │
│  Regra: nenhum JOIN/UPDATE em dados de outro domínio                │
└───────────────▲──────────────────────────▲──────────────────────────┘
                │ AssociadoRef / UnidadeProdutivaRef / ContextoUsuario │
┌───────────────┴──────────────────────────┴──────────────────────────┐
│ CAMADA 1 — NÚCLEO E PLATAFORMA                                      │
│  K1 Identidade & Acesso   K2 Território & Referências               │
│  K3 Plataforma & Auditoria  ·  L1 Read models  ·  Dados (1 banco)   │
└──────────────────────────────────────────────────────────────────────┘
```

**Implantação:** um único serviço de API (`apps/api`), um único banco, um único pipeline. As
fronteiras são **lógicas e verificadas automaticamente**, não processos separados.

### 4.2 Contratos do núcleo (o "dado comum" formalizado)

```ts
// packages/contracts/src/kernel.ts — fonte única para API, Web, Mobile e relatórios

export type Papel = 'admin' | 'gestor' | 'tecnico' | 'diretoria' | 'agricultor' | 'agroindustria';
export type Origem = 'staff' | 'associado';

export interface ContextoUsuario {
  usuarioId: string;                 // uuid (nunca o id sequencial) — estável entre módulos
  origem: Origem;
  associadoId: number | null;        // resolvido UMA vez, na autenticação
  papeis: Papel[];
  certificadoraId: number | null;    // escopo de tenant (fronteira de dados)
  associacoesVisiveis: number[];     // escopo multiassociação
  areasProducao: ('vegetal' | 'animal' | 'agroindustria')[];
  expiraEm: string;                  // ISO-8601
}

export interface AssociadoRef {
  id: number;
  uuid: string;
  nomeExibicao: string;              // "Nome Sobrenome" — nunca CPF em claro
  cpfMascarado: string | null;       // "***.***.***-12" (LGPD por padrão)
  status: 'pendente' | 'ativo' | 'inativo' | 'desligado';
  registroCnpo: string | null;
  associacaoPrincipalId: number | null;
  microrregiaoId: number | null;
  atualizadoEm: string;              // controle de cache/consistência
}

export interface UnidadeProdutivaRef {
  id: number;
  uuid: string;
  tipo: 'propriedade' | 'agroindustria' | 'ambiente_criacao';
  associadoId: number;
  rotulo: string;
  municipioId: number | null;
}

export const ROTAS_KERNEL = {
  associados: '/api/v2/kernel/associados',        // ?ids=1,2,3&campos=ref (lote)
  unidades: '/api/v2/kernel/unidades-produtivas',
  contexto: '/api/v2/auth/me',
} as const;
```

Regras de uso do contrato:
- **Lote obrigatório** em listagens (evita N+1): até 100 IDs por chamada.
- **CPF mascarado por padrão**; dado em claro só com permissão explícita e registro em auditoria.
- **`uuid` é a identidade pública**; o `id` sequencial nunca aparece em URL.
- **Contrato congelado por versão** (`kernel@1`); mudança incompatível exige `kernel@2` e plano de
  migração de consumidores.

### 4.3 Identidade e acesso (K1) — a decisão mais delicada

O legado tem **duas portas de entrada** (`usuarios` e `associados`) e o login atual valida as duas,
com **preferência para a conta de sistema** (`login.php:90-111`). Preservar essa precedência é
requisito de paridade — o login v2 atual diverge (ver 2.5). O plano elimina a ambiguidade em duas
etapas:

| Etapa | Comportamento | Fonte de escrita | Fonte de leitura |
|---|---|---|---|
| **Coexistência** (ondas 1–5) | `POST /auth/login` aceita e-mail ou CPF; valida `usuarios` e `associados` na **mesma precedência do PHP** (conta de sistema primeiro), bcrypt compatível; rehash para argon2id no primeiro login válido | legado (PHP) grava as senhas | K1 lê via **adaptador de credenciais** (interface única) |
| **Cutover de D1** | credenciais migradas para `auth_credenciais` (sujeito, e-mail/CPF normalizados, hash, status); legado deixa de ser fonte após redirect | K1 | K1 |
| **Desligamento** | colunas `senha/nivel/tipo/status` de `associados` são removidas após 90 dias sem acesso legado | K1 | K1 |

**Nunca dual-write.** Enquanto o PHP for dono da tabela, ele é o único escritor. O adaptador do K1 é
explícito e temporário (ADR-13), e sua remoção é um item do gate de desligamento.

Complementos:
- **Contexto no token**: papéis, escopo de associação, certificadora e áreas de produção. A "etapa 2"
  do login legado (órgão/especialidade/setor) vira contexto de sessão, não segundo formulário.
- **Sessão**: web com cookie `httpOnly`/`Secure`/`SameSite=Lax`; mobile com refresh em armazenamento
  seguro do sistema + PIN/biometria para reabrir o app.
- **Ponte legada**: `POST /api/v2/legacy/session-bridge` emite JWT curto a partir de `$_SESSION`
  para a PWA durante a transição; tem data de validade e é removida na Fase 9.
- **Escopo**: recurso fora do escopo devolve **404** (não 403), para não revelar existência.
- **Tenant**: guard obrigatório por certificadora e por associação; teste cruzado bloqueia merge.

### 4.4 As cinco leis + enforcement automatizado

1. Um módulo **nunca** faz `JOIN` em tabela de outro módulo.
2. Um módulo **nunca** faz `UPDATE`/`DELETE` em tabela de outro módulo.
3. Credenciais, papéis e sessão vivem no K1 — não em `associados`.
4. Dado comum é lido **por referência** (`*Ref`), nunca por coluna alheia.
5. Comunicação entre módulos é **interface pública ou evento**, nunca SQL.

| Enforcement | Onde | Falha o quê |
|---|---|---|
| `dependency-cruiser` com regra `no-cross-module` | CI | import entre pastas de módulos |
| ESLint `boundaries` | CI | import de `prisma` fora de `*.repository.ts` |
| Regra de schema Prisma por prefixo + revisão de `@map` | CI + code review | acesso indevido a tabela alheia |
| Teste de contrato (OpenAPI × Zod × SDK) | CI | rota sem contrato ou tipo divergente |
| Teste de autorização cruzada (matriz de papéis × rotas) | CI (bloqueante) | IDOR / tenant leak |
| Revisão humana com checklist de fronteira | PR | violação semântica que ferramenta não pega |

### 4.5 Comunicação: quando usar cada mecanismo

```
Comando que precisa de resposta imediata
  Controller → Service do módulo A → Porta pública do módulo B (in-process)
                                     └─ timeout 500 ms, fallback bloqueante

Efeito colateral que pode esperar
  Service do módulo A → evento no outbox (mesma transação) → worker
                                     └─ consumidor idempotente atualiza seu estado

Leitura gerencial
  L1 (job/consulta) → analytics.* (read model) → PDF/Excel → storage + link assinado

Navegação/experiência
  Web BFF / Mobile SDK → /api/v2 → módulos (composição, sem regra no cliente)
```

Detalhes não negociáveis:
- **Outbox transacional**: o evento é gravado na mesma transação do dado; um worker publica.
  Sem evento fantasma, sem dado sem evento.
- **Idempotência**: todo consumidor registra `eventoId`; reentrega não duplica.
- **Ordem**: por agregado (`agregadoId` como chave de partição); fora de ordem é aceito e
  reconciliado por `versao`/`atualizadoEm`.
- **Falha**: retry com backoff exponencial, DLQ visível, replay manual documentado.
- **Versionamento**: `tipo@versao`; adicionar campo é compatível, remover exige nova versão.

### 4.6 Catálogo inicial de eventos de domínio

| Evento | Publica | Consumidores principais | Efeito |
|---|---|---|---|
| `associado.criado` | D1 | K1 (cria credencial), D4 (plano), D8 (boas-vindas) | onboarding |
| `associado.aprovado` | D1 | K1 (libera acesso), D3 (pendência de 1ª visita), D8 | ativação |
| `associado.status_alterado` | D1 | K1 (suspende/revoga tokens), D4 (suspende cobrança) | ciclo de vida |
| `vinculo.alterado` | D1 | D4, L1 | financeiro/relatório |
| `unidade_produtiva.criada` | D2/D5/D6 | D3, D6, L1 | escopo de fiscalização |
| `atividade.registrada` | D2 | D3 (evidência), L1 | rastreabilidade |
| `lote.criado` / `lote.encerrado` | D2 | D6 (ordem de produção), L1 | cadeia produtiva |
| `insumo.utilizado` | D2 | D3 (conformidade), D6 (balanço), L1 | prova |
| `insumo.bloqueado` | D7 | D2 (alerta impeditivo), D3 | regulação |
| `visita.realizada` | D3 | D1 (histórico), L1 | conformidade |
| `nao_conformidade.aberta` / `resolvida` | D3 | D1, D8 (notificação), L1 | correção |
| `certificado.emitido` | D3 | D1, L1, portal | documento |
| `mensalidade.gerada` | D4 | D8 (cobrança), L1 | arrecadação |
| `pagamento.confirmado` / `estornado` | D4 | L1, D8 | caixa |
| `evento.publicado` / `presenca.confirmada` | D8 | L1, L2 | governança |
| `assembleia.encerrada` / `voto.registrado` | D8 | L1, portal | decisão |
| `animal.registrado` | D5 | L1, D3 | rebanho |
| `ordem_producao.emitida` | D6 | L1, D3 | agroindústria |
| `arquivo.enviado` | K3 | D2, D3, D6 | evidência |

### 4.7 Ownership de tabelas (regra: um dono, sem exceção)

| Dono | Tabelas |
|---|---|
| **K1** | `usuarios`, `auth_credenciais` (nova), `auth_refresh_tokens`, `password_resets` |
| **K2** | `estados`, `cidades`, `mesorregioes_rs`, `microrregioes_rs`, `cnpo`, `cnpo_atualizacoes` |
| **K3** | `audit_log`, `notificacoes`, `arquivos` (nova), `eventos_outbox`, `eventos_consumidos`, `feature_flags`, `sync_idempotencia` |
| **D1** | `associados`, `associacoes`, `associado_associacao`, `ficha_adesao`, `ficha_adesao_produtos`, `carteirinhas`, `entidades` |
| **D2** | `propriedades`, `propriedade_membros`, `lavouras`, `atividades_diarias`, `categorias_atividades`, `produtos`, `lotes_producao`, `lote_atividade`, `lote_insumo`, `ocorrencias_manejo`, `historico_conversao`, `culturas` |
| **D3** | `roteiros_inspecao`, `visitas_tecnicas`, `nao_conformidades`, `plano_manejo`, `plano_manejo_cultivos`, `certificacao_participativa`, `certificadoras` |
| **D4** | `planos_mensalidades`, `mensalidades`, `pagamentos`, `caixa`, `contas` |
| **D5** | `animais`, `animal_lotes`, `manejo_animal_registros`, `controle_*_animal`, `ambiente_criacao_animal`, `ambiente_criacao_membros`, `densidades_maximas_animais` |
| **D6** | `agroindustrias`, `agroindustria_*`, `agro_*`, `receitas_agroindustria`, `ingredientes_receita`, `ordens_producao`, `controle_higienizacao`, `rastreabilidade_insumos_processo` |
| **D7** | `insumos`, `insumos_organicos_aprovados`, `fornecedores`, `fornecedor_unidades` |
| **D8** | `assembleias`, `assembleia_pautas`, `assembleia_votos`, `eventos`, `evento_presenca`, `participacoes` |
| **L1** | `analytics.*` (read models) e `relatorio_jobs` — nenhuma tabela transacional |
| **A decidir (ADR na Fase 1)** | `caderno_campo`, `certificacao_produtos` — provável descarte após varredura de uso |

Regra de evolução do banco: **migração aditiva e reversível** enquanto o PHP escrever. Nunca
renomear/dropar coluna que o legado lê; campos novos entram `NULL` ou com default.

### 4.8 Por que monólito modular (e quando reconsiderar)

| Critério | Monólito modular (recomendado) | Microserviços |
|---|---|---|
| Consistência de dados | transação única | sagas/outbox em tudo |
| Custo operacional | 1 API + 1 banco | N serviços, N pipelines, malha |
| Equipe | 2–5 devs | 3+ squads |
| Velocidade inicial | alta | baixa |
| Fronteiras | verificadas no CI | impostas pela rede |

**Gatilhos objetivos para extrair um serviço** (nenhum ocorre hoje): um módulo com carga de CPU
ou pico 10× o resto; necessidade de escala independente; ciclo de deploy conflitante; limite de
time (2 pizzas por módulo); exigência regulatória de isolamento. Enquanto isso, o **outbox + ports**
mantêm a opção aberta a custo baixo.

---

## 5. Fichas dos módulos

Cada ficha define responsabilidade, limites, dados, contrato e critério de pronto. O detalhamento
de telas e regras de cálculo está em `docs/v3/02-CATALOGO-DE-MODULOS-E-TELAS.md` e no PRD.

### K1 — Identidade & Acesso

- **Responsabilidade:** autenticar (staff e associado), emitir/rotacionar tokens, resolver o
  `ContextoUsuario`, autorizar por papel + recurso + escopo, gerenciar credenciais e sessões.
- **Não faz:** cadastro do agricultor (D1), regra de negócio de outro domínio, renderização.
- **Contrato:** `/auth/*`; `GET /auth/me`; `GET /kernel/associados|unidades`; `session-bridge` (temporário).
- **Publica/consome:** publica `sessao.revogada`; consome `associado.aprovado`, `associado.status_alterado`.
- **Depende de:** D1 (apenas via adaptador de credenciais, temporário) e K3 (auditoria).
- **Canais:** web (cookie), mobile (Bearer + refresh seguro), PWA (bridge).
- **Riscos:** paridade de senha bcrypt; duplicidade de identidade; sequestro de sessão.
- **Pronto quando:** login legado funciona sem trocar senha; teste de tenant cruzado bloqueia merge;
  100% das rotas exigem authz declarada; reuso de refresh revoga a família; suspensão invalida tokens.

### K2 — Território & Referências

- **Responsabilidade:** estados, cidades, mesorregiões/microrregiões do RS, CNPO/PEAF, consulta
  pública e mapas; referências estáveis usadas por todos.
- **Não faz:** decidir escopo de acesso (isso é K1) nem gravar endereço do associado (D1).
- **Contrato:** `/territorios/*`, `/cnpo/*`, importação versionada das bases oficiais.
- **Depende de:** nada além de K3 (auditoria de importação).
- **Canais:** web (mapas, filtros), mobile (leitura offline do território do produtor).
- **Riscos:** base oficial muda; importação sem versionamento; coordenadas divergentes.
- **Pronto quando:** importação idempotente e versionada; consulta pública com rate limit;
  mapa acessível e testado em 360 px.

### K3 — Plataforma, Auditoria & Administração

- **Responsabilidade:** `audit_log` imutável, uploads seguros (MIME real, re-encode, fora do
  webroot), notificações/e-mail/push, outbox e DLQ, idempotência de sync, feature flags,
  administração de usuários e configurações.
- **Não faz:** regra de domínio; conteúdo de notificação é definido pelo módulo dono.
- **Contrato:** `/admin/*`, `/arquivos/*`, `/notificacoes/*`, `/feature-flags/*`; envelope de evento.
- **Canais:** admin web; API para todos.
- **Riscos:** virar "gaveta"; auditoria sem retenção; upload como vetor de RCE.
- **Pronto quando:** toda ação sensível auditada (quem/quando/antes/depois); upload passa por
  validação de conteúdo; replay de evento documentado; flags com histórico.

### D1 — Quadro Associativo & Adesão

- **Responsabilidade:** pré-cadastro público, cadastro, aprovação/reprovação, ficha de adesão,
  vínculo com associação, desligamento, carteirinha (PDF + validação pública), "meu perfil".
- **Não faz:** autenticar; cobrar; fiscalizar; calcular conformidade.
- **Dados:** `associados`, `associacoes`, `associado_associacao`, `ficha_adesao*`, `carteirinhas`.
- **Contrato:** `/associados`, `/associacoes`, `/pre-cadastro`, `/ficha-adesao`, `/carteirinhas`,
  `/publico/carteirinha/:token`; exporta `AssociadoRef`.
- **Publica:** `associado.criado|aprovado|status_alterado|desligado`, `vinculo.alterado`.
- **Depende de:** K1, K2, K3.
- **Canais:** web gestão (fila de pendentes, edição, massa), mobile agricultor (meu perfil, ficha,
  carteirinha).
- **Riscos:** CPF duplicado; aprovação sem auditoria; exclusão sem soft delete; ficha com assinatura.
- **Pronto quando:** máquina de estados testada; CPF validado e único; exclusão = soft delete +
  anonimização; carteirinha com token opaco invalidável; matriz de paridade 100% OK.

### D2 — Caderno de Campo (Produção Vegetal)

- **Responsabilidade:** propriedades, lavouras, atividades diárias, categorias, produtos, lotes,
  ocorrências, histórico de conversão, rastreabilidade por QR, exportações de campo.
- **Não faz:** validar insumo (consulta D7); decidir conformidade (D3); emitir rótulo (D6).
- **Dados:** 12 tabelas (ver 4.7).
- **Contrato:** `/propriedades`, `/lavouras`, `/atividades`, `/produtos`, `/lotes`, `/ocorrencias`,
  `/sync/atividades` (lote idempotente), `/publico/rastreabilidade/:codigo`.
- **Publica:** `unidade_produtiva.criada`, `atividade.registrada`, `lote.criado|encerrado`,
  `insumo.utilizado`.
- **Depende de:** D1 (referências), D7 (validação de insumo), K3 (arquivos/sync).
- **Canais:** **mobile offline-first** (registro em campo), web para técnico/gestor (filtros,
  exportação).
- **Riscos:** duplicação no sync; perda de foto; conflito offline; consulta lenta em listas.
- **Pronto quando:** registrar 10 atividades sem rede → sincronizar → exatamente 10 no servidor;
  500 itens na fila sem travar; QR resolve cadeia completa; retenção de 3 anos (sem DELETE físico).

### D3 — Fiscalização & Conformidade

- **Responsabilidade:** templates de roteiro versionados, agendamento e realização de visita,
  pontuação, não conformidades com prazo, plano de manejo, certificação participativa,
  certificadoras, emissão de certificado/declaração em PDF, score de conformidade.
- **Não faz:** cadastrar associado; registrar atividade de campo (lê referências de D2).
- **Dados:** 7 tabelas (ver 4.7).
- **Contrato:** `/roteiros`, `/visitas`, `/nao-conformidades`, `/plano-manejo`, `/certificacoes`,
  `/certificadoras`, `/publico/certificado/:token`.
- **Publica:** `visita.realizada`, `nao_conformidade.aberta|resolvida`, `certificado.emitido`.
- **Depende de:** D1, D2 (escopo/evidência), D7 (insumo permitido), K3 (PDF/fotos).
- **Canais:** mobile do técnico (visita com fotos/GPS/offline), web do gestor e da certificadora.
- **Riscos:** item crítico que deveria reprovar e não reprova; PDF divergente; certificado sem trilha.
- **Pronto quando:** checklist versionado com itens críticos bloqueantes; pontuação 100% coberta por
  teste; NC com máquina de estados; certificado assinado e auditável.

### D4 — Financeiro Associativo

- **Responsabilidade:** planos de mensalidade, geração em lote idempotente, cobrança PIX (3
  provedores), baixa manual, estorno transacional, caixa, contas a pagar/receber, inadimplência.
- **Não faz:** decidir status do associado (reage a evento de D1).
- **Dados:** 5 tabelas.
- **Contrato:** `/planos`, `/mensalidades`, `/pagamentos`, `/caixa`, `/contas`, webhooks
  `/webhooks/pix/:provedor` (assinatura + idempotência).
- **Publica:** `mensalidade.gerada`, `pagamento.confirmado|estornado`.
- **Depende de:** D1 (quem cobrar), K3 (jobs/notificações), provedores externos.
- **Canais:** web gestão (tesouraria); mobile associado (2ª via, PIX, comprovante).
- **Riscos:** webhook duplicado/fora de ordem; estorno inconsistente; dinheiro em float.
- **Pronto quando:** gerar 2× não duplica; webhook repetido processa 1×; estorno reverte caixa e
  pagamento na mesma transação; valores em centavos (inteiro).

### D5 — Produção Animal

- **Responsabilidade:** animais, lotes, ambientes, alimentação, sanidade, reprodução,
  rastreabilidade animal, densidades.
- **Não faz:** caderneta vegetal; rotulagem (D6).
- **Contrato:** `/animais`, `/animal/lotes`, `/animal/ambientes`, `/animal/manejos`.
- **Publica:** `animal.registrado`, `manejo.registrado`.
- **Depende de:** D1, K3.
- **Canais:** mobile (registro no curral), web (painel zootécnico).
- **Pronto quando:** histórico por animal/lote completo, exportável; regra de densidade validada.

### D6 — Agroindústria & Processamento

- **Responsabilidade:** unidades agroindustriais, setores, atividades, receitas e ingredientes,
  ordens de produção, higienização, balanço de massas, rastreabilidade, autorização de rótulo.
- **Não faz:** produzir matéria-prima (consome lote de D2).
- **Contrato:** `/agroindustrias`, `/receitas`, `/ordens-producao`, `/higienizacao`, `/rotulos`.
- **Publica:** `ordem_producao.emitida`, `rotulo.autorizado`.
- **Depende de:** D1, D2 (lote), D7 (insumo), K3.
- **Canais:** web (produção e rótulo), mobile (higienização e ordens).
- **Pronto quando:** % orgânico calculado e testado; balanço de massas alerta saída > entrada;
  <70% orgânico bloqueia menção ao termo.

### D7 — Suprimentos & Insumos

- **Responsabilidade:** catálogo de insumos, insumos orgânicos aprovados (Portaria 52/2021),
  fornecedores e unidades, validação de uso.
- **Não faz:** registrar a aplicação (D2/D5) nem decidir conformidade (D3).
- **Contrato:** `/insumos`, `/insumos-aprovados`, `/fornecedores`, `GET /insumos/:id/permitido`.
- **Publica:** `insumo.bloqueado`, `catalogo.atualizado`.
- **Depende de:** K2 (referências), D1 (fornecedor vinculado).
- **Canais:** web (catálogo/importação), mobile (consulta rápida com cache offline).
- **Pronto quando:** validação bloqueante conforme norma; catálogo versionado e importável;
  consulta funciona offline no app (cache de catálogo).

### D8 — Governança & Comunicação

- **Responsabilidade:** assembleias (pautas, votação, encerramento, resultado imutável), eventos e
  presença, notificações/lembretes, e-mail e WhatsApp.
- **Não faz:** decidir elegibilidade (consulta D1); renderizar tela de outro módulo.
- **Contrato:** `/assembleias`, `/eventos`, `/presencas`, `/notificacoes`.
- **Publica:** `assembleia.publicada|encerrada`, `voto.registrado`, `evento.publicado`,
  `presenca.confirmada`.
- **Depende de:** D1, K3.
- **Canais:** web diretoria (convocar/apurar), mobile associado (votar, confirmar presença).
- **Pronto quando:** 1 voto por associado; resultado imutável após encerramento; lembrete com
  antecedência configurável; trilha de auditoria.

### L1 — Relatórios & Analytics

- **Responsabilidade:** relatórios administrativos, de gestor e de diretoria; PDF/Excel; modelos de
  leitura; catálogo de relatórios com versão e paridade visual.
- **Não faz:** escrita transacional; regra de domínio; composição de tela.
- **Dados:** `analytics.*` + `relatorio_jobs` (jobs assíncronos, links assinados).
- **Contrato:** `/relatorios` (catálogo), `POST /relatorios/:id/execucoes`, `GET /relatorios/execucoes/:id`,
  download com expiração.
- **Depende de:** eventos/ETL de todos os domínios.
- **Canais:** web (gestão/diretoria). Mobile: apenas indicadores resumidos via L2.
- **Riscos:** divergência de número; relatório lento travando o sistema; PDF ilegível no campo.
- **Pronto quando:** 100% dos relatórios inventariados com diff visual aprovado; geração assíncrona;
  nenhuma consulta a tabela transacional; link expira.

### L2 — Portal, Dashboards & BFF Multicanal

- **Responsabilidade:** compor a experiência por perfil e canal; dashboards (admin, gestor,
  diretoria, agricultor); BFF web; deep links; onboarding; estados de erro/vazio/offline.
- **Não faz:** regra de negócio; acesso direto a banco; cache de dado sensível sem política.
- **Contrato:** `/bff/*` (web) e uso direto de `/api/v2` (mobile) via SDK.
- **Depende de:** todos os módulos (somente leitura/composição).
- **Canais:** web e mobile.
- **Pronto quando:** cada perfil vê apenas o que pode; layout 360/768/1280 sem scroll horizontal;
  WCAG AA nos fluxos principais; tempo de carga dentro do orçamento.

---

## 6. Estratégia multicanal (browser + mobile)

### 6.1 Princípio

> **A regra de negócio é uma só; a experiência é que muda.**

- API única versionada (`/api/v2`) com OpenAPI 3.1.
- `packages/contracts` (Zod) como fonte de verdade dos tipos; `packages/sdk` gerado/consumido por
  web e mobile. Nenhum cliente redefine tipo.
- Autorização sempre no servidor; a UI apenas esconde o que o usuário não pode (defesa em
  profundidade, nunca a única defesa).

### 6.2 Web (gestão e portal)

| Item | Decisão |
|---|---|
| Stack | Next.js 16 (App Router) + Tailwind/shadcn, conforme `docs/v3/05` |
| Autenticação | BFF com cookies `httpOnly`; refresh no servidor; CSRF por double-submit |
| Renderização | Server Components para leitura densa; Client para formulários |
| Dados | BFF agrega `/api/v2`; sem acesso direto ao banco |
| Perfis | admin, gestor, diretoria, técnico, agricultor (portal), agroindústria |
| Acessibilidade | WCAG AA, navegação por teclado, foco visível, contraste, 44×44 px |
| Responsivo | 360 px, 768 px, 1280 px, 1440 px; tabelas viram cartões no mobile |

### 6.3 Mobile de campo (app nativo/Expo)

| Item | Decisão |
|---|---|
| Stack | Expo SDK 57 (React Native) + TypeScript |
| Offline | SQLite local + outbox + sync idempotente (`Idempotency-Key`) |
| Captura | câmera (re-encode e EXIF), GPS, QR Code, assinatura na tela |
| Sessão | refresh em SecureStore + PIN/biometria; expiração e logout remoto |
| Push | Expo Push para lembretes, aprovação, cobrança e NC |
| Atualização | EAS Update com canal de rollout; versão mínima suportada no servidor |
| Dispositivos-alvo | Android 9+ (inclui aparelhos de entrada), iPhone iOS 15+ |
| Tamanho | instalação < 60 MB; abertura < 3 s em aparelho de entrada |
| Offline por módulo | D2, D3 (visita), D5, D6 (higienização); D7 consulta em cache |
| Não offline | D4 pagamento (exige rede), D8 votação (exige rede), D1 aprovação (staff) |

**Fluxo de sincronização (obrigatório e testado):**

```
[app] cria registro local (UUID do cliente) → fila outbox (estado=pending)
   │
   ├─ POST /sync/atividades  (Idempotency-Key = UUID do cliente)
   │      servidor: grava + outbox de domínio + registra idempotência
   │
   ├─ 200 ⇒ marca synced, remove da fila, atualiza dado canônico
   ├─ 409 conflito ⇒ marca conflito, mostra ao usuário, aplica regra documentada
   ├─ 401 ⇒ renova token e retenta (1 vez)
   └─ 5xx/timeout ⇒ backoff exponencial (máx. 5 min), retoma na reconexão
```

Regras de conflito (documentadas e testadas):
- **Atividade de campo**: cliente vence se o registro ainda não existe no servidor; se existe,
  compara `atualizadoEm` e preserva o mais recente, guardando o descartado em auditoria.
- **Visita técnica**: servidor vence (é evidência fiscal); app mostra divergência e permite anexar
  justificativa.
- **Nunca descarte silencioso**: todo conflito vira registro visível.

### 6.4 PWA de transição (legado) e pós-cutover

- Durante a migração, a PWA atual (`manifest.json`, `sw.js`, `db_sync.js`) continua sendo o canal
  de campo para quem já usa — sem investimento novo além de correções.
- Após o cutover, a web nova é responsiva e instalável (PWA) como **canal secundário** para quem
  não pode/quer instalar app; o app nativo é o canal primário de campo (offline real).
- Cache do service worker tem política de invalidação por versão e botão "atualizar agora" — evita
  dado velho no campo.

### 6.5 Matriz de canais por módulo

| Módulo | Web gestão | Web agricultor | Mobile campo | Offline |
|---|:---:|:---:|:---:|:---:|
| K1 Identidade | ● | ● | ● | parcial (sessão) |
| K2 Território | ● | ● | ● | leitura do próprio território |
| K3 Plataforma | ● (admin) | — | ● (sync/notificações) | fila |
| D1 Quadro Associativo | ● | ● | ● (perfil, ficha, carteirinha) | não |
| D2 Caderno de Campo | ● (técnico) | ● | ● | **sim (completo)** |
| D3 Fiscalização | ● | consulta | ● (visita) | **sim (visita)** |
| D4 Financeiro | ● | ● (2ª via/PIX) | ● (consulta/PIX) | não |
| D5 Produção Animal | ● | ● | ● | **sim** |
| D6 Agroindústria | ● | ● | ● (higienização) | parcial |
| D7 Suprimentos | ● | consulta | consulta | cache de catálogo |
| D8 Governança | ● | ● (voto/presença) | ● | não |
| L1 Relatórios | ● | — | resumo | não |
| L2 Portal/BFF | ● | ● | ● | n/a |

### 6.6 Autenticação por canal

| Canal | Access token | Refresh | Armazenamento | Logout remoto |
|---|---|---|---|---|
| Web (BFF) | cookie `httpOnly`, 15 min | cookie `httpOnly`, 30 dias, rotativo | servidor | sim (revoga família) |
| Mobile | Bearer, 15 min | SecureStore, 30 dias, rotativo | dispositivo | sim |
| PWA legado | bridge JWT curto | ponte temporária | memória/sessão | ao desligar o PHP |

### 6.7 Usabilidade no contexto rural (requisito, não estética)

- **Sol e luva:** contraste alto, alvos grandes, botões primários no polegar.
- **Baixa escolaridade:** linguagem direta, ícone + rótulo, confirmação antes de ação destrutiva.
- **Conectividade ruim:** toda tela de campo tem estado *offline*, *sincronizando*, *sincronizado*
  e *erro com ação*; nunca "tela branca".
- **Aparelho fraco:** evitar biblioteca pesada, listas virtualizadas, imagens comprimidas.
- **Confiança:** mostrar claramente quando o dado ainda não subiu ("3 registros pendentes").

### 6.8 Orçamentos de qualidade

| Métrica | Alvo |
|---|---|
| LCP p75 (web, 4G) | < 2,5 s |
| INP p75 | < 200 ms |
| Abertura do app (aparelho de entrada) | < 3 s |
| Sincronização de 10 registros | < 5 s em 3G |
| Taxa de sync com sucesso | > 99% |
| Conflitos não resolvidos | 0 |
| Acessibilidade | WCAG 2.1 AA nos 10 fluxos principais |
| Cobertura de testes do núcleo | ≥ 80% linhas / 70% branches |

---

## 7. Plano de execução (não executado)

### 7.1 Estratégia

**Estrangulamento (strangler fig) + monólito modular + duas trilhas em paralelo.**

- **Trilha A — Blindagem do legado:** torna o sistema atual seguro e congelado o suficiente para
  coexistir. Não é opcional e não espera decisão de arquitetura.
- **Trilha B — Plataforma modular:** constrói o alvo por ondas, rota a rota, com paridade comprovada
  e desligamento incremental do PHP.

Por que não big-bang: já houve uma tentativa anterior (Laravel) e as tabelas órfãs ainda estão no
banco `sisagro`. Um segundo big-bang tem alta probabilidade de virar escombro.

### 7.2 Trilha A — Blindagem e congelamento (0–8 semanas)

| # | Tarefa | Entrega | Critério de aceite |
|---|---|---|---|
| A1 | Versionamento e higiene | commit de tudo, `sinporg/` versionado, `.gitignore` revisado | `git status` limpo; clone limpo sobe (ver Anexo F) |
| A2 | Backup e restauração testada | dump verificado + restauração em staging | restauração executada com sucesso e registrada |
| A3 | Rotação de segredos | chaves/e-mail/DB fora do código e do histórico | `gitleaks` limpo; app sobe por env |
| A4 | Remover superfície perigosa | 23 scripts da raiz + 36 em `dev-utils/` fora do webroot ou bloqueados por CLI | varredura HTTP não encontra script executável |
| A5 | Guarda central de API | 100% dos endpoints com authz; LFI corrigido (usar `rota_segura.php`) | teste automatizado de rotas anônimas = 401; LFI testado |
| A6 | CSRF e uploads | cobertura CSRF nos POSTs; upload validado por conteúdo | checklist de segurança executado |
| A7 | Escopo de tenant no legado | filtro por certificadora nas consultas críticas | teste de leitura cruzada falha |
| A8 | Observabilidade mínima | log estruturado, alerta de 5xx, healthcheck | painel + alerta ativos |
| A9 | Congelamento de features | janela de 8 semanas sem feature nova no legado | changelog congelado e comunicado |

> **Regra:** a Trilha B só começa a Onda 1 quando A1–A5 estão verdes. As demais podem correr em paralelo.

### 7.3 Trilha B — Fases e ondas

| Fase | Objetivo | Entregáveis principais | Gate | Esforço | Depende |
|---|---|---|---|---|---|
| **F0** | Aprovar e preparar | decisões da seção 11, ADRs iniciais, staging, papéis | G0 | 2 sem | — |
| **F1** | Fundação e Kernel | contratos `kernel@1`, JWT + guards + tenant, outbox, auditoria, CI com fronteiras, deploy 1 comando | G1, G2 | 6–8 sem | F0, Trilha A |
| **F2** | Núcleo do agricultor | D1 (associados, ficha, carteirinha, aprovação) + K2 (território/CNPO) + portal inicial | G3 | 8–10 sem | F1 |
| **F3** | Campo | D2 (caderno completo) + D7 (insumos) + **app offline beta** com sync | G3, G7 | 12–14 sem | F2 |
| **F4** | Fiscalização | D3 (roteiros, visitas, NC, plano, certificação) | G3 | 8–10 sem | F3 |
| **F5** | Financeiro | D4 (planos, mensalidades, PIX, caixa, contas) | G3 | 6–8 sem | F2 |
| **F6** | Produção | D5 (animal) + D6 (agroindústria) em paralelo | G3 | 10–12 sem | F3 |
| **F7** | Governança | D8 (assembleias, eventos, comunicação) | G3 | 5–6 sem | F2 |
| **F8** | Leitura e experiência | L1 (relatórios em paridade) + L2 (dashboards e BFF) | G3, G8 | 10–12 sem | F2–F7 |
| **F9** | Mobile GA e desligamento | app nas lojas, redirects 301, remoção de pontes, **0 PHP** | G9, G10 | 8–10 sem | F3–F8 |

**Paralelização segura:** no máximo 2 frentes de domínio ativas ao mesmo tempo, sem tabela
compartilhada; cada frente em sua branch; o núcleo (K1/K3) só muda com janela e regressão completa.
F5 e F7 podem rodar em paralelo a F4/F6 com times separados.

### 7.4 Caminho crítico e cronograma

```
Mês:        1   2   3   4   5   6   7   8   9  10  11  12  13  14  15  16
Trilha A:  ████████
F1 Kernel:     ████████
F2 Núcleo:             ██████████
F3 Campo:                        ██████████████
F4 Fiscal:                                   ██████████
F5 Financ:                     ████████
F6 Produção:                                 ████████████
F7 Govern.:                    ██████
F8 Leitura:            ██████████████████████████████
F9 Mobile:                                       ██████████████
```

**Caminho crítico:** A1–A5 → F1 (Kernel) → F2 (D1) → F3 (D2) → F9 (desligamento).
Qualquer atraso nessas fases empurra o fim. F4–F8 podem absorver atraso com mais paralelismo,
desde que o núcleo esteja estável.

**Faixas de prazo:**
- 4–5 pessoas experientes + agentes com revisão: **12–16 meses**.
- 2–3 pessoas: **18–24 meses**.
- **MVP "Campo + Cadastro"** (K1, K2, D1, D2, D7, L2 básico, app offline; relatórios e financeiro
  ficam no legado): **6–8 meses** — recomendo este recorte para valor cedo.

### 7.5 Gates (cada um tem evidência executável)

| Gate | Nome | Evidência que libera |
|---|---|---|
| **G0** | Decisão e baseline | decisões assinadas; backup restaurado; git limpo; segredos rotacionados |
| **G1** | Fundação | `pnpm build/test/lint` verdes; deploy 1 comando; `/health`; OpenAPI gerado |
| **G2** | Kernel | login legado sem trocar senha; teste de tenant cruzado falha o build; 100% rotas com authz declarada; auditoria gravando |
| **G3** | Módulo pronto | matriz de paridade 100% OK; matriz de autorização verde; cobertura ≥80%; 0 violação de fronteira; runbook |
| **G4** | Contrato de API | OpenAPI sem rota não documentada; SDK gera sem erro; contrato versionado |
| **G5** | Migração de dados | migração aditiva testada sobre cópia do dump; reversível; reconciliação de contagem |
| **G6** | Paridade web | E2E de todos os fluxos do módulo verdes em 360/768/1280 px |
| **G7** | Offline mobile | 10 registros offline → 10 no servidor; 500 na fila; conflito visível; rede caindo no meio |
| **G8** | Paridade de relatórios | diff visual aprovado por relatório do inventário |
| **G9** | Cutover | flag 100%; 301 ativo; rollback executado em staging; suporte avisado |
| **G10** | Desligamento | 0 PHP em produção; pontes removidas; `session_start` só no histórico |

### 7.6 Artefatos obrigatórios por módulo (DoR → DoD)

**Antes de começar (Definition of Ready):**
1. Tabelas com ownership definido.
2. Contrato do Kernel congelado na versão usada.
3. Matriz de paridade em branco com todas as ações do legado e `arquivo:linha`.
4. Rotas-alvo no OpenAPI.
5. Dados anonimizados em staging.
6. Fiscal designado + especialista de domínio do SINPORG com horas alocadas.

**Para terminar (Definition of Done):** os seis blocos do gate G3 — funcional, segurança,
arquitetura, qualidade, operação e regulatório (checklist completo em
`docs/v3/09-CRITERIOS-DE-ACEITE-E-QUALIDADE.md`).

### 7.7 Dados: migração, cutover e rollback

| Tema | Decisão |
|---|---|
| Fonte de escrita | **uma por tabela em cada momento** (nunca dual-write) |
| Migrações | aditivas e retrocompatíveis enquanto o PHP escreve; `prisma migrate` como fonte de verdade |
| Identidade pública | adicionar `uuid` às entidades-núcleo, com backfill idempotente |
| Backfill | scripts idempotentes, executados em staging sobre cópia do dump, com contagem de reconciliação |
| Cutover | por rota/módulo, atrás de feature flag; 5% → 50% → 100% |
| Conviver | PHP serve o que não migrou; proxy por prefixo (`/api/v2`, `/novo`) |
| Redirect | URL antiga devolve 301 por 30 dias após o cutover |
| Rollback | desligar flag e voltar ao PHP; imagens tagueadas por SHA; banco **nunca** é revertido com perda |
| Retenção | 3 anos para diário de campo, insumos e balanço de massas; soft delete + anonimização |
| Exclusão | pedido LGPD → anonimização com preservação de trilha fiscal |

### 7.8 Segurança no plano (resumo executável)

| Frente | Medida | Gate |
|---|---|---|
| Autenticação | JWT curto + refresh rotativo; bcrypt→argon2id no login; rate limit | G2 |
| Autorização | papel + recurso + escopo; 404 fora do escopo; tenant guard testado | G2 |
| Entrada | Zod em 100% dos endpoints; whitelist de campos | G3 |
| Upload | MIME real + re-encode + fora do webroot + nome aleatório | G3 |
| Webhooks | assinatura + idempotência | G3 |
| Segredos | fora do git, rotação, `env.schema` falha rápido | G0 |
| Dependências | `pnpm audit`, Dependabot, lockfile | G1 |
| LGPD | minimização, mascaramento de CPF, retenção, DPIA, encarregado | G9 |
| Auditoria | `audit_log` imutável com antes/depois | G2 |
| Testes | matriz de autorização + IDOR + tenant cruzado no CI | G3 |

### 7.9 Observabilidade e SLOs

- OpenTelemetry (traces) + Pino (JSON) + Prometheus/Grafana + Sentry.
- `X-Request-Id` do cliente ao banco; log sem PII.
- SLOs: disponibilidade 99,9%; p95 leitura < 500 ms; p95 escrita < 1,5 s; 5xx < 0,1%.
- Alertas: 5xx, p95, fila de sync, DLQ, falha de job de relatório, backup atrasado.

### 7.10 Equipe, papéis e esforço

| Papel | Dedicação | Responsabilidade |
|---|---|---|
| Tech lead / arquiteto | 1 | fronteiras, contratos, ADRs, revisão final |
| Dev backend/fullstack sênior | 2 | Kernel + domínios |
| Dev frontend | 1 | web, design system, acessibilidade |
| Dev mobile | 1 | Expo, offline, lojas |
| QA automação | 1 | E2E, contratos, autorização, sync |
| DevOps/segurança | 0,5 | CI/CD, infra, secrets, DAST |
| Especialista de domínio (SINPORG) | 0,5 | paridade de regra, validação de aceite |
| Fiscal por módulo (par) | rotativo | auditoria independente com evidência |
| Agentes de codificação | apoio | inventário, testes, migração — **sempre com revisão humana** |

**Esforço estimado:** 60–90 pessoas-mês (inclui paridade, testes, mobile e desligamento).
**Infra:** nuvem BR com banco gerenciado e backup PIT — ordem de grandeza R$ 300–1.500/mês;
lojas: Apple US$ 99/ano + Google US$ 25 único; e-mail/push com camada gratuita inicial.

### 7.11 Como os agentes entram sem virar risco

- Cada módulo tem **construtor e fiscal independentes** (protocolo descrito em `docs/v3/08`).
- Agente **não aprova o próprio trabalho**; o fiscal reproduz a evidência.
- Regras anti-cola: fiscal não pode usar o mesmo contexto do construtor; após 3 aprovações sem
  achado, troca-se o fiscal.
- Toda alteração gerada por agente passa por CI e revisão humana antes do merge.
- Limite: no máximo 2 frentes ativas para não gerar conflito de arquivos (usar worktrees separadas).

---

## 8. Riscos e mitigações

| # | Risco | Prob. | Impacto | Mitigação | Sinal de alerta |
|---|---|---|---|---|---|
| R1 | Repetir o big-bang (já houve tentativa Laravel) | Alta | Fatal | ondas verticais obrigatórias; nenhuma onda avança incompleta | módulo entregue sem paridade |
| R2 | Regressão funcional sem testes no legado (inclui a divergência de precedência do login v2 vs. `login.php`) | Alta | Alto | matriz de paridade + fiscal + E2E; corrigir o login v2 antes do G2 | item PENDENTE no gate |
| R3 | Perder trabalho por falta de commit (250 pendências; `sinporg/` não versionado) | **Alta** | **Alto** | A1 na semana 1; commits pequenos; branches | `git status` crescendo |
| R4 | Vazamento de dados na coexistência (sem tenant no legado) | Alta | Alto | Trilha A (A5–A7); API só depois | consulta cruzada aprovada |
| R5 | Duplicação no sync offline | Média | Alto | idempotência + testes de rede intermitente | contagem divergente no staging |
| R6 | Divergência de relatórios (percepção de erro) | Média | Alto | diff visual + snapshot + PHP de transição | número diferente do legado |
| R7 | Equipe pequena + curva de TypeScript | Alta | Alto | tech lead dedicado; treinamento antes de F1; pareamento | PRs grandes/atrasados |
| R8 | Dois sistemas por tempo demais | Média | Alto | prazo por onda + critério de desligamento + custo visível | nenhuma rota desligada há 2 meses |
| R9 | Conflito de arquivos por trabalho paralelo/agentes | Média | Médio | máx. 2 frentes; worktree por frente; escopo de escrita por tarefa | merge conflicts recorrentes |
| R10 | Bloqueio por MariaDB EOL / versão Prisma | Média | Médio | upgrade na Fase 0; versão fixada; staging | avisos do Prisma |
| R11 | Migração quebrar o PHP | Alta | Alto | migrações aditivas; nunca renomear/dropar | erro 500 no legado após migrate |
| R12 | Adoção baixa no campo | Média | Alto | piloto com 1 associação; treinamento; offline real | % de uso semanal |
| R13 | Revisão de loja (Apple/Google) atrasar | Média | Médio | builds cedo; política de privacidade; conta do cliente pronta | rejeição repetida |
| R14 | Fadiga de fiscalização | Média | Alto | rotação; evidência reproduzível; 3 rodadas → escalar | aprovações em série sem achado |
| R15 | Mudança regulatória (Portaria 52/2021, CNPO) | Baixa | Médio | catálogo versionado; regra isolada em D7/D3 | nova instrução normativa |
| R16 | Custo de coexistência acima do previsto | Média | Médio | MVP por valor; cortar escopo, não qualidade | orçamento estourado 2 meses |

**Pontos de falha do próprio plano (honestidade):**
1. O prazo depende de disciplina de escopo; se o SINPORG adicionar requisitos por onda, o plano
   escorrega — a mitigação é a janela de congelamento e o comitê de mudança.
2. A paridade de relatórios é a parte mais subestimada; por isso ela tem fase própria e diff visual.
3. O app offline só é confiável se a fila for testada em rede ruim de verdade, não só em laboratório.
4. A qualidade dos agentes depende da qualidade dos prompts e da revisão; sem fiscal humano, vira
   velocidade sem direção.

---

## 9. Critérios de sucesso e Definição de Pronto

### 9.1 Definição de Pronto do programa

| # | Critério | Verificação |
|---|---|---|
| DP1 | 0 arquivos PHP em produção | varredura no deploy |
| DP2 | 13 unidades com parecer de gate aprovado | 13 × evidência G3 |
| DP3 | Web cobre todas as rotas do catálogo | E2E autenticado 200 |
| DP4 | App publicado nas duas lojas | builds assinados |
| DP5 | Offline real no campo | teste de 10 registros sem rede |
| DP6 | Relatórios em paridade | diff visual aprovado |
| DP7 | OpenAPI sem rota não documentada | gate G4 |
| DP8 | Auth 100% JWT | `session_start` só no histórico |
| DP9 | 0 segredos no repositório | `gitleaks` limpo |
| DP10 | Prisma Migrate é a única fonte | `migrate status` up to date |
| DP11 | Deploy e rollback em 1 comando | runbook executado por terceiro |
| DP12 | Observabilidade ativa | painel + alerta |
| DP13 | Backup com restauração testada | 4 semanas verdes |
| DP14 | Retenção de 3 anos | nenhum DELETE físico indevido |

### 9.2 Métricas de sucesso do negócio

| Métrica | Linha de base | Meta em 12 meses pós-GA |
|---|---|---|
| Agricultores com registro de atividade no mês | a medir no piloto | ≥ 60% dos ativos |
| Tempo para registrar uma atividade | a medir | < 90 s |
| Sync com sucesso | — | > 99% |
| Adesões aprovadas sem retrabalho | a medir | > 80% |
| Relatórios gerados sem suporte manual | a medir | > 90% |
| Chamados de suporte por usuário/mês | a medir | queda de 30% |
| Custo de infraestrutura/mês | atual (XAMPP) | dentro de R$ 1.500 |

---

## 10. O que eu não faria (e por quê)

1. **Microserviços agora** — 2–5 devs, 1.505 registros e um banco: complexidade sem retorno.
2. **Reescrever do zero em paralelo** — 82 mil linhas de regra não documentada; alto risco de
   regressão e de nunca terminar (evidência: tentativa Laravel anterior).
3. **Mover a tabela `associados` inteira para o kernel no início** — 52 colunas, 34 consumidores,
   migração de dados e de login ao mesmo tempo. O contrato resolve antes; o dado migra depois.
4. **Dividir por pasta/tela** — reproduz o acoplamento atual com outro nome.
5. **Expor API antes de corrigir LFI, CSRF e authz** — transformaria uma dívida em incidente.
6. **Investir mais na PWA legada como estratégia** — ela é ponte, não destino; o offline real exige
   app nativo.
7. **Confiar em "vai dar certo" sem gate** — cada fase tem evidência; sem evidência, não avança.
8. **Comprar um ERP genérico** — as regras de certificação orgânica, CNPO e balanço de massas são
   específicas; a adaptação custaria mais que o plano.

---

## 11. Decisões necessárias e próximos passos

### 11.1 Decisões que dependem de você

| # | Decisão | Opções | Minha recomendação |
|---|---|---|---|
| D-01 | Estratégia | (a) monólito modular + strangler (b) reescrita big-bang (c) só PWA | **(a)** |
| D-02 | Congelamento do legado | aceitar 8 semanas de feature freeze / não aceitar | **aceitar** — sem isso, R3 e R11 disparam |
| D-03 | Prioridade do MVP | Campo+Cadastro / Financeiro / só relatórios | **Campo+Cadastro** (dor real + mobile) |
| D-04 | Multi-tenant por certificadora | requisito duro / opcional | **requisito duro** (LGPD e contrato) |
| D-05 | Infraestrutura | nuvem BR / on-premise do SINPORG | **nuvem BR** com backup gerenciado |
| D-06 | Equipe e prazo | 2–3 devs (18–24 m) / 4–5 devs (12–16 m) / MVP 6–8 m | **MVP 6–8 m + 4–5 devs** |
| D-07 | Lojas de app | contas do SINPORG / conta de terceiro | **contas do SINPORG** |
| D-08 | Validação de paridade | especialista de domínio com horas / sem especialista | **com especialista** (senão o gate vira carimbo) |
| D-09 | Uso de agentes | com revisão humana obrigatória / sem revisão | **com revisão** |
| D-10 | Política de dados sensíveis | mascarar CPF por padrão / exibir | **mascarar por padrão** com auditoria |

### 11.2 Se aprovado, os primeiros 10 dias (a serem executados depois)

1. Reunião de decisão (D-01 a D-10) e registro em ADRs.
2. Commit integral do estado atual e versionamento do `sinporg/` (A1).
3. Backup + teste de restauração em staging (A2).
4. Rotação de segredos e remoção de scripts expostos (A3, A4).
5. Correção do LFI e guarda central de API no legado (A5).
6. Congelamento de features comunicado ao SINPORG (A9).
7. Designação do especialista de domínio e dos fiscais.
8. Geração do inventário de paridade de D1 e D2 (telas, campos, ações, `arquivo:linha`).
9. Validação do contrato `kernel@1` com os times de web e mobile.
10. Preparação do staging com dados anonimizados.

### 11.3 Perguntas que preciso responder com o SINPORG

1. Quantos associados ativos e quantos usam o app hoje? Qual o perfil do dispositivo?
2. O processo de adesão/ficha tem exigência jurídica de assinatura física ou digital?
3. Quem pode aprovar associado e como é a segregação de funções na prática?
4. As certificadoras são externas? Qual o contrato de dados com elas (tenant)?
5. Quais relatórios são obrigatórios por normativa e com qual periodicidade?
6. Qual o prazo legal/contratual para retenção e para atender um pedido LGPD?
7. Existe verba e janela para publicação nas lojas? Quem assina a conta?
8. Quem é a pessoa que pode dizer "isto está igual ao legado"?

---

## Anexo A — Mapa do legado para os módulos

| Origem no legado | Destino |
|---|---|
| `modulos/associados/`, `modulos/associacoes/`, `includes/ficha_adesao_modal.php`, `carteirinha_publica.php` | D1 |
| `modulos/caderno_campo/` (65 arquivos), `rastreabilidade.php` | D2 |
| `modulos/roteiros_inspecao/`, `modulos/certificacao/`, `modulos/conformidade/`, `certificadoras_*.php`, `api/certificacao/` | D3 |
| `modulos/mensalidades/`, `modulos/caixa/`, `modulos/contas/`, `modulos/relatorios_diretoria/`, `api/pix/`, `classes/Mensalidade.php`, `Pagamento.php`, `Caixa.php` | D4 |
| `modulos/producao_animal/`, `modulos/manejo_animal/` | D5 |
| `modulos/agroindustria/` | D6 |
| `modulos/fornecedor/`, `modulos/caderno_campo/insumos/`, `api/insumos_aprovados.php`, `sql/seed_insumos_*` | D7 |
| `modulos/assembleia/`, `modulos/eventos/`, `modulos/comunicacao/`, `includes/lembretes.php`, `includes/widget_eventos.php` | D8 |
| `relatorios.php`, `relatorios/`, `api/relatorios/`, `classes/ReportManager.php`, `ReportConformidade.php`, `VisitaReport.php`, `Relatorio.php` | L1 |
| `modulos/dashboard/`, `modulos/portal/`, `includes/menu.php`, `header.php`, `footer.php`, `perfil.php` | L2 |
| `login.php`, `classes/User.php`, `PasswordReset.php`, `CSRFProtection.php`, `ApiAuth.php`, `api/legacy/` | K1 |
| `api/get_cidades.php`, `api/regionalizacao/`, `api/cnpo_consulta.php`, `modulos/regionalizacao/`, `consulta_cnpo/`, mapas | K2 |
| `classes/AuditLog.php`, `Mailer.php`, `ImageUploader.php`, `Lembrete.php`, `api/uploads/`, `dev-utils/` | K3 |
| `index.php` (roteador), `config/`, `class Database.php`, `auth` compartilhado | Plataforma (desaparece como conceito) |

## Anexo B — Matriz de dependências entre módulos

| Módulo | Depende de (síncrono) | Consome eventos de | É consumido por |
|---|---|---|---|
| K1 | K3 | D1 | todos |
| K2 | K3 | — | todos |
| K3 | — | todos | todos |
| D1 | K1, K2, K3 | D4 (status financeiro) | todos |
| D2 | D1, D7, K3 | — | D3, D6, L1 |
| D3 | D1, D2, D7, K3 | D2 | D1, D8, L1 |
| D4 | D1, K3 | D1 | D8, L1 |
| D5 | D1, K3 | — | D3, L1 |
| D6 | D1, D2, D7, K3 | D2 | D3, L1 |
| D7 | K2, D1 | — | D2, D3, D6 |
| D8 | D1, K3 | D1, D3, D4 | — |
| L1 | read models | todos | L2 |
| L2 | todos (API) | — | — |

## Anexo C — Matriz de canais (detalhada)

| Fluxo | Web gestor | Web agricultor | Mobile | Offline |
|---|:---:|:---:|:---:|:---:|
| Login e recuperação | ● | ● | ● | não |
| Cadastro/aprovação | ● | — | ● (consulta) | não |
| Meu perfil / ficha / carteirinha | ● | ● | ● | não |
| Registrar atividade de campo | ● | ● | ● | **sim** |
| Registrar insumo / validar | ● | ● | ● | catálogo em cache |
| Registrar animal/manejo | ● | ● | ● | **sim** |
| Visita técnica / NC | ● | consulta | ● | **sim** |
| Plano de manejo | ● | consulta | ● | parcial |
| Ordem de produção / higienização | ● | ● | ● | parcial |
| Mensalidade / PIX | ● | ● | ● | não |
| Assembleia / voto | ● | ● | ● | não |
| Evento / presença | ● | ● | ● | não |
| Relatórios | ● | — | resumo | não |
| Administração | ● | — | — | não |

## Anexo D — Envelope de evento e idempotência

```json
{
  "id": "01J9...",
  "tipo": "atividade.registrada",
  "versao": 1,
  "ocorridoEm": "2026-10-21T10:00:00-03:00",
  "agregado": { "tipo": "atividade", "id": "uuid", "versao": 3 },
  "correlacaoId": "01J9...",
  "ator": { "usuarioId": "uuid", "origem": "associado" },
  "payload": { "propriedadeId": "uuid", "lavouraId": "uuid", "insumoId": "uuid" }
}
```

Contrato de consumo:
- `eventos_consumidos(eventoId, consumidor, processadoEm)` — chave única.
- Processar é idempotente; falha vai para DLQ com payload e erro.
- Replay manual por `tipo` + janela de tempo; nunca em produção sem aprovação.

## Anexo E — Checklist de gate por módulo (resumo)

```
[ ] Matriz de paridade 100% OK, com arquivo:linha e evidência
[ ] Matriz de autorização (anônimo, papéis, outro associado, outro tenant) verde
[ ] 0 violação de fronteira (dependency-cruiser + ESLint boundaries)
[ ] repository é o único que importa Prisma
[ ] Contrato no packages/contracts + OpenAPI sem rota não documentada
[ ] Cobertura ≥ 80% linhas / 70% branches; 100% nos cálculos
[ ] Migração aditiva testada sobre cópia do dump
[ ] E2E web em 3 larguras; mobile offline quando aplicável
[ ] Auditoria de ações sensíveis gravando
[ ] Runbook + README + observabilidade do módulo
[ ] Parecer do fiscal com evidência reproduzível
```

---

## Anexo F — Relevância e dependência da pasta `sinporg/`

> Verificação feita em 2026-10-21, **antes de qualquer decisão de remoção**. Nada foi alterado.

### F.1 Veredito

- **Para o sistema em operação: não há dependência funcional.** Nenhum `require`/`include` do
  legado aponta para o monorepo (exceto a ponte descrita em F.2), não há alias Apache, cron,
  processo Node escutando (portas 3000/3001 livres) nem tela PHP que o utilize. O app PHP
  continua idêntico sem a pasta.
- **Para o projeto de modernização: é o ativo mais valioso já produzido.** É a única implementação
  do alvo (API, contratos, SDK offline, schema, web, mobile, CI). Excluir equivale a reiniciar a
  Fase 1 do plano.
- **A exclusão, hoje, é irreversível:** 0 arquivos rastreados no Git, `sinporg/` aparece como
  `??`, e não existe `.gitignore`, zip, tar ou cópia de backup no workspace.

### F.2 Dependências reais encontradas

| # | Dependência | Evidência | Efeito de excluir |
|---|---|---|---|
| 1 | Ponte de sessão lê o `.env` do monorepo | `api/legacy/session-bridge.php:58` → `dirname(__DIR__,2).'/sinporg/.env'` | o endpoint passa a responder **500** (`JWT_ACCESS_SECRET nao configurado`). Nenhum arquivo do legado o chama hoje; é consumido pelo front novo/testes |
| 2 | Banco compartilhado já migrado pelo Prisma | `_prisma_migrations` com **9 migrations** (0–8) em `sisagro`; `auth_refresh_tokens` com 597 registros (0 ativos); `associados.uuid`; **25 views `v_*`**; colunas `atualizado_em` | não desfaz nada no banco, mas **perde o schema/migrations que documentam e gerenciam** essas mudanças |
| 3 | Histórico local incompleto | a pasta tem só as migrations **0, 1, 2**; o banco registra **3–8** (CNPO fulltext, uuid, FK de cidades, views BFF) | o banco é hoje o **único registro completo** dessas migrações; `prisma migrate` fica sem histórico reproduzível |
| 4 | Deploy | `deploy.sh` faz `rsync` de todo o app exceto `.git` e `node_modules` | o monorepo e o `.env` (11 chaves preenchidas, incluindo `RESEND_API_KEY`) **iriam para produção** |
| 5 | Documentação | `docs/v3/00-LEIA-ME.md:62` chama de “monorepo de destino”; relatórios citam `sinporg/prisma/migrations/0_baseline/migration.sql` como fonte de DDL | referências de rastreabilidade ficam quebradas |

### F.3 O que se perde e o que não se perde

| Não se perde | Perde |
|---|---|
| app PHP, banco e dados atuais | 5.653 linhas TypeScript implementadas e testadas |
| tabelas/views já criadas no banco | schema Prisma (93,9 KB, 76 modelos / 67 enums) e baseline de 1.509 linhas |
| documentação e relatórios | 6 módulos de API, contratos Zod, SDK com fila offline, guards de tenant |
| usuários e operação | web (4 telas), mobile (3 telas), 5 testes, CI, docker-compose, `.env.example` |
| | as 3 migrations locais + a referência das 6 já ausentes |

### F.4 Tamanho: o que realmente ocupa espaço

| Item | Tamanho |
|---|---:|
| `node_modules` | 1.150 MB |
| `apps/` com build (`.next`, `dist`, `node_modules` locais) | 128,5 MB |
| `.turbo` (cache) | 6,2 MB |
| **código-fonte útil** (sem dependências/build) | **6,89 MB / 208 arquivos** |

Se o objetivo for liberar disco, **não é preciso excluir a pasta**: remover `node_modules`,
`.next`, `dist`, `.turbo` e `.expo` libera ~1,28 GB e preserva 100% do código.

### F.5 Recomendação

1. **Não excluir agora.** Antes de qualquer remoção, versionar (`git add sinporg` em branch
   própria ou repositório separado) e arquivar um zip **fora do webroot**.
2. Recuperar/regenerar as migrations **3–8** a partir do banco (`mysqldump --no-data` + DDL das
   views + `prisma migrate diff`) — item mais urgente e independente da decisão.
3. Se o objetivo é tirar do webroot/produção: **mover** a pasta para fora do docroot (ex.:
   `C:\xampp\htdocs\_labs\sinporg`) e excluí-la do `deploy.sh`; ajustar `session-bridge` para ler
   o segredo de `config/secrets.php`.
4. Se a decisão for realmente descartar: (a) arquivar, (b) exportar DDL/views, (c) desativar a
   ponte, (d) atualizar a documentação, (e) **manter** as mudanças aditivas no banco (não dropar),
   (f) só então excluir.

---

### Encerramento

Este plano responde às três perguntas centrais:

1. **Como separar?** Em 13 unidades lógicas (3 núcleo, 8 domínios, 2 camadas), com ownership
   único de dados, contrato de kernel e comunicação por referência/evento — implantadas como
   monólito modular e extraídas por estrangulamento, nunca por big-bang.
2. **Quais módulos?** K1 Identidade, K2 Território, K3 Plataforma, D1 Quadro Associativo,
   D2 Caderno de Campo, D3 Fiscalização, D4 Financeiro, D5 Produção Animal, D6 Agroindústria,
   D7 Suprimentos, D8 Governança, L1 Relatórios, L2 Portal/BFF.
3. **Como atender browser e mobile?** Uma API e um contrato; web com BFF e acessibilidade; app
   offline-first para o campo; PWA como ponte; autorização e regra sempre no servidor.

**Nada aqui foi executado.** A execução começa somente após a decisão de D-01 a D-10 e o gate G0.
