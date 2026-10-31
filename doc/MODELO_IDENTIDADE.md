# Modelo de Identidade — `associados` × `usuarios`

> Documento de referência do projeto SINPORG. Define **quem é quem** no banco e
> como as duas tabelas de pessoas se relacionam. Qualquer tela, relatório,
> script de importação ou integração (PWA/Node) deve seguir estas regras.

## 1. As duas tabelas e a importância de cada uma

| | `associados` | `usuarios` |
|---|---|---|
| **O que representa** | A **pessoa/associado** (cadastro do quadro associativo) | A **conta de acesso ao sistema** (credencial + papel) |
| **Dados que possui** | nome, sobrenome, CPF, RG, nascimento, endereço completo, contato, situação associativa, certificadora | senha de login, `nivel` (1 Admin / 2 Gestor / 3 Usuário), órgão/setor/especialidade, e-mail de login |
| **É dona de** | fichas de adesão, mensalidades, carteirinhas, propriedades, lavouras, atividades, visitas técnicas, certificações, vínculos em `associado_associacao` (tudo por FK `associado_id`) | sessão do sistema (login principal), bridge JWT do PWA/Node, auditoria (`audit_log.user_id`) |
| **Também é tabela de login?** | Sim (`user_table='associados'`) | Sim (`user_table='usuarios'`) |
| **Quantidade hoje** | 30 registros | 11 registros |

**Resumo:** `associados` é a fonte canônica dos **dados da pessoa**; `usuarios` é a
fonte canônica do **acesso e do papel**. Um associado pode existir sem conta de
sistema; uma conta de sistema pode existir sem cadastro de associado (equipe
interna, ex.: Admin, Gestor, fiscal).

## 2. Contrato entre as tabelas (quando a pessoa tem conta)

```
usuarios.associado_id  =  associados.id
associados.user_id     =  usuarios.id       (link reverso obrigatório)
```

Ambas as colunas são **UNIQUE** e devem estar **sempre preenchidas juntas**
(relação 1:1). É esse vínculo — e não o e-mail — que autoriza:

- o Portal do Associado a vincular a sessão principal
  (`PortalAccess::usuarioVinculadoAoAssociado()`);
- o sistema a resolver "qual associado é este usuário"
  (`User::getAssociadoIdResolvido()`);
- a sincronização de dados entre as duas tabelas
  (`modulos/associados/meu_perfil.php`, por FK — nunca por e-mail).

## 3. Por que a mesma pessoa aparecia duas vezes

1. O import/rotina que cria o cadastro também cria a conta de sistema, **sem
   preencher os dois lados do vínculo** (foi o caso do Alderi:
   `usuarios.associado_id=31`, mas `associados.user_id=NULL`).
2. Formatos diferentes para a mesma informação: `usuarios` guardava o **nome
   completo** em `nome` com `sobrenome` vazio, enquanto `associados` separa
   `nome`/`sobrenome` — daí "dois Alderis" nas listagens.
3. Campos duplicados e divergentes (CPF vazio em `usuarios`, telefones
   corrompidos por estouro de INT — `2147483647`).
4. E-mails diferentes para a mesma pessoa: o do cadastro (muitas vezes o
   e-mail coletivo da família) e o da conta (individual).

## 4. Regras de ouro

1. **Uma pessoa = um cadastro em `associados`** (chave natural: CPF) e, no
   máximo, **uma conta em `usuarios`**.
2. Ao criar conta de sistema para um associado, **vincular os dois lados**
   (`usuarios.associado_id` + `associados.user_id`).
3. `associados` é a fonte canônica de nome, sobrenome, CPF, RG, endereço e
   contato. `usuarios` guarda apenas o que é exclusivo de conta
   (senha, nível, órgão/setor, e-mail de login) e **espelha** os dados pessoais.
4. **Nível efetivo:** quando existe conta vinculada, vale `usuarios.nivel`;
   sem conta, vale `associados.nivel`.
5. **E-mail:** a pessoa deve ter um **login único**. O e-mail do cadastro pode
   ser um contato coletivo; nesse caso o link mágico do Portal é enviado para o
   e-mail da conta vinculada (`PortalAccess::emailDeAcessoDoAssociado()`).
6. **Listagens:** telas de *pessoas* leem `associados`; telas de *contas* leem
   `usuarios`. A mesma pessoa não deve aparecer como duas linhas na mesma tela.
7. **Sincronização sempre por FK** (`associado_id`/`user_id`), nunca por e-mail.

## 5. Diagnóstico e reconciliação

Ferramenta: `dev-utils/reconciliar_identidades.php` (somente CLI).

```bash
php dev-utils/reconciliar_identidades.php                  # dry-run: só relatório
php dev-utils/reconciliar_identidades.php --apply           # aplica correções seguras
php dev-utils/reconciliar_identidades.php --user=14         # foca em uma conta
php dev-utils/reconciliar_identidades.php --apply --link=7:13   # confirma um par
```

O que o `--apply` faz (correções seguras, tudo vai para `audit_log`):

1. limpa FKs que apontam para IDs inexistentes;
2. completa o link reverso entre as tabelas;
3. espelha no `usuarios` os dados pessoais ausentes/corrompidos vindos do
   associado (CPF, nome/sobrenome no mesmo formato, telefone);
4. limpa telefones corrompidos (`2147483647`) também em contas sem cadastro;
5. padroniza o formato do nome (separa `nome`/`sobrenome`) em todas as contas.

Operações explícitas (só com `--apply`):

6. `--link=uid:aid` vincula um par confirmado manualmente (exige mesmo e-mail
   ou mesmo CPF; `--force` só após confirmação humana);
7. `--merge=origem:destino` **mescla duas contas da MESMA pessoa**: transfere
   todas as referências (`audit_log`, `auth_refresh_tokens`, `notificacoes`,
   `ficha_adesao.analisado_por`, `visitas_tecnicas.gestor_ciente_user_id`,
   `associados.ficha_aprovada_por`, `certificadoras.gestor_id`), herda o
   vínculo com o associado se o destino não tiver, **mantém os dados da conta de
   destino** (e-mail/CPF/senha), grava a linha removida em
   `tmp/merge_usuarios_<origem>_em_<destino>_<data>.json` e só então remove a
   duplicata. Exige mesmo nome ou mesmo CPF (`--force` para exceções).

As decisões já confirmadas pela associação ficam em
`dev-utils/identidades_ignoradas.json` (`pares_nao_duplicados` e
`emails_login_distintos_ok`) e aparecem no relatório como "decisões
registradas", para o time não reinvestigar o mesmo caso.

O relatório separa o que é **correção automática segura** do que é
**revisão manual** (mesmo nome com CPF/e-mail divergentes, e-mails de login
diferentes do cadastro).

## 6. Criar e editar contas pela interface

- **`/usuario/novo`** (`modulos/usuarios/cadastrar.php`, Admin): o campo
  *"Criar a conta a partir de um associado"* lista apenas associados ativos
  **sem conta**. Ao escolher um, nome, sobrenome, CPF e telefone são copiados do
  cadastro (fonte canônica) e, ao salvar, o sistema grava o vínculo nos dois
  lados dentro de uma transação. O e-mail de login pode ser diferente do e-mail
  do cadastro (ex.: e-mail individual × e-mail coletivo da família).
- **`/usuario/<id>/editar`** (`modulos/usuarios/editar.php`, Admin): permite
  vincular, trocar ou remover o vínculo. Associados que já pertencem a outra
  conta aparecem desabilitados. Com vínculo ativo, o campo Nome fica somente
  leitura (valor do cadastro) e CPF/telefone são sempre os do associado.
  Toda mudança de vínculo é registrada em `audit_log`
  (`IDENTIDADE_VINCULADA` / `IDENTIDADE_DESVINCULADA`).

## 7. Checklist de revisão manual

- [x] Contas com **dois e-mails** para a mesma pessoa: o Alderi foi unificado
      (conta e cadastro com o mesmo e-mail). O sistema aceita os dois cenários —
      com e-mails diferentes, o Link Mágico vai para o e-mail da conta vinculada
      (`PortalAccess::emailDeAcessoDoAssociado()`).
- [ ] Homônimos (`usuarios.id=15` Getúlio/tulio@gmail.com × `associados.id=29`
      Getúlio/getulio@gmail.com — CPFs divergentes): confirmar se é a mesma
      pessoa antes de vincular.
- [ ] `usuarios.id=16` "Gabriel Cibulski" (`nivel=1`, `tipo='usuario'') ×
      `associados.id=33` "Gabriel Matias": aparentemente pessoas diferentes;
      revisar também o nível (1 = Admin) da conta.
- [ ] Contas `nivel=1` com `tipo='usuario'`: revisar se o nível é intencional.

*Última atualização: sessão de correção de identidades (Alderi).*

---

## 11. Fluxo de entrada único (pedido de acesso → ficha → associado)

Implementado o fluxo definido com a associação: **a pessoa entra pelo sistema de
dentro para fora** — primeiro vira CONTA (`usuarios`), depois de autorizada e
com a ficha de adesão aprovada vira ASSOCIADO (`associados`), sempre vinculada
à mesma conta. Nunca as duas coisas soltas.

```
  (1) pedido de acesso            (2) Admin autoriza            (3) 1º acesso
  POST /api/pre_cadastro.php      /associados/pendentes        modal da ficha
  -> usuarios (status pendente)   -> usuarios.status = 'ativo' -> ficha_adesao
     nivel 3 + perfil de produção                                 (usuario_id)
                                                                      |
  (5) aprovado: cria o cadastro e vincula os DOIS lados               |
      usuarios.associado_id <-> associados.user_id  <-----------------+
      ficha_adesao.associado_id = novo associado (4) Diretoria aprova
                                                     /cadastros-pendentes
```

### Mudanças de schema (aditivas; nenhum dado existente foi alterado)
| Objeto | Mudança | Por quê |
|---|---|---|
| `ficha_adesao.usuario_id` | nova coluna INT NULL + UNIQUE + FK `usuarios(id)` ON DELETE SET NULL | a ficha passa a existir ANTES do cadastro, ligada à conta |
| `ficha_adesao.associado_id` | passou a NULLABLE | idem (hoje todas as fichas existentes continuam com o valor preenchido) |
| `usuarios.area_producao` / `usuarios.tem_agroindustria` | novas colunas | o pedido de acesso já informa o perfil de produção; na aprovação são copiados para o cadastro |

Migração versionada em `sql/migracao_ficha_adesao_usuario.sql` e aplicada por
`dev-utils/migrar_ficha_usuario.php` (idempotente, com dry-run por padrão).

### Regras implementadas
1. **`api/pre_cadastro.php`** grava sempre em `usuarios` (nível 3 para
   produtores, 2 para gestor), com `status='pendente'`, `tipo_usuario` e o
   perfil de produção. **Não cria mais** linha em `associados`.
2. **Autorização:** `/associados/pendentes` (Admin) já ativava as duas tabelas —
   agora é o único caminho de entrada para produtores.
3. **Primeiro acesso:** o modal da ficha (`includes/ficha_adesao_modal.php`) é
   oferecido também para contas `usuarios` nível 3; a API
   `/api/ficha_adesao.php?action=status` resolve o dono **pela sessão** e
   devolve `mostrar=true` / `sem_ficha` com os dados da conta para prefill.
   O login de uma conta de pedido de acesso (sem cadastro de associado) segue
   para `/caderno-campo/dashboard-agricultor`, onde o modal abre automaticamente.
4. **Submissão:** a ficha é gravada com `usuario_id` (e `associado_id` NULL)
   enquanto não existe cadastro.
5. **Aprovação (`FichaAdesao::aprovar`):** cria o registro em `associados` a
   partir dos dados da ficha (`criarAssociadoDaFicha`), preenche número de
   cadastro/data de entrada e **vincula os dois lados** dentro da mesma
   transação: `usuarios.associado_id = associados.id` e
   `associados.user_id = usuarios.id`.
6. **Sem duplicidade:** se o CPF da ficha já tem cadastro, a aprovação
   **reaproveita o cadastro** e apenas vincula; se esse cadastro já tiver outra
   conta vinculada, a aprovação é **recusada** com a mensagem *"Este CPF já
   possui cadastro de associado vinculado a outra conta de acesso. Verifique se
   não é um pedido duplicado."*
7. **Fluxo legado preservado:** fichas de associados já cadastrados continuam
   com `associado_id` preenchido e a aprovação segue sem criar novo cadastro.
8. **Listagens:** `FichaAdesao::listarPendentes()`/`listarAvaliadas()` usam
   `LEFT JOIN` de `associados` e `usuarios`, então fichas de pedidos de acesso
   (ainda sem cadastro) aparecem para a Diretoria.

### Evidência (execução real, dados de teste removidos ao final)
```
1) POST /api/pre_cadastro.php (perfil=vegetal)
   -> usuarios #125 {nivel:3, tipo_usuario:agricultor, area_producao:'vegetal',
                     status:'pendente', associado_id:null}
   -> associados criados: 0 (correto)
2) login antes da autorização -> permanece na tela de login (sem sessão)
3) Admin /associados/pendentes (acao=ativar, origem=usuario) -> status 'ativo'
4) /api/ficha_adesao.php?action=status -> mostrar=true, status=sem_ficha,
   prefill nome='Fluxo Teste' (vindo da conta)
5) POST /api/ficha_adesao.php (submeter) -> ficha #23
   {usuario_id:125, associado_id:null, status:'pendente'}
6) POST ...action=aprovar (Admin) -> "Ficha aprovada! Nº AAES-00023"
   associados #102 {nome:'Fluxo', cpf, cidade:'Porto Alegre', telefone,
                    status:'ativo', status_associado:'Ativo', user_id:125}
   usuarios  #125 {associado_id:102}
   ficha     #23  {status:'aprovada', associado_id:102, usuario_id:125}
   vínculo bidirecional: OK  |  associados com o CPF: 1 (sem duplicata)

7) Duplicidade: 2º pedido com o mesmo CPF -> aprovação HTTP 422
   "Este CPF já possui cadastro de associado vinculado a outra conta de acesso."
   associados com o CPF: continua 1

8) Fluxo legado: ficha de associado já cadastrado -> aprovação manteve
   associado_id e NÃO criou novo cadastro (36 -> 36)
```

---

## 12. Confirmação de privilégios do pedido de acesso (Gestor/Fiscal)

O nível pedido é apenas uma **solicitação**. A liberação passa por uma análise
explícita do Admin, que pode **confirmar, reduzir, elevar ou recusar** os
privilégios (ver `classes/AcessoPendente.php` e
`relatorios/RELATORIO_CONFIRMACAO_PRIVILEGIOS.md`):

| Campo em `usuarios` | Papel |
|---|---|
| `nivel_solicitado` / `perfil_solicitado` | o que a pessoa pediu (ex.: 2 / gestor) |
| `nivel` + `tipo` | o que o Admin **concedeu** (podem diferir do pedido) |
| `acesso_confirmado_por` / `acesso_confirmado_em` | quem analisou e quando |
| `acesso_negado_motivo` | motivo, quando o pedido é recusado |

Regra: **somente Admin** concede; níveis válidos 1 (Admin), 2 (Gestor/Fiscal) e 3
(Usuário); tudo auditado (`ACESSO_CONFIRMADO` / `ACESSO_NEGADO`). Lembre-se:
**número menor = mais privilégio**.
