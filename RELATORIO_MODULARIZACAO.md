# Relatório de Viabilidade de Modularização em Módulos Independentes
## SINPORG — Sistema de Gestão da Produção Orgânica e do Quadro Associativo

**Projeto analisado:** `C:\xampp\htdocs\app` — sistema de gestão da produção orgânica e do quadro associativo (SINPORG)
**Stack:** PHP 8.2 puro (sem framework) + PDO/MySQL-MariaDB 10.4 + Bootstrap 5/DataTables/jQuery no front, PWA com Service Worker e IndexedDB
**Banco em uso:** `sisagro` (87 tabelas, 48 chaves estrangeiras, 1 view)
**Data da análise:** análise estática completa do código + introspecção do schema MySQL
**Escopo medido:** 441 arquivos PHP / 84.734 linhas (excluindo `vendor/`, `node_modules/`, `recicla/`, `producao_bkp/`)

---

## 1. Sumário executivo

### 1.1 Veredito curto

**Sim, é tecnicamente viável separar o sistema em módulos independentes que se comuniquem por um dado comum — mas não no estado atual.** O sistema é um **monólito de páginas** (page-centric), não um monólito modular: existem pastas com nomes de módulos (`modulos/associados`, `modulos/roteiros_inspecao`, etc.), porém **não existem fronteiras reais** entre elas. O que hoje é chamado de "módulo" é apenas um diretório de telas que compartilha:

- o mesmo bootstrap (`config/config.php` → `classes/Database.php`),
- a mesma sessão global (`$_SESSION['user_id'|'user_nivel'|'user_table']`),
- o mesmo banco, com acesso SQL direto de qualquer página a qualquer tabela (168 das 220 páginas de módulo contêm SQL próprio),
- o mesmo shell de UI (`includes/header.php` + `includes/menu.php` + `includes/footer.php`),
- o mesmo roteador central (`index.php`, 1.484 linhas e ~222 blocos de rota).

O "dado comum" que hoje interliga tudo existe de forma **implícita**: é o `associado_id` (e, em segundo eixo, `propriedade_id` / `associacao_id`). Ele nunca foi formalizado como contrato — virou uma FK que cada módulo lê por JOIN direto e, pior, que vários módulos **escrevem** (ex.: `classes/Agroindustria.php:258` faz `UPDATE associados SET tem_agroindustria=1`).

### 1.2 O que a análise encontrou de mais relevante

| # | Achado | Impacto na modularização |
|---|---|---|
| 1 | `associados` é uma **god table**: identidade + autenticação (`senha`, `nivel`) + vínculo + certificação + ficha + notificações | Nenhum módulo é extraível enquanto o cadastro for também a tabela de login |
| 2 | **Roteador monolítico** (`index.php`: 222 rotas, 246 `require`) sem nenhum middleware de autenticação/autorização | Bloqueia extração: não há ponto único para plugar autorização por módulo |
| 3 | **Guarda de segurança por página**: 190 arquivos incluem `header.php` (que só verifica `isLoggedIn`); 51 páginas de módulo não incluem e fazem sua própria checagem; **56 arquivos não têm guarda aparente** e **30 endpoints de API não têm nenhuma verificação** | Fronteira de segurança inexistente — pré-requisito de qualquer separação |
| 4 | **SQL espalhado**: 2.102 comandos SQL em 309 arquivos; 168/220 páginas de módulo com SQL próprio; 44/52 classes também com SQL | Sem camada de acesso a dados, "módulo" não passa de pasta |
| 5 | **Relatórios são o maior agregador cross-domain**: `classes/ReportManager.php`, `classes/ReportConformidade.php` (1.041 linhas) e `api/relatorios/**` (22 arquivos) leem tabelas de 6 domínios diferentes | Devem virar camada de leitura (views/API), não "módulo" |
| 6 | **Duplicações estruturais**: `atividades_diarias` vs `agro_atividades_diarias`; `categorias_atividades` vs `agro_categorias_atividades`; `Ocorrencia` vs `agro_ocorrencias`; `lotes_producao` vs `animal_lotes`; **3 logins** diferentes; 2 esquemas em `password_resets`; 3 pilhas de relatórios | Antes de separar, é preciso decidir qual cópia é a canônica |
| 7 | **Defesa documentada e não aplicada**: `includes/rota_segura.php` (correção B-02 do `AUDITORIA_SEGURANCA.md`) **não é incluído em nenhum arquivo**; `index.php:34-44` ainda concatena o slug da URL no caminho | Sintoma clássico de código sem fronteira |
| 8 | **Já existe um v2 paralelo**: `C:\xampp\htdocs\monorepo` (NestJS + Prisma + Next.js, 39 modelos) e mais ~7 cópias do sistema em `htdocs` | A modularização precisa acontecer *sem* multiplicar a divergência |

### 1.3 Recomendação

**Cenário recomendado: Monólito Modular in-place, com estrangulamento progressivo (Strangler Fig), em 6 fases.** Não reescrever. Não quebrar em microserviços agora. O caminho é:

1. **Fase 0–1** — criar o *kernel* (identidade, sessão, autorização, roteamento, auditoria, migrations) e o *front controller* com middleware — isso não muda comportamento funcional, só a espinha dorsal.
2. **Fase 2** — formalizar o **dado comum** como contrato explícito (`AssociadoRef`, `UnidadeProdutivaRef`, IDs globais) e proibir escrita cruzada.
3. **Fase 3–5** — extrair módulos na ordem de **menor acoplamento primeiro**: Produção Animal → CNPO/Territorial → Suprimentos → Carteirinhas → Financeiro → Fiscalização → Caderno de Campo.
4. **Fase 6 (opcional)** — só então considerar extrair 1–2 módulos como serviço HTTP independente (candidatos: Produção Animal e Fiscalização), usando o monorepo v2 como destino/consumidor.

Os módulos passam a conversar **por contrato** (interface PHP no mesmo processo, mais tarde HTTP/eventos), e o dado comum é o **associado** e sua **unidade produtiva** — não mais um JOIN livre a `associados`.

---

## 2. Metodologia e evidências

A análise foi feita em cinco frentes, todas somente-leitura:

| Frente | O que foi medido | Instrumento |
|---|---|---|
| Inventário estrutural | arquivos, linhas, includes, tamanho por área | varredura recursiva de 441 arquivos PHP |
| Acoplamento de dados | 88 tabelas referenciadas × área consumidora; 48 FKs | regex de SQL no código + `information_schema` do MariaDB |
| Acoplamento de sessão/estado | 40+ chaves de `$_SESSION` × arquivos | regex de sessão |
| Fronteiras e segurança | guardas de página, CSRF, permissões, rotas dinâmicas | leitura dirigida + grep |
| Domínio | análise profunda de 4 blocos (núcleo, associados, fiscalização, produção, institucional/relatórios) | leitura de código com evidência linha a linha |

Arquivos de apoio gerados nesta análise (podem ser descartados):
`tmp/modularizacao/analyze.js`, `tmp/modularizacao/cross.js`, `tmp/modularizacao/guards.js`, `tmp/modularizacao/size.js`, `tmp/modularizacao/metrics.js` e `tmp/modularizacao/mod-analysis.json`.

### 2.1 Números-chave

| Métrica | Valor |
|---|---|
| Arquivos PHP analisados | **441** |
| Linhas de PHP | **84.734** |
| Tabelas no banco `sisagro` | **87** (InnoDB; exceção `cidades` MyISAM) |
| Chaves estrangeiras declaradas | **48** |
| Views / triggers | 1 view (`view_caderno_campo_consolidado`) / 0 |
| Rotas no roteador `index.php` | ~**222** blocos de decisão, 246 `require` |
| Includes de `includes/header.php` | **190** ocorrências (186 arquivos) |
| Páginas em `modulos/` | **220** |
| Páginas de módulo com SQL próprio | **168** (76%) |
| Comandos SQL no código PHP | **2.102** em 309 arquivos |
| Chamadas de permissão | **204** em 147 arquivos |
| Arquivos sem guarda aparente | **56** (inclui 30 APIs sem nenhuma checagem) |
| Links de menu hardcoded | **84** referências a `SITE_URL` em `includes/menu.php` |
| Scripts soltos na raiz / `dev-utils/` | **36** / **33** |
| Commits no repositório | **23** (histórico raso; 3 worktrees de agentes ativas) |

---

## 3. Arquitetura atual

### 3.1 Fluxo de uma requisição

```
Apache (.htaccess: RewriteRule ^(.*)$ index.php?url=$1)
   │
   ├── se a URL casa com arquivo/diretório existente → o .php é executado DIRETO
   │      (ex.: /app/modulos/associados/listar.php, /app/api/relatorios/visao-geral.php)
   │
   └── senão → index.php (roteador de ~222 blocos if)
                 │
                 ├── require 'config/config.php'      → session_start() + constantes
                 ├── require 'classes/<X>.php'         → classes de domínio conforme a rota
                 └── require 'modulos/<modulo>/<pagina>.php'
                        │
                        ├── require 'includes/header.php'  → guarda de login + CSS/JS
                        │      └── require 'includes/lembretes.php' + 'ficha_adesao_modal.php'
                        ├── require 'includes/menu.php'    → navegação + 4 queries cross-domain
                        ├── <SQL direto / new Classe()>
                        └── require 'includes/footer.php'  → modais globais de visita
```

Observações estruturais críticas:

- **Dois caminhos de entrada.** Como o `.htaccess` só reescreve quando o arquivo **não** existe, todo arquivo `.php` é acessível diretamente, sem passar pelo roteador. Isso significa que **qualquer middleware que se crie dentro do `index.php` será contornado** por acesso direto. Hoje isso é agravado pelo item seguinte.
- **O roteador não autentica.** `index.php:7-1165` apenas mapeia slug → arquivo. A checagem de login está delegada ao `header.php:8-11`, que só verifica `isLoggedIn()` — nunca o papel/permissão. Quem não inclui o header, não tem guarda.
- **O sistema já foi reescrito em cima de si mesmo.** Há scripts de migração avulsos (`dev-utils/migrate_*.php`, 20+ arquivos), tabelas órfãs de Laravel (`migrations`, `jobs`, `failed_jobs`, `cache`, `sessions`, `users`) dentro do `sisagro`, e uma view consolidada — evidência de camadas históricas sobrepostas sem contrato.

### 3.2 Tamanho por área (linhas de PHP)

| Área | Linhas | Arquivos |
|---|---:|---:|
| `classes/` | 13.184 | 52 |
| `modulos/caderno_campo` | 13.029 | 64 |
| `relatorios/` | 6.645 | 26 |
| raiz (`index.php`, `login.php`, `relatorios.php`, scripts) | 5.949 | 36 |
| `modulos/associados` | 4.455 | 17 |
| `modulos/agroindustria` | 3.810 | 24 |
| `modulos/roteiros_inspecao` | 3.804 | 12 |
| `api/relatorios` | 2.829 | 22 |
| `includes/` | 2.706 | 11 |
| `dev-utils/` | 2.565 | 33 |
| `modulos/producao_animal` | 2.256 | 17 |
| `modulos/plano_manejo` | 1.862 | 6 |
| `modulos/assembleia`, `eventos`, `certificacao`, `portal`, `mensalidades`, `relatorios_diretoria`, `associacoes`, `fornecedor`, `usuarios`, `caixa`, `dashboard`, `regionalizacao`, `conformidade`, `comunicacao`, `contas`, `manejo_animal`, `admin`, `consulta_cnpo` | 17.873 | 108 |
| `api/` (raiz das APIs) | ~7.000 | 30 |

Maiores arquivos individuais: `index.php` (1.484), `relatorios.php` (1.139), `classes/ReportConformidade.php` (1.041), `login.php` (960), `modulos/portal/area.php` (797), `includes/ficha_adesao_modal.php` (769), `api/roteiros_inspecao.php` (766), `relatorios/index.php` (756), `tools/gerar_manual.php` (752), `classes/Mensalidade.php` (748).


---

## 4. Diagnóstico de acoplamento

### 4.1 As oito dimensões de acoplamento

Um módulo só é independente quando **nenhuma** destas dimensões o liga aos demais. Medimos todas:

| Dimensão | Situação atual | Evidência | Gravidade |
|---|---|---|---|
| **1. Bootstrap** | Cada página monta seu próprio caminho relativo até `config/config.php` (`../`, `../../`, `../../../`); `header.php` e `footer.php` também se auto-incluem | 126 + 92 + 33 includes de `config` com profundidades diferentes; 8 páginas usam caminho relativo ao CWD (`'config/config.php'`) | 🔴 Alta |
| **2. Sessão / identidade** | `$_SESSION['user_id']` lido em **135 arquivos**, `user_nivel` em **117**; não há objeto de contexto de request | 28 áreas distintas leem `user_id` | 🔴 Alta |
| **3. Banco compartilhado** | 87 tabelas no mesmo schema, 48 FKs; **30 tabelas são tocadas por 3+ áreas** | `associados` em 34 áreas; `usuarios` em 23; `propriedades` em 16 | 🔴 Alta |
| **4. Acesso a dados** | Não existe repositório/camada: 168 das 220 páginas de módulo têm SQL; 44 de 52 classes também | 2.102 comandos SQL em 309 arquivos | 🔴 Alta |
| **5. Roteamento** | `index.php` conhece **todas** as rotas de **todos** os módulos; qualquer módulo novo exige editar o arquivo central | 222 blocos `if ($parts[0] === 'x')`, 1.484 linhas | 🔴 Alta |
| **6. UI / navegação** | `menu.php` tem 84 links hardcoded e decide visibilidade por perfil; contém regra de negócio (badges com contagem de `associados` e `ficha_adesao`) | `menu.php:38-39,52-53,218-220` (4 queries cross-domain) | 🟠 Média |
| **7. Relatórios** | Pilha de leitura que atravessa 6 domínios por SQL direto | `classes/ReportManager.php` (711 linhas), `classes/ReportConformidade.php` (1.041 linhas), 22 arquivos em `api/relatorios/` | 🟠 Média |
| **8. Duplicação de conceitos** | O mesmo conceito existe em 2–3 lugares com regras divergentes | 3 logins, 2 `password_resets`, 4 pares de tabelas duplicadas, 3 pilhas de relatórios | 🟠 Média |

### 4.2 Mapa de dependências: quem lê/escreve o quê

O banco tem 48 FKs declaradas, e o **grafo é fortemente centrado** em três nós. Isso é bom (o "dado comum" natural já existe) e ruim (todos dependem dele diretamente).

```
                          ┌──────────────────┐
                          │   associacoes    │
                          └────────┬─────────┘
                                   │
   ┌───────────────┐        ┌──────┴──────────┐         ┌──────────────────┐
   │  usuarios     │        │   associados    │◄────────│  certificadoras  │
   │ (técnico/     │        │  (HUB CENTRAL)  │         └──────────────────┘
   │  gestor)      │        │  + senha/nivel  │
   └───────┬───────┘        └───┬────┬────┬───┘
           │                    │    │    │
           │              ┌─────┘    │    └──────┐
           │              ▼          ▼           ▼
           │        ┌──────────┐ ┌────────┐ ┌──────────────┐
           │        │propriedades│ │ficha_  │ │  visitas_    │
           │        │ (2º HUB)  │ │adesao  │ │  tecnicas    │
           │        └─────┬─────┘ └────────┘ └──────┬───────┘
           │              │                          │
           ▼              ▼                          ▼
   ┌──────────────┐ ┌───────────┐            ┌─────────────────┐
   │ fornecedores │ │ lavouras  │            │nao_conformidades│
   └──────────────┘ └─────┬─────┘            └─────────────────┘
                          │
                          ▼
              ┌───────────────────────┐
              │ atividades_diarias    │ → lotes_producao → ordens_producao
              │ ocorrencias_manejo    │ → rastreabilidade_insumos_processo
              │ insumos / produtos    │ → receitas_agroindustria
              └───────────────────────┘
```

**Fronteiras já violadas (evidências concretas).** Estes são os pontos em que um módulo escreve ou decide sobre o dado de outro:

| Origem | Violação | Arquivo:linha |
|---|---|---|
| Agroindústria → Associados | `UPDATE associados SET tem_agroindustria=1` | `classes/Agroindustria.php:258` |
| Fiscalização → Associados | `User.php` (núcleo) consulta `agroindustrias` para decidir acesso | `classes/User.php:165` |
| Institucional → Associados | `UPDATE associados SET microrregiao_id, cluster_comunicacao` | `modulos/admin/clusters.php:106` |
| Portal → Associados | `UPDATE associados` direto na área do portal | `modulos/portal/area.php:102` |
| Usuários → Associados | `UPDATE associados` de notificações | `modulos/usuarios/salvar_notificacoes.php:29` |
| Financeiro → Financeiro vizinho | `Mensalidade` faz INSERT/DELETE em `pagamentos` e `caixa` | `classes/Mensalidade.php:266,319,324,423` |
| Relatórios → todos | JOIN direto em `associados, propriedades, insumos, lavouras, atividades…` | `classes/ReportManager.php:161-710` |
| Shell → domínios | `menu.php` consulta `associados` e `ficha_adesao` a cada render | `includes/menu.php:38-39,52-53,218-220` |
| Shell → domínios | `header.php` injeta `lembretes.php` (eventos) e `ficha_adesao_modal.php` (associados) em **todas** as páginas | `includes/header.php:142,147` |
| Shell → domínios | `footer.php` injeta os modais de visita técnica em todas as páginas | `includes/footer.php:88-89` |
| Produto → 3 domínios | `Produto::getByAssociadoId` faz INNER JOIN `lavouras→propriedades` e EXISTS sobre `propriedade_membros / agroindustria_membros / ambiente_criacao_membros` | `classes/Produto.php:37-40,94-138,169-198` |
| Fornecedor → 3 domínios | `Fornecedor::getUnidades` une `propriedades` + `agroindustrias` + `ambiente_criacao_animal` | `classes/Fornecedor.php:94-107,216-220` |

### 4.3 O problema-raiz: `associados` é simultaneamente cadastro e tabela de login

```
associados
├── identidade civil ....... nome, sobrenome, cpf, rg, data_nascimento, profissao
├── contato/endereço ....... email, telefone, endereco, cidade, estado, cep
├── AUTENTICAÇÃO ........... senha, nivel, tipo, status, ativo          ← deveria ser do kernel
├── vínculo associativo .... associacao_id, vinculo, vinculo_base, tipo_associado,
│                            status_associado, data_admissao, data_desligamento
├── certificação ........... certificadora_id, tipo_certificacao, registro_cnpo
├── ficha de adesão ........ ficha_enviada, ficha_aprovada, ficha_aprovada_por,
│                            ficha_aprovada_em, ficha_observacao
├── territorialidade ....... microrregiao_id, cluster_comunicacao
├── notificações ........... notificacoes_ativas, dias_antecedencia_evento,
│                            ultima_notificacao_evento
└── integração ............. user_id, tem_agroindustria, area_producao
```

Consequências diretas para a modularização:

1. **Nenhum módulo pode ser extraído para outro banco/serviço** enquanto o login estiver nessa tabela (o kernel não consegue autenticar sem ler o domínio Associados).
2. **`User::getAssociadoIdResolvido()`** (`classes/User.php:272-302`) precisa tentar **4 estratégias** para descobrir o associado do usuário logado — é o sintoma exato da falta de contrato de identidade.
3. **Toda página precisa saber se o ator é "associado" ou "usuário"** (`$_SESSION['user_table']` aparece em 16 áreas). Qualquer serviço de módulo terá que reproduzir essa ambiguidade.

### 4.4 Duplicações que impedem uma fronteira limpa

| Conceito | Cópias | Onde |
|---|---|---|
| Atividade diária | `atividades_diarias` / `agro_atividades_diarias` | `classes/AtividadeDiaria.php` / `classes/AgroAtividadeDiaria.php` |
| Categoria de atividade | `categorias_atividades` / `agro_categorias_atividades` | `classes/CategoriaAtividade.php` / `classes/AgroCategoriaAtividade.php` |
| Ocorrência | `ocorrencias_manejo` / `agro_ocorrencias` | `classes/Ocorrencia.php` / `classes/AgroOcorrencia.php` |
| Lote | `lotes_producao` / `animal_lotes` | `classes/LoteProducao.php` / `classes/ProducaoAnimal.php` |
| Login | 3 implementações | `login.php:75-160`, `classes/User.php:54-94` (**nunca chamado**), `modulos/portal/login.php:88-126` |
| Reset de senha | 2 esquemas de colunas | `classes/PasswordReset.php:52-56` (`token_hash`/`used`) vs `modulos/portal/login.php:144-149` (`token`/`usado`) |
| Relatórios | 3 pilhas | `relatorios.php` (1.139 L), `relatorios/index.php` (756 L) + `api/relatorios/**`, `relatorios_router.php` (**código morto**) |
| Home/dashboards | 4 dashboards + `includes/perfil.php` | `modulos/dashboard/dashboard_{admin,gestor,agroindustria,diretoria}.php` |
| Tabelas Laravel órfãs | `migrations`, `jobs`, `failed_jobs`, `job_batches`, `cache`, `cache_locks`, `sessions`, `users`, `personal_access_tokens` | dentro de `sisagro` |

### 4.5 Segurança como sintoma da ausência de fronteiras

A modularização **não é só estética** — hoje a falta de fronteira é um risco ativo. Achados confirmados nesta análise:

| Achado | Evidência |
|---|---|
| **LFI/Path Injection na rota de relatórios** — a correção documentada em `AUDITORIA_SEGURANCA.md` (B-02) **não está aplicada** | `includes/rota_segura.php` existe mas **nenhum arquivo o inclui**; `index.php:34-44` ainda faz `require __DIR__ . '/api/relatorios/' . $parts[2] . '.php'` |
| **30 endpoints de API sem nenhuma autenticação** (só `session_start()`) | `api/relatorios/{visao-geral,estoque-atual,atividades-diarias,inadimplencia,lista-associados,...}.php`, `api/get_cidades.php`, `api/pre_cadastro.php`, `api/regionalizacao/*` |
| **56 arquivos sem guarda aparente** de login | `modulos/certificacao/editar.php`, `modulos/agroindustria/rastreabilidade.php`, `modulos/associados/carteirinha_pdf.php`, `modulos/caderno_campo/validar_insumo.php`, `modulos/roteiros_inspecao/templates/certificado.php`… |
| **Autorização só por nível numérico** (1/2/3), sem escopo por módulo/recurso | `classes/User.php:104-114`; páginas leem `$_SESSION['user_nivel']` cru (ex.: `modulos/assembleia/pautas.php:16`) |
| **UI usada como autorização** — menu esconde link, mas a URL direta continua funcionando | `includes/menu.php` vs roteador sem middleware |
| **Chave de sessão inexistente em produção** | `includes/header.php:76` lê `$_SESSION['nivel']` (o correto é `user_nivel`) → `window.USER_NIVEL` sempre vazio |
| **Credenciais** de banco `root` sem senha no repositório; API key de e-mail no código | `config/config.php:9-11,25` |
| **Scripts destrutivos expostos no webroot** | 36 `.php` na raiz (`run_sql.php`, `fix_codes.php`, `debug_seed.php`, `check_*.php`), 33 em `dev-utils/`, sem guard de CLI |

> **Consequência para o plano:** nenhuma separação em módulos faz sentido antes de existir um **guard central**. É a Fase 1 e é pré-requisito de todo o resto.

---

## 5. O "dado comum": definindo o kernel compartilhado

Sua premissa — *"módulos independentes que se interligam através de um dado comum"* — é exatamente o desenho correto. A análise mostra que esse dado **já existe de fato**, mas está implícito, duplicado e sobrecarregado. É preciso torná-lo explícito.

### 5.1 Candidatos a chave comum (avaliação)

| Candidato | Estabilidade | Unicidade | Cobertura | Veredito |
|---|---|---|---|---|
| `associados.id` (int) | Alta (surrogate) | Sim (PK) | Alto — é a FK em 11 tabelas | ✅ **Chave primária interna do kernel** |
| `associados.cpf` | Baixa (editável) | `UNIQUE` mas nullable e vazio convertido em NULL | Alto | ⚠️ Chave de negócio secundária, não referência técnica |
| `associados.registro_cnpo` | Alta (registro oficial) | `UNIQUE` | Só produtores certificados | ⚠️ Bom para integração externa (CNPO), não para tudo |
| `associacoes.id` | Alta | Sim | Só o eixo institucional | ✅ Eixo secundário |
| `propriedades.id` | Alta | Sim | Unidades vegetais | ✅ Eixo de "unidade produtiva" |
| `encodeId/decodeId` | — | — | Só ofuscação de URL (AES-CBC) | ❌ Não é identificador de domínio |
| UUID | — | — | Não existe hoje no legado | ✅ **Adicionar** em entidades-núcleo (o v2 já usa) |

### 5.2 Contrato proposto do kernel (o "dado comum")

Proposta de **Kernel de Identidade, Acesso e Territorialidade**, com contrato mínimo e estável:

```php
// Contrato público do kernel — o único caminho pelo qual os módulos enxergam o "dado comum"
interface Kernel\AssociadoRef {
    public function id(): int;            // surrogate interno (associados.id)
    public function uuid(): string;       // NOVO: identificador global, estável entre serviços
    public function nome(): string;       // nome + sobrenome (para exibição)
    public function cpf(): ?string;       // chave de negócio (busca/integração)
    public function registroCnpo(): ?int; // chave de integração externa
    public function status(): string;     // ativo|inativo|pendente; status_associado
    public function tipoAssociado(): ?string;
    public function associacaoPrincipalId(): ?int;
    public function microrregiaoId(): ?int;
}

interface Kernel\UnidadeProdutivaRef {   // abstrai propriedade | agroindustria | ambiente
    public function tipo(): string;        // 'propriedade'|'agroindustria'|'ambiente_criacao'
    public function id(): int;
    public function uuid(): string;        // NOVO
    public function associadoId(): int;
    public function rotulo(): string;
}

interface Kernel\ContextoUsuario {        // substitui o uso cru de $_SESSION
    public function usuarioId(): int;
    public function associadoId(): ?int;   // resolvido UMA vez, de forma canônica
    public function papeis(): array;       // ['admin'|'gestor'|'agricultor'|'agroindustria'|'diretoria'...]
    public function areaProducao(): array; // ['animal','vegetal','agroindustria']
    public function pode(string $recurso, string $acao): bool;  // autorização por módulo
    public function associedadesVisiveis(): array;              // escopo multi-associação
}
```

**Regras de ouro (as "leis" da modularização):**

1. **Um módulo nunca faz JOIN em tabela de outro módulo.** Para exibir o nome do associado numa visita técnica, usa-se `AssociadoRef` (no monólito modular, uma interface PHP; no serviço, um endpoint `GET /kernel/associados/{id}`).
2. **Um módulo nunca faz UPDATE em tabela de outro módulo.** `tem_agroindustria` deixa de existir como coluna em `associados` — vira uma projeção/evento (`AgroindustriaRegistrada`) mantida por *listener*.
3. **Identidade sai do domínio.** `senha`, `nivel`, `tipo`, `status` migram para `kernel_usuarios`; `associados` guarda o *perfil do associado*, apontando para `usuario_id`.
4. **Todo dado comum é lido por referência, nunca por coluna.** Se um módulo precisa de "CPF do produtor", pede ao kernel.
5. **Comunicação entre módulos é por evento ou interface pública — nunca por SQL.**

---

## 6. Decomposição proposta

### 6.1 Módulos (bounded contexts) e viabilidade de extração

Ordenados por **facilidade** (extrair de cima para baixo):

| Ordem | Módulo | Tabelas próprias | LOC atual | Consumidores externos | Acoplamento | Viabilidade |
|---:|---|---|---:|---|---|---|
| 1 | **Produção Animal** | `animais, animal_lotes, manejo_animal_registros, controle_{alimentacao,sanidade,reproducao}_animal, ambiente_criacao_{animal,membros}, densidades_maximas_animais` | ~3.000 | dashboards, `ReportConformidade` | 🔵 Baixo — só `associado_id` | **ALTA** |
| 2 | **Territorialidade & CNPO** | `estados, cidades, mesorregioes_rs, microrregioes_rs, cnpo, insumos_organicos_aprovados` | ~1.500 | todos (leitura) | 🔵 Baixo — referência | **ALTA** |
| 3 | **Suprimentos** | `insumos, fornecedores, fornecedor_unidades` | ~2.500 | caderno, agroindústria, relatórios | 🟡 Médio — `Produto`/`Fornecedor` agregam 3 unidades | **MÉDIA-ALTA** |
| 4 | **Associados & Quadro Associativo** | `associados, associacoes, associado_associacao, ficha_adesao, ficha_adesao_produtos, carteirinhas` | ~6.000 | **quase tudo** | 🟠 Alto — é a god table | **MÉDIA (após kernel)** |
| 5 | **Financeiro da Associação** | `planos_mensalidades, mensalidades, pagamentos, caixa, contas` | ~2.500 | `relatorios_diretoria`, `api/relatorios` | 🟠 Alto — transação cruzada interna | **MÉDIA** |
| 6 | **Eventos, Assembleias & Comunicação** | `eventos, evento_presenca, assembleias, assembleia_pautas, assembleia_votos, notificacoes` | ~4.000 | dashboards, widgets globais | 🟡 Médio | **MÉDIA** |
| 7 | **Agroindústria** | `agroindustrias, agroindustria_membros, agro_setores, agro_*, receitas_agroindustria, ingredientes_receita, ordens_producao, controle_higienizacao, rastreabilidade_insumos_processo` | ~4.500 | dashboards, `ReportConformidade` | 🟡 Médio (lê produtos/insumos/lotes) | **MÉDIA** |
| 8 | **Fiscalização & Conformidade** | `roteiros_inspecao, visitas_tecnicas, nao_conformidades, plano_manejo, plano_manejo_cultivos, certificacao_participativa, certificacao_produtos, certificadoras` | ~6.500 | dashboards, relatórios, caderno (3 leituras) | 🟡 Médio | **MÉDIA-ALTA** |
| 9 | **Caderno de Campo / Produção Vegetal** | `propriedades, propriedade_membros, lavouras, atividades_diarias, categorias_atividades, produtos, lotes_producao, lote_atividade, lote_insumo, ocorrencias_manejo, historico_conversao` | ~14.000 | relatórios, dashboards, fiscalização, agroindústria | 🔴 Alto — é o núcleo produtivo | **BAIXA** (extrair por último) |
| — | **Kernel/Plataforma** | `kernel_usuarios, password_resets, audit_log, sessions, cache` | ~3.000 | — | — | **Fundação** |
| — | **Relatórios & Analytics** | (nenhuma — leitura) | ~9.500 | — | 🔴 Cross-domain | **Não é módulo: é camada de leitura** |
| — | **Portal do Associado / Dashboards** | (nenhuma — BFF) | ~2.500 | — | 🔴 Cross-domain | **BFF, não módulo** |

### 6.2 Grafo-alvo

```
        ┌─────────────────────────── KERNEL ────────────────────────────┐
        │  Identidade · Sessão · Autorização · Territorialidade · CNPO  │
        │  Auditoria · Notificações · Uploads · Eventos (bus + outbox)  │
        └───────┬───────────────┬───────────────┬───────────────┬───────┘
                │ AssociaRef    │ UnidadeRef    │ CtxUsuario    │ eventos
   ┌────────────┴───┐   ┌───────┴────────┐  ┌───┴──────────┐   │
   │ ASSOCIADOS     │   │  SUPRIMENTOS   │  │  PRODUÇÃO    │   │
   │ & QUADRO       │   │  & INSUMOS     │  │  ANIMAL      │   │
   │ ASSOCIATIVO    │   └───────┬────────┘  └──────┬───────┘   │
   └────────┬───────┘           │                  │           │
            │                   │                  │           │
   ┌────────▼───────┐   ┌───────▼────────┐  ┌──────▼───────┐   │
   │ FINANCEIRO     │   │ CADERNO DE     │◄─┤ AGROINDÚSTRIA│   │
   │ DA ASSOCIAÇÃO  │   │ CAMPO (vegetal)│  └──────┬───────┘   │
   └────────┬───────┘   └───────┬────────┘         │           │
            │                   │                  │           │
            │            ┌──────▼──────────────────▼───────┐   │
            │            │  FISCALIZAÇÃO & CONFORMIDADE    │◄──┘
            │            │  roteiros · visitas · NC · cert.│
            │            └──────────────┬──────────────────┘
            │                           │
   ┌────────▼───────────────────────────▼──────────────────────┐
   │  EVENTOS · ASSEMBLEIAS · COMUNICAÇÃO                      │
   └────────┬──────────────────────────────────────────────────┘
            │
   ┌────────▼──────────────────────────────────────────────────┐
   │  RELATÓRIOS / ANALYTICS (somente leitura, via views+API)  │
   └───────────────────────────────────────────────────────────┘

   Regra: setas só apontam para o KERNEL ou para interfaces/eventos.
   Nenhuma seta direta de dados entre módulos de mesmo nível.
```


---

## 7. Estratégias de modularização avaliadas

### 7.1 Comparativo

| Critério | **A. Monólito Modular in-place** (recomendado) | B. Serviços independentes (SOA/micro) | C. Reescrever no v2 (monorepo NestJS) |
|---|---|---|---|
| O que é | Manter PHP + banco único, criar kernel, fronteiras, contratos e middleware; extrair módulos "logicamente" | Extrair módulos como serviços HTTP com banco/API próprios | Jogar fora o legado e continuar `C:\xampp\htdocs\monorepo` |
| Esforço | Médio | Alto | Muito alto |
| Risco de regressão | Baixo (incremental, sempre entregável) | Alto (rede, transações, latência, consistência) | Muito alto (big-bang) |
| Prazo estimado | 6–9 meses (1 dev pleno, meio período para 2 fases) | +6 meses depois de A | 12–18 meses |
| Ganho de negócio no meio do caminho | Sim, a cada fase | Só no fim | Só no fim |
| Reaproveita as 84 mil linhas de regra de negócio | Sim | Sim | **Não** |
| Exige equipe com PHP *e* Node/TS | Não | Não | Sim |
| Resolve o "dado comum" | Sim (contrato do kernel) | Sim (obrigatório) | Sim (Prisma já modela) |
| Aproveitamento do v2 existente | Alto (usa como *referência de modelo* e destino) | Médio | — |

### 7.2 Por que **não** microserviços agora

- **48 FKs ativas.** Separar bancos significa perder integridade referencial declarada e passar a garantir consistência na aplicação + eventos + compensações. Sem testes automatizados (hoje **não há suíte** — só `playwright` instalado), o risco é proibitivo.
- **A god table de identidade.** Todos os módulos dependem de `associados`; enquanto isso existir, qualquer serviço precisa consultar o mesmo banco.
- **Sem guard central.** É impossível ter segurança por serviço se nem o monólito tem.
- **Operação.** Não há CI/CD, fila, observabilidade, nem ambientes separados. Microserviços multiplicariam um problema que ainda não existe.
- **Custo/benefício.** O sistema tem ~30 associados, 16 propriedades, 12 visitas técnicas e 9 usuários. O gargalo **não é escala**, é manutenibilidade. Microserviços resolvem escala organizacional (times autônomos), não manutenibilidade de um dev.

### 7.3 Por que **não** reescrever do zero

Existe um v2 em andamento (`C:\xampp\htdocs\monorepo`): NestJS 11 + Fastify + Prisma 6 + Next.js, com módulos `auth, associados, associacoes, propriedades, atividades, certificacao, roteiros, visitas, nao-conformidades, plano-manejo, uploads, localidades, health` e schema Prisma com 39 modelos. **Isso é valiosíssimo — como contrato de destino**, não como substituto imediato:

- O Prisma já contém o "dado comum" formalizado (`Associado`, `Propriedade`, `Usuario` com relação explícita).
- Modela o kernel com `Usuario` + `RefreshToken`, exatamente o que a seção 5.2 recomenda.
- Permite **estratégia strangler real**: novos módulos já nascem no v2, o kernel do legado passa a integrar por API, e módulos antigos migram quando houver janela.

Hoje, porém, o v2 está **incompleto** (base `sinporg_v2` com 2 associados e 0 visitas; nenhuma migração de dados do `sisagro`), e o legado tem regras de negócio que o v2 ainda não implementou (mensalidades/PIX, carteirinhas, assembleias com votação, assembleias, CADERNO de campo offline, relatórios PDF). Descartar 84 mil linhas de regra testada em produção é o maior risco do projeto.

### 7.4 Recomendação: **A + C combinados (strangler)**

```
Legado (PHP)                          v2 (monorepo NestJS/Prisma)
─────────────────────────────────     ──────────────────────────────
Fase 1-2: KERNEL no legado  ◄──────►  apps/api/src/auth  (JWT compartilhado)
Fase 3: Produção Animal     ─────────►  novo módulo NestJS (primeiro a migrar)
Fase 4: Territorial/CNPO    ─────────►  packages/db/prisma (fonte canônica)
Fase 5: Fiscalização        ─────────►  apps/api/src/visitas|roteiros|nao-conformidades
Fase 6: Relatórios          ─────────►  endpoints read-only + Next.js
Legado permanece como front (PHP) enquanto o backend vira módulos v2.
```

Ou seja: **modularizar o monólito PHP para criar as fronteiras e, ao mesmo tempo, usar o monorepo v2 como destino progressivo dos módulos extraídos** — sem big-bang.

---

## 8. Roadmap proposto

### Fase 0 — Estancar e preparar (2–3 semanas)

| Entrega | Detalhe | Critério de aceite |
|---|---|---|
| Segurança mínima | Aplicar `rota_segura.php` em `index.php`; guard em todas as APIs; remover/neutralizar os 36 `.php` da raiz e 33 de `dev-utils`; tirar credenciais do código | Nenhum endpoint responde dados sem autenticação; LFI fechado |
| Rede de segurança | Suíte de *smoke tests* com Playwright (já instalado) cobrindo as 30 telas principais | Fluxos críticos verdes antes de qualquer refatoração |
| Congelar o mapa | Documentar **ownership de cada uma das 87 tabelas** | `docs/ownership-tabelas.md` |
| Backup/telemetria | Dump versionado + log de erros centralizado | Restauração testada |

### Fase 1 — Kernel e espinha dorsal (4–6 semanas)

| Entrega | Detalhe |
|---|---|
| Front controller único | Rota **todos** os acessos por `public/index.php`; mover o app para fora do webroot (ou usar `.htaccess` que nega execução direta em `modulos/, api/, includes/, classes/`) |
| Router tabelado | Substituir os 222 `if`s por um mapa `rota → [Classe, método, middlewares]` com parâmetros nomeados |
| Middleware de autenticação | Um único ponto: sessão válida ou 401/redirect |
| Autorização por recurso | Substituir `nivel 1/2/3` por papéis + `pode(recurso, ação)`; aposentar `hasPermission` numérico |
| Contrato de sessão | `ContextoUsuario` único; eliminar leitura crua de `$_SESSION` nas páginas |
| Estrutura de módulos | `src/{Kernel,Modulos}/<Nome>/{Http,Application,Domain,Infrastructure}/` com PSR-4 no `composer.json` |
| Migrations | Composer + runner versionado (substituir `sql/*.sql` + `dev-utils/migrate_*.php`) |
| Logs/AuditLog | `AuditLog` já existe (56 chamadas) — oficializar como serviço do kernel |
| CSRF/unificação de login | Um só login; um só esquema de `password_resets` |

### Fase 2 — O dado comum vira contrato (3–4 semanas)

| Entrega | Detalhe |
|---|---|
| UUIDs | Adicionar `uuid` (CHAR(36), único) a `associados, associacoes, propriedades, usuarios` — habilita referência cross-serviço |
| `AssociadoRef` / `UnidadeProdutivaRef` | Interfaces + implementação no legado + endpoint no v2 |
| Separar identidade | `kernel_usuarios` (senha, nivel, papeis) com `associado_id`; `associados` deixa de ser tabela de login |
| Views de leitura | `vw_associado_ref`, `vw_propriedade_ref`, `vw_insumo_ref`… para os relatórios e dashboards |
| Proibir escrita cruzada | Remover os 6 `UPDATE`s externos listados em 4.2 (evento + projeção) |
| Testes de arquitetura | `deptrac` / PHPArkitect no CI: *falha o build* se um módulo importar classe de outro |

### Fase 3 — Extração dos módulos de baixo acoplamento (6–8 semanas)

1. **Produção Animal** → isolado, com API própria; dashboards passam a consumir.
2. **Territorialidade & CNPO** → vira serviço de referência (read-only, cacheável).
3. **Suprimentos & Insumos** → criar *registry de unidades produtivas* para resolver `Produto`/`Fornecedor`.
4. **Carteirinhas** (submódulo de Associados, quase autônomo) → primeira prova de extração simples.

### Fase 4 — Módulos de média complexidade (8–10 semanas)

5. **Financeiro da Associação** (mensalidades + pagamentos + caixa + contas + cobrança) — unificar a escrita hoje espalhada.
6. **Associados & Quadro Associativo** (após o kernel assumir a identidade).
7. **Eventos, Assembleias & Comunicação** (com outbox para e-mails, removendo o envio in-process).

### Fase 5 — Núcleo produtivo e fiscalização (8–10 semanas)

8. **Fiscalização & Conformidade** — extrair SQL das páginas para serviços; remover modais globais do footer.
9. **Caderno de Campo / Produção Vegetal** — o mais acoplado; resolver as 4 duplicações antes.
10. **Agroindústria** — consumir Suprimentos e Caderno via API.

### Fase 6 — Relatórios e dashboards (4–6 semanas)

11. **Relatórios & Analytics** viram camada read-only: nenhum SQL direto em tabela de módulo, apenas views/endpoints autenticados.
12. **Dashboards e Portal** viram BFF consumindo APIs dos módulos.
13. Decidir quais módulos extraídos viram **serviços** de fato (candidatos: Produção Animal e Fiscalização) e desligar as tabelas correspondentes do `sisagro`.

### Estimativa consolidada

| Fase | Duração | Marco de negócio |
|---|---|---|
| 0 — Estancar | 2–3 sem | Segurança e rede de testes |
| 1 — Kernel | 4–6 sem | Um único ponto de autenticação/autorização; router tabelado |
| 2 — Dado comum | 3–4 sem | Contrato de associado/unidade; sem escrita cruzada |
| 3 — Baixo acoplamento | 6–8 sem | 3–4 módulos independentes |
| 4 — Média complexidade | 8–10 sem | Financeiro e Associados isolados |
| 5 — Núcleo | 8–10 sem | Fiscalização e Caderno isolados |
| 6 — Leitura e serviços | 4–6 sem | Módulos conversando por contrato/API |
| **Total** | **~8–11 meses** (1 dev pleno) | Sistema modular, sem big-bang |

Com 2 devs em paralelo após a Fase 1: **~5–7 meses**.

---

## 9. Riscos e mitigação

| # | Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|---|
| R1 | **Regressão funcional** em regras não documentadas (84k linhas, sem testes) | Alta | Alto | Fase 0 obrigatória: smoke tests + testes de caracterização nas telas críticas; nunca refatorar sem rede |
| R2 | **Perda de integridade referencial** ao separar bancos | Média | Alto | Manter banco único até a Fase 5; integridade na aplicação + eventos; FK só é removida após o módulo ter API estável |
| R3 | **Ambiguidade de identidade** (associado vs usuário vs diretoria) | Alta | Alto | Resolver na Fase 2 com `ContextoUsuario` canônico e `kernel_usuarios` |
| R4 | **Divergência com o v2** (`monorepo`) e as ~7 cópias em `htdocs` | Alta | Médio | Definir o v2 como destino oficial e desativar cópias; um único repositório canônico |
| R5 | **Escopo infinito** — querer extrair tudo de uma vez | Alta | Alto | Ordem estrita por viabilidade (seção 6.1); cada fase é entregável e reversível |
| R6 | **Trabalho simultâneo de agentes/worktrees** gerando conflitos | Média | Médio | Uma worktree por frente, com fronteiras de arquivos definidas; merge antes de iniciar a fase seguinte |
| R7 | **Perda de funcionalidades exclusivas do legado** (PIX, PWA offline, PDFs, assembleias) não presentes no v2 | Alta | Alto | Inventário de paridade funcional antes de Fase 3; manter legado como fallback |
| R8 | **Dependência de uma pessoa** (o conhecimento está no código, não em docs) | Alta | Alto | Documentar ownership das tabelas, contratos e ADRs durante as Fases 0–2 |
| R9 | **Custo de manutenção durante a transição** (dois sistemas no ar) | Média | Médio | Strangler com proxy único (`.htaccess`/router) que decide legado vs v2 por rota |

---

## 10. Conclusões e recomendação final

### 10.1 Respondendo objetivamente à pergunta

**É viável separar em módulos independentes que se interligam por um dado comum?**

- **Tecnicamente: sim.** As fronteiras naturais existem e estão claras no banco (48 FKs), nas classes e nas pastas. O dado comum (associado + unidade produtiva) é identificável, estável e já presente.
- **No estado atual: não.** Sem middleware central, sem camada de dados, sem contrato de identidade e com acesso SQL livre entre áreas, "módulo" é apenas nomenclatura. Extrair hoje reproduziria o acoplamento em dobro.
- **O caminho correto é:** construir o **kernel** primeiro (≈2 meses), formalizar o **dado comum** como contrato (≈1 mês) e só então extrair, na ordem de menor acoplamento. Isso produz valor a cada fase, sem parar o sistema e sem descartar as 84 mil linhas de regra de negócio já em uso.

### 10.2 As cinco decisões estruturais que destravam tudo

1. **Front controller único + middleware.** Nada entra no sistema sem passar por um ponto de autenticação/autorização. Isso elimina de uma vez o problema dos 56 arquivos sem guarda e das 30 APIs abertas.
2. **Identidade fora do domínio.** `senha`/`nivel`/`tipo` saem de `associados` para um cadastro central de usuários. É o que torna o módulo Associados extraível.
3. **Contrato de referência.** `AssociadoRef` + `UnidadeProdutivaRef` + UUIDs: os módulos deixam de fazer JOIN e passam a **pedir** o dado comum.
4. **Fim da escrita cruzada.** Seis `UPDATE`s de módulos em tabelas alheias somem e viram eventos + projeções.
5. **Enforcement automático.** Testes de arquitetura no build. Fronteira sem verificação automática não sobrevive a seis meses de manutenção.

### 10.3 O que NÃO fazer

- ❌ Reescrever tudo de uma vez (mesmo com o v2 já iniciado).
- ❌ Ir para microserviços antes do monólito modular.
- ❌ Começar pelo módulo mais acoplado (Caderno de Campo / Associados).
- ❌ Separar bancos antes de existirem contratos e testes.
- ❌ Manter três logins, três relatórios e quatro pares de tabelas duplicadas convivendo — decidir a cópia canônica é parte da Fase 0–2.

---

## Anexo A — Rotas do roteador (agrupadas por prefixo)

| Prefixo | Rotas | Módulo-alvo |
|---|---|---|
| `associados, associado, associacoes, associacao, cadastros-pendentes, cadastro-ficha, fichas-completas, carteirinha, meu-perfil` | ~22 | Associados & Quadro Associativo |
| `dashboard/{admin,gestor,agricultor,agroindustria,diretoria}` | 5 | BFF de Dashboards |
| `caderno/{propriedades,lavouras,insumos,produtos,lotes,ocorrencias,atividades,categorias}` | ~40 | Caderno de Campo |
| `roteiros, visitas, nao-conformidades, certificacao, certificacoes, insumos-aprovados` | ~20 | Fiscalização & Conformidade |
| `agroindustria/**` | ~18 | Agroindústria |
| `producao-animal, manejo-animal` | ~10 | Produção Animal |
| `eventos, evento, assembleia` | ~15 | Institucional |
| `mensalidades, caixa, contas, mensalidade` | ~15 | Financeiro |
| `usuarios, usuario, fornecedor, plano-manejo, regionalizacao, admin, portal, comunicacao` | ~35 | Kernel / módulos diversos |
| `api/**` | ~20 | APIs por módulo |
| `relatorios/**` | ~10 | Camada de leitura |
| `teste, teste-db, teste-ajax, login, logout, completar-perfil` | ~8 | **Remover (debug)** |

## Anexo B — Ownership proposto das tabelas

| Módulo | Tabelas |
|---|---|
| **Kernel** | `kernel_usuarios` (novo) ← `usuarios`, `password_resets`, `audit_log`, `sessions`, `cache`, `notificacoes` |
| **Territorialidade** | `estados, cidades, mesorregioes_rs, microrregioes_rs, cnpo` |
| **Associados** | `associados, associacoes, associacoes_base, associado_associacao, ficha_adesao, ficha_adesao_produtos, carteirinhas` |
| **Financeiro** | `planos_mensalidades, mensalidades, pagamentos, caixa, contas` |
| **Caderno de Campo** | `propriedades, propriedade_membros, lavouras, atividades_diarias, categorias_atividades, produtos, lotes_producao, lote_atividade, lote_insumo, ocorrencias_manejo, historico_conversao` |
| **Produção Animal** | `animais, animal_lotes, manejo_animal_registros, controle_alimentacao_animal, controle_sanidade_animal, controle_reproducao_animal, ambiente_criacao_animal, ambiente_criacao_membros, densidades_maximas_animais` |
| **Suprimentos** | `insumos, insumos_organicos_aprovados, fornecedores, fornecedor_unidades` |
| **Agroindústria** | `agroindustrias, agroindustria_membros, agro_setores, agro_categorias_atividades, agro_atividades_diarias, agro_ocorrencias, receitas_agroindustria, ingredientes_receita, ordens_producao, controle_higienizacao, rastreabilidade_insumos_processo` |
| **Fiscalização** | `roteiros_inspecao, visitas_tecnicas, nao_conformidades, plano_manejo, plano_manejo_cultivos, certificacao_participativa, certificacao_produtos, certificadoras` |
| **Institucional** | `eventos, evento_presenca, assembleias, assembleia_pautas, assembleia_votos` |
| **Descartar** | `migrations, jobs, failed_jobs, job_batches, cache_locks, users, personal_access_tokens, entidades, produtores, participacoes, caderno_campo, culturas, agroindustria_existentes, agroindustria_atualizacoes, associacoes_base, certificacao_produtos` (após validação) |

## Anexo C — Itens de saneamento imediato (não dependem de modularização)

1. Aplicar `includes/rota_segura.php` em `index.php` (fecha o LFI B-02 documentado como corrigido).
2. Autenticar os 30 endpoints de API sem guarda.
3. Remover/proteger os 36 `.php` da raiz e os 33 de `dev-utils/` (`run_sql.php`, `fix_codes.php`, `debug_seed.php`, `update_associados_regiao.php` são destrutivos e estão no webroot).
4. Corrigir `includes/header.php:76` (`$_SESSION['nivel']` → `user_nivel`).
5. Corrigir os caminhos quebrados `../../../config` em `api/regionalizacao/microrregioes.php:5` e `api/comunicacao/whatsapp_links.php:5`.
6. Retirar `classes/ReportManager::getEventosReport` inexistente de `relatorios/eventos.php:60` (página quebrada) e apagar `relatorios_router.php` (código morto).
7. Mover credenciais de `config/config.php` para variáveis de ambiente.
8. Unificar os 3 logins e o esquema de `password_resets`.

---

*Relatório gerado por análise estática completa de `C:\xampp\htdocs\app` (441 arquivos PHP / 84.734 linhas), introspecção do schema `sisagro` (87 tabelas / 48 FKs) e leitura dirigida dos módulos de Associados, Fiscalização, Produção, Institucional/Relatórios e Núcleo/Auth. Nenhum arquivo da aplicação foi modificado.*

