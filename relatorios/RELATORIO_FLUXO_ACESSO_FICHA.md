# Relatório — Fluxo único de entrada: pedido de acesso → autorização → ficha → associado

**Objetivo do cliente:** toda nova pessoa entra pelo sistema como CONTA
(`usuarios`); após autorização do Admin, no primeiro acesso é exibida a ficha de
cadastro de associados; submetida e aprovada, os dados da ficha passam para o
cadastro do associado **conservando a relação com a conta** — mesma pessoa, sem
duplicidade de identidade.

## 1. Como ficou o fluxo

| Passo | Quem faz | O que acontece no banco |
|---|---|---|
| 1. Pedido de acesso | a pessoa (público, `/api/pre_cadastro.php`) | linha em **`usuarios`**: `status='pendente'`, `nivel=3` (2 se `perfil=gestor`), `tipo_usuario` e `area_producao`/`tem_agroindustria`. **Nada em `associados`.** |
| 2. Autorização | Admin (`/associados/pendentes`) | `usuarios.status='ativo'` |
| 3. Primeiro acesso | a pessoa (login) | o modal da ficha abre (`mostrar=true`, `sem_ficha`) com os dados da conta |
| 4. Submissão | a pessoa | linha em **`ficha_adesao`** com `usuario_id` e `associado_id = NULL`, `status='pendente'` |
| 5. Aprovação | Diretoria/Admin (`/cadastros-pendentes`) | cria a linha em **`associados`** a partir da ficha e **vincula os dois lados**: `usuarios.associado_id = associados.id` e `associados.user_id = usuarios.id`; a ficha recebe `associado_id`, `numero_cadastro`, `data_entrada` e `status='aprovada'` |

## 2. Arquivos

| Arquivo | Mudança |
|---|---|
| `sql/migracao_ficha_adesao_usuario.sql` | migração versionada (aditiva) |
| `dev-utils/migrar_ficha_usuario.php` | aplicador idempotente (dry-run por padrão) |
| `api/pre_cadastro.php` | produtores passam a ser gravados em `usuarios` (conta), não em `associados` |
| `classes/FichaAdesao.php` | `getByUsuarioId`, `statusDoUsuario`, `salvarPorUsuario`, `associadoIdDoUsuario`, `usuarioIdDoAssociado`, `vincularFicha`, `prepararDados`/`gravarFicha` (reuso), `aprovar()` cria+vincula o associado, `criarAssociadoDaFicha()`, listagens com `LEFT JOIN`, notificação aceita conta |
| `api/ficha_adesao.php` | `contaDaSessao()` (conta `usuarios`, login `associados` ou Portal), status/salvar/submeter pela conta ou pelo associado, prefill da conta |
| `includes/ficha_adesao_modal.php` | modal também para contas `usuarios` de nível 3 (a API decide exibir) |
| `login.php` | conta de pedido de acesso (usuários nível 3 sem cadastro) é levada ao dashboard do agricultor, onde o modal da ficha abre automaticamente (antes caía em `/meu-perfil`, centrado no cadastro do associado que ainda não existe) |
| `doc/MODELO_IDENTIDADE.md` | seção 11 com o fluxo e as regras |

## 3. Regras de integridade (anti-duplicidade)

- **CPF já cadastrado:** a aprovação **reaproveita** o cadastro existente em vez
  de criar outro (mesma pessoa).
- **CPF já com conta vinculada:** a aprovação é **recusada** com mensagem clara —
  evita que um segundo pedido roube/duplique a identidade.
- **Um associado ↔ uma conta:** garantido pelo `UNIQUE` de
  `usuarios.associado_id` e `associados.user_id`.
- **E-mail do cadastro:** se o e-mail da ficha já pertencer a outro cadastro,
  ele é gravado como `NULL` no associado (o login continua na conta em
  `usuarios`) — a aprovação nunca falha por causa disso.
- **Fluxo legado:** fichas de associados já existentes continuam funcionando
  exatamente como antes (nada é migrado à força).

## 4. Evidências

Ver seção 11 do `doc/MODELO_IDENTIDADE.md` (saída real dos testes 1 a 8, com
vínculo bidirecional comprovado, ausência de duplicidade e recusa do pedido
duplicado). Dados de teste criados e **removidos** ao final
(`tmp/cleanup_fluxo.php`); reconciliador de identidades rodou limpo
(0 correções / 0 pendências).

## 5. Pendências / recomendações

1. **Rotina de migração dos cadastros antigos:** associados criados pelo fluxo
   antigo (linha em `associados` com `status='pendente'`) continuam válidos;
   se a associação quiser padronizá-los no novo modelo, é possível converter
   cada um em conta (`usuarios`) + vínculo — o reconciliador já aponta esses
   casos.
2. **Notificação de aprovação:** hoje a pessoa descobre pelo modal no próximo
   acesso; pode ser interessante enviar e-mail (o `Mailer` já existe).
3. **Revisar `hasPerfilCompleto()`** para o novo fluxo: o desvio para
   `/meu-perfil` acontece antes do primeiro acesso à ficha; opcionalmente
   direcionar direto para a ficha quando não houver cadastro de associado.

---

## 6. Verificação independente (agente fiscal) e correções das ressalvas

**Veredito da fiscalização:** **APROVADO COM RESSALVAS** — 67/67 verificações
PASSOU (itens 1 a 9), relatório em
`relatorios/fiscalizacao-fluxo-acesso-2026-02.md`.

Burlas bloqueadas pela fiscalização: aprovar sem ser Diretoria/gestor (403),
anônimo (403), informar `usuario_id`/`associado_id` de terceiro no POST
(ignorado — a API usa a sessão), reenviar/aprovar ficha já aprovada, CPF
duplicado, POST sem CSRF e com CSRF de outra sessão (403), ficha de terceiro por
conta nível 3 e por anônimo ("Acesso negado"), ativação por nível 3/gestor
(negado).

| Ressalva | Situação | Correção aplicada |
|---|---|---|
| **R1 (MÉDIA)** CPF de associado já efetivado (com ficha aprovada) sem conta + novo pedido de acesso: a aprovação estourava a constraint `uq_ficha_associado` e o cliente recebia **erro SQL cru** (`SQLSTATE[23000]…`) | **Corrigida** | `criarAssociadoDaFicha()` detecta que o cadastro do CPF já tem ficha e lança mensagem de negócio; o `catch` de `aprovar()` trata `PDOException` **antes** de `RuntimeException` e nunca expõe SQL. Teste: HTTP 422 com *"Este CPF já possui cadastro de associado com ficha de adesão registrada. Verifique se não é um pedido duplicado."*, sem `SQLSTATE`, sem segundo associado, ficha permanece pendente. |
| **R2 (MÉDIA)** `POST /associados/pendentes` (ativar/inativar) **não validava CSRF** — um POST sem token ativava a conta (a autorização Admin-only estava correta) | **Corrigida** | `CSRFProtection::validatePostRequest()` no processamento + `getTokenField()` nos dois formulários. Teste: POST sem token → status permanece `pendente`; com o token da sessão → `ativo`. |

**Observação da fiscalização (O1)** sobre o
`modulos/associados/ficha_adesao_visualizar.php`: a edição citada **foi a
correção intencional** deste trabalho — o guard passou a exigir que a ficha
pertença ao usuário logado **por conta (`usuario_id`) ou por associado
(`associado_id`)**, fechando exatamente o vazamento que o agente identificou por
leitura de código (ficha de pedido de acesso tem `associado_id` NULL = 0, e
sessão anônima também resolve 0). Teste executado: dono da ficha → conteúdo;
outra conta de nível 3 → "Acesso negado". O hash atual do arquivo
(`787B55CCD9BB8B8334E2F8F9A98638EE`) é **o mesmo que a fiscalização verificou** —
nada mudou depois da bateria final.

Após as correções, a regressão do fluxo completa e do cenário de duplicidade foi
reexecutada com sucesso e os dados de teste foram removidos; o reconciliador de
identidades segue em **0 correções / 0 pendências**.

---

## 7. Revalidação independente das correções e fechamento da observação O3

**Veredito final da fiscalização:** **APROVADO COM RESSALVAS BAIXAS**
(seção 7 de `relatorios/fiscalizacao-fluxo-acesso-2026-02.md`).

| Item revalidado | Resultado |
|---|---|
| **R2 — CSRF em `POST /associados/pendentes`** | **PASSOU (8/8)**: sem token → status permanece `pendente` + "Erro de segurança: token CSRF inválido"; token de outra sessão → não muda; token da própria sessão → ativa/inativa; `csrf_token` presente nos 2 formulários |
| **R1 — CPF com cadastro já registrado** | **PASSOU (6/6)**: HTTP 422 com mensagem de negócio, **sem** `SQLSTATE`/`Duplicate entry`, `COUNT` de associados com o CPF = 1, ficha permanece `pendente`, conta não vinculada; o cenário de **dois pedidos de acesso** com o mesmo CPF também é recusado sem erro SQL |
| **Regressão** | **69/69 PASSOU** (bateria completa, já com o novo CSRF) e **34/34 PASSOU** (revalidação focada: fluxo normal com vínculo bidirecional, fluxo legado, reenvio/aprovação repetida, nível 3 sem aprovar, API sem CSRF, dono × terceiro/anônimo na ficha) |

### Observação O3 — resolvida por unificação da mensagem

A fiscalização apontou (baixa severidade) que o cenário "dois pedidos de acesso
com o mesmo CPF" passou a exibir a mensagem de *ficha registrada* em vez da de
*outra conta vinculada*, porque a nova checagem roda antes. Em vez de escolher
uma das duas, **as duas situações passaram a usar a MESMA mensagem** (constante
`FichaAdesao::MSG_CPF_JA_CADASTRADO`):

> *"Este CPF já possui cadastro de associado no sistema (ficha de adesão e/ou
> conta de acesso já vinculada). Verifique se não é um pedido duplicado."*

Testado nos **dois caminhos**: (a) cadastro com ficha registrada e (b) cadastro
sem ficha, mas com outra conta vinculada — ambos devolvem HTTP 422 com a mesma
mensagem, sem vazar SQL e sem criar segundo associado. Como a redação mudou
depois da revalidação do agente (que registrou o texto anterior), a verificação
do *texto* é a única parte que ficou de fora do carimbo dele; o comportamento
(bloqueio, integridade e ausência de erro SQL) foi reexecutado e confirmado
localmente após a mudança.

### Estado final (após tudo)

- Fluxo completo re-executado: pedido → só `usuarios` → autorização → ficha no
  1º acesso → submissão com `usuario_id` → aprovação cria e **vincula os dois
  lados** → **1 associado por CPF**.
- Duplicidade e R1/R2 revalidados; dados de teste removidos
  (`usuarios`/`associados` teste.* = 0, fichas com `usuario_id` = 0).
- Totais restaurados pelo fiscal: `associados=30`, `usuarios=10`,
  `ficha_adesao=5`. Reconciliador de identidades: **0 correções / 0 pendências**.
