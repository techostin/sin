# Análise — lógica de PRIMEIRO ACESSO do sistema atual × nova plataforma

> **Objetivo:** registrar exatamente como o primeiro acesso funciona hoje (`C:\xampp\htdocs\app`)
> e como a nova plataforma (`/app/assoc/`, `/app/social/`, `/app/producao/`) reproduz essa lógica,
> apontando as divergências encontradas e as correções feitas.
>
> Fontes lidas: `login.php`, `api/pre_cadastro.php`, `modulos/associados/pendentes.php`,
> `classes/AcessoPendente.php`, `classes/User.php` (`hasPerfilCompleto`, `podeAcessarDiretoria`),
> `includes/header.php`, `includes/ficha_adesao_modal.php`, `api/ficha_adesao.php`,
> `classes/FichaAdesao.php`.

---

## 1. Fluxo atual (sistema em produção)

```
[1] PRÉ-CADASTRO (tela de login)
      abas "Primeiro Acesso" + "Perfil de Acesso"
      → usuario informa dados + SITUAÇÃO (vegetal/animal/agroindústria/mista/gestor-fiscal)
      → INSERT usuarios (status='pendente', nivel_solicitado, perfil_solicitado,
                          tipo_usuario, area_producao, tem_agroindustria)
      → mensagem: aguardar confirmação do administrador

[2] LIBERAÇÃO DO ACESSO (Admin / "Cadastros Pendentes")
      AcessoPendente::solicitacoesPendentes()  (usuarios status='pendente')
      AcessoPendente::confirmarPrivilegios()   → status='ativo', nível concedido,
                                                 tipo, orgao/setor/especialidade,
                                                 acesso_confirmado_por/em,
                                                 audit ACESSO_CONFIRMADO
      AcessoPendente::negarSolicitacao()       → status='inativo', motivo,
                                                 audit ACESSO_NEGADO

[3] PRIMEIRO LOGIN (login.php)
      credenciais: usuarios.status='ativo' OU associados.status='ativo'
      (usuarios tem precedência; password_verify)
      → gestor (nível 2) sem órgão+setor+especialidade  → ETAPA 2 (confirmar vínculo)
      → usuarios nível 3 SEM associado/ficha             → dashboard do caderno (modal da ficha)
      → isPrimeiroAcesso = telefone vazio E cpf vazio    → flag primeiro_acesso
      → !hasPerfilCompleto()                             → /meu-perfil
      → isPrimeiroAcesso e não Admin                     → /meu-perfil
      → nível 3 → dashboard do caderno; demais → home

[4] FICHA DE ADESÃO (modal global)
      exibida a usuário logado nível 3 (conta ou associado) até aprovação
      estado vem de api/ficha_adesao.php?action=status:
        sem_ficha/rascunho → formulário
        pendente           → modal "aguardando avaliação" (data de submissão)
        recusada           → parecer + formulário para corrigir
        aprovada           → não exibe
      salvar (rascunho) / submeter (análise) com CSRF

[5] APROVAÇÃO DA FICHA (Diretoria/Presidência ou Admin)
      FichaAdesao::aprovar() → cria associados com os dados da ficha,
                               numero_cadastro, data_entrada, data_analise,
                               vincula usuario (usuarios.associado_id / associados.user_id)
      FichaAdesao::recusar() → parecer_diretoria (ficha pode ser corrigida/reenviada)

[6] VÍNCULO CRIADO
      conta → associado; a partir daí o nível 3 entra no sistema como produtor.
```

### 1.1 Regras exatas (com origem no código)

| Regra | Origem |
|---|---|
| Perfis do pré-cadastro: `vegetal`, `animal`, `agroindustria`, `mista`, `gestor` (este é o **Fiscal/Técnico**) | `api/pre_cadastro.php:31-64` |
| Conta nasce `pendente` e **não** cria `associados` (evita duplicidade) | `api/pre_cadastro.php:105-154` |
| Liberação de acesso: **somente Admin** (`podeConceder`) — nível concedido 1/2/3 | `classes/AcessoPendente.php:81-88, 159-249` |
| Negativa de acesso: **somente Admin**, com motivo e auditoria | `classes/AcessoPendente.php:256-300` |
| Gestor (nível 2) faz etapa 2 quando falta `orgao`, `setor` ou `especialidade` | `login.php:118-137` |
| `hasPerfilCompleto`: Admin sempre completo; Gestor exige órgão+setor+especialidade; Usuário exige **qualquer** um de nicho/telefone/cidade/endereço/CEP | `classes/User.php:420-449` |
| `isPrimeiroAcesso` = telefone vazio **E** CPF vazio; redireciona `/meu-perfil` (exceto Admin) | `login.php:187-204` |
| Nível 3 sem associação/ficha redireciona para o dashboard do caderno (onde o modal da ficha é exibido) — **antes** da checagem de perfil | `login.php:175-185` |
| Ficha só é oferecida a nível 3 (`usuarios` ou `associados`) | `includes/ficha_adesao_modal.php:20-36` |
| Aprovação/recusa da ficha: Diretoria/Presidência ou Admin | `api/ficha_adesao.php:193-211` (`User::podeAcessarDiretoria`) |
| Aprovação cria o `associados` a partir da ficha e vincula a conta | `classes/FichaAdesao.php:703-810, 775-776` |

---

## 2. O que a nova plataforma implementa

| Etapa | Onde | Status |
|---|---|---|
| Pré-cadastro com situação | `Cadastro::processar` + `UiLogin::login` (abas) | ✅ mesmos campos/regras/mensagens |
| Conta pendente aguardando liberação | `Fluxo::login` (status `pendente`) + `UiLogin::aguardando` | ✅ mensagem específica |
| Tela de liberação de acesso | `assoc/pendencias.php` + `Pendencias::liberarConta/negarConta` | ✅ mesmos efeitos/auditoria |
| Vínculo do Fiscal/Técnico | `Onboarding::salvarVinculo` + `UiLogin::vinculo` | ✅ órgão + cargo/especialidade + setor |
| Ficha de adesão | Dashboard com modal automático (`UiFichaModal`) + `Onboarding::salvarFicha` (reusa `FichaAdesao`) | ✅ mesma tabela/classe/status |
| Aprovação/recusa da ficha | `Pendencias::aprovarFicha/recusarFicha` (reusa `FichaAdesao::aprovar/recusar`) | ✅ Diretoria/Admin |
| Perfil essencial (telefone/CPF) | `Onboarding::salvarPerfil` + `UiLogin::perfil` | ✅ equivalente ao `/meu-perfil` |
| Ficha antes do perfil (nível 3 sem cadastro) | `Onboarding::pendencias` + dashboard modal | ✅ |
| Ficha reaparece enquanto pendente | modal no dashboard com status `pendente` | ✅ (reaparece a cada acesso até aprovar) |

### 2.1 Divergências encontradas e corrigidas

1. **Ordem das etapas.** A implementação inicial fazia `vínculo → perfil → ficha`; o legado faz
   `vínculo → ficha` (conta nível 3 sem cadastro é desviada para a ficha **antes** de `/meu-perfil`)
   e só depois `/meu-perfil`. Corrigido em `Onboarding::pendencias`.
2. **Vínculo incompleto.** O legado exige `orgao` **e** `setor` **e** `especialidade`
   (`hasPerfilCompleto`). Passamos a exigir os três (o setor estava opcional).
3. **Conta pendente tinha resposta genérica.** `Identidades` filtrava `status='ativo'`, então quem
   já havia se pré-cadastrado recebia "credenciais inválidas". Agora a senha é conferida também
   para `pendente`/`inativo` e o login mostra **"Aguardando liberação"** (sem revelar status a
   quem não sabe a senha).
4. **Não existia tela de liberação/aprovação no novo módulo.** Criada `assoc/pendencias.php`
   (permissão `associados.approve`) com as duas filas: contas e fichas.
5. **Autoridade da liberação (divergência deliberada).** No legado, `AcessoPendente` só permite
   **Admin**. A regra de negócio atual da associação diz que **diretor/presidente** também libera.
   A tela aceita `associados.approve` (admin, diretor, gestor de associação), mas **os efeitos no
   banco são os mesmos** do legado (status, nível, tipo, vínculo profissional,
   `acesso_confirmado_por/em`, auditoria `ACESSO_CONFIRMADO`/`ACESSO_NEGADO`). O nível **Admin**
   continua exclusivo do administrador.
6. **Ficha modal (paridade).** O legado abre um modal global (`static`, reaparece a cada acesso até
   a aprovação). A nova plataforma reproduz o mesmo comportamento no dashboard do associado e no
   dashboard do agricultor (`assoc/index.php` e `producao/index.php`): o produtor nível 3 sem
   cadastro entra e o modal abre automaticamente (`UiFichaModal`), com máscaras de CPF/telefone/CEP,
   normalização de decimais, botões **Salvar rascunho** e **Enviar ficha para análise** (rascunho
   via `FichaAdesao::salvarPorUsuario(..., false)`), os mesmos estados (`sem_ficha`, `rascunho`,
   `pendente`, `recusada`, `aprovada`), a mesma classe `FichaAdesao` e o mesmo fluxo de aprovação.
   Diferenças de implementação: o envio é um `POST` normal do dashboard (o legado usa AJAX); o
   aviso “Ficha em análise” permanece aberto (como no legado) até a aprovação.

---

## 3. Como validar

```powershell
# unitários do primeiro acesso e de perfis
vendor\bin\phpunit --filter "OnboardingTest|AutorizacaoTest"

# ponta a ponta: pré-cadastro → aguardando → liberação → ficha → análise
php dev-utils\smoke-primeiro-acesso.php   # 29 verificações

# regressão de perfis/sessões/admin
php dev-utils\smoke-plataforma.php        # 29 verificações
```

O smoke do primeiro acesso cobre: imagem de fundo e abas do login, criação da conta pendente,
mensagem "Aguardando liberação", liberação pela tela de pendências, conta ativada, redirecionamento
à ficha, vínculo do Fiscal/Técnico, barreira do fiscal na área associativa, envio da ficha e
status "em análise".

---

## 4. Tela de login — etapas e funcionalidades replicadas

A tela de login da nova plataforma foi estruturada com as **mesmas etapas** do sistema atual
(`login.php`), na mesma ordem e com as mesmas mensagens:

| Etapa/aba | Sistema atual | Nova plataforma |
|---|---|---|
| Fundo e marca | foto da lavoura (`assets/img/login-bg.jpg`), logo SINPORG, título do sistema | idem (mesma imagem/logo, manifest/PWA e apple-touch-icon) |
| **Entrar** | e-mail, senha, "Esqueceu?", olho para mostrar senha, loader "Verificando…" | idem |
| **Primeiro Acesso** | nome, sobrenome, e-mail, senha, confirmação; validação no cliente; "Continuar" | idem (mesmas mensagens: campos obrigatórios, senha mínima 6, senhas não coincidem) |
| **Perfil de Acesso** | 5 situações (Vegetal, Animal, Agroindústria, Mista, Gestor/Fiscal) com descrições, erro se não escolher, "Voltar" e "Solicitar Acesso" | idem (rótulo do gestor corrigido para **Fiscal / Técnico**, mesmas descrições) |
| **Sucesso** | tela "Cadastro enviado!" com "Aguarde a ativação por um administrador" e botão "Ir para Login" | idem |
| Vínculo (etapa 2) | pontos de etapa, órgão/cargo-especialidade/setor, loader "Aguarde…" | idem (setor obrigatório, igual a `hasPerfilCompleto`) |
| Erros | por painel (`cadastro-error`, `perfil-error`) e alerta no login | idem no servidor (CSRF, validação) e no cliente |
| Conta pendente | login bloqueado até Admin liberar | tela **"Aguardando liberação"** (a liberação é feita em `assoc/pendencias.php`) |

> Observação: no legado o envio do pré-cadastro é assíncrono (`fetch` para `api/pre_cadastro.php`)
> e o sucesso aparece sem recarregar. Na nova plataforma o envio é um `POST` normal da própria
> página, que renderiza a mesma tela de sucesso — mesmo fluxo e mesmo resultado para o usuário.

---

## 5. Pendências conhecidas

1. A **ficha completa** agora é exibida no **modal do dashboard** (`UiFichaModal`); a página
   `assoc/ficha.php`/`producao/ficha.php` continua disponível como “Ver minha ficha”/edição direta.
   Ambos usam a mesma tabela — não há duplicidade de dados.
2. A aprovação da ficha na nova tela usa `FichaAdesao::aprovar/recusar`; a tela legada continua
   funcionando durante a transição (mesma tabela/status).
3. O cadastro de `associados` aprovado não cria `usuario_papel`; o perfil do produtor é derivado
   pelo `associado_id` (fallback `produtor`). Cargos de diretoria vêm de `associado_associacao`.
4. Aprovação de **ficha** está liberada para `associados.approve` (diretor, gestor de associação,
   admin). Se a regra exigir apenas Diretoria Executiva, basta restringir a permissão no papel.
