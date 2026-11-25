# PRD — SINPORG v3
## Product Requirements Document — Replicação em Plataforma Moderna, Segura e de Deploy Simples

| Campo | Valor |
|---|---|
| **Produto** | SINPORG — Sistema de Gestão da Produção Orgânica e do Quadro Associativo |
| **Versão do documento** | 3.0 |
| **Sistema atual** | Monólito PHP 8.2 (84.734 linhas, 441 arquivos), MySQL/MariaDB `sisagro` (77 tabelas, 48 FKs, 1 view) |
| **Sistema alvo** | TypeScript — monorepo NestJS 12 + Prisma 7 + Next.js 16 + Expo SDK 57 |
| **Estratégia** | Migração incremental (*strangler fig*) com agentes construtores e fiscais por módulo |
| **Documentos irmãos** | `02` catálogo de telas · `03` dados · `04` API · `05` arquitetura · `06` segurança · `07` prompt · `08` agentes · `09` critérios · `10` roadmap |

---

## 1. Sumário executivo

O SINPORG é um sistema de gestão para agricultura familiar orgânica e certificação
participativa (SPG/OCS), usado por uma associação do Rio Grande do Sul. Ele cobre o
quadro associativo, o caderno de campo digital com funcionamento **offline**, a
fiscalização e certificação, o financeiro com cobrança PIX, a produção animal e a
agroindústria, a governança (assembleias e eventos) e uma camada ampla de relatórios.

**O sistema funciona** — está em produção e concentra regras de negócio valiosas e
regulatórias (Lei 10.831/2003, Decreto 6.323/2007, Portaria MAPA 52/2021, IN 50/2009).
O problema **não é funcional, é estrutural e de segurança**.

### 1.1 Diagnóstico em uma página

| Dimensão | Situação medida |
|---|---|
| **Arquitetura** | Monólito *page-centric*: 441 arquivos PHP, 194 ramos de rota em um `index.php` de 1.484 linhas, 2.102 comandos SQL em 309 arquivos, sem camada de dados |
| **Fronteiras** | Inexistentes. 168 das 220 páginas de módulo contêm SQL próprio; `associados` é tocada por 34 áreas |
| **Identidade** | `associados` é simultaneamente cadastro **e** tabela de login (senha, nível, status), com 17 campos de autenticação misturados a dados civis |
| **Autenticação** | 3 implementações distintas de login; sem `session_regenerate_id`; sem rate limit |
| **Autorização** | Nível numérico (1/2/3) lido cru da sessão; **56 arquivos sem guarda**; **~30 endpoints de API sem nenhuma verificação**; menu esconde link mas a URL direta funciona |
| **Multi-tenant** | `certificadora_id` existe mas é usado apenas para *branding*: **zero} filtro por certificadora nas consultas** |
| **Segurança** | Corrigidos: RCE por upload, LFI, SQLi de tabela, XSS por SVG, IDOR, CSRF, *debug* em produção, segredos versionados. **Abertos:** webhooks PIX sem assinatura, CSRF ausente em ~45 telas, exclusão por GET, IDOR em vários endpoints |
| **Dados** | 45 das 77 tabelas sem nenhuma FK; 4 tabelas mortas; 6 pares de tabelas duplicadas; 10 convenções de *timestamp*; 71 ENUMs; mojibake em 34 comentários; **perda silenciosa de dados já ocorrida** em 2 colunas ENUM |
| **Qualidade** | Nenhuma suíte de testes; bugs confirmados que quebram funcionalidades inteiras (cadastro de certificação, cadastro de fornecedor, fluxo PIX, rastreabilidade animal) |
| **Operação** | XAMPP/Apache com `RewriteBase` fixo; credenciais `root` sem senha; 36 scripts destrutivos no *webroot*; ~20 migrações manuais; sem CI/CD, sem staging, sem rollback |
| **Deploy** | Processo manual, sem reprodutibilidade, sem healthcheck |

### 1.2 O que foi construído no legado e **precisa ser preservado**

- **Máquinas de estado** do associado, da ficha de adesão, da visita técnica, da
  não conformidade, da certificação, da assembleia e do evento.
- **Regras regulatórias**: bloqueio de insumo não permitido (Portaria 52/2021), cálculo do
  percentual orgânico e selo (IN 50/2009), segregação e higienização (Decreto 6.323/2007),
  balanço de massas, retenção de 3 anos (Lei 10.831/2003).
- **Motor offline** (`db_sync.js`, IndexedDB) — prova de conceito funcional de campo sem rede.
- **Rastreabilidade por QR Code** do lote até a lavoura e os insumos.
- **67 ENUMs e 77 tabelas** com semântica de domínio já estabilizada.
- **Relatórios** com layout aprovado (templates HTML + `relatorio_estilo.css`).
- **Rotinas de importação** da base CNPO do MAPA (23.232 registros).

### 1.3 O que já existe do destino

Há um monorepo `sinporg/` **em andamento** (NestJS 12 + Fastify + Prisma 7 + Next.js 16 +
Expo 57) com:

- `prisma/schema.prisma` com **76 modelos e 67 enums**; baseline já gerada;
- `apps/api` com módulos `auth`, `associados`, `caderno-campo`, `relatorios`, `sync`, `health`;
- `packages/contracts` (Zod) e `packages/sdk` (com fila offline);
- `apps/web` (login, atividades, sincronização) e `apps/mobile` (tabs, login, sync);
- `infra/docker-compose.yml` e `.github/workflows/ci.yml`.

**Este PRD trata o monorepo como a base oficial do destino** — não como um projeto novo.
Ele define o que falta, com que critério de aceite e sob que método.

### 1.4 A decisão central deste PRD

> **Não haverá big-bang.** O banco `sisagro` **já contém os escombros de uma reescrita em
> Laravel que não terminou** (`users`, `sessions`, `cache`, `jobs`, `failed_jobs`,
> `migrations`, `password_reset_tokens`, `associacoes_base`, `produtores` — removidas
> apenas na Fase 0). A diferença entre uma migração que termina e outra que vira escombro
> **não é a linguagem: é o método**.

O método adotado é: **kernel primeiro → contrato do dado comum → ondas verticais por
módulo → par construtor/fiscal em ciclo fechado → desligamento do PHP só no fim.**

---

## 2. Visão do produto

### 2.1 Problema de negócio

Associações de agricultura orgânica precisam, para manter a certificação participativa:

1. **Provar conformidade** a cada auditoria (SisOrg, OAC, SPG/OPAC) — diário de campo,
   aplicação de insumos, plano de manejo, visitas, não conformidades;
2. **Registrar no campo**, onde não há internet, sem perder dados;
3. **Rastrear** do lote final até a parcela, os insumos e as atividades;
4. **Gerir o quadro associativo** e o financeiro (mensalidades, inadimplência, caixa);
5. **Prestar contas** à diretoria e à assembleia.

### 2.2 Objetivos do v3

| # | Objetivo | Métrica |
|---|---|---|
| O1 | Eliminar a falta de fronteira de segurança | 100% das rotas com guarda; 0 rotas públicas não declaradas |
| O2 | Tornar o sistema manutenível | 1 módulo por *bounded context*; 0 violação de fronteira no CI |
| O3 | Ter app nativo de campo com offline confiável | 0 duplicata e 0 perda em sincronização |
| O4 | Deploy reprodutível e reversível | Deploy e rollback em 1 comando |
| O5 | Preservar 100% das regras de negócio | Matriz de paridade completa por módulo |
| O6 | Atender ao regulatório sem exceção | 6 requisitos REG do checklist `09` verdes |
| O7 | Reduzir o custo de mudança | Nova funcionalidade não exige editar arquivo central |

### 2.3 Não-objetivos

- Trocar de banco (MariaDB permanece; o alvo é LTS 11.4).
- Reescrever o layout dos relatórios (reaproveita-se o HTML/CSS com Puppeteer).
- Introduzir microserviços agora (o gargalo é manutenibilidade, não escala: **30
  associados, 16 propriedades, 12 visitas, 12 usuários, 1.505 registros operacionais**).
- Multi-tenant como SaaS aberto (o escopo por certificadora é organizacional, não comercial).

---

## 3. Personas e perfis

| Perfil | Origem | Papel no v3 | Acesso |
|---|---|---|---|
| **Associado Agricultor** | `associados` (`tipo_usuario=agricultor`) | Registra o dia a dia, vê sua certificação e carteirinha | App mobile + portal próprio |
| **Associado Produtor de Agroindústria** | `associados` (`tipo_usuario=produtor_agroindustria`) | Receitas, ordens, higienização, rotulagem | App mobile + portal (**dashboard próprio é novidade do v3**) |
| **Técnico / Gestor** | `usuarios` (nível 2) | Visitas, roteiros, aprovações, conformidade | Web (dense) + mobile para campo |
| **Administrador** | `usuarios` (nível 1) | Gestão total, usuários, auditoria | Web |
| **Diretoria** | `usuarios` + papel | Assembleias, eventos, relatórios institucionais | Web |
| **Certificadora** | `certificadoras` | Escopo *tenant*: só vê seus produtores | Web (escopada) |

**Decisão de arquitetura (ADR-003):** a identidade sai do domínio. `senha`, `nivel` e
`papeis` migram para `kernel_usuarios`; `associados` guarda o **perfil do produtor** e
aponta para o usuário. É o que destrava a extração do módulo Associados.

---

## 4. Jornadas principais

### J1 — Cadastro e aprovação do associado
Pré-cadastro público → fila de aprovação → confirmação de privilégios pelo admin →
preenchimento e assinatura da ficha de adesão → aprovação (gera número `AAES-#####`, cria o
associado e vincula as duas tabelas) → emissão da carteirinha com QR de validação pública.

### J2 — Registro no campo (offline)
Agricultor abre o app sem rede → registra atividade/insumo/ocorrência → dado gravado local
imediatamente → **validação de insumo bloqueia não permitido** → ao reconectar, a fila
sincroniza **com chave de idempotência** → nenhuma duplicata, nenhuma perda.

### J3 — Ciclo de certificação
Agendamento de visita → execução do roteiro (checklist, evidências, assinatura do produtor)
→ pontuação e conformidade → geração automática de não conformidades → plano de ação com
prazo → acompanhamento até resolvida → emissão/renovação do certificado com histórico
preservado.

### J4 — Processamento na agroindústria
Receita com ingredientes → cálculo do percentual orgânico → **selo autorizado (ou bloqueio)**
→ higienização prévia obrigatória → ordem de produção consumindo lote do campo → balanço de
massas com alerta → QR Code do lote final.

### J5 — Ciclo financeiro
Plano de mensalidade → geração em lote (idempotente) → cobrança PIX → webhook validado e
idempotente → baixa transacional → caixa → relatórios de inadimplência e fluxo.

### J6 — Governança
Convocação de assembleia com pautas → publicação (notifica associados) → votação (1 voto por
associado) → encerramento → resultado imutável.

---

## 5. Escopo funcional

O sistema é decomposto em **12 módulos** (detalhamento de telas, campos e ações em
`02-CATALOGO-DE-MODULOS-E-TELAS.md`).

| ID | Módulo | Requisitos funcionais |
|---|---|---|
| **M1** | Associados & Quadro Associativo | RF-M1-01 a 14 |
| **M2** | Caderno de Campo (Produção Vegetal) | RF-M2-01 a 16 |
| **M3** | Fiscalização & Conformidade | RF-M3-01 a 18 |
| **M4** | Financeiro da Associação | RF-M4-01 a 12 |
| **M5** | Produção Animal | RF-M5-01 a 10 |
| **M6** | Agroindústria | RF-M6-01 a 14 |
| **M7** | Suprimentos & Insumos | RF-M7-01 a 07 |
| **M8** | Governança, Eventos & Comunicação | RF-M8-01 a 12 |
| **M9** | Territorialidade & CNPO | RF-M9-01 a 06 |
| **M10** | Relatórios & Analytics | RF-M10-01 a 09 |
| **M11** | Portal & Dashboards | RF-M11-01 a 08 |
| **M12** | Auditoria, Configuração & Administração | RF-M12-01 a 09 |

### 5.1 M1 — Associados & Quadro Associativo

| ID | Requisito | Prioridade |
|---|---|---|
| RF-M1-01 | Cadastrar associado com validação de CPF (dígito verificador) e unicidade | Must |
| RF-M1-02 | Pré-cadastro público com fila de aprovação e confirmação de privilégios | Must |
| RF-M1-03 | Máquina de estados: `pendente` → `ativo` → `inativo`, separada de `status_associado` (Ativo, Inadimplente, Afastado, Desligado, Falecido) | Must |
| RF-M1-04 | Ficha de adesão com assinatura, produtos declarados, situação fundiária e zona | Must |
| RF-M1-05 | Fluxo da ficha: rascunho → pendente → aprovada/recusada, com reenvio | Must |
| RF-M1-06 | Aprovação da ficha gera número sequencial e cria o associado, vinculando identidade | Must |
| RF-M1-07 | Vínculo associado ↔ associação (múltiplos vínculos com tipo e datas) | Must |
| RF-M1-08 | Carteirinha com token opaco, validade, QR de validação **pública** | Must |
| RF-M1-09 | PDF da carteirinha (2 formatos: resumo 100×160 e completo A5 paisagem com foto e situação financeira) | Should |
| RF-M1-10 | Envio de carteirinha por e-mail, individual e em massa (assíncrono) | Should |
| RF-M1-11 | Autoatendimento do associado (meu perfil) com campos restritos | Must |
| RF-M1-12 | Exclusão como **soft delete** + anonimização (LGPD × retenção regulatória) | Must |
| RF-M1-13 | Geração de QR **localmente** (sem enviar dados a `api.qrserver.com`) | Must |
| RF-M1-14 | Carteirinha válida **reenviável** sem invalidar a anterior (histórico) | Should |

### 5.2 M2 — Caderno de Campo

| ID | Requisito | Prioridade |
|---|---|---|
| RF-M2-01 | CRUD de propriedades (área, coordenadas, barreiras, zona de amortecimento, membros) | Must |
| RF-M2-02 | Histórico de uso do solo e **cálculo do período de conversão** restante | Must |
| RF-M2-03 | CRUD de lavouras com cultura, área, plantio e colheita prevista | Must |
| RF-M2-04 | CRUD de categorias de atividade (por associado) | Must |
| RF-M2-05 | Registro de atividade diária com tipo, período, categoria, lavoura, insumos e fotos | Must |
| RF-M2-06 | CRUD de insumos com composição, fabricante, lote e validade | Must |
| RF-M2-07 | **Validação bloqueante** de insumo contra a lista oficial (Portaria 52/2021) | Must |
| RF-M2-08 | Validação de insumo deve usar **correspondência exata/normalizada**, não `LIKE %termo%` | Must |
| RF-M2-09 | CRUD de produtos com unidade e tipo de unidade produtiva | Must |
| RF-M2-10 | CRUD de lotes de produção com quantidade e unidade | Must |
| RF-M2-11 | **Rastreabilidade** lote → produto → lavoura → propriedade → atividades → insumos, consultável por QR | Must |
| RF-M2-12 | CRUD de ocorrências de manejo com severidade e status | Must |
| RF-M2-13 | **Offline**: registrar atividade, insumo e ocorrência sem rede, com sincronização idempotente | Must |
| RF-M2-14 | Sincronização deve cobrir **criação e edição**, com resolução de conflito explícita | Must |
| RF-M2-15 | **Soft delete** em atividades, insumos e lotes (retenção de 3 anos) | Must |
| RF-M2-16 | Relatórios do caderno (atividades, insumos, produção, lotes, propriedades, produtores, ocorrências) em PDF e Excel | Must |

### 5.3 M3 — Fiscalização & Conformidade

| ID | Requisito | Prioridade |
|---|---|---|
| RF-M3-01 | CRUD de templates de roteiro (checklist com itens, obrigatoriedade e **peso**) | Must |
| RF-M3-02 | Tela de **edição** de roteiro (inexistente no legado) e geração da ficha | Must |
| RF-M3-03 | Agendamento e execução de visita com checklist, evidências e assinatura | Must |
| RF-M3-04 | Pontuação deve **aplicar o peso** do item (o legado ignora o peso) | Must |
| RF-M3-05 | Item crítico não conforme **reprova automaticamente** | Must |
| RF-M3-06 | Assinatura do produtor armazenada como **arquivo/blob** (nunca VARCHAR(255)) | Must |
| RF-M3-07 | Ciência ou recusa do produtor, com justificativa | Should |
| RF-M3-08 | Geração automática de NC com gravidade na escala completa (Baixa, Média, Alta, Crítica) | Must |
| RF-M3-09 | NC com ação corretiva, prazo, responsável e evidência de correção | Must |
| RF-M3-10 | Transições de status de NC validadas (Aberta → Em Correção → Resolvida) | Must |
| RF-M3-11 | CRUD de certificação (SPG, OCS, Rede Ecovida, Outro) sobre as **colunas reais** do schema | Must |
| RF-M3-12 | Renovação **cria novo registro** e preserva o histórico | Must |
| RF-M3-13 | Job diário promove certificação vencida para status "Vencida" | Must |
| RF-M3-14 | Certificado em PDF com QR de verificação pública e layout por certificadora | Must |
| RF-M3-15 | CRUD de certificadoras com logo, em **uma única API** e com CSRF | Must |
| RF-M3-16 | Plano de manejo com áreas, práticas, cultivos, croqui e aprovação | Must |
| RF-M3-17 | Croqui com URL **relativa**, validação de conteúdo e verificação de posse | Must |
| RF-M3-18 | Relatórios de conformidade (balanço de massas, rastreabilidade, validação de insumos, histórico de conversão, manejo animal) em PDF, Excel e HTML | Should |

### 5.4 M4 — Financeiro

| ID | Requisito | Prioridade |
|---|---|---|
| RF-M4-01 | CRUD de planos de mensalidade (tipo, valor, dia de vencimento) | Must |
| RF-M4-02 | Geração de mensalidades em lote, **idempotente e atômica** | Must |
| RF-M4-03 | Geração deve usar valor e dia do **plano** (o legado exige digitação manual) | Must |
| RF-M4-04 | Lançamento manual de mensalidade | Must |
| RF-M4-05 | **Cobrança PIX** com 3 provedores (Asaas, Gerencianet, Mercado Pago) | Must |
| RF-M4-06 | Coluna `txid` em `mensalidades` (**ausente no legado — quebra o fluxo**) | Must |
| RF-M4-07 | Webhook com **validação de assinatura** sobre o corpo bruto | Must |
| RF-M4-08 | Webhook **idempotente** por `event_id`; sem baixa dupla sob concorrência | Must |
| RF-M4-09 | Baixa transacional com gravação de `pagamentos.txid` e valor efetivo | Must |
| RF-M4-10 | Estorno **reversível e auditado** (o legado faz DELETE físico) | Must |
| RF-M4-11 | Caixa com lançamentos, saldo e fluxo por mês | Must |
| RF-M4-12 | Contas a pagar/receber **integradas ao caixa** (no legado são subsistemas isolados) | Should |

### 5.5 M5 — Produção Animal

| ID | Requisito | Prioridade |
|---|---|---|
| RF-M5-01 | CRUD de lotes de animais | Must |
| RF-M5-02 | CRUD de animais com sexo, raça, origem de conversão e tipo de entrada | Must |
| RF-M5-03 | CRUD de ambientes de criação com área, sistema e capacidade | Must |
| RF-M5-04 | **Validação de densidade máxima** por espécie/ambiente (tabela sem seed no legado) | Must |
| RF-M5-05 | Controle de alimentação com origem (orgânica, conversão, convencional autorizada) e justificativa | Must |
| RF-M5-06 | Controle de sanidade com tipo de tratamento e **cálculo de carência** | Must |
| RF-M5-07 | Controle de reprodução **com `lote_id` e `especie`** (colunas ausentes no legado) | Must |
| RF-M5-08 | Desmame com idade mínima por espécie (bovino 90 d, ovino/caprino 28 d, suíno 45 d) | Must |
| RF-M5-09 | Manejo animal (registros avulsos) com histórico por animal | Should |
| RF-M5-10 | Rastreabilidade animal por **token opaco**, sem enumeração por ID sequencial | Must |

### 5.6 M6 — Agroindústria

| ID | Requisito | Prioridade |
|---|---|---|
| RF-M6-01 | CRUD de unidades de agroindústria | Must |
| RF-M6-02 | CRUD de setores, categorias e atividades diárias | Must |
| RF-M6-03 | CRUD de ocorrências com severidade | Must |
| RF-M6-04 | CRUD de receitas com ingredientes, quantidade e unidade | Must |
| RF-M6-05 | **Cálculo do percentual orgânico** normalizando massa e volume corretamente | Must |
| RF-M6-06 | Classificação do selo: ≥95% Orgânico Brasil; 70–95% "com ingredientes orgânicos"; <70% **bloqueia menção** | Must |
| RF-M6-07 | Edição de receita **não pode reescrever retroativamente** a rastreabilidade de lotes passados | Must |
| RF-M6-08 | Livro de higienização com sanitizantes permitidos e **vínculo a fornecedor/equipamento** | Must |
| RF-M6-09 | **RF08**: ordem de produção de lote orgânico exige higienização prévia **do mesmo produtor** | Must |
| RF-M6-10 | Ordem de produção consumindo lote primário do campo (**RF10** — ignorado no legado) | Must |
| RF-M6-11 | **Balanço de massas** com unidades normalizadas, distinguindo ganho de perda | Must |
| RF-M6-12 | Alerta automático quando saída excede entrada | Must |
| RF-M6-13 | QR Code do lote final com `lote_processado_final` **único** | Must |
| RF-M6-14 | Rastreabilidade pública sem expor CNPJ de fornecedor nem número de nota fiscal | Must |

### 5.7 M7 — Suprimentos & Insumos

| ID | Requisito | Prioridade |
|---|---|---|
| RF-M7-01 | CRUD de fornecedores **funcional** (o legado chama método inexistente e POSTa para rota inexistente) | Must |
| RF-M7-02 | Unidades do fornecedor resolvidas pelo *registry* do kernel (não por JOIN a 3 domínios) | Must |
| RF-M7-03 | Manter a lista oficial de insumos aprovados versionada e atualizável | Must |
| RF-M7-04 | Busca por nome, nome comercial, princípio ativo e fabricante | Must |
| RF-M7-05 | Classificação de tipo de insumo: Orgânico, Em conversão, Convencional, Não permitido | Must |
| RF-M7-06 | Corrigir os tipos de `fornecedores.cidade`/`estado` (int → referência) e coordenadas de propriedade (varchar → decimal) | Must |
| RF-M7-07 | Auditoria de NF, lote e validade do insumo por fornecedor | Should |

### 5.8 M8 — Governança, Eventos & Comunicação

| ID | Requisito | Prioridade |
|---|---|---|
| RF-M8-01 | CRUD de assembleias com convocação, data, local e tipo de votação | Must |
| RF-M8-02 | Pautas com tipo (aprovação, eleição, outros) e opções quando em rascunho | Must |
| RF-M8-03 | Publicação notifica os associados | Must |
| RF-M8-04 | Votação: **1 voto por associado por pauta**, auditável, com IP e *user agent* | Must |
| RF-M8-05 | Votação acessível a **qualquer** sessão de associado (o legado só aceita a do Portal) | Must |
| RF-M8-06 | Encerramento bloqueia votação e **congela o resultado** | Must |
| RF-M8-07 | Resultado com quórum, aptos e exportação CSV | Must |
| RF-M8-08 | CRUD de eventos com data/hora (o legado perde a hora) e status | Must |
| RF-M8-09 | Confirmação de presença em **uma única fonte** (o legado grava em JSON e em tabela) | Must |
| RF-M8-10 | Lista de presença e relatório, com escopo de acesso correto | Must |
| RF-M8-11 | Notificações e lembretes com antecedência configurável por associado | Must |
| RF-M8-12 | Central de comunicação por cluster, com envio de e-mail e links de WhatsApp | Should |

### 5.9 M9 — Territorialidade & CNPO

| ID | Requisito | Prioridade |
|---|---|---|
| RF-M9-01 | Dados de referência: estados, cidades, mesorregiões e microrregiões do RS | Must |
| RF-M9-02 | **Conectar `cidades.estado` à tabela `estados`** (hoje desconectada) | Must |
| RF-M9-03 | Consulta CNPO com **índice de busca normalizado** (não `REPLACE` encadeado em full scan) | Must |
| RF-M9-04 | Importação periódica da base CNPO do MAPA como **job versionado e observável** | Must |
| RF-M9-05 | Vínculo produtor ↔ registro CNPO (hoje 100% NULL) | Must |
| RF-M9-06 | APIs de localidade cacheadas, somente leitura | Should |

### 5.10 M10 — Relatórios & Analytics

| ID | Requisito | Prioridade |
|---|---|---|
| RF-M10-01 | Catálogo de relatórios por perfil e por módulo | Must |
| RF-M10-02 | Geração **assíncrona** (fila) com notificação e download por URL assinada | Must |
| RF-M10-03 | Paridade visual com os relatórios atuais (Puppeteer sobre o HTML existente) | Must |
| RF-M10-04 | Exportação Excel (ExcelJS) e HTML imprimível | Must |
| RF-M10-05 | Relatórios administrativos, de gestor e de diretoria | Must |
| RF-M10-06 | **Todos** os endpoints de relatório autenticados (o legado expõe 200 sem auth) | Must |
| RF-M10-07 | Escopo por perfil: associado vê só o seu; certificadora só o seu | Must |
| RF-M10-08 | Dashboards por perfil (admin, gestor, agricultor, **agroindústria**, diretoria) | Must |
| RF-M10-09 | Nenhum SQL direto em tabela de outro módulo — apenas views e endpoints | Must |

### 5.11 M11 — Portal & Dashboards

| ID | Requisito | Prioridade |
|---|---|---|
| RF-M11-01 | Login unificado (staff e associado) com contexto de sessão | Must |
| RF-M11-02 | Área do associado (resumo, certificação, pendências, carteirinha) | Must |
| RF-M11-03 | Controle de acesso ao portal com política explícita | Must |
| RF-M11-04 | Dispatch de dashboard por perfil | Must |
| RF-M11-05 | Layout dual-mode: sidebar (desktop) e bottom nav (mobile) | Must |
| RF-M11-06 | PWA com assets **locais** (o CDN atual quebra o offline) | Must |
| RF-M11-07 | Acessibilidade AA e alvos de toque ≥44 px | Should |
| RF-M11-08 | Corrigir `USER_NIVEL` vazio e todos os efeitos de sessão ausente | Must |

### 5.12 M12 — Auditoria, Configuração & Administração

| ID | Requisito | Prioridade |
|---|---|---|
| RF-M12-01 | CRUD de usuários do sistema com papéis | Must |
| RF-M12-02 | `kernel_usuarios` separado de `associados` (Lei L3) | Must |
| RF-M12-03 | Auditoria *append-only* de ações sensíveis (quem, quando, o quê, antes/depois) | Must |
| RF-M12-04 | Endpoint de consulta de auditoria (o legado não expõe) | Should |
| RF-M12-05 | Recuperação de senha unificada (uma tabela, um esquema) | Must |
| RF-M12-06 | Rate limit e *lockout* progressivo no login | Must |
| RF-M12-07 | `session_regenerate_id` equivalente: rotação de refresh token | Must |
| RF-M12-08 | Corrigir `auth_refresh_tokens.expires_at` (ON UPDATE torna o token eterno) | Must |
| RF-M12-09 | `feature_flags` por módulo, para ativar rota nova sem deploy | Must |

---

## 6. Requisitos não funcionais

### 6.1 Segurança (RNF-S) — detalhamento em `06-SEGURANCA-E-CONFORMIDADE.md`

| ID | Requisito | Verificação |
|---|---|---|
| RNF-S01 | Guarda de autenticação **global**; rota pública é exceção declarada e decorada | Semáforo de rotas no CI |
| RNF-S02 | Autorização por **papel + recurso + escopo**, nunca por nível numérico | Teste de matriz de autorização |
| RNF-S03 | **Escopo por certificadora (tenant)** obrigatório em todo o domínio de certificação | Teste de acesso cruzado bloqueia o merge |
| RNF-S04 | **Negar por padrão**: ausência de escopo significa "nenhum registro", nunca "todos" | Teste unitário do repositório |
| RNF-S05 | CSRF em **100%** das mutações; token validado no servidor | Inventário automatizado de rotas POST |
| RNF-S06 | Nenhuma exclusão por `GET`; mutação só por método não idempotente | Lint de rotas |
| RNF-S07 | Upload validado por **conteúdo**, re-encodado, fora do webroot | Teste com arquivo forjado |
| RNF-S08 | Webhooks com assinatura validada sobre o corpo bruto | Teste com assinatura inválida → 401 |
| RNF-S09 | Rate limit e *lockout* no login e na recuperação de senha | Teste de 20 tentativas |
| RNF-S10 | Evento `LOGIN` (sucesso e falha) no `audit_log` | Consulta à trilha após login |
| RNF-S11 | Erros nunca expõem stack, SQL, caminho absoluto ou PII | Amostragem de logs e respostas |
| RNF-S12 | Zero segredo em repositório; falha ao subir se variável obrigatória faltar | `gitleaks` + teste de *boot* |

### 6.2 Desempenho e capacidade (RNF-P)

| ID | Requisito |
|---|---|
| RNF-P01 | Latência p95 < 500 ms (leitura) e < 1.500 ms (escrita) na carga nominal |
| RNF-P02 | **Sem N+1**: toda listagem usa `include`/`select` explícito ou consulta agregada |
| RNF-P03 | Toda consulta por FK, status ou data tem índice; índice conferido no *code review* |
| RNF-P04 | Consulta CNPO indexada por coluna normalizada (elimina o *full scan* de 23.232 linhas) |
| RNF-P05 | Relatórios pesados **assíncronos** (fila), nunca na requisição web |
| RNF-P06 | Escala real: 30 associados, 16 propriedades, ~1.500 registros operacionais — **o gargalo é manutenibilidade, não volume** |

### 6.3 Disponibilidade e operação (RNF-O)

| ID | Requisito |
|---|---|
| RNF-O01 | Disponibilidade 99,9% mensal |
| RNF-O02 | Deploy em **1 comando**; rollback em **1 comando** |
| RNF-O03 | Migrações automáticas, versionadas e reversíveis |
| RNF-O04 | Healthcheck `/api/v2/health` e *rolling restart* sem downtime |
| RNF-O05 | Backup diário com **restauração testada semanalmente** |
| RNF-O06 | Observabilidade: OpenTelemetry, logs JSON com `traceId`, métricas, alerta de 5xx |
| RNF-O07 | Configuração só por variável de ambiente, validada na inicialização |

### 6.4 Offline e mobilidade (RNF-M)

| ID | Requisito |
|---|---|
| RNF-M01 | Registrar atividade, insumo e ocorrência **sem rede**, com resposta imediata da UI |
| RNF-M02 | Sincronização **idempotente** (`Idempotency-Key` gerada no dispositivo) |
| RNF-M03 | Cobertura de **criação e edição** (o legado só cobre criação) |
| RNF-M04 | **Nenhuma perda silenciosa**: falha de sessão/HTTP deve reter o item e informar |
| RNF-M05 | Resolução de conflito explícita (servidor autoritativo) e registrada |
| RNF-M06 | Assets do PWA **locais** (o CDN atual quebra o modo offline) |
| RNF-M07 | Layout dual-mode: sidebar ≥992 px, bottom nav abaixo; alvos ≥44 px |

### 6.5 Dados e retenção (RNF-D)

| ID | Requisito |
|---|---|
| RNF-D01 | Migrações **aditivas e retrocompatíveis** enquanto o PHP escrever |
| RNF-D02 | `created_at` e `updated_at` confiáveis em todas as tabelas (base da sincronização) |
| RNF-D03 | **Soft delete** + retenção mínima de 3 anos em diário de campo, insumos e balanço de massas |
| RNF-D04 | `audit_log` *append-only*, imutável |
| RNF-D05 | `uuid` único nas entidades-núcleo; URLs expõem `uuid`, nunca ID sequencial nem ID cifrado |
| RNF-D06 | Toda tabela tem **um** módulo dono (ownership documentado) |
| RNF-D07 | Importação da base externa (CNPO) idempotente, versionada e com trilha |

### 6.6 Acessibilidade e usabilidade (RNF-A)

| ID | Requisito |
|---|---|
| RNF-A01 | WCAG 2.2 AA: contraste, foco visível, rótulos, navegação por teclado |
| RNF-A02 | Sem scroll horizontal em 360, 768 e 1280 px |
| RNF-A03 | Mensagens de erro úteis ao usuário final, com indicação do campo |
| RNF-A04 | Idioma pt-BR; formatação de data, número e moeda local |

### 6.7 Manutenibilidade (RNF-Q)

| ID | Requisito |
|---|---|
| RNF-Q01 | Cobertura ≥80% linhas / ≥70% *branches*; 100% nos cálculos de negócio |
| RNF-Q02 | Zero violação de fronteira entre módulos (`dependency-cruiser` no CI) |
| RNF-Q03 | `repository` é o único ponto de acesso a dados por módulo |
| RNF-Q04 | Contratos em `packages/contracts`, consumidos por API, Web e Mobile |
| RNF-Q05 | OpenAPI 3.1 completo; nenhuma rota não documentada |
| RNF-Q06 | Duplicação de código < 5% |
| RNF-Q07 | `README` e *runbook* por módulo; ADR por decisão estrutural |

---

## 7. Regras de negócio críticas (especificação de cálculo)

Estas regras têm **cobertura de teste obrigatória de 100%** (gate QUA-2) e são as de maior
risco de divergência.

### 7.1 Rotulagem orgânica (IN 50/2009)

```ts
percentualOrganico = Σ(peso dos ingredientes orgânicos) / Σ(peso total) × 100
selo = percentual >= 95 ? 'ORGANICO_BRASIL'
     : percentual >= 70 ? 'INGREDIENTES_ORGANICOS'
     : 'NENHUM'   // bloqueia qualquer menção ao termo orgânico
```

**Correções obrigatórias em relação ao legado:**
- Normalizar massa e volume **corretamente** (o legado soma g e mL 1:1 e trata "un" como 1 g);
- Unificar o arredondamento entre servidor e cliente (legado: 2 casas no PHP × 1 casa no JS).

**Testes de fronteira:** 69,99 → NENHUM · 70,00 → INGREDIENTES_ORGANICOS · 94,99 →
INGREDIENTES_ORGANICOS · 95,00 → ORGANICO_BRASIL.

### 7.2 Balanço de massas (Lei 10.831/2003 + Portaria 52/2021)

```ts
divergencia = |totalEntrada - quantidadeFinal| / totalEntrada × 100
status = totalEntrada <= 0 ? 'EM_ANALISE'
       : divergencia > 10 ? 'ALERTA'
       : 'APROVADO'
```

**Correções obrigatórias:**
- **Normalizar unidades** antes de somar (o legado soma KG, L e UN sem conversão);
- Distinguir **ganho** de **perda** (o `abs()` do legado esconde a direção);
- Não fixar a unidade "kg" nas mensagens;
- Aplicar a tolerância de 10% **efetivamente** (no legado ela só aparece na descrição textual).

### 7.3 Pontuação de visita técnica

```ts
itensAplicaveis = itens.filter(i => i.resposta !== 'NA')
pontuacao = Σ(peso dos conformes) / Σ(peso dos aplicaveis) × 100
conformidade = itemCriticoNaoConforme ? 'NAO_CONFORME'
             : pontuacao === 100 ? 'CONFORME'
             : pontuacao >= pontuacaoMinima ? 'PARCIALMENTE'
             : 'NAO_CONFORME'
```

**Correção obrigatória:** o campo **peso** do checklist **nunca entrou na fórmula** no
legado (a pontuação é contagem simples de itens). O peso passa a valer.

### 7.4 Validação de insumo (Portaria MAPA 52/2021)

Regra: insumo cujo princípio ativo, nome ou nome comercial **não conste** da lista oficial
de permitidos é **bloqueado** com alerta impeditivo.

**Correções obrigatórias:** usar correspondência **normalizada e exata** (o legado usa
`LIKE '%termo%' LIMIT 1` sem `ORDER BY` — um nome de 1 caractere casa com o primeiro
aprovado e libera um produto proibido). Validar **no servidor** o comprimento mínimo (no
legado a checagem existe apenas no cliente).

### 7.5 Período de conversão para orgânico (RF03)

Cálculo do tempo restante de conversão a partir de `historico_conversao.data_inicio`,
`tipo_uso_solo` e do status atual (EM_CONVERSAO, ORGANICO, CONVENCIONAL). Regra de
negócio a validar com o SINPORG quanto ao prazo por cultura (referência: 12 meses para
culturas anuais, conforme Portaria 52/2021).

### 7.6 Juros, multa e inadimplência (financeiro)

O vencimento considera o dia do **plano** (1 a 28). Inadimplência = mensalidade não paga
com data de vencimento no passado. **Decisão pendente de negócio:** cobrar juros/multa? O
legado não implementa; se sim, parametrizar por plano.

### 7.7 Carência de tratamento animal (sanidade)

Um animal sob tratamento excepcional tem carência mínima de **96 horas** (regra existente no
legado em `sanidade_cadastrar.php`); não pode ser certificado dentro do período. O v3 deve
**calcular a data de liberação**, não apenas registrar o campo.

### 7.8 Desmame por espécie

Idade mínima: **bovino 90 dias**, **ovino e caprino 28 dias**, **suíno 45 dias** (regra
existente no legado, a preservar).

### 7.9 Densidade máxima por ambiente

`ocupacao = nº de animais / área do ambiente`, comparado a `densidades_maximas_animais`
por espécie/sistema. **A tabela está vazia no legado — o v3 precisa de seeds.**

---

## 8. Defeitos do legado que NÃO podem ser replicados

> Esta seção é o **ativo mais valioso** do PRD: ela transforma 100+ horas de análise em uma
> lista de verificação executável. Cada item deve virar **teste de regressão** no v3.

### 8.1 Segurança — crítico

| # | Defeito verificado | Evidência |
|---|---|---|
| S1 | **PDF público com PII de todos os associados sem autenticação** | `/relatorio-associados` → `modulos/relatorios/relatorio_associados.php` sem guarda (o gêmeo em `api/` tem guarda) |
| S2 | **Multi-tenant inexistente**: `certificadora_id` só resolve logo; 1 única query filtra por ele, e é script de migração | `CertificadoOrganico.php`, `VisitaReport.php`; grep de `certificadora_id = ?` |
| S3 | **IDOR sistêmico "escopo nulo = todos"**: 21 páginas usam `GESTOR ? null : getAssociadoId()`; `getAssociadoId()` devolve NULL para conta sem vínculo (estado de toda conta recém-aprovada) e o `getAll($filtro)` só filtra se *truthy* | `classes/User.php:259-272`; `ManejoAnimal.php:21` |
| S4 | **CSRF ausente em 47 de 86 handlers POST (55%)**, inclusive troca de e-mail de login e senha | `modulos/usuarios/perfil.php` |
| S5 | **Exclusão por GET sem verificação de posse** em 6 listagens de produção animal e 7 de agroindústria | `*/listar.php` |
| S6 | **Webhooks PIX sem assinatura, sem allowlist de IP, CORS `*`**, e o erro **ecoa o txid** (permite sondagem) | `api/pix/webhook/*`, `api/.htaccess` |
| S7 | **Baixa duplicável sob concorrência**: idempotência apenas por checagem de status, sem lock nem UNIQUE | `classes/Mensalidade.php` |
| S8 | **IDOR de escrita** em `api/plano_manejo.php` (GET, PUT e DELETE sem dono) e em `receitas_editar.php` | — |
| S9 | **Login sem rate limit e sem evento `LOGIN`** em `audit_log` (69 chamadas de auditoria, nenhuma de autenticação) | `login.php` |
| S10 | **`usuarios.nivel` DEFAULT 1** = Admin silencioso para qualquer INSERT que omita a coluna | `migration.sql:1493` |
| S11 | **Sem `.htaccess` em `modulos/`**: acesso direto a qualquer página, contornando o roteador | — |
| S12 | **`logout` por GET** que só faz `session_destroy()`, sem limpar `$_SESSION` nem expirar cookie | `classes/User.php:110-112` |
| S13 | **Assinaturas de visita truncadas**: dataURL base64 gravado em `VARCHAR(255)` — **4 de 4 assinaturas corrompidas** | `visitas/realizar.php` |
| S14 | **Enumeração pública** em rastreabilidade animal por ID sequencial (`uuid=AL-%06d`) e de lote por UUID `mt_rand` (não criptográfico) | `LoteProducao.php:23-29` |
| S15 | **Credenciais `root` sem senha versionadas**; `die()` vaza mensagem do driver; `ID_ENCODE_KEY` com default público | `config/config.php` |

### 8.2 Funcional — quebra de funcionalidades inteiras

| # | Defeito verificado | Impacto |
|---|---|---|
| F1 | `mensalidades.txid` **não existe** no schema, mas é usada com `ERRMODE_EXCEPTION` sem `try/catch` | **Todo o fluxo PIX responde 500** |
| F2 | `PIX_PROVIDER` **nunca é definido** | O botão "Gerar cobrança PIX" nem aparece |
| F3 | `certificacao/cadastrar.php` grava em `data_vencimento` e `escopo`, **colunas inexistentes** | Cadastro/edição de certificação pela UI está morto |
| F4 | Cadastro de fornecedor chama `vincularUnidades()` (**método inexistente**) e POSTa para rota inexistente | Módulo de fornecedor inoperante |
| F5 | Todas as telas de **carteirinha** enviam ID cru enquanto o roteador faz `decodeId()`; `ficha.php` (único lugar com `encodeId`) **não tem rota** e é destino de 7 redirects | Carteirinha inacessível; 404 encadeado |
| F6 | `producao_animal/rastreabilidade.php` consulta `controle_reproducao_animal.lote_id` (**coluna inexistente**) e engole a exceção | A seção Reprodução **nunca aparece** |
| F7 | `$_SESSION['user_tipo_usuario']` **nunca é gravado** pelo login | Dispatch de dashboard errado; produtor de agroindústria cai no dashboard de agricultor |
| F8 | `fichas_completas.php` tem botões Aprovar/Recusar **sem handler POST** | Botões inertes |
| F9 | Rotas 404: `/roteiro-inspecao/editar/{id}` e `/roteiro-inspecao/ficha/{id}` | Links quebrados em duas telas |
| F10 | `propriedades/historico_conversao.php` **sem rota** | Link permanente para 404 |
| F11 | Modais de ocorrência usam jQuery `$.ajax` contra endpoint que exige CSRF, e o *monkey-patch* global só cobre `window.fetch` | **403 sistemático — os modais nunca gravam** |
| F12 | Relatórios de conformidade retornam **401 mesmo para o admin** | Funcionalidade inacessível |

### 8.3 Perda e corrupção de dados

| # | Defeito verificado | Impacto |
|---|---|---|
| D1 | `MODIFY COLUMN ... ENUM` aplicado sobre rótulos em **mojibake** | **Perda silenciosa já ocorrida**: `plano_manejo_cultivos.tipo_semente` vazio em **11/11** linhas; 2 de 5 NCs com `status = ''` |
| D2 | `insumos/editar.php` não envia `data_validade`, `tipo_insumo`, `aprovado_organico` nem a lista oficial, e o `update()` grava defaults | **Cada edição zera a conformidade e apaga a validade** do insumo |
| D3 | Estorno faz **DELETE físico** de `pagamentos` e `caixa`, sem auditoria | Perda de trilha |
| D4 | Exclusões físicas convivem com soft delete (`excluido` existe e é morto em vários módulos) | Inconsistência e perda de retenção regulatória |
| D5 | `ReceitaAgroindustria::update()` faz DELETE+INSERT dos ingredientes e sem `AND excluido = 0` | **Reescreve retroativamente** a rastreabilidade pública de lotes passados |
| D6 | Renovação de certificação **sobrescreve** o mesmo registro | Histórico de certificação perdido |
| D7 | `associados.estado` (CHAR(2)) recebe **ID numérico** nos formulários; `cidade` alterna entre ID e texto livre | Registros somem de listagens e relatórios por INNER JOIN |
| D8 | `dashboard_agricultor.php` faz fallback `SELECT id FROM associados LIMIT 1` | **Vazamento de dados de outro produtor** |
| D9 | `atividades_diarias.insumo_utilisado` é **texto livre**, não FK | Rastreabilidade de insumo no lote é textual e não confiável |
| D10 | Coluna `observações` com **acento** no identificador | Quebra introspecção e ORM |
| D11 | `auth_refresh_tokens.expires_at` com `ON UPDATE current_timestamp()` | Tokens **nunca expiram**; sem valor no INSERT, nascem expirados |

### 8.4 Regras de negócio incorretas

| # | Defeito verificado | Correção exigida |
|---|---|---|
| R1 | **Peso do checklist ignorado** na pontuação | Aplicar peso |
| R2 | **RF08 contornável**: a query de higienização ignora `$associadoId` e a chamada passa o literal `'Qualquer linha'` | Escopar por produtor e usar o equipamento real |
| R3 | **RF10 inexistente**: `lote_primario_id` e `produto_id` gravados sempre NULL; a tela rotula insumos como "Lotes Primários" | Vincular ordem ao lote do campo |
| R4 | **RF12 ambíguo**: `lote_processado_final` sem UNIQUE; a página pública usa `fetch()` | Unicidade e busca determinística |
| R5 | **Percentual orgânico soma massa e volume 1:1** e trata "un" como 1 g | Normalizar unidades |
| R6 | **Balanço de massas soma unidades heterogêneas** com `abs()` | Normalizar e distinguir ganho/perda |
| R7 | Tolerância de **10% só existe na descrição**, nunca no código | Aplicar |
| R8 | Gravidade automática de NC é **binária** (Crítica/Alta); Baixa e Média nunca são geradas; a UI usa `'Critica'` sem acento contra o ENUM `'Crítica'` | Escala completa e rótulo correto |
| R9 | `verificado_por` (INT) recebe **nome** do usuário | Referenciar usuário |
| R10 | `gravidade` fora da whitelist do PUT | Permitir a atualização |
| R11 | `hashid` de visita não é gerado em novas visitas | Gerar sempre |
| R12 | FK de técnico da visita aponta para `associados` recebendo `user_id` de `usuarios` | Corrigir a referência |
| R13 | Assembleia só vota com sessão do **Portal**; `tipo_votacao='ponderada'` é decorativo (peso sempre 1,00) | Votação por qualquer sessão de associado; implementar ou remover a ponderação |
| R14 | Evento grava presença em **duas fontes** (`eventos.confirmados` JSON **e** `evento_presenca`) | Fonte única |
| R15 | `eventos/editar.php` perde a hora (input `date` em coluna `DATETIME`) | Preservar hora |
| R16 | Geração de mensalidades exige **digitar o valor**; o plano não alimenta valor nem dia; verificação+INSERT **não é atômica** | Usar o plano e tornar atômico |
| R17 | `contas` e `caixa` são **subsistemas isolados** | Integrar |
| R18 | `controle_reproducao_animal` **não tem `lote_id` nem `especie`** | Adicionar |
| R19 | `ambiente_criacao_membros` **não é usado por nenhum código**; ambiente não se vincula a lote/animal | Implementar ou remover |
| R20 | Higienização **sem tela de edição** e sem validação de ordem cronológica | Implementar |
| R21 | Dependência dura de **seed de sanitizantes**: sem ele o módulo de produção inteiro fica inoperante | Seeds obrigatórios + mensagem clara |

### 8.5 Qualidade de código e operação

| # | Defeito |
|---|---|
| Q1 | `index.html` (45 KB) na raiz é cópia antiga do roteador — **vaza código-fonte** |
| Q2 | ~36 scripts destrutivos na raiz e 33 em `dev-utils/` acessíveis |
| Q3 | `relatorios_router.php` e `modulos/caderno_campo/relatorios.php` (713 linhas com Chart.js) são **código morto** |
| Q4 | `try/catch` vazios engolindo `PDOException` (ex.: rastreabilidade animal) |
| Q5 | Caminhos quebrados `../../../config` em 2 APIs (fora do app) |
| Q6 | 4 bibliotecas de PDF no legado (mPDF, TCPDF, Dompdf) + PhpSpreadsheet |
| Q7 | N+1 em várias listagens (`associados/listar`, `carteirinha_listar`, `inadimplentes_micro`, `acompanhar_presenca`, `insumos/validacao`) |
| Q8 | Nenhuma suíte de testes; `TESTES.md` desatualizado em relação ao código |

---

## 9. Estado atual × estado alvo

| Dimensão | Hoje (PHP) | Alvo (TypeScript) |
|---|---|---|
| Entrada | `.htaccess` + roteador de 194 ramos; todo `.php` acessível direto | Front controller único; rotas declaradas |
| Autenticação | Sessão PHP; 3 logins; sem rotação | JWT + refresh rotativo; login unificado |
| Autorização | Nível numérico lido cru; 56 arquivos sem guarda | Guardas + `pode(recurso, ação)` + escopo |
| Identidade | `associados` é cadastro **e** login | `kernel_usuarios` + `AssociadoRef` |
| Dados | SQL em 309 arquivos; sem camada | Prisma; `repository` único por módulo |
| Fronteiras | Inexistentes | 5 leis verificadas no CI |
| Multi-tenant | Branding apenas | `CertificadoraScopeGuard` obrigatório |
| Offline | POC sem idempotência; perde dados | Outbox + `Idempotency-Key` + conflito explícito |
| Relatórios | Síncronos, travam a requisição; 4 libs | Fila; Puppeteer sobre o HTML atual; ExcelJS |
| Deploy | Manual (XAMPP) | Docker + 1 comando + rollback |
| Observabilidade | `error_log` | OpenTelemetry + Pino + métricas |
| Testes | Nenhum | Vitest + Playwright + Testcontainers |

---

## 10. Arquitetura e stack (resumo — detalhe em `05-ARQUITETURA-E-DEPLOY.md`)

**Linguagem:** TypeScript (Node 24) em monorepo Turborepo + pnpm. A justificativa decisiva
não é a linguagem, é `packages/contracts`: **uma definição de tipo serve API, Web e Mobile**.
Go ou Rust seriam mais seguros por construção, mas perderiam esse compartilhamento e
encareceriam a contratação.

**Stack:** NestJS 12 + Fastify · Prisma 7 (`@prisma/adapter-mariadb`) · MariaDB 11.4 LTS ·
Redis 7 + BullMQ · Zod 4 · JWT + argon2id · Next.js 16 + Tailwind 4 + shadcn + TanStack ·
Expo SDK 57 + expo-sqlite · Puppeteer + ExcelJS · OpenTelemetry · Vitest + Playwright ·
Docker + Caddy.

**Estrutura de módulo obrigatória:** `module.ts` · `controller.ts` · `service.ts` ·
`repository.ts` (único com Prisma) · `dto/` · `policies/` · `events/` · testes.

**As 5 leis:** (L1) nenhum JOIN em tabela alheia · (L2) nenhum UPDATE em tabela alheia ·
(L3) identidade fora do domínio · (L4) dado comum por referência · (L5) comunicação por
interface pública ou evento.

---

## 11. Dados e migração (detalhe em `03-MODELO-DE-DADOS-E-OWNERSHIP.md`)

**Situação medida:** 77 tabelas · 1.025 colunas · 48 FKs · 1 view · 100% InnoDB ·
collation única (`utf8mb4_unicode_ci`) · **45 tabelas sem nenhuma FK** · **33 tabelas
"ilha"** · 71 ENUMs · 10 convenções de *timestamp* · 34 comentários em mojibake.

**Volume real:** 30.332 linhas, das quais **28.827 (95%) são bases externas** (`cnpo` 23.232
e `cidades` 5.595). O sistema operacional tem **1.505 registros**.

**Fase 0 obrigatória (bloqueia tudo):** 0 tabelas sem PK · 0 MyISAM · uma collation ·
duplicatas reconciliadas com ADR · resíduos de Laravel removidos · MariaDB em LTS ·
segredos rotacionados · `prisma db pull` sem avisos.

**Tabelas mortas a remover (verificado: 0 linhas, 0 FKs, 0 referências no código):**
`caderno_campo`, `participacoes`, `lote_atividade`, `entidades`; e `certificacao_produtos`
(órfã).

**Decisões pendentes de negócio (ADR):** cópia canônica de `atividades_diarias` ×
`agro_atividades_diarias`, `categorias_atividades` × `agro_categorias_atividades`,
`ocorrencias_manejo` × `agro_ocorrencias`, `lotes_producao` × `animal_lotes`,
`contas` × `caixa`, `agroindustrias` × `agroindustria_existentes`.

**Migrações aditivas** enquanto o PHP escrever; `prisma migrate` é a única fonte de verdade
a partir da baseline.

---

## 12. Segurança e conformidade (resumo — detalhe em `06-SEGURANCA-E-CONFORMIDADE.md`)

**Já corrigido no legado e não pode regredir:** RCE por upload, LFI nas rotas dinâmicas,
SQLi via `$_SESSION['user_table']`, XSS por SVG, IDOR no cadastro de atividade, CSRF nas
telas de atividade, `display_errors` em produção, segredos versionados.

**A corrigir por construção:** ~30 endpoints de API sem autenticação · 56 arquivos sem
guarda · autorização por nível numérico · UI usada como autorização · escrita cruzada entre
domínios · god table de identidade · 3 logins e 2 esquemas de reset · scripts destrutivos no
webroot · credenciais `root` · sem rate limit · webhooks sem assinatura · chave de sessão
inexistente · sem trilha confiável · sem testes.

**Regulatório (matriz completa no documento 06):** Lei 10.831/2003 (retenção de 3 anos e
rastreabilidade) · Decreto 6.323/2007 (segregação e higienização) · Portaria MAPA 52/2021
(insumos permitidos e sementes) · IN 50/2009 (rotulagem e percentual orgânico) · Portarias
404/2022, 811/2023, 685/2024 e 1.201/2024 (atualizações da lista).

**LGPD:** minimização, base legal, direito de acesso e correção, e **anonimização em vez de
exclusão** no conflito com a retenção regulatória (documentar em ADR).

---

## 13. Estratégia de migração (detalhe em `10-ROADMAP-E-ONDAS.md`)

**Fases:** −1 levantamento (2–3 sem) → 0 saneamento (2–3 sem) → 1 fundação (4–6 sem) →
2 ondas de módulos (40–52 sem) → 3 mobile (8–12 sem) → 4 relatórios em paridade (6–8 sem) →
5 desligamento do PHP (2–3 sem). **Total: 12–18 meses** com 2–3 devs.

**Ondas:** 1 Núcleo (M1, M9, M11) · 2 Campo (M2, M7) · 3 Fiscalização (M3) ·
4 Financeiro (M4) · 5 Produção (M5, M6) · 6 Governança e Leitura (M8, M10) · 7 Mobile.

**Regras de convivência:** uma única fonte de escrita por tabela · migrações aditivas ·
*feature flag* por módulo · ponte de sessão PHP→JWT temporária · nenhuma onda avança
incompleta.

**Ordem por acoplamento medido** (do menor para o maior): Produção Animal → Territorial e
CNPO → Suprimentos → Associados → Financeiro → Eventos e Assembleias → Agroindústria →
Fiscalização → Caderno de Campo (por último, é o núcleo produtivo).

---

## 14. Critérios de aceite (resumo — detalhe em `09-CRITERIOS-DE-ACEITE-E-QUALIDADE.md`)

**Definição de Pronto do projeto (14 itens):** zero PHP em produção · paridade nos 12
módulos assinada · Web completa · app publicado nas duas lojas · offline funcional ·
relatórios em paridade · API documentada · auth 100% JWT · zero segredos · Prisma Migrate
como fonte única · deploy e rollback em 1 comando · observabilidade ativa · backup com
restauração testada · retenção regulatória de 3 anos.

**Gate por módulo (G1–G10):** compila e sobe · cobertura 80/70 com 100% nos cálculos ·
fronteira sem violação · contrato OpenAPI completo · autorização 100% testada com IDOR e
tenant cruzado · matriz de paridade 100% · regulatório · migração aditiva testada ·
observabilidade · documentação.

**Regra de ouro:** nenhum módulo é desligado do PHP sem G1–G10 verdes.

---

## 15. Organização dos agentes (resumo — detalhe em `08-ORGANIZACAO-DOS-AGENTES.md`)

**39 agentes:** 3 de governança · 8 de plataforma · **12 pares** construtor+fiscal (um par
por módulo) · 4 fiscais transversais (paridade, segurança, qualidade, regulatório).

**Ciclo por módulo:** construtor implementa e entrega dossiê de evidências → fiscal audita
por 6 perguntas fixas e emite parecer → reprovado volta ao construtor → **máximo 3 rodadas**
→ se falhar, o módulo é **BLOQUEADO** e escalado. **Nunca se aprova por cansaço.**

**Regras anti-cola (8):** o fiscal não lê o código antes de planejar a auditoria · planeja a
partir do legado e do PRD · reproduz os comandos · escreve o próprio teste de borda · reporta
ao menos um achado · exige reprodução mínima · não corrige o código · é rotacionado após 3
aprovações sem achado.

---

## 16. Riscos

| # | Risco | Prob. | Impacto | Mitigação |
|---|---|---|---|---|
| R1 | Repetir o big-bang (já houve uma tentativa em Laravel) | Alta | Fatal | Ondas verticais; nenhuma avança incompleta |
| R2 | Regressão em 84 mil linhas sem testes | Alta | Alto | Anexos de legado + matriz de paridade + fiscal dedicado |
| R3 | Falta de domínio TypeScript na equipe | Alta | Alto | Tech lead dedicado; treinamento antes da Fase 1 |
| R4 | Relatórios PDF divergirem | Média | Alto | Puppeteer sobre o HTML atual; serviço PHP de transição |
| R5 | Duplicação no sync offline | Média | Alto | `Idempotency-Key` + testes de rede intermitente |
| R6 | Dados legados duplicados | Alta | Médio | Fase 0 com decisão de negócio documentada |
| R7 | Dois sistemas indefinidamente | Média | Alto | Prazo por onda + critério de desligamento |
| R8 | Regressão de segurança na troca de auth | Média | Alto | ScopeGuard testado + IDOR por rota + rate limit |
| R9 | Migração de schema quebrar o PHP | Alta | Alto | Aditiva até o desligamento |
| R10 | Fiscal virar carimbo | Média | Alto | 8 regras anti-cola + rotação |
| R11 | Conhecimento concentrado em uma pessoa | Alta | Alto | Anexos, ADRs, runbooks e ownership produzidos nas Fases −1 e 0 |
| R12 | Conflito entre agentes/paralelismo | Média | Médio | Máx. 2 pares ativos; nunca com tabela compartilhada |

---

## 17. Métricas de sucesso

| Métrica | Alvo | Como medir |
|---|---|---|
| Rotas com guarda de autenticação | 100% | Inventário no CI |
| Rotas com CSRF em mutação | 100% | Inventário no CI |
| Cobertura de testes | ≥80% linhas / ≥70% branches | Vitest |
| Cobertura dos cálculos de negócio | 100% | Vitest |
| Violações de fronteira | 0 | dependency-cruiser |
| Cobertura da matriz de paridade | 100% por módulo | Parecer fiscal |
| Vulnerabilidades altas/críticas | 0 | `pnpm audit` + DAST |
| Duplicatas no sync | 0 | Teste de sincronização |
| Latência p95 (leitura) | < 500 ms | OpenTelemetry |
| Erros 5xx | < 0,1% | OpenTelemetry |
| Tempo de deploy | < 10 min | Pipeline |
| MTTR de incidente | < 2 h | Runbook + alertas |
| Paridade de relatórios | 100% | Diff visual aprovado |
| Arquivos PHP em produção | 0 | Verificação final |

---

## 18. Glossário

| Termo | Significado |
|---|---|
| **OCS** | Organização de Controle Social — mecanismo de certificação por confiança |
| **SPG / OPAC** | Sistema Participativo de Garantia / Organismo Participativo de Avaliação da Conformidade |
| **OAC** | Organismo de Avaliação da Conformidade |
| **CNPO** | Cadastro Nacional de Produtores Orgânicos (MAPA) |
| **SisOrg** | Sistema de cadastro de produtores orgânicos do MAPA |
| **UPO** | Unidade de Produção Orgânica |
| **PMO** | Plano de Manejo Orgânico |
| **NC** | Não Conformidade |
| **RF08 / RF09 / RF10 / RF11 / RF12** | Requisitos funcionais do PRD regulatório de agroindústria |
| **Balanço de massas** | Comparação entre matéria-prima entrada e produto final processado |
| **Selos** | "Produto Orgânico Brasil" (≥95%) e "Produto com ingredientes orgânicos" (70–95%) |
| **Kernel** | Núcleo de identidade, acesso, territorialidade e auditoria — o "dado comum" |
| **Paridade** | Equivalência funcional verificada entre o legado e a versão nova |
| **Strangler fig** | Migração incremental em que o novo substitui o antigo rota a rota |
| **Gate** | Critério objetivo de aceitação (G1–G10) |

---

## 19. Rastreabilidade — evidências produzidas nesta análise

| Artefato | Conteúdo |
|---|---|
| `docs/v3/02-CATALOGO-DE-MODULOS-E-TELAS.md` | 12 módulos, rotas, telas, ações, defeitos por módulo |
| `docs/v3/03-MODELO-DE-DADOS-E-OWNERSHIP.md` | 77 tabelas, 1.025 colunas, 48 FKs, ownership, regras de migração |
| `docs/v3/04-CONTRATOS-DE-API.md` | contrato HTTP alvo, convenções, matriz de paridade de rotas |
| `docs/v3/05-ARQUITETURA-E-DEPLOY.md` | stack, topologia, 12 requisitos de deploy, ADRs |
| `docs/v3/06-SEGURANCA-E-CONFORMIDADE.md` | OWASP, multi-tenant, LGPD, matriz regulatória, 18 testes |
| `docs/v3/07-PROMPT-MESTRE-MULTIAGENTE.md` | o prompt operacional completo |
| `docs/v3/08-ORGANIZACAO-DOS-AGENTES.md` | 39 agentes, protocolo construtor/fiscal, gates |
| `docs/v3/09-CRITERIOS-DE-ACEITE-E-QUALIDADE.md` | DoD, testes obrigatórios, SLOs, checklists |
| `docs/v3/10-ROADMAP-E-ONDAS.md` | fases, ondas, backlog de épicos e histórias |
| `docs/v3/anexos/mapa-de-telas-reais.md` | **230 rotas navegadas na aplicação em execução**, com campos e ações |
| `doc/SCHEMA_SISAGRO_COMPLETO.md` | introspecção completa do banco com diagramas |
| `RELATORIO_MODULARIZACAO.md` | viabilidade de modularização e fronteiras |
| `PLANO_MIGRACAO_NODE.md` | plano técnico de migração para Node |
| `AUDITORIA_SEGURANCA.md` · `SEGURANCA_SEGREDOS.md` | auditoria de segurança do legado |
| `RELATORIO_NUCLEO_AUTH_SEGURANCA.md` | núcleo: identidade, auth, authz, sessão, 33 chaves |
| `RELATORIO_MODULO_ASSOCIADOS_GOVERNANCA.md` | associados, associações, usuários, eventos, assembleia |
| `docs/RELATORIO_TECNICO_CADERNO_CAMPO.md` | caderno de campo, offline/PWA, rastreabilidade |
| `doc/RELATORIO_CERTIFICACAO_INSPECAO_PLANO_MANEJO.md` | certificação, inspeção, plano de manejo |
| `RELATORIO_FINANCEIRO_PRODUCAO.md` | financeiro, PIX, agroindústria, produção animal, fornecedor |

---

**Documento vivo.** Toda alteração de escopo, de regra de negócio ou de decisão estrutural
deve gerar (a) um ADR em `docs/adr/` e (b) atualização da matriz de paridade do módulo
afetado. O `orquestrador` (A0) é o guardião deste documento.
