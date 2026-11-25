# Plataforma de sistemas — perfis, permissões e sessões

> **Modelo mental:** o projeto é um **framework** com três sistemas registrados
> (`assoc`, `social`, `producao`). O que cada pessoa vê e pode fazer é decidido pelo
> **perfil** dela, não pela tela. O `admin` tem permissão curinga (`*`) e acesso total
> a todos os sistemas. Cada sistema tem **login próprio** e **sessão isolada**, mas
> todos compartilham a mesma base de dados e o mesmo núcleo.

Implementação: `src/Platform/` (núcleo) + `assoc/`, `social/`, `producao/` (cascas).
Testes: `tests/Unit/Platform/AutorizacaoTest.php` (matriz de perfis) e
`dev-utils/smoke-plataforma.php` (ponta a ponta).

---

## 1. Mapa da plataforma

```
/app/                       SISTEMA ATUAL (index.php) — acesso do admin/criador
├── /inicio.php             landing (Orgânico Brasil) — pública
├── /assoc/                 Gestão Associativa
│   ├── login.php           login próprio   (SINPORG_ASSOC,  /app/assoc/)
│   ├── index.php           painel por perfil
│   └── logout.php          encerra só esta sessão
├── /social/                Controle Social e Certificação (OPAC/OCS)
├── /producao/              Produção do Agricultor
└── /index.php              sistema legado (intacto, também via /app/)
```

> **Importante:** o `DirectoryIndex` da raiz é `index.php` primeiro. Assim `/app/` continua abrindo
> o sistema atual (redireciona para `/app/login`), e a landing nova fica em `/app/inicio.php`.
> No painel novo, o admin vê o atalho “Sistema atual (legado)” no rodapé do menu lateral.
>
> A **landing** pode ser acessada em `/app/inicio.php` ou pela URL amigável **`/app/site`**. Os
> botões de acesso ficam **no mesmo header dos links institucionais** (Sobre Nós, Produção Orgânica
> e Comissões): **Área do Associado** → `/app/assoc/login.php`, **Área do Técnico** →
> `/app/social/login.php`, **Área do Agricultor** → `/app/producao/login.php` e o ícone
> **🔐 Área do Administrador** → `/app/login` (acesso exclusivo do admin/criador). No celular os
> mesmos botões aparecem no menu responsivo. Cada destino aparece **uma única vez** por breakpoint
> (não há seção/barra duplicando os acessos). A tela de login do sistema atual (`/app/login`)
> permanece sem esses botões.
>
> **Validação de área no login:** cada portal confere as credenciais **e** se o perfil pertence
> àquela área (o admin tem acesso global). Se um técnico tentar a área do associado/agricultor, ou
> um agricultor tentar a área do técnico, **nenhuma sessão é criada** — o sistema retorna para a
> landing (`/app/site?acesso=negado`) com aviso para escolher o portal correto. Contas em primeiro
> acesso (ficha/vínculo) continuam liberadas no portal do próprio perfil. A tentativa fica
> registrada como `LOGIN_AREA_ERRADA` no `audit_log`.

> **Troca de conta e sessão anterior:** qualquer POST de credenciais descarta a identidade
> autenticada anterior **antes** de validar CSRF, senha e área. Se a tentativa falhar (token
> expirado, senha errada ou área incorreta), a sessão antiga **não permanece ativa** — o usuário
> volta ao login, sem herdar a conta anterior. O login legado e o Portal do Associado seguem a
> mesma regra. O ID de sessão é regenerado e os dados de identidade são limpos em `Sessao::entrar`.
>
> **CPF x e-mail no login:** o login por e-mail nunca interpreta o campo como CPF quando não há
> 11 dígitos. Antes, um e-mail sem dígitos virava CPF vazio e podia casar com associados de CPF
> vazio (ex.: `cici@gmail.com`), liberando acesso com a senha correta daquela conta. Agora o CPF
> só é usado quando o identificador tem exatamente 11 dígitos.

| Sistema | Rota | Cookie de sessão | Perfis aceitos |
|---|---|---|---|
| Gestão Associativa | `/app/assoc/` | `SINPORG_ASSOC` | `admin`, `gestor_associacao`, `diretoria`, `produtor` |
| Controle Social | `/app/social/` | `SINPORG_SOCIAL` | `admin`, `gestor_fiscalizacao`, `fiscal_campo`, `certificador`, `diretoria` |
| Produção | `/app/producao/` | `SINPORG_PRODUCAO` | `admin`, `produtor`, `agroindustria`, `gestor_associacao`, `diretoria` |

> O cookie é restrito ao caminho do sistema (`path=/app/assoc/`). Por isso é possível
> estar logado no `assoc` e não no `social`, e o logout de um não derruba o outro.

---

## 2. Arquitetura do núcleo

```
src/Platform/
├── Aplicacoes.php      registro dos sistemas + funções + catálogo de permissões
├── Urls.php            URLs derivadas do script (funciona em /app/ e na raiz)
├── Sessao.php          sessão isolada por sistema (nome e cookie próprios)
├── Csrf.php            token CSRF por sessão
├── Identidades.php     valida credenciais e carrega PERFIL/PERMISSÕES
├── Autorizacao.php     pode() / podeAcessar() / funcoesVisiveis() (admin = *)
├── Auditoria.php       grava em audit_log (login, logout, negação)
├── Plataforma.php      fachada usada pelas páginas
├── Ui.php              telas: login, painel por perfil, 403
├── Fluxo.php           login / painel protegido / logout
└── Bootstrap.php       config + autoload do núcleo + buffer de saída
```

As cascas de cada sistema têm **3 arquivos** e nenhuma regra de negócio:

```php
// assoc/login.php
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';
\App\Platform\Fluxo::login('assoc');
```

```php
// assoc/index.php
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';
\App\Platform\Fluxo::home('assoc');
```

```php
// assoc/logout.php
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';
\App\Platform\Fluxo::logout('assoc');
```

---

## 3. Como o perfil decide as funções

Fluxo de uma requisição autenticada:

```
login.php → Identidades::porCredenciais()
              ├── usuarios (equipe)  → papéis em usuario_papel/papeis
              └── associados (produtor) → papel produtor
           → permissões = banco ∪ permissões do papel
           → Sessao::entrar() (regenera ID, grava perfil)
           → redireciona para index.php

index.php → Fluxo::home()
           → Autorizacao::podeAcessar(perfil, sistema)
           → Autorizacao::funcoesVisiveis(perfil, sistema)
           → Ui::painel()  (renderiza SÓ as funções permitidas)
```

O painel mostra **quantas funções o perfil libera de quantas o sistema tem** (ex.: `5 de 9`)
e o administrador vê `9 de 9` no módulo de associação com o selo **Acesso admin** e os três
sistemas liberados.

### 3.1 Matriz de perfis

| Perfil | Permissões principais | Sistemas |
|---|---|---|
| `admin` | `*` (curinga) | todos (acesso total) |
| `diretor` (presidente/vice) | `associados.*`, `financeiro.*`, `governanca.*`, `documentos.*`, `relatorios.read`, `portal.read`, `config.read` | **assoc (total)** |
| `tesoureiro` | `associados.read`, `financeiro.read/write`, `governanca.read`, `relatorios.read`, `portal.read` | assoc (área financeira) |
| `secretario` | `associados.read`, `documentos.read/write`, `governanca.read`, `relatorios.read`, `portal.read` | assoc (gestão de documentos) |
| `gestor_associacao` | `associados.*`, `financeiro.*`, `governanca.*`, `relatorios.read`, `portal.read`, `caderno.read` | assoc, producao |
| `diretoria` | `associados.read`, `financeiro.read`, `governanca.read`, `relatorios.read`, `fiscalizacao.read`, `caderno.read` | assoc, social, producao (leitura) |
| `gestor_fiscalizacao` (Fiscal/Técnico) | `fiscalizacao.*`, `caderno.read`, `relatorios.read`, `config.read` | **social** (+ produção, leitura) — **sem assoc** |
| `fiscal_campo` | `fiscalizacao.read`, `fiscalizacao.write`, `relatorios.read` | social |
| `certificador` | `fiscalizacao.read`, `fiscalizacao.review`, `fiscalizacao.certificate` | social |
| `produtor` | `caderno.read/write`, `portal.read`, `agroindustria.read`, `associados.self`, `documentos.self`, `financeiro.self`, `relatorios.self` | assoc (somente os próprios dados), producao |
| `consumidor` | `portal.read`, `associados.self`, `documentos.self`, `financeiro.self`, `relatorios.self` | assoc (somente os próprios dados) |
| `agroindustria` | `agroindustria.read/write`, `caderno.read`, `portal.read` | producao |

> Regra do negócio: **técnico/fiscal não acessa a área associativa**. O módulo de associação é
> da diretoria (diretor/vice = total; tesoureiro = financeiro; secretário = documentos).

### 3.1.1 Cargos da diretoria vindos do vínculo associativo

O cargo da pessoa em `associado_associacao.tipo_vinculo` vira perfil automaticamente:

| `tipo_vinculo` | Perfil aplicado |
|---|---|
| `presidente`, `vice-presidente`, `diretor` | `diretor` |
| `tesoureiro` | `tesoureiro` |
| `secretario` | `secretario` |
| `conselho_fiscal` | `diretoria` (leitura) |
| `membro`, comissões | sem perfil adicional (segue `produtor`) |

A conta de acesso pode ser da tabela `associados` (portal) ou `usuarios` vinculada ao associado;
nos dois casos o cargo é lido do vínculo. Para atribuir um perfil de diretoria a uma conta sem
vínculo, use `usuario_papel` (papéis `diretor`, `tesoureiro`, `secretario` criados pelo
`scripts/migrar-papeis-diretoria.php`).

### 3.2 Regra do administrador (acesso total)

`Autorizacao::ehAdmin()` considera administrador quando **qualquer** condição é verdadeira:

1. papel `admin` em `usuario_papel`;
2. `permissoes` contém `*`;
3. `usuarios.tipo = 'admin'`;
4. `usuarios.nivel = 1` (compatibilidade com o legado, onde nível 1 = Admin).

Consequência: o admin entra nos três sistemas (cada um com sessão própria) e vê todas as
funções, inclusive as que ainda estão em implantação.

### 3.3 Contas sem papel

Contas legadas sem linhas em `usuario_papel` derivam o perfil de `tipo`/`nivel`/`associado_id`:
`admin` (nível 1), **fiscal/técnico** (`tipo='gestor'` ou nível 2) e `produtor` (nível 3 com
`associado_id`). Sem perfil derivável, o acesso é **negado** (fail closed) e o painel orienta a
pedir atribuição de papel ao administrador.

### 3.4 Retrocompatibilidade com o "Gestor" do legado (nível 2) = FISCAL/TÉCNICO

No sistema antigo existe **um único** nível 2 chamado "Gestor/Fiscal". Na prática, essas contas
são de **fiscal/técnico** (MAPA, EMATER, prefeituras, certificadoras) — **não** de gestor da
associação. Por isso o mapeamento correto é `gestor_fiscalizacao`, e **não** `gestor_associacao`.

A migração inicial (K1) havia atribuído `gestor_associacao` a essas contas. A correção tem duas
partes:

1. **Fallback por nível** — conta sem `usuario_papel` com `tipo='gestor'` ou `nivel=2` recebe
   `gestor_fiscalizacao` (`Identidades::papeisLegados`).
2. **Reclassificação dos dados** — contas de nível 2 receberam `gestor_fiscalizacao` e tiveram o
   `gestor_associacao` indevido removido:

```powershell
php scripts\migrar-papeis-gestor-legado.php            # dry-run
php scripts\migrar-papeis-gestor-legado.php --apply    # grava + audit_log
```

Resultado (3 contas): **kajiani (#10), vanessa (#20) e Alvaro (#221)** agora são
`gestor_fiscalizacao` (fiscal/técnico). Reversão: o script informa os IDs de `usuario_papel`
criados/removidos.

### 3.4.1 Papéis da diretoria e isolamento do fiscal

```powershell
php scripts\migrar-papeis-diretoria.php            # dry-run
php scripts\migrar-papeis-diretoria.php --apply    # grava
```

O script: cria as permissões `documentos.read/write`; cria os papéis `diretor`, `tesoureiro` e
`secretario` com seus vínculos em `papel_permissao`; e remove `associados.read`/`portal.read` dos
papéis de fiscalização (`gestor_fiscalizacao`, `fiscal_campo`, `certificador`) — garantindo que o
técnico/fiscal **não acesse a área associativa**.

### 3.5 Primeiro acesso e ficha de adesão (mesma lógica do sistema atual)

> Análise detalhada, com origem em cada regra do legado: [ANALISE-PRIMEIRO-ACESSO.md](ANALISE-PRIMEIRO-ACESSO.md).

A tela de login reaproveita a **mesma imagem de fundo** (`assets/img/login-bg.jpg`) e o logo do
sistema atual, com três abas: **Entrar**, **Primeiro Acesso** e **Perfil de Acesso**.
Se já houver uma sessão autenticada e o formulário de login for enviado com outra conta, o sistema
valida as credenciais informadas e **troca a conta** (não redireciona mais para o painel da conta
anterior). A mesma regra vale para o login legado (`/app/login`).

O fluxo tem **duas aprovações** independentes, na mesma ordem do sistema atual:

1. **Pré-cadastro** (`Cadastro::processar`): a pessoa informa em qual situação se classifica —
   Vegetal, Animal, Agroindústria, Mista, Fiscal/Técnico ou **Consumidor/Cliente** —, a conta nasce
   em `usuarios` com `status='pendente'`, `nivel_solicitado` e `perfil_solicitado`. Ela **não entra**
   no sistema: o login mostra **"Aguardando liberação"**.
2. **Liberação do acesso** (`assoc/pendencias.php`, permissão `associados.approve`): a diretoria
   confirma o nível concedido (Fiscal/Técnico, Usuário; Admin só pelo administrador). Efeitos
   idênticos ao legado: `status='ativo'`, `nivel`, `tipo`, `acesso_confirmado_por/em` e auditoria
   `ACESSO_CONFIRMADO`/`ACESSO_NEGADO`. Também é possível negar com motivo.
3. **Vínculo** (`Onboarding::salvarVinculo`): Fiscal/Técnico (nível 2) sem órgão, setor e
   especialidade confirma os três (equivalente a `hasPerfilCompleto()` e à etapa 2 do login legado).
4. **Ficha de adesão** (`assoc/ficha.php`, `producao/ficha.php` e **modal no dashboard**): produtor
   (nível 3) **sem cadastro de associado** entra direto no dashboard e, no mesmo instante, recebe o
   **modal da ficha** (equivalente a `includes/ficha_adesao_modal.php` no legado). O modal tem as
   mesmas regras: estados `sem_ficha`/`rascunho` mostram o formulário completo; `pendente` mostra o
   aviso “Ficha em análise” com a data; `aprovada` não exibe nada. Usa a **mesma tabela
   `ficha_adesao`** e a **mesma classe `FichaAdesao`** (`salvarPorUsuario`), com os mesmos estados.
   - O modal é exibido **somente** no dashboard de entrada (login/painel, sem `?funcao=`) quando a
     ficha está em `sem_ficha`/`rascunho`, e ao abrir **Minha ficha** nesses mesmos estados. Ele
     **não reaparece** ao navegar por outros itens do menu lateral. Fichas `pendente`/`recusada`
     continuam consultáveis em **Minha ficha**, com o respectivo aviso/editar.
   - **Enquanto não aprovada**, o associado pode editar/reenviar a ficha em `ficha.php`
     (rascunho, pendente ou recusada). A página **Minha ficha** (`assoc/minha-ficha.php`) mostra o
     botão **Editar ficha** nesses estados; depois de aprovada, fica somente leitura/impressão.
5. **Aprovação da ficha** (`assoc/pendencias.php` → `Pendencias::aprovarFicha`): a Diretoria/
   Presidência confere a ficha completa em `assoc/ficha-analise.php`, marca **“Confirmo que conferi
   a ficha e que ela está completa”** e só então aprova. O sistema também valida os campos mínimos
   (`Onboarding::completudeFicha`: tipo, nome, nascimento, CPF, município/UF, telefone, e-mail e
   aceite); ficha incompleta tem a aprovação bloqueada com a lista de pendências. A aprovação reusa
   `FichaAdesao::aprovar/recusar`, cria o `associados`, gera número/data de cadastro e vincula a
   conta (`usuarios.associado_id`).
6. **Perfil essencial** (`Onboarding::salvarPerfil`): conta sem telefone e CPF completa os dados
   essenciais (equivalente ao redirecionamento para `/meu-perfil`), depois da ficha.

Ordem aplicada por `Onboarding::pendencias`: **vínculo → ficha → perfil**.

> Única divergência deliberada: no legado a **liberação do acesso** é exclusiva do Admin
> (`AcessoPendente::podeConceder`); na nova plataforma ela usa `associados.approve` (diretor/
> gestor de associação), conforme a regra de negócio atual. Os efeitos no banco e a auditoria são
> os mesmos, e o nível Admin continua exclusivo do administrador.

Para conferir qualquer conta:

```powershell
php scripts\diagnostico-acesso.php vanessa@gmail.com
```

O diagnóstico mostra papéis, permissões, admin e a decisão/funções por sistema.

### 3.6 Menu lateral por perfil + DataTable por funcionalidade

O menu lateral é **obrigatório em todas as telas internas** (como no sistema atual): o painel,
pendências/aprovações, agendar visita, certificações (lista, formulário, detalhes, histórico e
certificado) e ficha de adesão usam o mesmo layout (`Ui::abrirLayout()` / `Ui::fecharLayout()`),
com item ativo destacado, gaveta no celular, seção de sistemas e atalho admin para o legado. As
telas de login/primeiro acesso continuam sem menu (pré-autenticação), como no sistema atual.

O painel tem **menu lateral** (desktop fixo; no celular vira gaveta com
botão ☰/overlay e fecha no Esc) e, no conteúdo, a **DataTable da funcionalidade selecionada** —
o cartão de boas-vindas e o grid de cards foram removidos.

- **Menu lateral:** fundo **#E8E8E8** (classe `menu-lateral-fundo`), função ativa em destaque; itens
  com `?funcao=<chave>`; contador "X de Y funcionalidades liberadas"; seção **Sistemas**
  (atual/liberado/sem acesso); Página inicial e Sair.
- **Barra superior:** também com fundo **#E8E8E8** (classe `barra-superior-fundo`), mantendo
  hambúrguer, nome/e-mail, atalhos de Meu perfil/Configurações, Painel e Sair.
- **Conteúdo:** faixa de cabeçalho do módulo com ícone, perfil, permissão e contador; a DataTable
  ocupa **quase toda a largura da tela** (o container não tem `max-w`), com busca, seletor de
  registros, paginação e **exportação CSV da página atual**; o rodapé indica os fluxos em
  implantação.
- **DataTables local** (`landing/vendor/jquery.min.js`, `datatables.min.js/css`): busca,
  ordenação e paginação em pt-BR, sem CDN em tempo de execução; tema customizado (cabeçalho,
  linhas, controles e paginação) combinando com o restante do sistema.
- **Fundo da página:** todas as telas internas usam um **lavado lilás quase transparente** em
  degradê, do tom mais presente no canto **superior direito** para quase incolor no **inferior
  esquerdo** (`rgba(169,140,201,.18) → rgba(195,174,223,.10) → rgba(236,227,247,.03)` sobre branco,
  direção 225°), destacando menu lateral, barra superior, cards e DataTables — que permanecem
  brancos com a mesma aparência e contraste. Classe reutilizável `fundo-lilas-pagina`;
  login/landing ficam de fora e a impressão força fundo branco.
- **Ações internas:** quando o fluxo já existe, o botão da linha/toolbar aponta para a tela
  (ex.: *Liberar acessos e fichas* → `pendencias.php`); quando ainda não existe, aparece
  desabilitado com o aviso **"Fluxo em implantação"**.

### 3.7 Meu perfil e Configurações (barra superior)

No canto superior direito de todas as telas internas (painel e páginas de módulo) há os atalhos
**👤 Meu perfil** e **⚙ Configurações**:

- `/{sistema}/meu-perfil.php` — nome, sobrenome, e-mail, telefone, CPF, nascimento, endereço,
  CEP e vínculo institucional (órgão/setor/especialidade/nicho); valida e-mail único, CPF/CEP e
  grava auditoria `PERFIL_ATUALIZADO`. Contas legadas que guardam `cidade`/`estado` como IDs
  numéricos têm os valores convertidos para o **nome da cidade** e a **UF** na exibição
  (`Conta::resolverLocalidade`).
- `/{sistema}/configuracoes.php` — preferências (tema/idioma/notificações em sessão),
  notificações de eventos (`notificacoes_ativas`, `dias_antecedencia_evento`, avisos de
  hoje/próximos/atrasados) e troca de senha com conferência da senha atual (auditoria
  `SENHA_ALTERADA`).
- No **topo do menu lateral** o nome do usuário foi omitido; ficam apenas avatar e selo de perfil,
  já que o nome aparece na barra superior.

### 3.8 Papéis do associado e atribuição pela Diretoria/Admin

Papéis disponíveis no módulo de associação (`usuario_papel` → `papeis`):

| Papel | Acesso |
|---|---|
| `produtor` (rótulo **Membro associado**) | Ficha, documentos/carteirinha, mensalidades, certificados e meu cadastro — **somente os próprios dados**; também produção |
| `consumidor` (rótulo **Membro consumidor**) | Ficha, documentos/carteirinha, mensalidades, certificados e meu cadastro — **somente os próprios dados**; **sem produção** |
| `diretor` | Acesso total ao módulo (presidente/vice também derivam para este papel) |
| `tesoureiro` | Área financeira do módulo |
| `secretario` | Gestão de documentos |
| `gestor_associacao` | Quadro, adesões e financeiro |
| `diretoria` | Leitura do quadro, financeiro, governança e relatórios |
| `agroindustria` | Portal do associado e produção |
| `admin` | Acesso total (não atribuível pela tela) |
| `gestor_fiscalizacao`, `fiscal_campo`, `certificador` | Fiscalização (não atribuíveis pela tela) |

- **Membro associado (papel `produtor`)**: tem funções de autoatendimento no menu, todas filtradas
  pelo próprio `associado_id` — **Meu cadastro** (`portal.read`, abre a edição do perfil),
  **Minha ficha** (`associados.self`), **Meus documentos** (`documentos.self`),
  **Minhas mensalidades** (`financeiro.self`) e **Meus certificados** (`documentos.self`). Não vê
  quadro geral, financeiro global, papéis nem telas da diretoria.
- **Minha ficha** (`/app/assoc/minha-ficha.php`): página de consulta da própria ficha, com número,
  situação, datas, identificação, contato, endereço, propriedade/produção, dados bancários,
  produtos/culturas, observações e aceite; botão **Imprimir / Salvar PDF**. A consulta é sempre
  limitada pela identidade autenticada (`associado_id`/`usuario_id` da ficha).
- **Diferenciação no cadastro (ficha de adesão)**: o campo **Tipo de associado** agora inclui
  **Consumidor(a)/Cliente**, além de agricultor familiar, colaborador, honorário e institucional.
  No pré-cadastro também há a situação **Consumidor / Cliente**. Na aprovação da ficha
  (`Pendencias::aprovarFicha`), o papel atribuído é `consumidor` quando a ficha/conta é de
  consumidor, ou `produtor` nos demais casos; assim o consumidor recebe somente o autoatendimento
  e **não** acessa a produção.
- **Na aprovação da ficha** (`Pendencias::aprovarFicha`), o associado recebe automaticamente o
  papel `produtor` (ou `consumidor`) e o nível 3 caso a conta ainda não tenha nível — corrigindo o
  caso de conta aprovada sem nenhuma funcionalidade no portal.
- **Fallback de leitura**: conta vinculada a um associado que não tenha nenhum papel do módulo
  recebe `produtor` na hora de montar a identidade.
- **Herança do membro associado:** além do escopo específico, **diretor, tesoureiro, secretário,
  gestor de associação e diretoria** também recebem as funções de autoatendimento (minha ficha,
  meus documentos, minhas mensalidades, meus certificados, meus relatórios e meu cadastro).
- **Tela de gestão**: `/app/assoc/papeis.php` é **exclusiva do administrador do sistema**
  (`Autorizacao::ehAdmin`), com busca por nome/e-mail, edição por checkboxes e auditoria
  `PAPEIS_ATUALIZADOS`. Diretor/presidente têm o escopo total do módulo, mas **não** a gestão
  global de papéis; papéis de admin/fiscalização são preservados.
- **Acesso total do sistema:** somente o papel `admin` tem `*`/acesso aos três sistemas e às
  funções administrativas (papéis, configuração e legado).
- **Trava de escopo do membro**: em `Autorizacao::pode`, uma conta **somente membro** (sem papel
  elevado) só pode ter permissões de autoatendimento/produção (`associados.self`,
  `documentos.self`, `financeiro.self`, `relatorios.self`, `portal.read`, `caderno.*`,
  `agroindustria.*`). Permissões residuais do banco (`associados.read`, `relatorios.read`,
  `fiscalizacao.read`) são ignoradas — o membro não vê a lista completa de associados, o
  financeiro global, relatórios gerais nem a fiscalização. O script
  `scripts/corrigir-permissoes-membro.php` removeu essas permissões antigas do papel `produtor`.
- **Efeito imediato**: `Sessao::usuario()` recarrega a identidade do banco a cada requisição, então
  aprovações e mudanças de papel valem sem novo login.

### 3.11 Plano de Manejo obrigatório (todas as áreas)

O **Plano de Manejo** é obrigatório para qualquer agricultor, independentemente de produzir
hortaliças/vegetais, animais ou apenas agroindústria:

- O agricultor acessa **Meu plano de manejo** em `/app/producao/meu-plano.php`.
- A página inicial segue o padrão legado `/planos-manejo`: **DataTable** com busca, ordenação,
  paginação, seletor de quantidade, filtros Todos/Aprovados/Em Análise com contadores e colunas
  Safra/Tipo/Propriedade/Agricultor/Status/Versão/Data de criação/Ações. As ações abrem as páginas
  derivadas **Ver**, **Editar** e **Excluir**; edição e exclusão apenas em rascunho ou revisão,
  com **+ Novo plano** no topo.
- O cadastro/edição segue o **wizard legado de 5 etapas**: 1 Identificação, 2 Propriedade,
  3 Práticas, 4 Cultivos e 5 Finalização. A etapa 5 mostra resumo, DataTable de cultivos e
  observações antes de salvar como rascunho ou enviar para análise.
- Pode cadastrar/editar o plano, informar áreas, solo, água, clima, práticas de manejo, cultivos e
  enviar **croqui** (JPG/PNG/GIF/WebP/PDF até 5 MB).
- O plano pode ser salvo como **Rascunho** ou enviado como **Em Análise**; em análise/aprovado não
  pode ser alterado pelo agricultor, mas pode gerar um **novo plano** para outra safra.
- O painel de produção exibe card, aviso e **modal obrigatório** enquanto não houver plano
  enviado para análise.
- O técnico/gestor/admin consulta o plano completo em `social/modulo.php?modulo=planos&ver=ID`,
  registra parecer/sugestões de ajuste e histórico (tabela `plano_manejo_pareceres`).

### 3.12 Painéis de entrada e identidade visual

Cada sistema tem um painel de entrada com cor própria no card superior, cards de indicadores e
seções de acompanhamento:

| Painel | Cor do topo | Conteúdo |
|---|---|---|
| Administração (`/app/assoc/` como admin) | **violeta/índigo** | associados, pendências, usuários, certificações, ordens, CNPO |
| Associação (`/app/assoc/`) | **verde/emerald** | quadro, adesões, mensalidades, visitas, NCs, assembleias; ficha e confirmação de visitas |
| Produção (`/app/producao/`) | **âmbar/laranja** | propriedades, lavouras, atividades, insumos, lotes, rebanho, agroindústria |
| Controle Social (`/app/social/`) | **azul/ciano** | painel do gestor/técnico, visitas, NCs, planos, certificações e relatórios |

Os modais do legado foram portados: ficha de adesão (rascunho), confirmações de visitas do
gestor/técnico (ciência + reagendamento) e confirmações de visitas do agricultor (ciente/recusar
com motivo). A identidade de cada card superior é definida em `UiPainel`/`UiSocialModulos`.

As definições ficam em `src/Platform/Tabelas.php` e foram baseadas nas telas reais do sistema
atual (colunas extraídas de `modulos/associados/listar.php`, `certificacao/listar.php`,
`roteiros_inspecao/visitas/listar.php`, `mensalidades/listar.php`, `assembleia/listar.php`,
`carteirinha_listar.php`, `caderno_campo/*`, `agroindustria/ordens_listar.php` e
`certificadoras_list.php`), garantindo que a lógica e as colunas já validadas sejam preservadas.

| Funcionalidade (exemplos) | Base no sistema atual |
|---|---|
| Quadro de associados | `modulos/associados/listar.php` — nome, e-mail, telefone, cidade, status, nível |
| Adesões e fichas | `cadastros_pendentes.php` — associado, tipo, CPF, município, submissão, nº, resultado |
| Mensalidades e caixa | `mensalidades/listar.php` — associado, ref., associação, plano, vencimento, valor, situação, pagamento |
| Assembleias e eventos | `assembleia/listar.php` — título, associação, período, status, pautas, votos |
| Carteirinhas | `carteirinha_listar.php` — associado, e-mail, categoria, situação, financeiro |
| Visitas técnicas | `roteiros_inspecao/visitas/listar.php` — data, propriedade, agricultor, roteiro, ciente, status, conformidade |
| Certificações | `certificacao/listar.php` — tipo, nº, associado, status, emissão, validade, organismo |
| Caderno de campo | `caderno_campo/atividade_diaria/listar.php` — #, nome, data, horário, tipo, categoria, status, propriedade, lavoura, associado |
| Lotes / Insumos / Ordens | `lotes_producao/listar.php`, `insumos/listar.php`, `ordens_listar.php` |

Para conectar uma ação/tela real: acrescente `'rota' => 'arquivo.php'` à função em
`Aplicacoes::DEFS`, crie a página protegida por `Autorizacao::pode()` e implemente a ação na
definição da tabela em `Tabelas.php`.

O fiscal/técnico vê apenas as funções de fiscalização; o diretor/tesoureiro/secretário veem as do
módulo de associação; o admin vê todas e os três sistemas.

### 3.9 Produção do Agricultor (implementação em andamento)

O painel `/app/producao/` foi ampliado para cobrir os módulos legados de **Produção Vegetal**,
**Produção Animal** e **Agroindústria**, mantendo separação total do associativo e do controle
social (certificações, visitas, NCs e plano ficam fora deste painel).

- Menu agrupado em **Produção Vegetal / Produção Animal / Agroindústria / Ferramentas**.
- CRUD genérico de **22 recursos** em `producao/registro.php?recurso=...` (lista, busca, inclusão,
  edição, exclusão lógica quando aplicável), com escopo por `associado_id`/propriedade.
- Escrita separada por módulo: `caderno.write` (vegetal/animal) e `agroindustria.write`
  (agroindústria); agricultor só altera o próprio escopo.
- Relatórios com contadores/CSV e página de **rastreabilidade** por lote/código público.
- Auditoria `PRODUCAO_CRIADO/ATUALIZADO/EXCLUIDO`.

Inventário completo do legado e lacunas conscientes (upload de imagens, QR/impressão, painéis
agregados, PDF/Excel, tabelas de detalhe e regras de área): [ANALISE-PRODUCAO-AGRICULTOR.md](ANALISE-PRODUCAO-AGRICULTOR.md).


---

## 4. Sessão, CSRF e proteção de acesso

| Item | Decisão |
|---|---|
| Nome da sessão | `SINPORG_<SISTEMA>` |
| Cookie | `path=/app/<sistema>/`, `HttpOnly`, `SameSite=Lax`, `Secure` quando HTTPS |
| Fixação de sessão | `session.use_strict_mode=1` + `session_regenerate_id(true)` no login |
| Expiração | 8 h de inatividade (renovada a cada requisição) |
| CSRF | token por sessão em todo `POST`; rotacionado no login |
| Força bruta | 5 tentativas por 10 min (por sessão), mensagem genérica |
| Enumeração | e-mail/CPF e senha inválidos devolvem a mesma mensagem |
| Auditoria | `LOGIN`, `LOGOUT`, `LOGIN_FALHOU`, `ACESSO_NEGADO_APP` em `audit_log` |
| Código-fonte | `/app/src/` e `/app/tests/` bloqueados pelo `.htaccess` (403) |

O login aceita **e-mail** (equipe) e **e-mail ou CPF** (agricultor), com
`password_verify()` sobre `usuarios.senha` / `associados.senha` — sem criar uma terceira
base de credenciais.

---

## 5. Como estender

### 5.1 Nova função em um sistema existente

1. Declare a função em `Aplicacoes::DEFS[<slug>]['funcoes']` com `chave`, `titulo`,
   `descricao`, `permissao` e `icone`.
2. Garanta que algum perfil tenha a permissão (catálogo `PERMISSOES_POR_PAPEL` ou
   `papel_permissao` no banco).
3. A função aparece no painel **apenas** para quem tem a permissão; crie a rota real
   e proteja-a com `Plataforma::pode($id, '<permissao>')`.

### 5.2 Novo sistema

1. Adicione a entrada em `Aplicacoes::DEFS` (nome, rota, cor, ícone, permissões,
   perfis aceitos, funções).
2. Crie `novo/index.php`, `novo/login.php`, `novo/logout.php` (3 linhas cada).
3. Nada mais: sessão, login, CSRF, auditoria, 403 e painel por perfil vêm do núcleo.

### 5.3 Uso dentro de uma página de negócio

```php
require dirname(__DIR__) . '/src/Platform/Bootstrap.php';
\App\Platform\Plataforma::iniciar('assoc');

$id = \App\Platform\Plataforma::usuario();
if ($id === null) {
    header('Location: ' . \App\Platform\Plataforma::url('login.php'));
    exit;
}

if (!\App\Platform\Autorizacao::pode($id, 'associados.write')) {
    \App\Platform\Ui::erro(403, 'Sem permissão', 'Seu perfil não pode cadastrar associados.');
    exit;
}
```

---

## 6. Verificação

```powershell
# matriz de perfis (unitário)
vendor\bin\phpunit --filter AutorizacaoTest

# regras do primeiro acesso (unitário)
vendor\bin\phpunit --filter OnboardingTest

# regressão completa
vendor\bin\phpunit

# ponta a ponta: segurança/sessões/admin (senha temporária, restaurada no finally)
php dev-utils\smoke-plataforma.php

# ponta a ponta: pré-cadastro, vínculo do fiscal, ficha de adesão
php dev-utils\smoke-primeiro-acesso.php
```

O `smoke-plataforma` valida 43 pontos (cookies/sessões isoladas, CSRF, admin nos três sistemas,
`15 de 15` funções no assoc, menu lateral com funcionalidades e sistemas e fundo **#E8E8E8**,
**DataTable renderizada** com as colunas do sistema atual e fundo lilás quase transparente,
**atalhos de Meu perfil/Configurações** e bloqueio de `/src/`).
O `smoke-primeiro-acesso` valida 114 pontos (imagem de fundo e abas do login, situação no
pré-cadastro, **aguardando liberação**, liberação pela tela de pendências, vínculo do fiscal,
dados essenciais, ficha de adesão em `ficha_adesao`, aprovação, menu lateral do fiscal com os
**13 itens de fiscalização** (painel do gestor + 12 itens legados), as funcionalidades abrindo em
tela própria, **modal de confirmações de visitas com ciência/reagendamento**, **agendamento de
visita gravado em `visitas_tecnicas`**, **módulo de certificações** (filtros,
criação, edição, renovação, histórico, certificado e exclusão), **módulo de certificadoras**
(lista, criação, edição e membros), **menu lateral em todas as telas internas** (painel,
pendências, agendamento, certificações e ficha), **Meu perfil/Configurações** (topo direito,
validação, preferências e senha) e bloqueio do fiscal na área associativa).

A importação do menu de fiscalização (12 itens) e a ativação do painel do gestor/técnico
estão detalhadas em [ANALISE-MENU-FISCALIZACAO.md](ANALISE-MENU-FISCALIZACAO.md).
`docs/ANALISE-MENU-FISCALIZACAO.md` e o módulo de certificações em
`docs/ANALISE-CERTIFICACOES.md`.

---

## 7. Limitações conhecidas e próximos passos

1. **Escopo de certificadora/associação** (multi-tenant) ainda não filtra consultas de
   negócio — o próximo módulo deve aplicar `certificadora_id`/`associacao_id` no repositório.
2. **Senha do produtor** ainda vive em `associados.senha` (legado); a migração para uma
   credencial única (`usuario`) está prevista no desenho do D1.
3. As funções do painel estão marcadas **"Em implantação"**: são o contrato de UI; as
   telas de negócio entram módulo a módulo, sempre atrás de `Autorizacao::pode()`.
4. `perfil` é derivado dos papéis; a atribuição de papéis do módulo de associação tem tela nova
   em `assoc/papeis.php`, **exclusiva do administrador**; a tela legada de usuários continua
   disponível para papéis de fiscalização.
5. **Aprovação de ficha e liberação de acesso** já estão no novo módulo
   (`assoc/pendencias.php`), reusando `FichaAdesao`/mesma tabela; a tela legada continua
   disponível durante a transição.
6. **Fiscal/técnico sem acesso à área associativa é intencional.** Se um dia precisar de
   consulta pontual, atribua o papel `diretoria` (somente leitura) ou crie uma permissão de
   leitura específica — não devolva `associados.read` ao papel de fiscalização.
7. **Cargos da diretoria**: com vínculo em `associado_associacao` o perfil é automático; sem
   vínculo, atribua `diretor`/`tesoureiro`/`secretario` em `usuario_papel` (papéis criados pelo
   `scripts/migrar-papeis-diretoria.php`).
8. **Liberação de acesso (divergência deliberada):** o legado era exclusivo do Admin; a nova
   plataforma usa `associados.approve` (diretor/gestor de associação), mantendo os mesmos efeitos
   e auditoria. O nível Admin continua exclusivo do administrador.
