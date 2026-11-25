# Análise: dois "Alderi" no sistema — mesma pessoa em duas tabelas

**Pergunta:** existem dois usuários com o nome "Alderi"; em quais tabelas estão,
qual a relação entre elas, qual a importância de cada uma e qual a solução?

**Resposta curta:** são **a mesma pessoa**, cadastrada nas duas tabelas de
identidade do sistema — `associados` (cadastro do membro) e `usuarios` (conta de
acesso). O vínculo existia só de um lado e os dados estavam divergentes. Foi
reconciliado.

---

## 1. Onde estavam os dois registros

| | Tabela `associados` (id **31**) | Tabela `usuarios` (id **14**) |
|---|---|---|
| nome / sobrenome | `Alderi` / `da Silva Porto` | `Alderi da Silva Porto` / *(vazio)* |
| CPF | `21326554879` | *(vazio)* |
| e-mail | `familiacibulskiorganicos@gmail.com` | `alderi@gmail.com` |
| telefone | `51985271131` | `2147483647` ← valor corrompido (estouro de INT) |
| nível | 3 (Usuário) | 3 (Usuário) |
| certificadora | 16 | 16 |
| link | `user_id = NULL` | `associado_id = 31` |
| criado em | `2026-01-27 18:32:37` | `2026-01-27 18:32:37` ← **mesmo instante** |
| dependências | ficha, 1 mensalidade, carteirinha, **2 propriedades**, 3 atividades, 4 visitas técnicas, 1 certificação, 2 vínculos de associação | apenas 3 registros em `audit_log` |

## 2. A relação entre as tabelas (e por que é a mesma pessoa)

- **Vínculo declarado:** `usuarios.associado_id = 31` → a conta aponta para o
  cadastro. O link reverso (`associados.user_id`) estava **em branco**.
- **Evidências de que é a mesma pessoa:** mesmo nome completo, mesmo
  `created_at` (ao segundo), mesma certificadora (16), mesma faixa de nível e o
  vínculo explícito na FK. A diferença de e-mail é explicada pelo cadastro usar
  o e-mail **coletivo da família** e a conta usar o e-mail **individual**.

## 3. Importância de cada tabela no projeto

- **`associados` — a pessoa (cadastro do quadro associativo).** É a tabela
  "dona" da vida do associado: ficha de adesão, mensalidades, carteirinha,
  propriedades, lavouras, atividades do caderno de campo, visitas técnicas,
  certificações e vínculos de diretoria (`associado_associacao`). Também serve
  como login (`user_table='associados'`). **Excluí-la significaria perder todo o
  histórico do associado** (por isso é a fonte canônica dos dados pessoais).
- **`usuarios` — a conta de acesso ao sistema.** Guarda a senha do sistema, o
  `nivel` (1 Admin / 2 Gestor / 3 Usuário), órgão/setor/especialidade e o
  `certificadora_id`. É a tabela usada pelo login principal e pela ponte
  JWT/PWA (`api/legacy/session-bridge.php`), e a que a auditoria referencia
  (`audit_log.user_id`).

O contrato do projeto é: **1 pessoa = 1 registro em `associados`** e, se ela
precisar de acesso ao sistema, **1 conta em `usuarios`** ligada pelos dois
lados (`usuarios.associado_id` ↔ `associados.user_id`).

## 4. Causa-raiz

1. O import/rotina criou os dois registros **sem fechar o vínculo reverso**
   (`associados.user_id` ficou nulo).
2. Formats diferentes: `usuarios.nome` recebeu o **nome completo** e
   `sobrenome` ficou vazio — por isso a listagem mostra "dois Alderis".
3. Campos duplicados e divergentes (CPF só no cadastro; telefone corrompido por
   estouro de INT na conta).
4. E-mails distintos (cadastro coletivo × conta individual) sem uma regra clara
   de qual é o login.

O mesmo padrão foi encontrado em outros registros: `usuarios.id=7` ↔
`associados.id=13` (Edmilson, FKs apontando para o id 1, inexistente),
`id=11` ↔ `28` e `id=12` ↔ `29` (esses dois já corretos).

## 5. Solução aplicada

### 5.1 Ferramenta de diagnóstico/reconciliação (nova)
`dev-utils/reconciliar_identidades.php` — dry-run por padrão, `--apply` aplica
apenas correções seguras, `--link=uid:aid` confirma um par, `--force` para
casos sem e-mail/CPF coincidente. Nunca apaga registros e registra tudo em
`audit_log` (`IDENTIDADE_RECONCILIADA`, `IDENTIDADE_VINCULADA`).

### 5.2 Dados corrigidos (aplicado)
| Correção | Antes → Depois |
|---|---|
| Link reverso do Alderi | `associados[31].user_id`: NULL → **14** |
| Formato do nome | `usuarios[14]`: `nome='Alderi da Silva Porto'`/sobrenome vazio → **`nome='Alderi'` / `sobrenome='da Silva Porto'`** |
| CPF na conta | `usuarios[14].cpf`: vazio → **21326554879** |
| Telefone corrompido | `usuarios[14].telefone`: 2147483647 → **51985271131** |
| Outros pares | `usuarios[7]↔associados[13]` vinculados (mesmo e-mail); FKs inválidas (`usuarios[7].associado_id=1`, `associados[13].user_id=1`, `associados[30].user_id=13`) limpas; CPF/nome/telefone espelhados em 11 e 12; telefone corrompido de `usuarios[10]` limpo |
| Resultado final | dry-run com **0 correções pendentes**; 4 pares vinculados nos dois sentidos; 0 FKs inválidas |

### 5.3 Código (para o problema não voltar)
- `classes/PortalAccess.php`: `associadoIdPorEmail()` resolve o e-mail **do
  cadastro ou da conta de sistema** e `emailDeAcessoDoAssociado()` define para
  qual e-mail vai o Link Mágico (conta individual quando existir).
- `modulos/portal/login.php`: pedido e validação do Link Mágico usam essa
  resolução — Alderi entra pelo Portal com **qualquer um dos dois e-mails**.
- `login.php` e `classes/User.php`: o nome exibido na sessão passa a ser
  `nome + sobrenome` (com o nome agora separado, a barra superior continua
  mostrando "Alderi da Silva Porto").
- Documento do modelo: `doc/MODELO_IDENTIDADE.md`.

## 6. Evidências

```
php tmp/test_identidade_alderi.php
  associadoIdPorEmail(alderi@gmail.com) [conta]             31    OK
  associadoIdPorEmail(familiacibulskiorganicos@gmail.com)   31    OK
  usuarioVinculadoAoAssociado(31).id                        14    OK
  emailDeAcessoDoAssociado(31, email familia)      'alderi@gmail.com' OK
  associadoIdDoToken(token do e-mail da conta)               31    OK
  TODOS OS TESTES PASSARAM

php dev-utils/reconciliar_identidades.php
  --- Correções propostas (0) ---  (nada a corrigir)
  --- FKs invalidas: usuarios=0 associados=0 ---
```

Regressão do Portal após as mudanças (criar/limpar fixtures): login próprio OK,
bloqueio de terceiro com a mensagem de segurança OK, IDOR por token de terceiro
bloqueado, reuso de token falhando, link mágico do titular funcionando.

## 7. Decisões que dependem da associação (não alteradas automaticamente)

1. ~~**E-mail único para o Alderi**~~ **RESOLVIDO pela associação:** a conta
   (`usuarios[14]`) e o cadastro (`associados[31]`) passaram a usar o **mesmo
   e-mail** (`familiacibulskiorganicos@gmail.com`), unificação feita pela tela
   `/usuario/14/editar` em 14/09 às 11:08 (registrado no log do Apache). O
   vínculo 1:1 continua íntegro e o sistema suporta os dois cenários: se os
   e-mails forem diferentes, o Link Mágico é enviado para o e-mail da conta
   vinculada; se forem iguais, tudo resolve pelo mesmo endereço.
2. **`usuarios.id=15` (Getúlio, tulio@gmail.com, CPF 46874651351) ×
   `associados.id=29` (Getúlio, getulio@gmail.com, CPF 64049876000)**: mesmo
   nome, CPFs diferentes → pode ser uma **segunda conta da mesma pessoa** ou um
   homônimo. Se confirmado: `php dev-utils/reconciliar_identidades.php --apply --link=15:29 --force`.
3. **`usuarios.id=16` "Gabriel Cibulski" (nível 1) × `associados.id=33`
   "Gabriel Matias"**: aparentemente pessoas diferentes; aproveitar para revisar
   o nível 1 (Admin) da conta.

## 8. Prevenção recomendada (próximos passos de código)

- `modulos/usuarios/cadastrar.php`: criar a conta **a partir** de um associado
  existente (ou criar o associado junto), preenchendo os dois lados do vínculo.
- `modulos/usuarios/editar.php`: manter o link sincronizado ao salvar.
- Rodar `dev-utils/reconciliar_identidades.php` (dry-run) periodicamente ou no
  CI, falhando quando houver divergência; exigir vínculo 1:1 para contas de
  associados.

---

## 9. Execução dos itens 2 e 3 (decisões da associação)

### Item 2 — Getúlio: mantidos `getulio@gmail.com` e CPF `64049876000`
Havia **duas contas da mesma pessoa**:
`usuarios[12]` (getulio@gmail.com, CPF 64049876000, já vinculada ao `associados[29]`)
e `usuarios[15]` (tulio@gmail.com, CPF 46874651351, sem vínculo). Aplicado:

```bash
php dev-utils/reconciliar_identidades.php --apply --merge=15:12
```

| Antes | Depois |
|---|---|
| 2 contas para a mesma pessoa (12 e 15) | **1 conta (#12)** — canônica: `getulio@gmail.com` / CPF `64049876000` |
| `audit_log.user_id=15` → 7 registros (4 LOGIN + 3 LOGOUT) | transferidos para `user_id=12` (histórico preservado) |
| conta #15 existia como login `tulio@gmail.com` | removida; login `tulio@gmail.com` deixa de existir |
| `associados[29].user_id` | mantido em 12 (vínculo 1:1 íntegro) |

Backup da linha removida: `tmp/merge_usuarios_15_em_12_20260914_160109.json`
(com auditoria `IDENTIDADE_MESCLADA`).

### Item 3 — Gabriel: mantido **Gabriel Matias** (`associados[33]`)
Confirmado que `usuarios[16]` ("Gabriel Cibulski", biel@gmail.com, nível 1) e
`associados[33]` ("Gabriel Matias", gabriel@gmail.com) são **pessoas
diferentes**: nenhum vínculo foi criado e o cadastro de Gabriel Matias foi
mantido intacto. A decisão ficou registrada em
`dev-utils/identidades_ignoradas.json` (o reconciliador não volta a sinalizar
esse par). Aproveitou-se para revisar o formato do nome das contas de equipe
(`usuarios[16]` agora é `Gabriel` / `Cibulski`).

## 10. Vínculo na criação e edição de contas (implementado)

- `modulos/usuarios/cadastrar.php`: novo campo **"Criar a conta a partir de um
  associado"** (lista só associados ativos sem conta). Ao salvar, os dados
  pessoais vêm do cadastro, o vínculo é gravado **nos dois lados** dentro de uma
  transação e tudo é auditado. JS de conveniência preenche nome/e-mail ao
  escolher o associado.
- `modulos/usuarios/editar.php`: novo campo **"Associado vinculado"** (Admin),
  com os associados já vinculados a outra conta desabilitados; ao selecionar, o
  Nome passa a somente leitura (valor canônico do cadastro) e CPF/telefone são
  sincronizados; desvincular limpa os dois lados; conflitos são recusados com
  mensagem (`"Este associado já está vinculado a outra conta de acesso."`).

### Evidências (HTTP, com fixture temporária criada e removida ao final)
```
GET /usuario/novo ............... 200, select de associados presente
POST /usuario/novo (assoc 94) ... conta #122 criada com nome/sobrenome/CPF do cadastro
                                  associados[94].user_id = 122  (link reverso OK)
GET /usuario/122/editar ......... select + Nome readonly + link para o cadastro
POST editar (associado_id vazio)  usuarios.associado_id = NULL e associados.user_id = NULL
POST editar (revinculando) ...... dados canônicos restaurados e link reverso = 122
POST editar (associado de outra conta)  recusado; vínculo anterior preservado (94)
```

Ao final, os dados de teste foram removidos e o reconciliador rodou limpo:
**0 correções propostas, 0 pendências**.
