# SINPORG — Plano de Desmembramento em Módulos e Execução Multicanal

## Revisão 2 — cenário pós-exclusão do monorepo `sinporg/`

> **Natureza:** proposta técnica e plano de execução. **Nada foi executado.**
> Não houve alteração de código, banco, deploy ou configuração. Único artefato: este documento.
>
> **Versão:** 2.0 · **Data:** 2026-10-21 · **Status:** aguardando decisão (G0)
> **Substitui:** Revisão 1 (`docs/v4/PLANO-DE-MODULOS-E-EXECUCAO.md`), cuja premissa era o
> monorepo `sinporg/` — pasta que não existe mais no projeto.
> **Escopo recontado hoje:** 450 arquivos PHP / 81.178 linhas; banco `sisagro` com 78 tabelas
> base, 1.016 colunas, 49 FKs e 27 views; 249 pendências no Git; documentação existente;
> PWA atual (`sw.js` v5, `db_sync.js`).
>
> **Revisão 2.1 — emenda de segurança (2026-10-27):** o PHP 8.2.12 em uso está afetado pelas
> CVE-2026-109618/109619 e a linha 8.2 encerra em 31/12/2026. Ver o parecer
> [`PARECER-LINGUAGEM-SEGURANCA-LGPD.md`](PARECER-LINGUAGEM-SEGURANCA-LGPD.md). Nenhuma fase
> começa antes do gate **G0.1** (PHP corrigido).

---

## 0. O que mudou desde a revisão anterior

| Dimensão | Revisão 1 (com `sinporg/`) | Revisão 2 (cenário atual) |
|---|---|---|
| Fundação da plataforma nova | API NestJS, Prisma, contratos Zod, SDK, web e mobile já iniciados | **inexistente** — precisa ser construída do zero ou substituída |
| Estratégia recomendada | continuar o strangler sobre o monorepo TS | **modularizar o próprio PHP (monólito modular) + API + PWA-first** |
| Prazo estimado | 12–16 meses | **11–15 meses** (3–4 devs), MVP em 5–7 |
| Risco principal | paridade e sync offline | **refatorar 81 mil linhas sem testes** + tentação de uma terceira reescrita |
| Artefatos remanescentes | código, schema, migrations, testes | banco já alterado (9 migrations, 27 views, `uuid`, `atualizado_em`), schema em `tmp/prisma-spike`, docs desatualizadas |
| Mobile | Expo nativo | **PWA primeiro**; app nativo apenas por decisão posterior (gate) |

**O que a exclusão não apagou** (e o novo plano transforma em ativo):

1. **`uuid` já preenchido** em todas as tabelas-núcleo — 31/31 associados, 8/8 lavouras, 9/9 lotes,
   11/11 atividades, 13/13 produtos, 14/14 insumos. Identificador público pronto para a API.
2. **`atualizado_em` com `ON UPDATE CURRENT_TIMESTAMP`** em 5 tabelas (atividades, lavouras, lotes,
   insumos, agroindústrias) — base pronta para sincronização incremental do PWA.
3. **27 views `v_*`** já criadas no banco — read models prontos para relatórios e dashboards.
4. **`auth_refresh_tokens`** (tabela sem uso pela aplicação, com 597 registros históricos e 0 ativos)
   — pode ser reutilizada para tokens de um app nativo futuro.
5. **`tmp/prisma-spike/schema.prisma`** (86 modelos, 99 KB) e **`tmp/baseline_orig/`** (15 arquivos
   originais de API) — recuperáveis como referência de modelo e como *golden master* de regressão.

**O que foi perdido e não volta pelo Git:** 0 arquivos da `sinporg/` estavam rastreados
(`?? sinporg/`), então não há `git restore`. O que resta são os artefatos acima.

**Dívidas deixadas pela exclusão** (tratadas na Trilha A): `api/legacy/session-bridge.php` agora
aponta para `sinporg/.env` inexistente e responde 500; 21 documentos com 75 referências à pasta
sumida; 9 migrations registradas no banco sem arquivos correspondentes; 597 tokens órfãos;
2,93 GB de caches em `tmp/` (`pnpm-store`, `npm-cache`, `prisma-spike`).

### Sobre a exigência de "100% de eficácia, segurança e usabilidade"

Não existe garantia de 100% em uma replataforma/modularização de 81 mil linhas sem testes. O que
este plano oferece é verificável: **matriz de paridade por módulo, gates com evidência executável,
testes automatizados, rollout gradual e rollback**. Se um gate não passa, o módulo não avança.

---

## 1. Resposta direta

### 1.1 Se eu fosse separar este projeto hoje, como faria?

Sete passos, nesta ordem:

1. **Estabilizar e recuperar antes de modularizar.** Versionar as 249 pendências, testar backup,
   corrigir o LFI ativo (`index.php:49-51`; `includes/rota_segura.php` não é incluído por nenhum
   arquivo), reparar/remover a ponte quebrada, exportar o que só existe no banco (views, migrations)
   e limpar os caches de `tmp/` preservando o schema do spike.
2. **Criar o Kernel de contratos em PHP** — `ContextoUsuario`, `AssociadoRef`,
   `UnidadeProdutivaRef` — como interfaces públicas (o "dado comum" deixa de ser SQL espalhado).
3. **Introduzir um bootstrap único, um roteador por módulo e as camadas Controller → Service →
   Repository**, migrando rota a rota (strangler dentro do monólito), sem reescrever tudo de uma vez.
4. **Colocar cada tabela sob um único dono** e proibir `JOIN`/`UPDATE` cruzado — verificado por
   ferramenta no CI (`deptrac`), não por boa vontade.
5. **Publicar uma API JSON única** (`/api/v1`) consumida pelos mesmos serviços — é isso que permite
   browser e mobile sem duplicar regra.
6. **Camada de leitura separada** (views + serviço de relatórios) para dashboards, PDF e Excel.
7. **PWA-first no mobile**, aproveitando `sw.js` + `db_sync.js` existentes; app nativo só depois de
   um gate que prove que a PWA não atende (push iOS, background sync, câmera avançada).

> **Não faria:** terceira reescrita total, microserviços, ou "esperar a plataforma nova" — a
> primeira tentativa (Laravel) deixou tabelas no banco; a segunda (monorepo TS) foi excluída.
> O caminho de menor risco agora é **modular o que já funciona e é conhecido**.

### 1.2 Reorganização dos objetivos → capacidades → módulos

| Objetivo do projeto | Capacidade necessária | Módulo dono | Indicador |
|---|---|---|---|
| Manter o quadro associativo confiável | Cadastro, aprovação, vínculo, adesão, carteirinha | **D1** | % cadastros aprovados sem retrabalho |
| Comprovar conformidade orgânica | Roteiros, visitas, NC, plano de manejo, certificação | **D3** | % produtores com visita em dia |
| Registrar e comprovar a produção | Propriedades, lavouras, atividades, lotes, insumos | **D2 + D7** | % atividades registradas no mês |
| Rastrear a cadeia produtiva | Lotes, ordens, balanço de massas, rótulo | **D6** (+ D2/D5) | % lotes rastreáveis |
| Sustentar o financeiro da associação | Planos, mensalidades, PIX, caixa, contas | **D4** | % inadimplência / conciliação |
| Rastrear o rebanho | Animais, lotes, sanidade, reprodução | **D5** | % animais com histórico completo |
| Decidir e comunicar | Assembleias, votações, eventos, presença | **D8** | % associados alcançados |
| Gerir por evidências | Read models, relatórios, dashboards | **L1 + L2** | relatórios sem trabalho manual |
| Operar com segurança e rastreabilidade | Identidade, escopo, auditoria, arquivos | **K1 + K3** | 0 rotas sem autorização |
| Localizar e classificar o agricultor | Território, CNPO, microrregiões | **K2** | % registros com território válido |

### 1.3 Os módulos — 13 unidades lógicas

| ID | Módulo | Missão | Dados que possui | Canal principal |
|---|---|---|---|---|
| **K1** | Identidade & Acesso | Quem é, o que pode, em qual escopo | `usuarios`, credenciais, sessões | Web + PWA |
| **K2** | Território & Referências | Onde está e quais referências oficiais valem | `estados`, `cidades`, `meso/microrregiões`, `cnpo` | Web + PWA (leitura) |
| **K3** | Plataforma & Auditoria | Auditoria, arquivos, notificações, eventos, config | `audit_log`, `notificacoes`, `arquivos`, `eventos` | API + Admin |
| **D1** | Quadro Associativo & Adesão | Ciclo de vida do associado | `associados`, `associacoes`, `vinculo`, `ficha_adesao*`, `carteirinhas` | Web + PWA |
| **D2** | Caderno de Campo | Produção vegetal com evidência | `propriedades`, `lavouras`, `atividades_diarias`, `lotes_producao`, `ocorrencias_manejo`… | **PWA offline** |
| **D3** | Fiscalização & Conformidade | Provar conformidade orgânica | `roteiros_inspecao`, `visitas_tecnicas`, `nao_conformidades`, `plano_manejo*`, `certificacao_*` | Web + PWA |
| **D4** | Financeiro Associativo | Arrecadar e controlar | `planos_mensalidades`, `mensalidades`, `pagamentos`, `caixa`, `contas` | Web + PWA (consulta) |
| **D5** | Produção Animal | Rastrear rebanho | `animais`, `animal_lotes`, `controle_*_animal`, `ambiente_criacao*` | PWA + Web |
| **D6** | Agroindústria | Transformar com rastreabilidade e rótulo | `agroindustrias`, `agro_*`, `receitas`, `ordens_producao`, `rastreabilidade_*` | Web + PWA |
| **D7** | Suprimentos & Insumos | Só insumo permitido, com fornecedor | `insumos`, `insumos_organicos_aprovados`, `fornecedores` | Web + PWA (cache) |
| **D8** | Governança & Comunicação | Decidir e informar | `assembleias*`, `eventos`, `evento_presenca`, `participacoes` | Web + PWA |
| **L1** | Relatórios & Analytics | Responder perguntas com PDF/Excel | nenhum transacional; read models (27 views) | Web |
| **L2** | Portal, Dashboards & Composição | Compor a experiência por perfil/canal | nenhum; agrega via API | Web + PWA |

### 1.4 Como todos se comunicam se o comum é o agricultor/associado?

O ponto comum **não é a tabela `associados`** — é o **contrato do Kernel**. Em PHP:

| Mecanismo | Como fica | Exemplo |
|---|---|---|
| **Referência do Kernel** | interface + provider, retorno em lote | D3 pede `AssociadoRef` para exibir nome/status na visita |
| **Comando síncrono** | interface pública do módulo, chamada no mesmo processo | D2 valida insumo no D7 antes de gravar |
| **Evento de domínio** | `EventDispatcher` + tabela `eventos` (outbox) | D1 publica `associado.aprovado` → K1 libera acesso, D3 cria pendência |
| **Read model** | views `v_*` + consultas de L1 | dashboards e relatórios |
| ~~SQL cruzado~~ | **proibido** | nada de `JOIN` em tabela de outro módulo |

As cinco leis (adaptadas para PHP) e o enforcement estão na seção 4.4. A diferença entre "módulos"
e "pastas" é justamente essa: **fronteira verificada por ferramenta**.

### 1.5 Browser e mobile sem duplicar regra

- **Browser:** as páginas continuam server-rendered (Bootstrap/jQuery atuais), mas viram *thin
  pages* que chamam o controller do módulo. Nada de reescrever a UI inteira.
- **Mobile:** **PWA-first** — instalável, offline com IndexedDB (motor `db_sync.js` já existe),
  câmera via input de captura, geolocalização, Web Push no Android. Sincronização usa `uuid`
  gerado no cliente + `Idempotency-Key` + `atualizado_em` (já no banco).
- **App nativo:** só depois de um piloto real com a PWA. Se necessário, **Capacitor** embrulha a
  própria PWA — muito mais barato que reescrever em Expo. Reaproveita `auth_refresh_tokens` para
  autenticação por token.

### 1.6 O que este plano entrega

- Decomposição justificada com evidências do código e do banco atuais.
- Arquitetura-alvo em PHP (Kernel, módulos, API v1, read models) com enforcement automatizado.
- Estratégia multicanal com PWA-first e decisão explícita sobre app nativo.
- Execução em 2 trilhas e 10 fases, com gates, esforço, caminho crítico, riscos e rollback.
- Recuperação dos artefatos remanescentes (banco, schema, baseline, caches) como parte da Fase 0.
- O que **não** pode ser prometido (100%) e como cada risco é verificado.

---

## 2. Diagnóstico atual (com evidências recontadas)

### 2.1 Números do projeto hoje

| Métrica | Valor | Fonte |
|---|---:|---|
| Arquivos PHP (aplicação) | 450 | recontagem própria (sem `tmp/`, `vendor/`, `recicla/`) |
| Linhas de PHP | 81.178 | idem |
| Roteador `index.php` | 1.396 linhas, 224 referências a `$parts[0]`, 248 `require` | idem |
| Páginas de módulo | 220 arquivos em `modulos/` | idem |
| Endpoints em `api/` | 56 (25 usam `ApiAuth`) | idem |
| Páginas em `relatorios/` | 26 | idem |
| Comandos SQL no código | 1.122 ocorrências | idem |
| Arquivos lendo `$_SESSION['user_id']` | 104 | idem |
| Arquivos lendo `$_SESSION['user_nivel']` | 79 | idem |
| Arquivos que recebem `$_POST` | 118 | idem |
| Arquivos que citam CSRF | 91 | idem |
| `rota_segura.php` incluído por | **0 arquivos** (LFI segue ativo) | idem |
| `.php` na raiz | 36 (13 telas/roteadores + 23 scripts de diagnóstico) | idem |
| Tabelas base / colunas / FKs | 78 / 1.016 / 49 | banco `sisagro` |
| Views | 27 | banco |
| Tabelas sem PRIMARY KEY | 0 | banco (saneamento anterior válido) |
| `associados` / `ficha_adesao` | 31 / 7 registros | banco |
| Migrations Prisma registradas | 9 (arquivos locais: 0) | `_prisma_migrations` |
| Tokens órfãos | 597 (0 ativos) | `auth_refresh_tokens` |
| Pendências no Git | 249 | `git status` |
| Referências obsoletas à `sinporg/` | 21 arquivos / 75 ocorrências | varredura de `.md` |
| Caches remanescentes | **2,93 GB** em `tmp/` | `pnpm-store` 2,19 GB + `npm-cache` 0,52 GB + `prisma-spike` 0,22 GB |

### 2.2 Arquitetura atual

```
Browser / PWA
   │
   ▼
Apache + .htaccess ──► index.php (roteador central: 224 referências a $parts[0])
                           │
                           ├──► modulos/<x>/*.php  (220 páginas; cada uma monta o próprio bootstrap)
                           ├──► api/*.php          (56 endpoints; guarda irregular)
                           ├──► relatorios/*.php   (26 páginas; SQL próprio)
                           └──► classes/*.php      (52 modelos com SQL dentro)
                                      │
                                      ▼
                          MariaDB "sisagro" (78 tabelas, 49 FKs, 27 views)
                                      ▲
                                      └── mesma tabela lida/escrita por 3+ áreas
```

### 2.3 Os oito acoplamentos que impedem modularizar

| # | Dimensão | Evidência | Efeito |
|---|---|---|---|
| 1 | Bootstrap | cada página monta o caminho até `config/config.php` | arquivos-ilha |
| 2 | Sessão | 104 arquivos leem `user_id`; 79 leem `user_nivel` | identidade espalhada |
| 3 | Banco | `associados` em 34 áreas; 30 tabelas com 3+ consumidores | impossível extrair sem contrato |
| 4 | Acesso a dados | 1.122 SQL em ~300 arquivos; sem repositório | regra e persistência misturadas |
| 5 | Roteamento | `index.php` conhece todas as rotas | todo módulo depende do arquivo central |
| 6 | UI/Navegação | `menu.php` com 84 links e 4 consultas de negócio por render | shell conhece domínio |
| 7 | Relatórios | 3 pilhas de relatório; SQL cruzando 6 domínios | leitura sem fronteira |
| 8 | Duplicação | 3 logins, 2 resets, 4 pares de tabelas duplicadas | regra divergente |

### 2.4 O problema-raiz: `associados` com cinco papéis

```
associados (52 colunas)
├── identidade civil ............ nome, cpf, rg, nascimento
├── contato/endereço ............ email, telefone, cep
├── AUTENTICAÇÃO ................ senha, nivel, tipo, status      ← deveria ser K1
├── vínculo associativo ......... associacao_id, vinculo, tipo_associado, status_associado
├── certificação ................ certificadora_id, tipo_certificacao, registro_cnpo
├── ficha de adesão ............. ficha_enviada, ficha_aprovada_por/em
├── territorialidade ............ microrregiao_id, cluster_comunicacao
├── notificações ................ notificacoes_ativas, dias_antecedencia_evento
└── integração .................. user_id, tem_agroindustria, area_producao
```

**Decisão:** o Kernel passa a **definir o contrato** agora; as credenciais migram para
`auth_credenciais` apenas quando D1 for migrado, preservando a precedência do login atual
(`login.php:90-111` valida `usuarios` e `associados`, preferindo a conta de sistema). Sem big-bang
de dados.

### 2.5 O que a plataforma excluída deixou de útil (e como usar)

| Artefato remanescente | Onde | Uso no novo plano |
|---|---|---|
| `uuid` preenchido em todas as tabelas-núcleo | banco | identificador público da API (nunca o id sequencial) |
| `atualizado_em` com `ON UPDATE CURRENT_TIMESTAMP` | 5 tabelas (atividades, lavouras, lotes, insumos, agroindústrias) | pull incremental do PWA |
| 27 views `v_*` | banco | read models para L1/dashboards |
| `auth_refresh_tokens` | banco (597 históricos, 0 ativos) | base para token de app nativo futuro |
| `tmp/prisma-spike/schema.prisma` | `tmp/prisma-spike/` (86 modelos) | referência do modelo de dados; preservar antes de limpar `tmp/` |
| `tmp/baseline_orig/` (15 arquivos) | `tmp/baseline_orig/` | *golden master* para testes de regressão das APIs |
| `tmp/crawl`, `tmp/modularizacao` | `tmp/` | evidências de levantamento anterior |

### 2.6 O que ficou quebrado com a exclusão (e precisa de correção)

| Item | Evidência | Correção proposta |
|---|---|---|
| Ponte de sessão aponta para pasta inexistente | `api/legacy/session-bridge.php:58` lê `../../sinporg/.env`; alvo não existe → 500 | **remover** o endpoint (não há consumidor) ou movê-lo para `config/secrets.php` |
| Documentação cita pasta removida | 21 arquivos / 75 ocorrências | triagem: manter conceito, corrigir caminho ou marcar obsoleto |
| 9 migrations sem arquivos | `_prisma_migrations` | exportar registro para `sql/legacy/`; manter tabela como histórica inerte |
| 597 tokens órfãos | `auth_refresh_tokens` | backup + truncate; manter tabela reservada |
| 27 views sem "dono" documentado | banco | exportar DDL para `sql/views/`; avaliar e assumir como read models de L1 |
| 2,93 GB de cache | `tmp/` | preservar `schema.prisma` + `baseline_orig`; apagar caches (`pnpm-store`, `npm-cache`) |
| Base de teste congelada | `tmp/baseline_orig/` | mover para `storage/baseline/` e usar em testes de paridade |

### 2.7 Dívidas de segurança que condicionam o plano

| Achado | Evidência | Impacto |
|---|---|---|
| LFI na rota de relatórios | `index.php:49-51`; `rota_segura.php` sem nenhum include | crítico |
| Endpoints sem autenticação | só 25/56 usam `ApiAuth` | alto |
| POST sem CSRF | 47 de 86 (documentado em auditoria) | alto |
| Sem escopo por certificadora | consultas sem filtro de tenant | alto (LGPD) |
| Scripts expostos | 23 na raiz + 36 em `dev-utils/` | alto |
| Credenciais no código | `config/config.php` (`root` sem senha) | alto |
| Views com PII | `v_associados_resumo` expõe CPF/e-mail | médio (se exposta por API) |

---

## 3. Decisão de arquitetura

### 3.1 Comparativo das opções

| Critério | **A. PHP modular + API + PWA** (recomendada) | B. Reconstruir plataforma TS | C. Só PWA sem modularizar | D. Microserviços |
|---|---|---|---|---|
| Reaproveita as 81 mil linhas | **sim** | não (reescreve) | sim | sim |
| Tempo até valor real | **5–7 meses (MVP)** | 12–18+ meses | 3–4 meses | 18+ meses |
| Risco de regressão | médio (controlável por paridade) | **alto** | baixo | muito alto |
| Custo operacional | baixo (1 app PHP) | médio/alto | baixo | alto |
| Mobile offline | PWA (bom; iOS limitado) | Expo (melhor) | PWA | PWA/Expo |
| Manutenibilidade | **melhora muito** com kernel/camadas | alta | não resolve | alta, mas complexa |
| Segurança | corrige na origem | corrige na reescrita | parcial | alta complexidade |
| Equipe necessária | PHP + front + QA | TS sênior + mobile + DevOps | PHP | 3+ squads |
| Adequação ao momento | **alta** | baixa (2 tentativas anteriores) | baixa (dívida continua) | nenhuma |

### 3.2 Recomendação

**Opção A — modularizar o monólito PHP, expor API JSON e atender o mobile com PWA-first.**
Motivos objetivos:

1. O ativo do projeto é **regra de negócio**, e ela está no PHP (81 mil linhas). Reescrever é
   apostar duas vezes no mesmo risco que já falhou (Laravel e monorepo TS).
2. O banco já tem os elementos que faltariam para começar (`uuid`, `atualizado_em`, views).
3. A PWA já existe e funciona parcialmente (`sw.js` v5, fila IndexedDB) — o menor caminho para o
   campo é completá-la, não trocar de tecnologia.
4. A modularização pode ser feita **rota a rota**, com o sistema no ar, sem janela de risco total.
5. Se no futuro a organização decidir voltar ao TypeScript, as fronteiras e a API já estarão
   prontas — a extração fica barata. **A arquitetura não fecha essa porta; ela abre.**

### 3.3 Quando reconsiderar a opção B

Gatilhos objetivos (não ideológicos): time com 3+ devs TS dedicados; necessidade de app nativo com
offline pesado em iOS; crescimento de carga que o PHP não absorva; contratação de equipe TS; decisão
societária de produto SaaS multi-tenant. Nenhum desses ocorre hoje.

**Emenda 2.1:** a decisão de linguagem **não** deve ser tomada como reação à CVE do PHP (falha de
runtime, corrigida por patch). Se a organização decidir migrar, o parecer recomenda
**TypeScript/Node.js (NestJS)** como alvo e **Java 21/25 LTS** como alternativa de maior assurance —
sempre por módulo e após o MVP. Ver
[`PARECER-LINGUAGEM-SEGURANCA-LGPD.md`](PARECER-LINGUAGEM-SEGURANCA-LGPD.md).

### 3.4 ADRs desta revisão

| ADR | Decisão |
|---|---|
| ADR-20 | Monólito modular em PHP como alvo imediato |
| ADR-21 | Kernel de identidade/acesso separado do domínio D1 |
| ADR-22 | API JSON v1 única para web e mobile; sem regra no cliente |
| ADR-23 | PWA-first; app nativo só por gate (Capacitor se necessário) |
| ADR-24 | `uuid` como identificador público; `atualizado_em` como base de sync |
| ADR-25 | Views `v_*` como read models de L1, com DDL versionado em `sql/views/` |
| ADR-26 | Nenhuma migração destrutiva enquanto houver página legada lendo a tabela |
| ADR-27 | Fronteiras verificadas por `deptrac` + PHPStan + CI |
| ADR-28 | Relatórios em PHP (mPDF/PhpSpreadsheet já instalados), jobs via cron |
| ADR-29 | Sessão cookie para web/PWA; token (tabela existente) apenas para app nativo futuro |
| ADR-30 | Remover a ponte `session-bridge` (temporária e quebrada) na Trilha A |
| ADR-31 | Linguagem: permanecer em **PHP 8.4.27+/8.5.12+ corrigido** no curto prazo; alvo de migração **TypeScript/Node.js**, decidido em gate pós-MVP (ver parecer) |
| ADR-32 | **SLA de patch crítico ≤ 48h**; assinatura dos avisos do PHP e WAF como mitigação compensatória |
| ADR-33 | Controles de LGPD obrigatórios em qualquer stack: RBAC+tenant, auditoria, mascaramento, retenção, runbook e registro de incidente |

---

## 4. Arquitetura-alvo

### 4.1 Camadas

```
┌────────────────────────────────────────────────────────────────────┐
│ EXPERIÊNCIA                                                       │
│  Web server-rendered (páginas finas)   PWA (offline/instalável)   │
│  Admin/Diretoria                       App nativo (gate futuro)   │
└──────────────▲──────────────────────────────▲─────────────────────┘
               │ HTML + JSON                    │ JSON /api/v1
┌──────────────┴──────────────────────────────┴─────────────────────┐
│ HTTP: Router por módulo · Middleware (auth, CSRF, policy, rate)   │
│       Envelope de resposta · Tratamento de erro · Auditoria       │
└──────────────▲──────────────────────────────▲─────────────────────┘
               │ Controller (fino)              │
┌──────────────┴──────────────────────────────┴─────────────────────┐
│ MÓDULOS (8 domínios): D1…D8                                      │
│   Service (regra/transação) → Repository (único com PDO/SQL)     │
│   Policy (autorização) · Eventos publicados/consumidos           │
└──────────────▲──────────────────────────────▲─────────────────────┘
               │ contratos do Kernel            │ read models
┌──────────────┴──────────────────────────────┴─────────────────────┐
│ KERNEL: K1 Identidade/Acesso · K2 Território · K3 Plataforma      │
│ L1 Relatórios (views) · L2 Composição (dashboards)               │
└────────────────────────────────────────────────────────────────────┘
```

### 4.2 Estrutura de pastas-alvo (incremental)

```
app/
├── index.php                  # front controller: só bootstrap + dispatch
├── src/
│   ├── Kernel/
│   │   ├── Contracts/         # ContextoUsuario, AssociadoRef, UnidadeProdutivaRef, EventBus
│   │   ├── Auth/              # sessão, política, escopo, rate limit
│   │   ├── Http/              # Request, Response, Router, Middleware
│   │   ├── Persistence/       # Conexão PDO única, UnitOfWork
│   │   ├── Events/            # dispatcher + outbox
│   │   └── Support/           # config, log, upload, audit
│   ├── Modules/
│   │   ├── Associados/        # D1: Controller, Service, Repository, Policy, dto/
│   │   ├── CadernoCampo/      # D2
│   │   ├── Fiscalizacao/      # D3
│   │   ├── Financeiro/        # D4
│   │   ├── ProducaoAnimal/    # D5
│   │   ├── Agroindustria/     # D6
│   │   ├── Suprimentos/       # D7
│   │   └── Governanca/        # D8
│   ├── Reports/               # L1: consultas, PDF/Excel, jobs
│   └── Portal/                # L2: dashboards, composição
├── routes/                    # um arquivo por módulo (registry)
├── views/                     # templates por módulo
├── api/v1/                    # entrypoints JSON (compatíveis com o roteador)
├── config/                    # config, env, secrets
├── sql/                       # migrations aditivas + views versionadas
├── tests/                     # PHPUnit (unit/integration) + E2E Playwright
└── docs/                      # ADRs, paridade, runbooks
```

As páginas atuais continuam acessíveis durante a migração; cada rota migrada passa a apontar para o
novo controller e a antiga vira `301` após 30 dias.

### 4.3 Contratos do Kernel (PHP)

```php
<?php
// src/Kernel/Contracts/ContextoUsuario.php
interface ContextoUsuario
{
    public function usuarioId(): string;          // uuid
    public function origem(): string;             // 'staff' | 'associado'
    public function associadoId(): ?int;
    public function papeis(): array;              // admin|gestor|tecnico|diretoria|agricultor|agroindustria
    public function certificadoraId(): ?int;      // escopo de tenant
    public function associacoesVisiveis(): array;
    public function areasProducao(): array;       // vegetal|animal|agroindustria
    public function pode(string $recurso, string $acao): bool;
}

// src/Kernel/Contracts/AssociadoRef.php
interface AssociadoRef
{
    public function id(): int;
    public function uuid(): string;
    public function nomeExibicao(): string;
    public function cpfMascarado(): ?string;      // LGPD por padrão
    public function status(): string;             // pendente|ativo|inativo|desligado
    public function registroCnpo(): ?string;
    public function associacaoPrincipalId(): ?int;
    public function microrregiaoId(): ?int;
    public function atualizadoEm(): string;
}

// src/Kernel/Contracts/UnidadeProdutivaRef.php
interface UnidadeProdutivaRef
{
    public function tipo(): string;               // propriedade|agroindustria|ambiente_criacao
    public function id(): int;
    public function uuid(): string;
    public function associadoId(): int;
    public function rotulo(): string;
}
```

Regras: leitura em lote (até 100 IDs), CPF mascarado por padrão, `uuid` em URL, contrato versionado
(`kernel@1`).

### 4.4 As cinco leis e enforcement em PHP

1. Módulo **nunca** faz `JOIN` em tabela de outro módulo.
2. Módulo **nunca** faz `UPDATE`/`DELETE` em tabela de outro módulo.
3. Credenciais/papéis/sessão vivem no K1 (não em `associados`).
4. Dado comum é lido por **referência** (`*Ref`), nunca por coluna alheia.
5. Comunicação é **interface pública ou evento**, nunca SQL.

| Enforcement | Ferramenta | Falha o quê |
|---|---|---|
| Dependências entre camadas/módulos | `qossmic/deptrac` | import cruzado |
| SQL fora de Repository | PHPStan (regra custom) + revisão | `new PDO`/`query()` em controller |
| Tabela de outro dono | script CI que compara `FROM/UPDATE` com mapa de ownership | SQL cruzado |
| Contrato do Kernel | testes de contrato (PHPUnit) | mudança incompatível |
| Autorização | matriz de papéis × rotas (PHPUnit + E2E) | rota aberta / IDOR / tenant |
| Paridade | matriz por módulo + E2E Playwright | tela/ação faltando |

### 4.5 Comunicação entre módulos

```
Comando com resposta imediata:
  Controller → Service A → Interface pública do módulo B (in-process, timeout lógico)

Efeito colateral:
  Service A → evento no outbox (mesma transação) → listener/worker
  (notificações, auditoria, atualização de read model)

Leitura gerencial:
  L1 → views v_* / consultas homologadas → PDF/Excel → storage + link

Experiência:
  Web/PWA → /api/v1 ou controller HTML → serviços (sem regra no cliente)
```

Catálogo inicial de eventos (curto, pragmático): `associado.aprovado`, `associado.status_alterado`,
`vinculo.alterado`, `atividade.registrada`, `lote.criado`, `insumo.utilizado`,
`nao_conformidade.aberta`, `visita.realizada`, `certificado.emitido`, `mensalidade.gerada`,
`pagamento.confirmado`, `assembleia.encerrada`, `presenca.confirmada`, `arquivo.enviado`.
Começar com listeners síncronos para auditoria/notificação; outbox/worker só quando houver job
lento (relatórios, e-mail em massa).

### 4.6 API v1 — convenções

| Item | Decisão |
|---|---|
| Prefixo | `/api/v1` |
| Formato | JSON `application/json; charset=utf-8`; upload `multipart/form-data` |
| Autenticação | sessão PHP (cookie `HttpOnly`/`SameSite=Lax`) para web/PWA; token reservado para nativo |
| CSRF | token por sessão em POST/PATCH/DELETE (também para PWA same-origin) |
| IDs | `uuid` em URL; nunca id sequencial |
| Dinheiro | inteiro em centavos |
| Datas | ISO-8601 com fuso |
| Paginação | `?page=&perPage=` + envelope `meta` |
| Erros | `{error:{code,message,details,requestId}}` |
| Idempotência | `Idempotency-Key` em sync e webhooks |
| Correlação | `X-Request-Id` em log e resposta |
| Docs | OpenAPI 3.1 gerado a partir dos contratos, versionado em `docs/api/` |

### 4.7 Ownership de tabelas (um dono por tabela)

| Dono | Tabelas |
|---|---|
| K1 | `usuarios`, `auth_credenciais` (nova), `auth_refresh_tokens`, `password_resets` |
| K2 | `estados`, `cidades`, `mesorregioes_rs`, `microrregioes_rs`, `cnpo`, `cnpo_atualizacoes` |
| K3 | `audit_log`, `notificacoes`, `arquivos` (nova), `eventos` (outbox), `feature_flags`, `sync_idempotencia` (nova) |
| D1 | `associados`, `associacoes`, `associado_associacao`, `ficha_adesao`, `ficha_adesao_produtos`, `carteirinhas`, `entidades` |
| D2 | `propriedades`, `propriedade_membros`, `lavouras`, `atividades_diarias`, `categorias_atividades`, `produtos`, `lotes_producao`, `lote_atividade`, `lote_insumo`, `ocorrencias_manejo`, `historico_conversao`, `culturas` |
| D3 | `roteiros_inspecao`, `visitas_tecnicas`, `nao_conformidades`, `plano_manejo`, `plano_manejo_cultivos`, `certificacao_participativa`, `certificadoras` |
| D4 | `planos_mensalidades`, `mensalidades`, `pagamentos`, `caixa`, `contas` |
| D5 | `animais`, `animal_lotes`, `manejo_animal_registros`, `controle_*_animal`, `ambiente_criacao_animal`, `ambiente_criacao_membros`, `densidades_maximas_animais` |
| D6 | `agroindustrias`, `agroindustria_*`, `agro_*`, `receitas_agroindustria`, `ingredientes_receita`, `ordens_producao`, `controle_higienizacao`, `rastreabilidade_insumos_processo` |
| D7 | `insumos`, `insumos_organicos_aprovados`, `fornecedores`, `fornecedor_unidades` |
| D8 | `assembleias`, `assembleia_pautas`, `assembleia_votos`, `eventos`, `evento_presenca`, `participacoes` |
| L1 | `analytics.*`/views + `relatorio_jobs` (nova) |
| A decidir | `caderno_campo`, `certificacao_produtos` (ADR na F1) |

### 4.8 Destino dos artefatos remanescentes do banco

| Artefato | Decisão | Ação |
|---|---|---|
| `uuid` (13 tabelas) | adotar como ID público | backfill só onde faltar; nunca expor id interno |
| `atualizado_em` (5 tabelas) | base do sync | manter; estender às tabelas de D2 na fase dela (aditivo) |
| 27 views `v_*` | read models de L1 | exportar DDL para `sql/views/`; homologar; documentar proprietário |
| `auth_refresh_tokens` | reservada para token nativo futuro | backup + `TRUNCATE`; não dropar |
| `_prisma_migrations` | registro histórico inerte | exportar para `sql/legacy/`; manter |
| `tmp/prisma-spike/schema.prisma` | referência de modelo | mover para `docs/referencia/schema-spike.prisma` |
| `tmp/baseline_orig/` | golden master de regressão | mover para `storage/baseline/` |
| `tmp/pnpm-store`, `npm-cache` | lixo de build | remover (~2,7 GB) após preservar os itens acima |

---

## 5. Fichas dos módulos

### K1 — Identidade & Acesso
- **Faz:** autentica staff e associado, cria `ContextoUsuario`, autoriza por papel+recurso+escopo,
  gerencia sessão, CSRF, rate limit, recuperação de senha.
- **Não faz:** cadastro do agricultor (D1), regra de domínio.
- **Implementação:** `src/Kernel/Auth` + `src/Modules/Identidade` (adaptador de credenciais legadas).
- **Pronto quando:** 100% das rotas com autorização declarada; login legado funciona sem trocar
  senha; IDOR e tenant cruzado reprovam em teste; auditoria de login.

### K2 — Território & Referências
- **Faz:** estados, cidades, meso/microrregiões RS, CNPO/PEAF, mapas, importação versionada.
- **Implementação:** serviços de consulta + cache; sem escrita por outros módulos.
- **Pronto quando:** importação idempotente; consulta pública com rate limit; mapa em 360 px.

### K3 — Plataforma & Auditoria
- **Faz:** `audit_log` imutável, uploads seguros, notificações, outbox, feature flags, config admin.
- **Pronto quando:** ações sensíveis auditadas; upload validado por conteúdo; replay de evento.

### D1 — Quadro Associativo & Adesão
- **Faz:** pré-cadastro, cadastro, aprovação, ficha, vínculo, carteirinha, meu perfil.
- **Dados:** ver 4.7. **API:** `/associados`, `/associacoes`, `/pre-cadastro`, `/ficha-adesao`,
  `/carteirinhas`, `/publico/carteirinha/:token`.
- **Canais:** web gestão (fila, edição, massa); PWA agricultor (perfil, ficha, carteirinha).
- **Pronto quando:** máquina de estados testada; CPF validado/único; soft delete com anonimização;
  carteirinha com token opaco; matriz de paridade 100% OK.

### D2 — Caderno de Campo
- **Faz:** propriedades, lavouras, atividades, categorias, produtos, lotes, ocorrências, conversão,
  rastreabilidade por QR, exportações.
- **API:** `/propriedades`, `/lavouras`, `/atividades`, `/produtos`, `/lotes`, `/ocorrencias`,
  `/sync/atividades`, `/publico/rastreabilidade/:codigo`.
- **Canais:** **PWA offline-first**; web para técnico/gestor.
- **Pronto quando:** 10 registros offline → 10 no servidor; 500 na fila; conflito visível; retenção
  3 anos sem DELETE físico; QR resolve a cadeia.

### D3 — Fiscalização & Conformidade
- **Faz:** templates versionados, visitas, pontuação, NC com prazo, plano de manejo, certificação
  participativa, certificadoras, certificado PDF, score.
- **Canais:** PWA do técnico (fotos/GPS/offline); web da certificadora.
- **Pronto quando:** item crítico bloqueia; pontuação 100% testada; NC com estados; certificado
  auditável.

### D4 — Financeiro Associativo
- **Faz:** planos, geração em lote idempotente, PIX (3 provedores), baixa, estorno, caixa, contas,
  inadimplência.
- **Canais:** web tesouraria; PWA associado (2ª via/PIX/comprovante).
- **Pronto quando:** gerar 2× não duplica; webhook repetido processa 1×; estorno transacional;
  valores em centavos.

### D5 — Produção Animal
- **Faz:** animais, lotes, ambientes, alimentação, sanidade, reprodução, rastreabilidade.
- **Canais:** PWA (curral) + web (painel).
- **Pronto quando:** histórico por animal/lote completo e exportável; densidade validada.

### D6 — Agroindústria
- **Faz:** unidades, setores, receitas, ordens, higienização, balanço de massas, rastreabilidade,
  autorização de rótulo.
- **Pronto quando:** % orgânico calculado e testado; balanço alerta; <70% bloqueia menção.

### D7 — Suprimentos & Insumos
- **Faz:** catálogo, insumos aprovados (Portaria 52/2021), fornecedores/unidades, validação.
- **Canais:** web (catálogo), PWA (consulta com cache).
- **Pronto quando:** bloqueio normativo; catálogo versionado; consulta offline no app.

### D8 — Governança & Comunicação
- **Faz:** assembleias/pautas/votos, eventos/presença, notificações, e-mail/WhatsApp.
- **Pronto quando:** 1 voto por associado; resultado imutável; lembrete configurável.

### L1 — Relatórios & Analytics
- **Faz:** catálogo de relatórios, PDF/Excel em job, read models, paridade visual.
- **Base pronta:** 27 views existentes; mPDF/PhpSpreadsheet já instalados.
- **Pronto quando:** 100% inventariados com diff aprovado; geração assíncrona; nenhuma consulta
  transacional direta.

### L2 — Portal, Dashboards & Composição
- **Faz:** dashboards por perfil, home do agricultor, estados vazio/erro/offline, onboarding.
- **Pronto quando:** cada perfil vê só o que pode; 360/768/1280 sem scroll horizontal; WCAG AA.

---

## 6. Estratégia multicanal (browser + mobile)

### 6.1 Web

| Item | Decisão |
|---|---|
| Renderização | PHP server-rendered (mantém Bootstrap/jQuery atuais) |
| Páginas | finas: bootstrap + controller do módulo; sem SQL na página |
| Responsivo | 360/768/1280/1440; tabelas viram cartões no mobile |
| Acessibilidade | WCAG 2.1 AA nos 10 fluxos principais; teclado, contraste, rótulos |
| Performance | LCP p75 < 2,5 s em 4G; imagens comprimidas; sem CDN novo para assets críticos |
| PWA | instalável; cache versionado; botão "atualizar agora" |

### 6.2 Mobile: PWA primeiro

| Capacidade | PWA hoje | O que falta |
|---|---|---|
| Instalação | `manifest.json` existe | completar ícones/shortcuts/screenshots |
| Offline shell | `sw.js` v5 com cache | revisar política (não cachear dados sensíveis) |
| Fila offline | `db_sync.js` (IndexedDB, `sync_queue`) | idempotência ponta a ponta + conflitos |
| Navegação | CSS pronto, **bottom nav não renderizado** (0 ocorrências) | implementar no `menu.php` |
| Formulários/listas | desktop-first | layouts mobile-first nas telas de campo |
| Câmera/GPS/QR | input de captura + geolocation | padronizar upload/re-encode e QR |
| Push | Web Push (Android) | iOS: limitação documentada; nativo só por gate |

**Limites conhecidos da PWA:** sem background sync confiável no iOS, push limitado, câmera menos
rica. Por isso existe o gate de app nativo (6.4).

### 6.3 Sincronização offline

```
[PWA] cria registro com uuid do cliente (crypto.randomUUID)
   │  grava local + enfileira em IndexedDB (sync_queue)
   ▼
POST /api/v1/sync/atividades   (Idempotency-Key = uuid do cliente)
   │  servidor: valida, grava, marca sync_idempotencia, publica evento
   ├─ 200  → marca synced e remove da fila
   ├─ 409  → conflito visível (regra documentada)
   ├─ 401  → renova sessão e retenta 1×
   └─ 5xx  → backoff exponencial; retoma na reconexão
```

- **Pull incremental:** `GET /api/v1/sync/changes?desde=<atualizado_em>` usando as colunas
  existentes; estender às tabelas de D2 na fase D2.
- **Conflitos:** atividade de campo → cliente vence se o servidor não tiver o uuid; visita técnica →
  servidor vence (evidência fiscal) e o app permite justificativa. Nunca descarte silencioso.
- **Fotos:** compressão no cliente, upload separado, vínculo por uuid; retry independente.
- **Testes obrigatórios:** 10 offline → 10 no servidor; 500 na fila; rede caindo no meio; duas
  sincronizações do mesmo lote.

### 6.4 Gate de app nativo

Só iniciar app nativo se **todas** forem verdadeiras após 3 meses de PWA em piloto:
1. Necessidade real de push confiável em iOS ou background sync.
2. Métrica de uso mostra que instalação da PWA não basta.
3. Orçamento e contas de loja aprovados.
4. Time com capacidade para manter dois canais.

Caminho recomendado: **Capacitor** embrulhando a PWA (mesma base), não uma reescrita. Autenticação
por token usando `auth_refresh_tokens` (tabela já existente).

### 6.5 Autenticação por canal

| Canal | Sessão | Observação |
|---|---|---|
| Web | cookie de sessão PHP (`HttpOnly`, `SameSite=Lax`, `Secure` em HTTPS) | CSRF por token |
| PWA | mesma sessão (same-origin) | expiração e logout limpam IndexedDB |
| App nativo (futuro) | access token curto + refresh rotativo | reutiliza tabela existente |

### 6.6 Matriz de canais

| Módulo | Web gestão | Web agricultor | PWA campo | Offline |
|---|:---:|:---:|:---:|:---:|
| K1 | ● | ● | ● | sessão |
| K2 | ● | ● | ● | leitura do território |
| K3 | ● | — | ● | fila |
| D1 | ● | ● | ● | não |
| D2 | ● | ● | ● | **sim** |
| D3 | ● | consulta | ● | **sim** |
| D4 | ● | ● | ● | não |
| D5 | ● | ● | ● | **sim** |
| D6 | ● | ● | ● | parcial |
| D7 | ● | ● | ● | cache |
| D8 | ● | ● | ● | não |
| L1 | ● | — | resumo | não |
| L2 | ● | ● | ● | n/a |

### 6.7 Usabilidade no contexto rural

Contraste alto (sol), alvos ≥ 44 px, linguagem direta, confirmação em ações destrutivas, estados
claros de offline/sincronizando/erro, indicação de "registros pendentes", funcionamento em aparelho
de entrada (Android 9+), abertura < 3 s.

### 6.8 Notificações

- E-mail (Resend já integrado) e WhatsApp (links existentes) para avisos.
- Web Push para PWA (Android); no iOS, apenas e-mail/WhatsApp até decisão de app nativo.
- Lembretes por cron (`lembretes.php` já existe) — migrar para job do módulo D8.

### 6.9 Segurança do canal offline

- Não cachear páginas com PII; cache só de assets e catálogo público.
- Logout limpa IndexedDB e caches do app.
- Sessão expira; sync exige sessão válida.
- Aparelho perdido: revogar sessões (K1) e invalidar token (futuro nativo).

---

## 7. Plano de execução (não executado)

### 7.1 Estratégia

**Strangler dentro do monólito, em duas trilhas:**

- **Trilha A — Estabilização e recuperação (0–4 semanas).** Correções emergenciais, versionamento,
  recuperação de artefatos, limpeza e congelamento. Não depende de decisão arquitetural.
- **Trilha B — Modularização progressiva (ondas).** Kernel → D1/D2 → demais módulos → L1/L2 → PWA GA.
  Cada onda entrega valor em produção, com paridade comprovada.

### 7.2 Trilha A — tarefas e aceite

| # | Tarefa | Entrega | Critério de aceite |
|---|---|---|---|
| A1 | Versionamento | commit das 249 pendências; branch de trabalho | `git status` limpo; clone sobe |
| A2 | Backup e restauração | dump verificado + teste em staging | restauração executada e registrada |
| A3 | Segredos | rotação de chaves; `config/secrets.php` fora do Git | `gitleaks` limpo |
| A4 | Ponte quebrada | remover `api/legacy/session-bridge.php` (sem consumidor) | 404/ausente; docs atualizadas |
| A5 | Recuperação de artefatos | schema spike → `docs/referencia/`; DDL das 27 views → `sql/views/`; registro das 9 migrations → `sql/legacy/`; baseline → `storage/baseline/` | arquivos versionados e legíveis |
| A6 | Limpeza | remover `pnpm-store` e `npm-cache` de `tmp/` (~2,7 GB) após A5 | espaço liberado; nada útil removido |
| A7 | LFI e guarda central | usar `rota_segura.php`; 100% dos endpoints com autorização | teste anônimo = 401; LFI testado |
| A8 | CSRF e uploads | cobertura nos POSTs; validação por conteúdo | checklist de segurança |
| A9 | Escopo de tenant | filtro por certificadora nas consultas críticas | leitura cruzada falha |
| A10 | Triagem documental | marcar v3/v4 como históricos; corrigir 75 referências | docs sem caminho morto |
| A11 | Congelamento | janela de 6–8 semanas sem feature nova no legado | comunicado e changelog |
| **A12** | **Patch de segurança do PHP (P0)** | atualizar para 8.4.27+/8.5.12+ (ou 8.2.35+ como ponte), hardening do `php.ini`, WAF, rotação de segredos e sessões | `php -v` corrigido; app e PDFs funcionando; segredos novos; triagem de logs registrada |
| **A13** | **Avaliação de incidente LGPD** | análise de exploração e risco; comunicação à ANPD/titulares se aplicável; registro por 5 anos | decisão documentada; protocolo (se aplicável) |
| **A14** | **Governança de patches** | SLA ≤ 48h para crítico; assinatura dos avisos; janela de atualização; inventário de runtimes | processo publicado e primeiro ciclo executado |

> **Status em 2026-10-30:** **P0 técnico efetivo** — PHP 8.4.27, `php.ini` endurecido,
> `expose_php=Off`, `disable_functions` ativos, WAF local, `TRACE=405` e segredos/sessões
> rotacionados. Evidências em [`EXECUCAO-A4-HARDENING-PHP.md`](EXECUCAO-A4-HARDENING-PHP.md),
> [`EXECUCAO-A5-WAF.md`](EXECUCAO-A5-WAF.md) e
> [`EXECUCAO-A6-SEGREDOS-SESSOES.md`](EXECUCAO-A6-SEGREDOS-SESSOES.md).
> **Pendências P0:** `RESEND_API_KEY` (manual), logs de produção e decisão LGPD
> ([`EXECUCAO-A7-LOGS-INCIDENTE.md`](EXECUCAO-A7-LOGS-INCIDENTE.md),
> [`EXECUCAO-A8-AVALIACAO-LGPD.md`](EXECUCAO-A8-AVALIACAO-LGPD.md)); WAF de borda sem credenciais.
>
> **Próximo passo operacional:** [`KICKOFF-IMPLEMENTACAO-MODULAR.md`](KICKOFF-IMPLEMENTACAO-MODULAR.md)
> — fundação F1, piloto **K2** e **D1** como primeiro módulo de negócio.
>
> **⛔ Congelamento de features ATIVO desde 30/10/2026** (revisão 11/12/2026; término mínimo
> 18/12/2026). Regras e exceções em [`CONGELAMENTO-FEATURES.md`](CONGELAMENTO-FEATURES.md);
> painel em [`STATUS.md`](../../STATUS.md). **Item 2 da Fase 0 concluído**: commit `796d11d`,
> tag `pre-modularizacao` e branch `feat/f1-kernel` ([relatório](EXECUCAO-FASE0-ITEM2-GIT.md)).
> **Itens 3 e 5 concluídos**: staging anonimizado em `http://localhost/app-staging` + `feature_flags`
> ([item 3](FASE0-ITEM3-STAGING.md)) e paridade K2/D1 ([item 5](FASE0-ITEM5-PARIDADE-K2-D1.md)).
> **Itens 4 e 6 pendentes** (dependências externas/humanas):
> [item 4](FASE0-ITEM4-P0-RESIDUAL.md) e [item 6](FASE0-ITEM6-FISCAIS-ESPECIALISTA.md).
>
> **🚀 F1 iniciada em 30/10/2026 por autorização explícita**, com os itens 4 e 6 ainda pendentes.
> Fundação entregue: tags `f1-fundacao` (`2979360`) e `f1-k2-d1` (`9564ca9`), branch `feat/f1-kernel`.
> **Onda 1** (01/11/2026): K1 identidade/capabilities, K3 auditoria/numeração e L2 manifesto —
> tag `onda1-k1-k3-l2` (`089f246`), 42 testes verdes. Ordem oficial em
> [`PLANO-ONDAS-APROVADO.md`](PLANO-ONDAS-APROVADO.md); rastreabilidade transversal **T1** incluída.
> **Onda 2** (01/11/2026): D1 leitura/escrita core, T1 lotes/eventos/elos/QR, D2 vegetal e D7 insumo —
> tag `onda2-d1-t1-d2` (`4097523`), 49 testes verdes.
> **Onda 3** (01/11/2026): `AccessScope` row-level em D1/T1/D3, visitas/NC/revisão/certificado,
> PDF com hash/HMAC, verificação pública, evidências e E2E autenticado — tags `onda3-d3` (`0c67a84`),
> `onda3-documentos` (`b357201`), `onda3-evidencias` (`e49c7f9`) e `onda3-e2e` (`91a8a0e`).
> **Onda 4** (01/11/2026): animal, sanidade, reprodução, movimentação, transformação, receita/ordem,
> higienização, insumos legados, governança e financeiro — tags `onda4-d5-d6` (`20d6abb`),
> `onda4-animais` (`bd7b4c6`), `onda4-d6-completo` (`7699553`) e `onda4-d7-d8-d4` (`7bf19d1`).
> **Onda 5** (01/11/2026): L1 `relatorios/resumo`, L2 `portal/dashboard` por capability e menu
> filtrado — tag `onda5-l1-l2` (`349383c`), 74 testes verdes. Pendências externas em
> [`PENDENCIAS-FECHAMENTO.md`](PENDENCIAS-FECHAMENTO.md).
> Evidências: [`EXECUCAO-F1-FUNDACAO.md`](EXECUCAO-F1-FUNDACAO.md),
> [`EXECUCAO-F1-K2-D1.md`](EXECUCAO-F1-K2-D1.md),
> [`EXECUCAO-ONDA1-K1-K3-L2.md`](EXECUCAO-ONDA1-K1-K3-L2.md),
> [`EXECUCAO-ONDA2-D1-T1-D2-D7.md`](EXECUCAO-ONDA2-D1-T1-D2-D7.md) e
> [`EXECUCAO-ONDA3-D3.md`](EXECUCAO-ONDA3-D3.md).

### 7.3 Trilha B — fases

| Fase | Objetivo | Entregáveis principais | Esforço | Depende |
|---|---|---|---|---|
| **F1** | Fundação PHP | composer PSR-4, bootstrap único, container, Kernel (contratos), router por módulo, middleware, envelope JSON, erros, auditoria, deptrac/PHPStan/PHPUnit/CI, deploy em 1 comando | 5–7 sem | A1–A8 |
| **F2** | Núcleo do agricultor | D1 completo + K2 + API v1 + páginas finas + portal inicial | 7–9 sem | F1 |
| **F3** | Campo + Suprimentos + PWA offline | D2 + D7 + sync incremental + PWA (bottom nav, formulários, cache) | 9–12 sem | F2 |
| **F4** | Fiscalização | D3 + certificados PDF | 6–8 sem | F3 |
| **F5** | Financeiro | D4 + PIX + caixa/contas | 5–7 sem | F2 |
| **F6** | Produção | D5 + D6 (paralelo) | 8–10 sem | F3 |
| **F7** | Governança | D8 + notificações/assembleias | 4–6 sem | F2 |
| **F8** | Leitura e experiência | L1 (views/relatórios em paridade) + L2 (dashboards) | 8–10 sem | F2–F7 |
| **F9** | PWA GA e endurecimento | piloto, correções, acessibilidade, push, gate nativo, runbooks | 6–8 sem | F3–F8 |

**Paralelização:** no máximo 2 frentes de domínio ao mesmo tempo, sem tabela compartilhada; kernel só
muda em janela com regressão completa.

### 7.4 Caminho crítico, prazo e equipe

```
Mês:        1   2   3   4   5   6   7   8   9  10  11  12  13  14  15
Trilha A:  ██████
F1 Fund.:     ████████
F2 Núcleo:            ██████████
F3 Campo:                        █████████████
F4 Fiscal:                                   ████████
F5 Financ:                     ████████
F6 Produção:                                 ██████████
F7 Govern.:                    ██████
F8 Leitura:            █████████████████████████████
F9 PWA GA:                                       █████████
```

- **Caminho crítico:** A1–A7 → F1 → F2 → F3 → F9.
- **MVP recomendado** (segurança + Kernel + D1 + D2/D7 + PWA offline): **5–7 meses**.
- **Programa completo:** **11–15 meses** com 3–4 pessoas; **16–20 meses** com 2–3.

| Papel | Dedicação |
|---|---|
| Tech lead / arquiteto PHP | 1 |
| Dev backend sênior | 1–2 |
| Dev frontend/PWA | 1 |
| QA automação (PHPUnit + Playwright) | 1 |
| DevOps/segurança | 0,5 |
| Especialista de domínio (SINPORG) | 0,5 |
| Fiscal independente por módulo | rotativo |
| Agentes de codificação | apoio com revisão humana obrigatória |

Esforço estimado: **50–75 pessoas-mês** (inclui paridade, testes, PWA e endurecimento). Sem custo
de reescrita de stack e sem nova infraestrutura além do servidor atual (ou nuvem simples).

### 7.5 Gates

| Gate | Nome | Evidência |
|---|---|---|
| **G0** | Decisão e baseline | decisões assinadas; backup restaurado; Git limpo; segredos rotacionados |
| **G0.1** | **Runtime corrigido** | `php -v` ≥ 8.4.27/8.5.12 (ou 8.2.35 como ponte com data de saída); hardening aplicado; WAF ativo; sessões invalidadas; triagem LGPD registrada |
| **G1** | Fundação | `composer test`, PHPStan e deptrac verdes; deploy 1 comando; `/api/v1/health` |
| **G2** | Kernel | login legado sem trocar senha; autorização 100% declarada; IDOR/tenant reprovam; auditoria gravando |
| **G3** | Módulo pronto | paridade 100%; matriz de autorização verde; cobertura ≥80%; 0 violação de fronteira |
| **G4** | Contrato de API | OpenAPI sem rota não documentada; contrato versionado |
| **G5** | Dados | migração aditiva testada sobre cópia do dump; reversível; reconciliação |
| **G6** | Paridade web | E2E em 360/768/1280 px |
| **G7** | Offline PWA | 10 offline → 10 no servidor; 500 na fila; conflito visível; rede instável |
| **G8** | Paridade de relatórios | diff visual aprovado por relatório |
| **G9** | GA | piloto com 1 associação; acessibilidade AA; runbook; rollback testado |

### 7.6 Dados, migração e rollback

| Tema | Decisão |
|---|---|
| Fonte de escrita | uma por tabela; sem dual-write |
| Migrações | aditivas (`ALTER TABLE ... ADD`), versionadas em `sql/`, testadas em cópia |
| Identificadores | `uuid` já existente; backfill idempotente onde faltar |
| Sync | `atualizado_em` existente; novas colunas só em aditivo |
| Views | DDL versionado; recriar por script, nunca "só no banco" |
| Cutover | por rota/módulo com feature flag; 301 da URL antiga por 30 dias |
| Rollback | desligar flag; código anterior tagueado; banco nunca revertido com perda |
| Retenção | 3 anos em diário de campo, insumos e balanço; soft delete + anonimização |

### 7.7 Segurança

| Frente | Medida | Gate |
|---|---|---|
| Autenticação | sessão segura, regeneração, rate limit, recuperação com token | G2 |
| Autorização | papel+recurso+escopo; 404 fora do escopo; tenant guard | G2 |
| Entrada | validação central + whitelist; prepared statements | G3 |
| Upload | MIME real, re-encode, fora do webroot, nome aleatório | G3 |
| Webhooks | assinatura + idempotência | G3 |
| Segredos | fora do Git, `config/secrets.php`, rotação | G0 |
| Dependências | `composer audit` no CI | G1 |
| LGPD | minimização, CPF mascarado, retenção, auditoria, DPIA | G9 |
| Testes | matriz de autorização + IDOR + tenant no CI | G3 |

### 7.8 Observabilidade

- Log estruturado com `requestId` (sem PII), rotação de logs.
- Healthcheck `/api/v1/health`; monitor de 5xx; alerta de fila de sync e de jobs de relatório.
- Métricas simples: tempo por requisição, taxa de erro, sync pendente, relatórios com falha.
- SLOs: p95 leitura < 500 ms; escrita < 1,5 s; 5xx < 0,1%; sync com sucesso > 99%.

### 7.9 Como os agentes entram

Cada módulo tem construtor e fiscal independentes; o agente não aprova o próprio trabalho; toda
alteração passa por CI e revisão humana; máximo de 2 frentes ativas; worktree separada por frente;
evidência reproduzível obrigatória. Sem isso, velocidade sem direção.

---

## 8. Riscos e mitigação

| # | Risco | Prob. | Impacto | Mitigação | Sinal de alerta |
|---|---|---|---|---|---|
| R1 | Terceira tentativa de reescrita total | Média | Fatal | monólito modular; nenhum big-bang | surgir proposta de "jogar tudo fora" |
| R2 | Regressão em 81 mil linhas sem testes | Alta | Alto | matriz de paridade + golden master + E2E | item PENDENTE no gate |
| R3 | Refatoração quebrar produção | Média | Alto | rota a rota, flag, 301, rollback | erro 500 após deploy |
| R4 | Equipe pequena e sobrecarga | Alta | Alto | MVP curto; cortar escopo, não qualidade | atraso 2 ondas seguidas |
| R5 | PWA não atender iOS/campo | Média | Médio | piloto real; gate nativo (Capacitor) | reclamações de sync no iPhone |
| R6 | Duplicação no sync | Média | Alto | idempotência + testes de rede instável | contagem divergente |
| R7 | Divergência de relatórios | Média | Alto | diff visual + snapshot + views homologadas | número diferente do legado |
| R8 | Documentação obsoleta induzir erro | Alta | Médio | triagem A10; marcar v3/v4 como históricos | alguém seguindo caminho morto |
| R9 | Banco com "migrations fantasma" | Alta | Médio | exportar registro; não usar Prisma; documentar | tentativa de rodar Prisma |
| R10 | Dívida de segurança durante coexistência | Alta | Alto | Trilha A antes das ondas | rota aberta descoberta |
| R11 | Perda de artefatos remanescentes | Média | Médio | A5 antes de A6; backup + versionamento | `tmp` limpo sem cópia |
| R12 | Conflito de arquivos por agentes/frentes | Média | Médio | 2 frentes máx.; worktree; escopo de escrita | merges conflitantes |
| R13 | Fadiga de fiscalização | Média | Alto | rotação; evidência reproduzível | aprovações sem achado |
| R14 | Adoção baixa no campo | Média | Alto | piloto com 1 associação; treinamento | uso semanal estagnado |
| R15 | Mudança regulatória (Portaria 52/2021, CNPO) | Baixa | Médio | catálogo versionado em D7/D3 | nova instrução normativa |
| R16 | Custo oculto de manter dois canais | Média | Médio | PWA-first; nativo só por gate | dois times de app |
| **R17** | **CVE crítica / EOL do runtime PHP** | Alta | Alto | A12/A14; PHP 8.4/8.5; SLA ≤48h; WAF | aviso de segurança sem patch em 48h |
| **R18** | Migração de linguagem duplicar trabalho | Média | Alto | decidir somente no gate pós-MVP; modularizar por API; nunca big-bang | início de reescrita paralela |

**Pontos de falha do próprio plano:**
1. A modularização em PHP exige disciplina de fronteira; sem `deptrac` no CI, volta a ser "pastas".
2. A paridade de relatórios é a parte mais subestimada — por isso tem fase e diff visual.
3. PWA no iOS tem limites reais; o gate nativo precisa ser honesto, não postergado por vaidade.
4. O cronograma depende de congelamento de escopo; sem isso, escorrega.

---

## 9. Critérios de sucesso e Definição de Pronto

### 9.1 Definição de Pronto do programa

| # | Critério | Verificação |
|---|---|---|
| DP1 | 13 unidades com gate aprovado | evidência G3 por unidade |
| DP2 | 0 rotas sem autorização declarada | teste automatizado |
| DP3 | 0 SQL cruzado entre módulos | deptrac/script no CI |
| DP4 | Web cobre as telas do catálogo | E2E autenticado |
| DP5 | PWA offline no campo | teste de 10 registros sem rede |
| DP6 | Relatórios em paridade | diff visual aprovado |
| DP7 | API documentada (OpenAPI 3.1) | gate G4 |
| DP8 | 0 segredos no repositório | `gitleaks` limpo |
| DP9 | Backup com restauração testada | 4 semanas verdes |
| DP10 | Deploy e rollback em 1 comando | runbook executado por terceiro |
| DP11 | Retenção de 3 anos | nenhum DELETE físico indevido |
| DP12 | Observabilidade ativa | alerta de 5xx + fila + jobs |

### 9.2 Métricas de negócio

| Métrica | Meta em 12 meses pós-GA |
|---|---|
| Agricultores com atividade registrada no mês | ≥ 60% dos ativos |
| Tempo para registrar uma atividade | < 90 s |
| Sync com sucesso | > 99% |
| Adesões aprovadas sem retrabalho | > 80% |
| Relatórios gerados sem suporte manual | > 90% |
| Chamados de suporte/usuário/mês | queda de 30% |
| Custo de infraestrutura | dentro do orçamento atual |

---

## 10. O que eu não faria

1. **Terceira reescrita total** — duas tentativas anteriores deixaram rastro; o ativo é a regra.
2. **Microserviços** — 3–4 devs, 31 associados, um banco: complexidade sem retorno.
3. **Reescrever a UI inteira em SPA** — risco alto, benefício baixo; páginas finas resolvem.
4. **Manter `session-bridge` quebrado** — remover é mais seguro que "consertar" sem consumidor.
5. **Dropar as 27 views e o `uuid`** — são ativos gratuitos; versionar e usar.
6. **Rodar Prisma novamente** — as migrations não existem mais; o histórico está no banco.
7. **App nativo antes do piloto PWA** — custo de dois canais sem evidência de necessidade.
8. **Modularizar sem CI de fronteira** — sem `deptrac`, "módulo" vira pasta.

---

## 11. Decisões necessárias e próximos passos

### 11.1 Decisões

| # | Decisão | Opções | Recomendação |
|---|---|---|---|
| D-01 | Estratégia | (A) PHP modular + PWA (B) reconstruir TS (C) só PWA (D) microserviços | **A** |
| D-02 | Congelamento | 6–8 semanas / não | **sim** |
| D-03 | MVP | Campo+Cadastro+PWA / Financeiro / relatórios | **Campo+Cadastro** |
| D-04 | Mobile | PWA-first com gate nativo / nativo já | **PWA-first** |
| D-05 | Artefatos do banco | adotar uuid/views/sync e limpar tokens / ignorar | **adotar e limpar** |
| D-06 | Ponte quebrada | remover / reparar | **remover** |
| D-07 | Limpeza de `tmp/` | preservar artefatos e remover caches / manter | **limpar após A5** |
| D-08 | Paridade | especialista de domínio com horas / sem | **com especialista** |
| D-09 | Equipe/prazo | 3–4 devs (11–15 m) / 2–3 devs (16–20 m) | **3–4 devs + MVP** |
| D-10 | Lojas | só se gate nativo / já | **só por gate** |
| **D-11** | **Patch de segurança P0** | aprovar agora / esperar decisão de linguagem | **aprovar agora, incondicional** |
| **D-12** | **Linguagem** | manter PHP corrigido / migrar já para TS / Java | **manter PHP no curto prazo; decidir no gate pós-MVP** |
| **D-13** | **Controles LGPD** | formalizar agora / depois | **agora, independente da stack** |

### 11.2 Primeiros 10 dias (após aprovação)

1. Reunião de decisão (D-01 a D-10) + ADRs.
2. Commit integral e branch de trabalho (A1).
3. Backup + teste de restauração (A2).
4. Exportar artefatos: schema spike, DDL das views, migrations, baseline (A5).
5. Remover ponte quebrada e ajustar documentação (A4, A10).
6. Corrigir LFI e guarda central (A7).
7. Congelamento comunicado (A11).
8. Designar especialista de domínio e fiscais.
9. Inventariar paridade de D1 e D2 (telas, campos, ações, `arquivo:linha`).
10. Preparar staging com dados anonimizados.

### 11.3 Perguntas para o SINPORG

1. Quantos associados ativos e qual o perfil de dispositivo/conectividade no campo?
2. A ficha de adesão exige assinatura física ou digital?
3. Quem aprova associado e como é a segregação de funções?
4. As certificadoras são externas? Qual o contrato de dados (tenant)?
5. Quais relatórios são obrigatórios por normativa e com qual periodicidade?
6. Prazo de retenção e de resposta a pedidos LGPD?
7. Há verba/janela para app nativo e contas de loja?
8. Quem é a pessoa que valida "isto está igual ao legado"?

---

## Anexo A — Mapa do legado para os módulos

| Origem | Destino |
|---|---|
| `modulos/associados/`, `modulos/associacoes/`, `includes/ficha_adesao_modal.php`, `carteirinha_publica.php` | D1 |
| `modulos/caderno_campo/` (65 arquivos), `rastreabilidade.php` | D2 |
| `modulos/roteiros_inspecao/`, `modulos/certificacao/`, `modulos/conformidade/`, `certificadoras_*.php`, `api/certificacao/` | D3 |
| `modulos/mensalidades/`, `modulos/caixa/`, `modulos/contas/`, `modulos/relatorios_diretoria/`, `api/pix/` | D4 |
| `modulos/producao_animal/`, `modulos/manejo_animal/` | D5 |
| `modulos/agroindustria/` | D6 |
| `modulos/fornecedor/`, `modulos/caderno_campo/insumos/`, `api/insumos_aprovados.php`, `sql/seed_insumos_*` | D7 |
| `modulos/assembleia/`, `modulos/eventos/`, `modulos/comunicacao/`, `includes/lembretes.php`, `widget_eventos.php` | D8 |
| `relatorios.php`, `relatorios/`, `api/relatorios/`, `classes/Report*`, `VisitaReport.php` | L1 |
| `modulos/dashboard/`, `modulos/portal/`, `includes/menu.php`, `header.php`, `footer.php` | L2 |
| `login.php`, `classes/User.php`, `PasswordReset.php`, `CSRFProtection.php`, `ApiAuth.php` | K1 |
| `api/get_cidades.php`, `api/regionalizacao/`, `api/cnpo_consulta.php`, `modulos/regionalizacao/`, `consulta_cnpo/` | K2 |
| `classes/AuditLog.php`, `Mailer.php`, `ImageUploader.php`, `Lembrete.php`, `api/uploads/` | K3 |
| `index.php`, `config/`, `classes/Database.php` | Plataforma (deixa de ser ponto único) |

## Anexo B — Cenário B (migrar de linguagem): custo honesto

> **Emenda 2.1:** o alvo recomendado é **TypeScript/Node.js (NestJS)**; **Java 21/25 LTS** é a
> alternativa de maior assurance. A decisão só deve ocorrer no **gate pós-MVP** — nunca como reação
> à CVE do PHP. Detalhes e matriz comparativa em
> [`PARECER-LINGUAGEM-SEGURANCA-LGPD.md`](PARECER-LINGUAGEM-SEGURANCA-LGPD.md).

Se a organização decidir migrar de linguagem mesmo sem a `sinporg/`:

| Item | Estimativa |
|---|---|
| Recriar fundação (NestJS/Prisma/contratos/SDK/CI) | 4–6 meses |
| Migrar os 8 domínios com paridade | 10–14 meses |
| App nativo (Expo) + lojas | 6–8 meses (paralelo parcial) |
| Total | **18–24 meses** com 4–6 pessoas |
| Riscos | perda de regra não documentada; segunda reescrita já falhou; custo de dois sistemas |
| Pré-requisitos | equipe TS sênior, orçamento, decisão de produto, congelamento total do legado |

**Conclusão:** viável, mas não é o caminho de menor risco hoje. A Opção A deixa essa porta aberta:
com fronteiras e API prontas, cada módulo pode ser extraído depois, um por vez.

## Anexo C — Matriz de canais (resumo)

| Fluxo | Web gestor | Web agricultor | PWA | Offline |
|---|:---:|:---:|:---:|:---:|
| Login/recuperação | ● | ● | ● | não |
| Cadastro/aprovação | ● | — | consulta | não |
| Perfil/ficha/carteirinha | ● | ● | ● | não |
| Atividade de campo | ● | ● | ● | **sim** |
| Insumos | ● | ● | ● | cache |
| Animal/manejo | ● | ● | ● | **sim** |
| Visita/NC | ● | consulta | ● | **sim** |
| Plano de manejo | ● | consulta | ● | parcial |
| Ordem/higienização | ● | ● | ● | parcial |
| Mensalidade/PIX | ● | ● | ● | não |
| Assembleia/voto | ● | ● | ● | não |
| Evento/presença | ● | ● | ● | não |
| Relatórios | ● | — | resumo | não |
| Administração | ● | — | — | não |

## Anexo D — Superfície inicial da API v1

```
POST /api/v1/auth/login          POST /api/v1/auth/logout
GET  /api/v1/auth/me             POST /api/v1/auth/recuperar-senha
GET  /api/v1/kernel/associados?ids=...&campos=ref
GET  /api/v1/kernel/unidades-produtivas
GET  /api/v1/associados          POST /api/v1/associados
GET  /api/v1/associados/{uuid}   PATCH /api/v1/associados/{uuid}
GET  /api/v1/associados/pendentes
GET  /api/v1/publico/carteirinha/{token}
GET  /api/v1/territorios/cidades?uf=RS
GET  /api/v1/propriedades        POST /api/v1/propriedades
GET  /api/v1/atividades          POST /api/v1/atividades
POST /api/v1/sync/atividades     GET  /api/v1/sync/changes?desde=...
GET  /api/v1/insumos/{id}/permitido
GET  /api/v1/relatorios          POST /api/v1/relatorios/{id}/execucoes
GET  /api/v1/health
```

Envelope: `{data, meta}`; erro: `{error:{code,message,details,requestId}}`; `uuid` em URL; centavos
para dinheiro; `Idempotency-Key` em sync/webhooks.

## Anexo E — Checklist de recuperação dos artefatos (Trilha A)

```
[ ] Backup do banco + teste de restauração
[ ] Exportar schema spike para docs/referencia/schema-spike.prisma
[ ] Exportar DDL das 27 views para sql/views/
[ ] Exportar registro de _prisma_migrations para sql/legacy/
[ ] Mover tmp/baseline_orig para storage/baseline/
[ ] Verificar e truncar auth_refresh_tokens (após backup)
[ ] Remover session-bridge (sem consumidor)
[ ] Remover pnpm-store e npm-cache de tmp/ após os itens acima
[ ] Versionar tudo (Git) antes de qualquer limpeza
```

## Anexo F — Triagem da documentação existente

| Documento | Status | Uso |
|---|---|---|
| `docs/v3/01-PRD…` | válido | requisitos, personas, regras, defeitos |
| `docs/v3/02-CATALOGO…` | válido | inventário de telas/ações por módulo |
| `docs/v3/03-MODELO…` | válido com ajuste | ownership e dicionário (banco mudou: 78 tabelas/27 views) |
| `docs/v3/04-CONTRATOS…` | parcial | conceito e matriz de rotas; stack muda |
| `docs/v3/05-ARQUITETURA…` | **obsoleto** | previa TS/monorepo |
| `docs/v3/06-SEGURANCA…` | válido | requisitos e testes |
| `docs/v3/07/08/10` | **obsoleto** | previam execução multiagente TS |
| `docs/v3/09-CRITERIOS…` | válido | DoD, testes, gates |
| `docs/v4/…` (Revisão 1) | superado | manter como histórico |
| `RELATORIO_MODULARIZACAO.md` | válido | diagnóstico de acoplamento |
| `AUDITORIA_SEGURANCA.md` | parcial | correções documentadas; LFI não aplicado |
| `PLANO_MIGRACAO_NODE.md` / `ROADMAP_IMPLEMENTACAO.md` | **obsoleto** | cenário TS |

---

### Encerramento

Este plano responde às três perguntas centrais **no cenário atual, sem a `sinporg/`**:

1. **Como separar?** Modularizando o próprio PHP em 13 unidades lógicas (3 núcleo, 8 domínios,
   2 camadas), com contrato de Kernel, camadas Controller→Service→Repository, API JSON única e
   fronteiras verificadas por `deptrac` no CI — sem terceira reescrita.
2. **Quais módulos?** K1 Identidade, K2 Território, K3 Plataforma, D1 Quadro Associativo,
   D2 Caderno de Campo, D3 Fiscalização, D4 Financeiro, D5 Produção Animal, D6 Agroindústria,
   D7 Suprimentos, D8 Governança, L1 Relatórios, L2 Portal/Dashboards.
3. **Como atender browser e mobile?** Web server-rendered com páginas finas; PWA-first para o
   campo (offline com `uuid` + `atualizado_em` + idempotência); app nativo apenas por gate, via
   Capacitor sobre a própria PWA.

**Nada foi executado.** A execução começa somente após as decisões D-01 a D-10 e o gate G0.
