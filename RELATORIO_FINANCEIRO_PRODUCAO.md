# Relatório Técnico — Módulos FINANCEIRO e PRODUÇÃO (SINPORG)
Workspace: `C:\xampp\htdocs\app` · PHP puro (sem framework) + MySQL/MariaDB · Front controller `index.php`
Documento gerado por leitura integral dos arquivos. Cada afirmação traz citação `arquivo:linha`.

---

## 0. Base de análise e metodologia

- Front controller: `.htaccess:24` manda toda requisição que não seja arquivo/diretório existente para `index.php?url=$1`. Os arquivos reais em `api/**` são servidos **diretamente** (sem passar pelo roteador) — é o caso dos webhooks PIX.
- O roteador amigável está em `index.php:4-1179`; `$parts = explode('/', $rawUrl)` em `index.php:8`.
- **Não existe guarda global de autenticação no roteador**: `index.php:1-8` apenas carrega `config/config.php`, e `config/config.php` termina em `config/config.php:104` (apenas bootstrap de sessão, sem checagem de login). Portanto **cada arquivo de módulo é responsável pela própria autorização**, e páginas que não checam nada são públicas.
- Bootstrap das telas internas: `includes/header.php:5` cria `$user = new User()`; `includes/header.php:16-19` redireciona para `/login` se `!$user->isLoggedIn()` (salvo `$PERMITIR_ACESSO_ANONIMO` / `$PERMITIR_SESSAO_PORTAL`). Várias telas carregam `header.php` **depois** de fazer o próprio check (ex.: `modulos/contas/*`), outras fazem o check antes (ex.: `modulos/caixa/*`).
- Semântica de nível (`config/config.php:22-24`): `NIVEL_ADMIN=1`, `NIVEL_GESTOR=2`, `NIVEL_USUARIO=3`.
  - `User::hasPermission()` (`classes/User.php:114-139`): nível 1 passa sempre; exige `is_numeric($_SESSION['user_nivel'])` e `> 0` (endurecimento anti-escalonamento em `classes/User.php:119-133`).
  - `User::podeAcessarDiretoria()` (`classes/User.php:380-383`) = Admin **ou** membro de diretoria/comissão.
  - `User::isMembroDiretoria()` (`classes/User.php:363-370`); cargos em `classes/User.php:278-287` (diretor, secretario, tesoureiro, comissao_educativa, comissao_etica, conselho_fiscal, presidente, vice-presidente).
  - `User::getAssociadoId()` (`classes/User.php:259-272`) devolve `null` em 3 cenários — inclusive quando o usuário é da tabela `usuarios` sem `associado_id` e o vínculo só existe por `associados.user_id`.
  - `User::getAssociadoIdResolvido()` (`classes/User.php:299-329`) é a versão robusta (cobre as 3 formas de sessão).
- `Database::getInstance()` usa `PDO::ERRMODE_EXCEPTION` (`classes/Database.php:15`) → **qualquer SQL com coluna inexistente lança exceção**; onde não há `try/catch`, resulta em erro fatal/HTTP 500.

### Roteamento dos módulos analisados (`index.php`)

| Rota | Arquivo | Linha do roteador |
|---|---|---|
| `/caixa` | `modulos/caixa/listar.php` | `index.php:1093-1095` |
| `/caixa/lancamento` | `modulos/caixa/cadastrar.php` | `index.php:1096-1098` |
| `/caixa/cobranca` | `modulos/caixa/cobranca.php` | `index.php:1099-1101` |
| `/caixa/relatorio` | `modulos/caixa/relatorio.php` | `index.php:1102-1104` |
| `/caixa/{id}/excluir` | `modulos/caixa/excluir.php` (`$_GET['id']=$parts[1]`) | `index.php:1105-1107` |
| `/contas` | `modulos/contas/listar.php` | `index.php:801-804` |
| `/conta/novo` | `modulos/contas/cadastrar.php` | `index.php:805-808` |
| `/conta/{id}/editar` | `modulos/contas/editar.php` | `index.php:809-813` |
| `/contas/excluir` (na prática `/contas/excluir` cai no roteador como `$parts[1]='excluir'**) | `modulos/contas/excluir.php` **não possui rota** | — |
| `/mensalidades` | `modulos/mensalidades/listar.php` | `index.php:1061-1063` |
| `/mensalidade/novo` | `modulos/mensalidades/cadastrar.php` | `index.php:1064-1066` |
| `/mensalidade/gerar` | `modulos/mensalidades/gerar.php` | `index.php:1067-1069` |
| `/mensalidade/{id}/baixa` | `modulos/mensalidades/baixa.php` | `index.php:1070-1072` |
| `/mensalidade/{id}/estornar` | `modulos/mensalidades/estornar.php` | `index.php:1073-1075` |
| `/mensalidade/{id}/excluir` | `modulos/mensalidades/excluir.php` | `index.php:1076-1078` |
| `/planos` | `modulos/mensalidades/planos.php` | `index.php:1079-1081` |
| `/plano/novo` | `modulos/mensalidades/plano_cadastrar.php` | `index.php:1082-1084` |
| `/plano/{id}/editar` | `modulos/mensalidades/plano_editar.php` | `index.php:1085-1087` |
| `/plano/{id}/excluir` | `modulos/mensalidades/plano_excluir.php` | `index.php:1088-1090` |
| `/comunicacao` | `modulos/comunicacao/index.php` | `index.php:1166-1168` |
| `/fornecedor` · `/fornecedor/novo` · `/fornecedor/{id}/editar` | `modulos/fornecedor/{listar,cadastrar,editar}.php` | `index.php:739-751` |
| `/agroindustria/minhas` · `/minha/nova` · `/minha/{id}/editar` | `minhas_*.php` | `index.php:920-927` |
| `/agroindustria/setores` · `/setor/novo` · `/setor/{id}/editar` | `setores_*.php` | `index.php:929-936` |
| `/agroindustria/categorias` · `/categoria/novo` · `/categoria/{id}/editar` | `categorias_*.php` | `index.php:938-945` |
| `/agroindustria/atividades` · `/atividade/novo` · `/atividade/{id}/editar` | `atividades_*.php` | `index.php:947-954` |
| `/agroindustria/ocorrencias` · `/ocorrencia/novo` · `/ocorrencia/{id}/editar` | `ocorrencias_*.php` | `index.php:956-963` |
| `/agroindustria/receitas` · `/receitas/nova` · `/receitas/{id}/editar` | `receitas_*.php` | `index.php:965-972` |
| `/agroindustria/higienizacao` · `/higienizacao/nova` | `higienizacao_*.php` | `index.php:974-978` |
| `/agroindustria/ordens` · `/ordens/nova` · `/ordens/{id}` | `ordens_{listar,cadastrar,visualizar}.php` | `index.php:980-988` |
| `/agroindustria/rastreabilidade` | `agroindustria/rastreabilidade.php` (**pública**) | `index.php:989-991` |
| `/producao-animal` (painel) | `producao_animal/painel.php` | `index.php:994-996` |
| `/producao-animal/lotes` · `/lote/novo` · `/lote/{id}/editar` | `lotes_*.php` | `index.php:997-1005` |
| `/producao-animal/alimentacao` · `/alimentacao/novo` · `/alimentacao/{id}/editar` | `alimentacao_*.php` | `index.php:1006-1014` |
| `/producao-animal/sanidade` · `/sanidade/novo` · `/sanidade/{id}/editar` | `sanidade_*.php` | `index.php:1015-1023` |
| `/producao-animal/reproducao` · `/reproducao/novo` · `/reproducao/{id}/editar` | `reproducao_*.php` | `index.php:1024-1032` |
| `/producao-animal/ambientes` · `/ambiente/novo` · `/ambiente/{id}/editar` | `ambientes_*.php` | `index.php:1033-1041` |
| `/producao-animal/rastreabilidade?uuid=|?lote=` | `producao_animal/rastreabilidade.php` (**pública**) | `index.php:1042-1044` |
| `/manejo-animal` · `/novo` · `/{id}/editar` · `/{id}` | `manejo_animal/animais_*.php` | `index.php:1047-1058` |

Observações de roteamento:
- A rota `/contas/excluir` **não existe** em `index.php` (não há `conta/{id}/excluir`) → a tela `modulos/contas/excluir.php` é inalcançável por URL amigável; além disso ela só foi desenhada para POST com `id` no corpo (`modulos/contas/excluir.php:12`) e faz `header('Location: listar.php?msg=excluido')` (caminho **relativo**, `modulos/contas/excluir.php:23`) → resolveria para `/app/listar.php` (404).
- `/comunicacao` não tem subrotas; os links WhatsApp são obtidos por AJAX em `/api/comunicacao/whatsapp_links.php`.

---


---


# PARTE 1 — GRUPO FINANCEIRO

## 1.1 Classes de domínio financeiro

### `classes/PlanoMensalidade.php` (114 linhas)
- **Propósito**: CRUD da tabela `planos_mensalidades` (tabela de preços por associação: mensalidade/anuidade/jóia).
- **Tabelas**: `planos_mensalidades p` + `LEFT JOIN associacoes ac ON ac.id = p.associacao_id` (`classes/PlanoMensalidade.php:18-20`).
- **Métodos**: `getAll($associacaoId)` (`:15-34`, filtra por `p.associacao_id`, ordena `ativo DESC, nome ASC`); `getAtivos()` (`:36-42`, filtro em PHP por `ativo === 1`); `getById()` (`:44-54`); `create()` (`:56-78`); `update()` (`:80-102`); `delete()` (`:104-113`, DELETE físico).
- **Regras/validações** (`:59-66` e `:83-90`): `Sanitizer::sanitizeArray()`; `associacao_id` vazio → `NULL` (plano vale para todas); `valor = Sanitizer::sanitizeMoney()`; **`dia_vencimento` fica travado no intervalo 1..28** (`< 1 → 10`; `> 28 → 28`); `tipo` restrito a `['mensalidade','anuidade','joia']` (fallback `mensalidade`); `ativo` = presença do checkbox.
- **Riscos**: `delete()` é exclusão física; a proteção de histórico é feita no controlador (`plano_excluir.php`), não na classe.

### `classes/Mensalidade.php` (748 linhas) — coração do financeiro
- **Tabelas**: `mensalidades m` LEFT JOIN `associados a`, `associacoes ac`, `planos_mensalidades p` (`:18-29`, `:66-74`, `:87-93`); `pagamentos` (`:109-113`, `:239-245`, `:403-409`); `caixa` (`:265-270`, `:422-427`); `associado_associacao aa` + `associados` no lote (`:144-149`).
- **Leituras**:
  - `getAll(array $filtros)` `:15-61` — filtros dinâmicos por `associacao_id`, `ano_referencia`, `mes_referencia`, `status_mensalidade`, `associado_id`; ordenação `ano DESC, mes DESC, data_vencimento DESC`.
  - `getById()` `:63-82`; `getByAssociado($id,$limite)` `:84-104` (usa `LIMIT ` . (int)$limite — interpolação de inteiro, sem risco de injeção).
  - `getPagamentos($mensalidadeId)` `:106-118` — `WHERE referencia_type='mensalidade' AND referencia_id=?`.
- **`gerarLote(array $dados)` `:124-214` — geração de mensalidades do mês**:
  1. `associacao_id` obrigatório (`:133-135`), `valor > 0` obrigatório (`:136-138`), mês 1..12 (`:139-141`).
  2. Universo de cobrança (`:144-152`): `SELECT DISTINCT a.id, a.vinculo FROM associado_associacao aa INNER JOIN associados a ON a.id = aa.associado_id WHERE aa.associacao_id = :assoc AND a.status = 'ativo' AND (a.status_associado IS NULL OR a.status_associado NOT IN ('Desligado','Falecido'))` — ou seja, **cobra quem está ativo e não desligado/falecido** (comentário explícito em `:143`).
  3. Vencimento calculado como `sprintf('%04d-%02d-%02d', ano, mes, diaVencimento)` (`:158-161`) com `dia_vencimento` também travado em 1..28.
  4. Idempotência por **checagem prévia** (`:165-170`): `SELECT COUNT(*) ... AND (plano_id = ? OR (plano_id IS NULL AND ? IS NULL))`; se > 0 → `pulados++` (`:188-191`).
  5. Inserção (`:172-177`): `fonte='automatica'`, `status_mensalidade='pendente'`, `created_at/updated_at=NOW()`.
  6. Retorno: `['sucesso','inseridos','pulados','total_associados']` (`:204-209`).
  - **Não usa o `valor` nem o `dia_vencimento` do plano**: o valor vem do formulário (`modulos/mensalidades/gerar.php:103`) e o dia default é 10 (`classes/Mensalidade.php:158`). O `plano_id` é apenas rótulo/agrupador — e o seletor de planos **não é filtrado pela associação escolhida** (`gerar.php:40` chama `getAtivos()` sem `associacaoId`).
  - **Idempotência é frágil**: verificação + inserção não são atômicas e não há `UNIQUE` no banco; duas execuções concorrentes podem duplicar.
- **`baixar($id, array $dados)` `:219-292` — baixa manual (ou automática via PIX)**:
  - Valida existência (`:222-225`); `metodo_pagamento` restrito a `['PIX','Boleto','Cartao','Dinheiro','Transferencia']` (fallback `Dinheiro`, `:227-231`); data default `date('Y-m-d')` (`:232`); **valor default = `valor_cobrado`** mas pode ser sobrescrito pelo POST (`:233`) → pagamento parcial é aceito sem controle de saldo.
  - Transação (`:237`): INSERT em `pagamentos` com `status_pagamento='pago'`, `referencia_type='mensalidade'`, `referencia_id=$id`, `comprovante_url` (`:239-258`) → UPDATE `mensalidades` para `'pago'` + `data_pagamento` + `observacoes` (`:260-263`) → INSERT em `caixa`: `tipo='Entrada'`, `categoria='Mensalidade'`, `referencia_type='mensalidade'`, `referencia_id=$id`, `criado_por=$_SESSION['user_id']` (`:265-279`). Commit (`:283`).
  - Retorna `caixa_id` (`:284`). Em `PDOException` → rollback + erro (`:285-291`).
  - **Não grava `pagamentos.txid`** (coluna existe e é UNIQUE — `migration.sql:1235,1240`), e **não toca em `contas`**.
- **`reverterBaixa($id)` `:298-337` — estorno**:
  - Exige status `'pago'` (`:306-308`).
  - Transação: status novo = `'atrasado'` se `strtotime(data_vencimento) < strtotime(date('Y-m-d'))` senão `'pendente'` (`:312-316`); `data_pagamento=NULL`, `observacoes=NULL`.
  - `DELETE FROM pagamentos WHERE referencia_type='mensalidade' AND referencia_id=?` (`:318-321`) — **exclusão física**.
  - `DELETE FROM caixa WHERE referencia_type='mensalidade' AND referencia_id=? AND tipo='Entrada'` (`:323-326`).
- **`criarManual(array $dados)` `:342-448`** (lançamento avulso, jóia, etc.):
  - Defaults de ano/mês correntes, mês travado 1..12 (`:349-352`); exige `associado_id > 0` (`:359-361`) e `valor > 0` (`:362-364`).
  - Se `associacao_id` não informado, usa `SELECT vinculo FROM associados WHERE id = ?` (`:367-374`) — coluna `associados.vinculo` existe (`migration.sql:356`).
  - `status = 'pago'` se houver `data_pagamento` (`:376`); `fonte='manual'` (`:384`).
  - Se pago: INSERT `pagamentos` (`:403-420`) + INSERT `caixa` (`:422-436`), tudo na mesma transação.
- **`getInadimplentes($assoc,$mes,$ano)` `:453-489`**: `status_mensalidade IN ('pendente','atrasado') AND data_vencimento < :hoje` + JOIN `associados`; usado pela tela de cobrança.
- **`getResumoFinanceiro($associadoId)` `:491-508`**: `SUM(valor_cobrado)` por status → `total_pago`, `total_em_aberto`, `qtd_em_aberto`, `qtd_pago`.
- **`getInadimplenciaPorMes($assoc,$limite=12)` `:515-547`**: `COUNT(*)` + `SUM(valor_cobrado)` agrupado por `ano_referencia, mes_referencia`, com `LIMIT` inteiro (`:530-532`).
- **`getPagamentosPorForma(...)` `:554-582`**: `GROUP BY metodo_pagamento ORDER BY valor DESC` sobre `pagamentos WHERE status_pagamento='pago'`.
- **`getPrevisaoReceita($assoc,$meses=3)` `:589-649`**: monta janela de N meses (`:596-603`), busca `SUM(valor_cobrado)` de pendentes/atrasados dentro da janela com predicado `(ano = :anoIni AND mes >= :mesIni) OR (ano > :anoIni AND ano < :anoFim) OR (ano = :anoFim AND mes <= :mesFim)` (`:618-620`), depois casa por chave `YYYY-MM` (`:630-639`) e soma o total (`:641-643`).
  - ⚠️ **A previsão ignora `data_vencimento`**: agrupa pelo **mês de referência** da mensalidade, não pela data de vencimento. Portanto "A Receber (3 meses)" é a soma das mensalidades com referência dentro da janela, mesmo que já vencidas.
- **`gerarPix($mensalidadeId,$dados)` `:654-703`**:
  - Exige `PixProvider.php` em runtime (`:656`); recusa se já paga (`:662-664`).
  - Gera `txid = $mensalidade['txid'] ?? 'MENS-'.$id.'-'.time()` (`:667`); busca `associados` (`:670-676`); chama `PixProvider::criarCobranca()` com valor, vencimento, descrição, `referencia_id`, dados cadastrais (`:678-694`).
  - Em sucesso: `UPDATE mensalidades SET txid = ?, updated_at = NOW()` (`:698-699`).
- **`processarWebhookPix($txid,$dadosWebhook)` `:708-747`**:
  1. `SELECT * FROM mensalidades WHERE txid = ?` (`:711-713`); não achou → `['erro' => 'Mensalidade não encontrada para txid: '.$txid]` (`:715-717`).
  2. Se já `'pago'` → `['sucesso'=>true,'ja_processado'=>true]` (**única idempotência existente**, `:719-721`).
  3. **Reconsulta o provedor**: `PixProvider::verificarStatus($txid)` (`:723-728`).
  4. Se `status === 'pago'` → chama `baixar()` com `metodo_pagamento='PIX'`, `data_pagamento` do provedor, `valor` do provedor, observação "Baixa automática via PIX (webhook)" (`:730-743`).
  5. Qualquer outro status → `['sucesso'=>false,'status'=>...]` com HTTP 200 (`:746`).

### `classes/Caixa.php` (228 linhas)
- **Tabelas**: `caixa cx` LEFT JOIN `associacoes ac` (`:18-20`).
- `getAll(array $filtros)` `:15-49`: filtros `associacao_id`, `tipo`, `data_inicio`, `data_fim`; ordena `data DESC, id DESC`.
- `create(array $dados)` `:63-95`: `tipo` normalizado para `'Saida'` ou `'Entrada'` (`:67`); **exige valor > 0** (`:68-71`); `data` default hoje (`:73`); `criado_por = $_SESSION['user_id']` (`:74`).
- `delete($id)` `:97-119`: **se o lançamento tem `referencia_type='mensalidade'`, chama `Mensalidade::reverterBaixa()` antes de apagar** (`:105-111`) — é o mecanismo que mantém caixa↔mensalidade coerentes; falhas são apenas logadas (`:108-110`) e o DELETE prossegue.
- **Cálculos**:
  - `getSaldo($assoc)` `:121-144`: `entradas = SUM(valor) WHERE tipo='Entrada'`, `saidas = SUM(valor) WHERE tipo='Saida'`, `saldo = entradas - saidas` (`:124-138`). **Saldo acumulado de todo o histórico** (sem recorte temporal).
  - `getResumoPeriodo($ini,$fim,$assoc)` `:146-170`: mesma fórmula restrita a `data BETWEEN`; retorna `saldo_periodo` (`:164`).
  - `getFluxoPorMes($assoc,$meses=6)` `:180-227`: pré-popula os N meses (`:185-196`) e usa `YEAR(data)/MONTH(data)` + `SUM(CASE WHEN tipo=...)` (`:199-204`), com `data >= :inicio` (`:205`); meses sem movimento ficam zerados (`:213-221`). Retorna `[['ano','mes','entradas','saidas','saldo']]`.
- `getById()` `:51-61`.

### `classes/Pagamento.php` (204 linhas, `final class`, métodos estáticos)
- Somente **leitura sobre `pagamentos`** (LEFT JOIN `associados`):
  - `getPorPeriodo($ini,$fim,$assoc)` `:18-47` — `WHERE status_pagamento='pago'`.
  - `getPagamentosPorForma($assoc,$ini,$fim,$associadoId)` `:54-88` — `GROUP BY metodo_pagamento ORDER BY valor DESC, qtd DESC`.
  - `getTotalRecebidoPeriodo()` `:95-124` — total + quantidade.
  - `getRecebidoPorMes($meses=6,$assoc)` `:131-175` — `YEAR(data_pagamento)/MONTH(data_pagamento)`, `data_pagamento >= :inicio`.
  - `getInadimplenciaPorMes()` `:183-186` — **delega** para `Mensalidade::getInadimplenciaPorMes()` (evita duplicação).
  - `getResumoGeral($assoc)` `:193-203` — `ticket_medio = total / quantidade` (`:199-201`).

### `classes/PixProvider.php` (381 linhas)
- `$provider = defined('PIX_PROVIDER') ? PIX_PROVIDER : 'asaas'` (`:18`) — ver §2.4 para o fato de **`PIX_PROVIDER` não estar definido em lugar nenhum**.
- Config (`:22-42`): `ASAAS_API_KEY`/`ASAAS_URL` (default `https://api.asaas.com/v3`), `GN_CLIENT_ID`/`GN_CLIENT_SECRET`/`GN_URL`, `MP_ACCESS_TOKEN` (URL fixa `https://api.mercadopago.com/v1`); `webhook_url = SITE_URL . '/api/pix/webhook/<provedor>'`.
- **Asaas** (`:79-213`): cobrança `POST /payments` com `billingType='PIX'`, `customer` (busca por `cpfCnpj` em `GET /customers?cpfCnpj=` ou cria novo, `:132-180`), `value`, `dueDate`, `description`, **`externalReference` = `$dados['txid']` quando informado** (`:96-98`), `notificationUrl` (`:93`); auth por header `access_token` (`:107`); timeout 30s. Status map (`:197-204`): `PENDING→pendente`, `RECEIVED/CONFIRMED/RECEIVED_IN_CASH→pago`, `OVERDUE→atrasado`, `REFUNDED→cancelado`.
- **Gerencianet** (`:216-311`): OAuth `client_credentials` com Basic base64 (`:269-286`); `POST /cob/imediata` com `calendario.expiracao=3600`, `devedor.cpf` (só dígitos), `valor.original` formatado, `chave` (PIX key), `solicitacaoPagador` (`:229-249`). Status map (`:307`): `ATIVA→pendente`, `CONCLUIDA→pago`, `REMOVIDA_PELO_USUARIO_RECEBEDOR→cancelado`, `EXPIRADA→atrasado`.
- **Mercado Pago** (`:314-379`): `POST /payments` `payment_method_id='pix'`, `payer.email`/nome/CPF, `notification_url`, `external_reference` (`:321-333`); QR em `point_of_interaction.transaction_data.qr_code(_base64)` (`:354-355`). Status map (`:376`): `pending→pendente`, `approved→pago`, `rejected/cancelled→cancelado`, `in_process→pendente`.
- Todos os métodos retornam `['erro' => ...]` em falha; nenhum valida assinatura de webhook (não é o papel da classe).
- ⚠️ `str_starts_with`/`defined` OK em PHP 8; `verificarStatusGerencianet()` **chama `getGerencianetToken()` a cada invocação** (sem cache) — custo/latência no caminho do webhook.

---


---


# PARTE 2 — TELAS FINANCEIRAS, PIX, FLUXO E RISCOS

## 2.1 Módulo CAIXA

### `modulos/caixa/listar.php` (203) — rota `/caixa`
- **Guarda**: `podeAcessarDiretoria()` → redirect `/login` (`:14-17`); `$podeGerenciarCaixa = hasPermission(NIVEL_GESTOR)` (`:18`). Ou seja: **Gestor/Fiscal com perfil de diretoria vê a lista, mas só quem é Admin/Gestor opera**.
- **Dados**: `Caixa::getAll(array_filter($f))` com filtros `associacao_id`, `tipo`, `data_inicio`, `data_fim` (`:20-28`); `getSaldo()` (`:29`); se houver filtro de data, `getResumoPeriodo()` com defaults `1970-01-01`/hoje (`:31-39`).
- **KPIs**: Entradas, Saídas, Saldo Geral (`getSaldo`), e o 4º card alterna entre "Saldo no Filtro" (`saldo_periodo`) e nº de lançamentos (`:72-109`).
- **Formulário (GET)**: `associacao_id`, `tipo` (`Entrada`/`Saida`), `data_inicio`, `data_fim` (`:114-143`).
- **Ações**: botão "Novo Lançamento" → `/caixa/lancamento` apenas se gestor (`:54-56`); por linha, form POST para `/caixa/{id}/excluir` com `CSRFProtection::getTokenField()` e `confirm()` (`:178-181`).
- **Tabela**: Data, Tipo (badge), Descrição, Categoria, Associação (sigla→nome), Valor (R$), Ações (`:153-187`); DataTables pt-BR, ordem por data desc, 25/página (`:192-202`).
- **Riscos**: nenhum paginador server-side (carrega todo o caixa e pagina no cliente).

### `modulos/caixa/cadastrar.php` (105) — rota `/caixa/lancamento`
- **Guarda**: `hasPermission(NIVEL_GESTOR)` → redirect `/login` (`:12-16`).
- **POST**: valida CSRF (`:22-24`) e chama `Caixa::create($_POST)` (`:26-34`); sucesso → `$_SESSION['success_message']` e redirect `/caixa` (`:31-33`).
- **Campos** (`:61-101`): `tipo` (select Entrada/Saida, default Entrada), `data` (date, default hoje), `descricao` (text, required), `valor` (text com máscara, required), `categoria` (text opcional), `associacao_id` (select opcional).
- **Regras**: valor precisa ser > 0 (`classes/Caixa.php:68-71`); o valor passa por `Sanitizer::sanitizeMoney()` (`classes/Sanitizer.php:73-94`): string só com dígitos é tratada como **centavos** (divide por 100), vírgula é formato BR, senão decimal com ponto.
- **Não há upload de comprovante** nesta tela, embora `caixa.comprovante_url` exista e seja preenchido na baixa de mensalidade.

### `modulos/caixa/excluir.php` (28) — rota `/caixa/{id}/excluir`
- **Guarda** `hasPermission(NIVEL_GESTOR)` (`:11-15`).
- Exige `?id` numérico + POST + CSRF (`:17`); chama `Caixa::delete()` (`:18`), que **reverte a baixa da mensalidade vinculada** (`classes/Caixa.php:105-111`) antes do DELETE; mensagens em sessão e redirect para `/caixa` (`:19-27`).

### `modulos/caixa/cobranca.php` (279) — rota `/caixa/cobranca`
- **Guarda**: `podeAcessarDiretoria()` (`:15-18`); `$podeGerenciarCobranca = hasPermission(NIVEL_GESTOR)` (`:19`).
- **Propósito**: lista de inadimplentes por associado (mensalidades pendentes/atrasadas com vencimento vencido) + disparo de lembrete.
- **Filtros GET**: `associacao_id`, `ano` (atual..-2), `mes` (`:21-25`, `:163-194`).
- **Dados**: `Mensalidade::getInadimplentes()` (`:27-32`); agrupamento em `$porAssociado` somando `valor_cobrado` por associado (`:123-137`); cabeçalho mostra `count()` e o total (`:199-202`).
- **Ação POST `acao=lembrete_cobranca`** (`:47-120`): exige CSRF (`:48-50`); filtra itens do associado (`:52-53`); se sem e-mail → aviso (`:74-76`); monta tabela HTML com referência/valor/vencimento e total (`:78-102`) e envia por `Mailer::send()` com `Mailer::layout()` (`:104-108`), assunto `[SITE_TITLE] Aviso de mensalidades em aberto` (`:106`).
- **WhatsApp**: link `https://wa.me/<numero>?text=<mensagem urlencoded>` gerado em template (`:235-245`), com normalização `telefoneWhatsApp()` (`:34-44`): só dígitos, exige ≥10, remove zeros à esquerda, trunca em 11 dígitos, prefixa `55`.
- **Bug confirmado**: no bloco do POST, o `foreach ($associacoes as $a)` (`:66`) roda **antes** de `$associacoes` ser definido (`:139`) → "Undefined variable" e **a sigla da associação sempre sai vazia** no e-mail (fallback "associação", `:91`).
- **Riscos**: `addslashes($a['nome'])` em atributo JS `onsubmit` (`:247`) — escape inadequado para contexto HTML/JS (um nome com `\'` ou `</script>` pode quebrar/injetar); nome/telefone do associado vão para `href` de `wa.me` via `urlencode` (aceitável).

### `modulos/caixa/relatorio.php` (197) — rota `/caixa/relatorio`
- **Guarda**: `podeAcessarDiretoria()` (`:14-17`). Uma única requisição, somente leitura.
- **Fontes**: `Caixa::getFluxoPorMes($assoc, 6)` (`:27`), `Mensalidade::getInadimplenciaPorMes($assoc, 12)` (`:28`), `Mensalidade::getPagamentosPorForma($assoc)` (`:29`), `Mensalidade::getPrevisaoReceita($assoc, 3)` (`:30`), `Caixa::getSaldo($assoc)` (`:32`).
- **KPIs** (`:69-102`): Entradas 6 meses (`array_sum(array_column($fluxo,'entradas'))`, `:33`), Saídas 6 meses (`:34`), Saldo Geral, Previsão 3 meses (`$previsao['total']`).
- **Blocos** (`:104-195`): tabela de Fluxo de Caixa realizado (mês, entradas, saídas, saldo); Inadimplência por Mês (referência, contas, valor); Pagamentos por Forma (forma, qtd, valor — `Transferencia` rotulado "Transferência", `:162`); Previsão de Receita 3 meses com rodapé de total.
- **Filtro**: apenas `associacao_id` (`:52-66`).

## 2.2 Módulo CONTAS (contas a pagar/receber genérico)

> `contas` é uma tabela **sem vínculo com associação, sem status e sem conceito de baixa**: `id, nome, tipo ENUM('Receita','Despesa'), valor DECIMAL(8,2), data DATE` (`sql/../sinporg/prisma/migrations/0_baseline/migration.sql:608-616`). Não existe "contas a pagar em aberto" — o tipo define tudo.

### `modulos/contas/listar.php` (168) — rota `/contas`
- **Ordem de includes invertida**: `header.php` e `menu.php` são incluídos (`:3-4`) **antes** do check `podeAcessarDiretoria()` (`:6-10`); a negação apenas imprime um alerta (sem redirect). `header.php:16-19` garante o login, então o efeito prático é "tela renderizada com aviso".
- `$podeGerenciarContas = hasPermission(NIVEL_GESTOR)` (`:11`).
- **Consulta**: `SELECT id, nome, tipo, valor, data FROM contas ORDER BY data DESC` (`:17`) dentro de try/catch que zera a lista em erro (`:20-22`).
- **Ações**: "Adicionar Nova Conta" → `/conta/novo` (só gestor, `:28-30`); por linha, "Editar" → `/conta/{id}/editar` e um botão de exclusão **desabilitado** que abre modal explicando que a exclusão foi temporariamente desativada (`:54-56`, `:113-167`).
- **Riscos**: `htmlspecialchars()` aplicado em `valor`/`data` (ok); DataTable no cliente (`:104-111`).

### `modulos/contas/cadastrar.php` (58) — rota `/conta/novo`
- **Guarda**: `hasPermission(NIVEL_GESTOR)` (`:6-10`), depois de incluir header/menu.
- **Campos**: `nome` (text required), `tipo` (select Receita/Despesa required), `valor` (text `mask-valor` required), `data` (date required) (`:32-55`).
- **INSERT** direto via PDO preparado (`:20-21`) — **sem sanitização de `valor`** (grava a string como veio; o MySQL converte para DECIMAL) e **sem CSRF** (não há `CSRFProtection`).
- **Riscos**: CSRF ausente (POST cross-site cria lançamentos); erro de banco ecoado ao usuário (`:24`).

### `modulos/contas/editar.php` (85) — rota `/conta/{id}/editar`
- **Guarda** `NIVEL_GESTOR` (`:6-10`); `$id = $_GET['id']` sem cast (`:12`) mas usado em prepared statement (`:23`, `:43`).
- Carrega a conta (`:22-29`) e faz `UPDATE contas SET nome,tipo,valor,data WHERE id=?` (`:42-43`). **Sem CSRF**. Mensagem de erro expõe `$e->getMessage()` (`:51`).

### `modulos/contas/excluir.php` (28) — **sem rota no roteador**
- **Guarda** `NIVEL_GESTOR` (`:6-10`); exige POST + CSRF + `id` (`:12-17`); `DELETE FROM contas WHERE id=?` (`:21-22`); redirect relativo quebrado (`:23`). Tela órfã (a UI nem oferece exclusão) → código morto com risco latente.

### APIs de relatório de contas
- `api/relatorios/contas-pagar.php` (82): `ApiAuth::exigirNivel(2,false)` (`:12`); `WHERE tipo='Despesa'` + filtros `dataInicio`/`dataFim` via `bindValue` (`:17-38`); devolve HTML com card "Total de Despesas do Período" (`:47-56`) e tabela (`:58-77`).
- `api/relatorios/contas-receber.php` (82): idêntico com `tipo='Receita'` (`:17`) e card verde (`:47-56`).
- Ambos são "contas a pagar/receber" **nominais**: não há status de liquidação, vencimento ou baixa — o relatório só reclassifica a tabela `contas` por tipo.
- `api/relatorios/fluxo-caixa-realizado-previsto.php` (153): `exigirNivel(2)` (`:14`); cruza `Caixa::getFluxoPorMes($assoc,3)` (`:20`) com `Mensalidade::getPrevisaoReceita($assoc,3)` (`:21`); totais `:33-38`; sinaliza superávit/déficit `$diferenca = totalRealizado - totalPrevisto` (`:38`, `:101-104`) e monta tabela mês a mês com diferença por linha (`:128-133`).
- `api/relatorios/previsao-receita.php` (91): `getPrevisaoReceita(null,3)` (`:18`), média mensal `total/count(meses)` (`:21`), % do total por mês (`:66`).
- `api/relatorios/pagamentos-por-forma.php` (105): `Pagamento::getPagamentosPorForma($assoc,$ini,$fim,$associadoId)` (`:21`), ticket médio `totalValor/totalQtd` (`:56`), % por forma (`:77`).

## 2.3 Módulo MENSALIDADES

### `modulos/mensalidades/listar.php` (168) — rota `/mensalidades`
- **Guarda**: `podeAcessarDiretoria()` (`:10-13`); `$podeGerenciarMensalidades = hasPermission(NIVEL_GESTOR)` (`:14`).
- **Filtros GET**: `associacao_id`, `ano`, `mes`, `status` (`:16-21`) → `Mensalidade::getAll(array_filter($f))` (`:24`).
- **Colunas**: Associado, Ref. (MM/AAAA), Associação, Plano, Vencimento, Valor, Situação (badge pago/atrasado/pendente, `:130-131`), Pagamento, Ações (`:111-152`).
- **Ações por linha** (`:134-148`): pago → `/mensalidade/{id}/estornar`; não pago → `/mensalidade/{id}/baixa`; **exclusão só se `fonte === 'manual'`** (`:141-146`) via POST com CSRF.
- **Atalhos** (`:38-43`): Gerar Lote (`/mensalidade/gerar`), Lançamento Manual (`/mensalidade/novo`), Planos (`/planos`), Cobrança (`/caixa/cobranca`).

### `modulos/mensalidades/gerar.php` (118) — rota `/mensalidade/gerar`
- **Guarda**: `hasPermission(NIVEL_GESTOR)` (`:13-17`).
- **POST** (CSRF, `:23-27`) → `Mensalidade::gerarLote($_POST)` (`:28`); mensagem "Lote gerado: X lançamento(s), Y já existente(s)" (`:33`).
- **Campos** (`:66-114`): `associacao_id` (required), `plano_id` (opcional, `getAtivos()` global — `:40`), `ano` (number 2020..2100), `mes` (select), `valor` (text, **digitado manualmente**), `dia_vencimento` (number 1..28, default 10).
- **Anti-reamostragem**: após sucesso os campos ficam `disabled` e o botão `hidden` (`:71,91,95,103,107,110`). ⚠️ `disabled` impede o reenvio pelo navegador, mas o endpoint continua idempotente apenas pela checagem COUNT — não há trava de servidor.
- **Gap**: o plano escolhido não traz valor/vencimento automaticamente (o `dia_vencimento`/`valor` do plano são ignorados).

### `modulos/mensalidades/baixa.php` (168) — rota `/mensalidade/{id}/baixa`
- **Guarda** `NIVEL_GESTOR` (`:12-15`); valida `id` numérico (`:17-21`) e existência (`:28-32`).
- **Dois POSTs distintos** (CSRF em ambos, `:35-37`):
  - `gerar_pix` (`:40-48`) → `Mensalidade::gerarPix($id)`; exibe TXID, copia-e-cola e QR (`:104-132`). O QR é renderizado a partir de data-URI quando o provedor devolve base64 (`data:image`/`iVBOR`/`/9j/`, `:121`) ou gerado via **`api.qrserver.com`** (serviço externo) a partir do copia-e-cola (`:124`) — o payload PIX é enviado a terceiros.
  - baixa normal (`:50-58`) → `Mensalidade::baixar($id,$_POST)` com redirect em sucesso.
- **Condição de exibição do botão PIX** (`:92`): `status !== 'pago' && defined('PIX_PROVIDER') && PIX_PROVIDER !== ''`. **Como `PIX_PROVIDER` não é definido em nenhum arquivo** (busca global: só `classes/PixProvider.php:7,18` e `baixa.php:92,100`), **o botão nunca aparece** → o fluxo PIX é inalcançável pela UI.
- **Formulário de baixa** (`:134-164`): `metodo_pagamento` (PIX/Boleto/Cartao/Dinheiro/Transferencia, required), `data_pagamento` (date, default hoje), `valor` (text, default `valor_cobrado`), `observacoes`. **Não há campo de comprovante** apesar de o método aceitar `comprovante_url`.

### `modulos/mensalidades/estornar.php` (89) — rota `/mensalidade/{id}/estornar`
- **Guarda** `NIVEL_GESTOR` (`:12-15`); carrega a mensalidade (`:23-28`); POST com CSRF (`:34-37`) → `reverterBaixa($id)` (`:38`) → sucesso grava mensagem e volta para `/mensalidades` (`:39-43`).
- Tela é apenas confirmação, com aviso textual de que remove pagamento + lançamento de caixa (`:77-80`).

### `modulos/mensalidades/excluir.php` (44) — rota `/mensalidade/{id}/excluir`
- **Guarda** `NIVEL_GESTOR` (`:12-15`); POST + CSRF (`:23-24`); carrega (`:26`) e **só exclui se `fonte === 'manual'`** (`:27-37`), executando `DELETE FROM mensalidades WHERE id = ?` (`:29`).
- ⚠️ Exclusão **não** remove `pagamentos`/`caixa` vinculados. Como só se aplica a manuais, o caso "manual já pago" pode deixar órfãos se for excluído por outro caminho (a UI esconde o botão quando pago, mas o endpoint não valida status). Sempre redireciona (`:43`).

### `modulos/mensalidades/cadastrar.php` (138) — rota `/mensalidade/novo`
- Lançamento manual avulso; monta campos de associado/associação/plano/ano/mês/valor/vencimento/observações/pagamento e delega a `Mensalidade::criarManual()`. (Detalhamento de campos conforme `gerar.php`; a lógica de negócio está em `classes/Mensalidade.php:342-448`.)

### Planos de mensalidade
- `planos.php` (105) — rota `/planos`: guarda `NIVEL_GESTOR` (`:12-15`); `PlanoMensalidade::getAll()` (`:17`); rótulos `mensalidade/anuidade/joia` → "Mensalidade/Anuidade/Jóia de entrada" (`:23-27`); colunas Nome, Tipo, Associação (ou badge "Todas"), Valor, Dia Venc., Situação, Ações (`:54-91`); ações editar (`/plano/{id}/editar`) e excluir (POST+CSRF, `:83-86`).
- `plano_cadastrar.php` (107) — `/plano/novo`: guarda `NIVEL_GESTOR`; CSRF (`:22-24`); `PlanoMensalidade::create($_POST)` (`:26`); campos `nome`, `tipo`, `valor`, `dia_vencimento` (1..28), `ativo` (switch, default ligado, `:85`), `associacao_id` ("vazio = vale para todas", `:90-96`).
- `plano_editar.php` (118) — `/plano/{id}/editar`: guarda `NIVEL_GESTOR`; carrega plano (`:24-29`); CSRF; `update()` (`:39`); nota de UI "Alterações valem para as próximas gerações de lote" (`:59`) — coerente com o fato de o lote não consultar o plano.
- `plano_excluir.php` (48) — `/plano/{id}/excluir`: guarda `NIVEL_GESTOR`; POST+CSRF (`:17`); **regra de integridade**: conta `SELECT COUNT(*) FROM mensalidades WHERE plano_id = ?` (`:21-23`); se `>0` faz **inativação lógica** (`UPDATE planos_mensalidades SET ativo = 0`, `:30`) preservando histórico; senão DELETE físico (`:37`).

## 2.4 Módulo COMUNICAÇÃO

### `modulos/comunicacao/index.php` (426) — rota `/comunicacao`
- **Guarda dupla** (`:14-17`): `hasPermission(NIVEL_GESTOR) || isMembroDiretoria()`.
- **Clusters** (`:25-31`): `SELECT DISTINCT cluster_comunicacao, COUNT(*) FROM associados WHERE estado = 23 AND cluster_comunicacao IS NOT NULL AND status='ativo' GROUP BY cluster_comunicacao` — **estado 23 = RS** (hardcoded).
- **Associações ativas** para filtro (`:34`).
- **Ação POST `enviar_email`** (`:43-130`): exige `cluster`, `assunto`, `corpo` (`:50-52`); consulta destinatários do cluster com e-mail não vazio e, opcionalmente, `EXISTS (... associado_associacao aa WHERE aa.associado_id=a.id AND aa.associacao_id=?)` (`:55-71`).
- **Personalização** (`:88-92`): substituição literal de `{{nome}}`, `{{cpf}}`, `{{cluster}}`.
- **Canais**: `email` (Resend, `enviarEmailResend()` `:134-172`: `POST https://api.resend.com/emails` com `Authorization: Bearer RESEND_API_KEY`, exige chave começando com `re_`, `:139-142`), `whatsapp` (apenas **gera links**, não envia, `:100-110`), `ambos`.
  - Guarda os links em `$_SESSION['whatsapp_links']`/`['whatsapp_cluster']` (`:124-127`) — **mas nenhum código lê essas chaves**; a UI refaz a consulta via API.
- **Contadores** (`:81-121`): `enviados`/`falhas`; relatório em `$mensagem` com link `onclick="abrirWhatsApps('{$cluster}')"` (`:119`).
- **Resumo por cluster** (`:293-348`): para cada cluster, duas `COUNT(*)` preparadas (com e-mail, com telefone) → N+1 queries.
- **Riscos**:
  1. **XSS refletido**: `$mensagem` é impresso **sem escape** (`:188`) e contém `$cluster` interpolado no `onclick` (`:119`) — `cluster` vem do POST (`:44`). Um cluster com aspas `'` quebra o JS; com markup, injeta HTML.
  2. **HTML injection em e-mail**: `$corpo` é inserido cru no template de e-mail (`:99`), com placeholders substituídos por `$dest['nome']`/`$dest['cpf']` **sem escape** (`:89-91`).
  3. **Rate limit/abuso**: laço de `curl` síncrono por destinatário (`:84-114`) sem limite por requisição → timeout do PHP e possível bloqueio do provedor.
  4. `{{cpf}}` é enviado por e-mail em texto — dado pessoal em massa.

### `api/comunicacao/whatsapp_links.php` (51)
- **Guarda**: `ApiAuth::exigirStaff()` (`:14`) — **Admin/Gestor apenas** (`classes/ApiAuth.php:145-151`). Isso é **inconsistente** com `modulos/comunicacao/index.php:14`, que também aceita membro de diretoria: um diretor que monta o envio na tela **recebe 403** no AJAX dos links.
- **CSRF**: `CSRFProtection::validatePostRequest()` (`:16-19`).
- **Consulta** (`:31-38`): `associados` com `estado=23 AND cluster_comunicacao=? AND status='ativo' AND telefone NOT NULL` — **não filtra por associação** (diferente da tela) e **ignora** o `associacao_id`.
- **Saída** (`:42-51`): `preg_replace('/\D/','',telefone)`, link `https://wa.me/55{digits}?text=...` com mensagem fixa (`:46`), chaveado por `"{nome} {sobrenome}"`.
- O comentário `:5` registra que o caminho do `require` foi corrigido (antes apontava para fora do app); o `relatorios/correcao-auth-apis-2026-02.md:263` documenta o problema antigo e alertava que corrigir o caminho **sem** adicionar `exigirLogin()`/`exigirStaff()` reabriria o vazamento — o `exigirStaff()` atual resolve isso.
- **Risco residual**: o retorno é renderizado com `innerHTML` no cliente (`modulos/comunicacao/index.php:406-416`), concatenando `nome` e `link` — DOM-XSS se um nome de associado contiver HTML.

---


---


# PARTE 3 — INTEGRAÇÃO PIX, FLUXO FINANCEIRO PONTA-A-PONTA E RISCOS

## 3.1 Webhooks PIX (arquivos servidos diretamente, sem roteador)

Os três endpoints são **arquivos PHP reais** sob `api/pix/webhook/` — servidos diretamente porque `.htaccess:17-21` só reescreve quando o arquivo não existe. Logo, **não passam por nenhum guard do roteador**.

| Endpoint | Extração do txid | Validação de origem |
|---|---|---|
| `api/pix/webhook/asaas.php` | `$data['payment']['id'] ?? $data['payment']['externalReference']` (`:22`) | **nenhuma** |
| `api/pix/webhook/gerencianet.php` | `$data['txid'] ?? $data['notification']['txid']` (`:20`) | **nenhuma** |
| `api/pix/webhook/mercadopago.php` | `$data['data']['id'] ?? $data['data']['external_reference']` (`:20`) | **nenhuma** |

Estrutura idêntica nos três:
1. `require` de `config/config.php` + `classes/Mensalidade.php` (e `Database.php` no Asaas) — `:5-7`.
2. `header('Content-Type: application/json')` — `:9`/`:8`.
3. Lê o corpo cru (`file_get_contents('php://input')`) e `json_decode(..., true)` (`:11-12`) — **nunca usa `$_POST`/form-encoded**.
4. JSON inválido → HTTP 400 `{"erro":"JSON inválido"}` (`:14-18`).
5. txid vazio → HTTP 400 `{"erro":"Txid não encontrado no payload"}` (`:24-28`).
6. `(new Mensalidade())->processarWebhookPix($txid, $data)` (`:30-31`).
7. Se o retorno tem `erro` → `error_log()` + HTTP 400 + JSON com o erro (`:33-38`); senão HTTP 200 com o JSON do resultado.

**Autenticação/assinatura**: nenhum dos três valida `asaas-access-token` (Asaas), `hmac`/`x-signature` (Gerencianet) nem `x-signature`/`x-request-id` (Mercado Pago). Também **não há allowlist de IP**. Agravantes de infraestrutura:
- `api/.htaccess:14` define `Access-Control-Allow-Origin: *` para toda a pasta `api/` — qualquer origem pode disparar POSTs (CORS não bloqueia envio de POST simples; ainda assim amplia a superfície).
- `api/.htaccess:1-8` libera `Require all granted` para todo o diretório.
- O endpoint **ecoa o `txid` recebido na mensagem de erro** (`classes/Mensalidade.php:716` + `api/pix/webhook/*.php:35`), permitindo **sondagem/enumeração** de txids existentes.

**Payloads efetivamente consumidos** (nada além disso é lido):
- **Asaas**: `payment.id`, `payment.externalReference` (`:22`); `comprovante_url` é lido de `$dadosWebhook['comprovante_url']` (`classes/Mensalidade.php:740`) — campo que **não existe** no payload do Asaas → sempre `null`.
- **Gerencianet**: `txid` ou `notification.txid` (`:20`).
- **Mercado Pago**: `data.id` ou `data.external_reference` (`:20`). O MP envia `type`/`action` no corpo; o código **ignora o tipo de evento** — um webhook de `merchant_order` (cujo `data.id` é o id da order, não do pagamento) entra no mesmo caminho.
- Nenhum endpoint valida `status`/`event` do payload: a decisão de baixar vem **exclusivamente** da reconsulta à API do provedor (`classes/Mensalidade.php:723-728`). Isso é a principal mitigação existente — mas só funciona se `txid` corresponder a um pagamento do provedor configurado.

## 3.2 Idempotência (análise detalhada)

- **Único mecanismo**: `classes/Mensalidade.php:719-721` — se `mensalidades.status_mensalidade === 'pago'`, retorna `['sucesso'=>true,'ja_processado'=>true]`.
- **Condição de corrida**: dois webhooks simultâneos leem `'pendente'` antes de qualquer `UPDATE`; não há `SELECT ... FOR UPDATE`, nem lock, nem `UNIQUE` em `(referencia_type, referencia_id)` em `pagamentos`. Resultado possível: **dois `pagamentos` e dois lançamentos de caixa para a mesma mensalidade**. `baixar()` abre transação (`:237`) mas não serializa contra a outra transação.
- **Replay**: um webhook repetido depois da baixa cai no curto-circuito e devolve 200 — correto.
- **Replay de txid alheio**: como não há validação de assinatura, um atacante pode postar qualquer `txid`; a baixa só ocorre se `PixProvider::verificarStatus($txid)` retornar `pago`. Ou seja, **não dá para forjar pagamento**, mas dá para (a) forçar chamadas à API do provedor (custo/rate-limit), (b) descobrir quais txids existem pelo texto do erro, (c) disparar a baixa de um pagamento legítimo de terceiro se o txid for conhecido/adivinhável.
- **Guarda adicional ausente**: `processarWebhookPix()` não confere se o `txid` encontrado pertence ao provedor que enviou o webhook — qualquer um dos três endpoints pode processar um pagamento criado por outro provedor enquanto o `PIX_PROVIDER` ativo for compatível.

## 3.3 O DEFEITO BLOQUEANTE do fluxo PIX: coluna `mensalidades.txid` inexistente

Verificação objetiva:
- `classes/Mensalidade.php:711` → `SELECT * FROM mensalidades WHERE txid = ?`
- `classes/Mensalidade.php:698` → `UPDATE mensalidades SET txid = ?, updated_at = NOW() WHERE id = ?`
- `classes/Mensalidade.php:667` → lê `$mensalidade['txid']`
- Schema baseline da tabela `mensalidades` (`sinporg/prisma/migrations/0_baseline/migration.sql:1082-1099`): colunas `id, associado_id, associacao_id, plano_id, fonte, ano_referencia, mes_referencia, valor_cobrado, data_vencimento, data_pagamento, status_mensalidade, observacoes, created_at, updated_at` — **não há `txid`**.
- A coluna `txid` existe **apenas em `pagamentos`** (`migration.sql:1235`, `UNIQUE INDEX idx_txid` em `:1240`; `sinporg/prisma/schema.prisma:1183`).
- Busca global por `txid` (47 ocorrências, excluindo `node_modules`/`vendor`): nenhuma migração adiciona `txid` a `mensalidades`; os diretórios de migração são apenas `0_baseline`, `1_auth_refresh_tokens`, `2_sync_timestamps`.
- `PDO::ERRMODE_EXCEPTION` está ativo (`classes/Database.php:15`) e **nem `gerarPix()` nem `processarWebhookPix()` têm `try/catch`** → a exceção `SQLSTATE[42S22] Column not found` sobe e produz **erro fatal (HTTP 500)**.
- Consequência: mesmo que `PIX_PROVIDER` fosse definido, `gerarPix()` quebraria ao salvar o txid e **todos os webhooks responderiam 500**; o fluxo PIX é, na prática, **não funcional como está** (a única peça que funcionaria é `PixProvider::criarCobranca()`, que não toca no banco).

## 3.4 Fluxo financeiro completo (ponta-a-ponta, como está no código)

```
[1] PLANO           /plano/novo  -> PlanoMensalidade::create()          (planos_mensalidades)
                    validacoes: dia 1..28, valor via sanitizeMoney, tipo enum, ativo
                    (plano NAO alimenta valor/dia da geracao de lote)

[2] GERACAO         /mensalidade/gerar -> Mensalidade::gerarLote()
                    universo: associado_associacao aa + associados.status='ativo'
                              e status_associado NOT IN ('Desligado','Falecido')
                    valor: digitado no formulario (nao vem do plano)
                    vencimento: ano/mes + dia_vencimento (1..28, default 10)
                    dedupe: COUNT() previo por (associado, associacao, ano, mes, plano)
                    INSERT mensalidades (fonte='automatica', status='pendente')

[3] COBRANCA PIX    /mensalidade/{id}/baixa -> botao "Gerar Cobranca PIX"
                    *** BLOQUEADO: exige defined('PIX_PROVIDER') => nunca renderiza ***
                    Mensalidade::gerarPix() -> PixProvider::criarCobranca()
                    *** BLOQUEADO: UPDATE mensalidades SET txid => coluna inexistente ***

[4] WEBHOOK         POST /api/pix/webhook/{asaas|gerencianet|mercadopago}
                    extrai txid -> Mensalidade::processarWebhookPix()
                    *** BLOQUEADO: SELECT ... WHERE txid => coluna inexistente ***
                    (sem assinatura, sem allowlist de IP, sem validacao de evento)

[5] BAIXA           Mensalidade::baixar() [manual pela tela OU automatica pelo webhook]
                    TX: INSERT pagamentos (status='pago') 
                      + UPDATE mensalidades (status='pago', data_pagamento)
                      + INSERT caixa (tipo='Entrada', categoria='Mensalidade')
                    valor pode ser sobrescrito (pagamento parcial sem controle)

[6] ESTORNO         /mensalidade/{id}/estornar -> Mensalidade::reverterBaixa()
                    UPDATE status ('atrasado' se vencida, senao 'pendente')
                    DELETE pagamentos  (fisico)
                    DELETE caixa       (fisico, tipo='Entrada')
                    Tambem acionado por Caixa::delete() quando o lancamento
                    tem referencia_type='mensalidade'

[7] CAIXA           /caixa  (lista + saldo) | /caixa/lancamento (manual Entrada/Saida)
                    Caixa::getSaldo = SUM(Entrada) - SUM(Saida)  (todo o historico)
                    Caixa::getFluxoPorMes -> agregacao YEAR/MONTH

[8] CONTAS          /contas | /conta/novo | /conta/{id}/editar
                    tabela 'contas' (Receita/Despesa, valor, data) SEM associacao,
                    SEM status e SEM baixa -> "a pagar/receber" e apenas rotulo de tipo

[9] RELATORIOS      /caixa/relatorio (fluxo 6m, inadimplencia 12m, formas, previsao 3m)
                    /caixa/cobranca   (inadimplentes + lembrete e-mail/WhatsApp)
                    api/relatorios/{contas-pagar,contas-receber,fluxo-caixa-realizado-previsto,
                                    previsao-receita,pagamentos-por-forma}.php  (exigirNivel 2)
```

**Pontos de ruptura do encadeamento** (todos citados acima):
1. `PIX_PROVIDER` inexistente → etapa [3] nunca inicia pela UI.
2. `mensalidades.txid` inexistente → etapas [3] e [4] falham com exceção fatal.
3. `contas` não conversa com `caixa`/mensalidades → o "fluxo" financeiro é, na verdade, **dois subsistemas paralelos**: (a) mensalidades→pagamentos→caixa e (b) contas (Receita/Despesa) isolado.
4. Baixa por valor livre + estorno por DELETE físico → sem trilha de auditoria (não há `audit_log` no caminho financeiro: `classes/Mensalidade.php` e `classes/Caixa.php` não chamam `AuditLog::registrar()`, ao contrário do módulo agro).

## 3.5 Inventário de tabelas financeiras (schema efetivo)

| Tabela | Colunas relevantes | Origem |
|---|---|---|
| `planos_mensalidades` | `associacao_id, nome, tipo ENUM('mensalidade','anuidade','joia'), valor DECIMAL(10,2), dia_vencimento TINYINT, ativo BOOL` | `sql/create_associacao_financeiro.sql:7-18`; `migration.sql:1337-1349` |
| `mensalidades` | `associado_id, associacao_id, plano_id, fonte ENUM('manual','automatica'), ano_referencia, mes_referencia, valor_cobrado, data_vencimento, data_pagamento, status_mensalidade ENUM('pendente','pago','atrasado','isenta','cancelada'), observacoes` — **sem txid** | `migration.sql:1082-1099`; ALTERs em `sql/create_associacao_financeiro.sql:58-60` |
| `pagamentos` | `associado_id, associacao_id, descricao_pagamento, valor, data_pagamento, data_vencimento, metodo_pagamento, status_pagamento ENUM('pendente','pago','cancelado'), referencia_type, referencia_id, comprovante_url, txid (UNIQUE), observacoes` | `migration.sql:1222-1242`; ALTERs `sql/create_associacao_financeiro.sql:65-66` |
| `caixa` | `associacao_id, tipo ENUM('Entrada','Saida'), descricao, categoria, valor, data, comprovante_url, referencia_type, referencia_id, criado_por, criado_em` | `sql/create_associacao_financeiro.sql:23-39`; `migration.sql:447-463`; ALTERs `sql/create_associacao_financeiro.sql:71-72` |
| `contas` | `id, nome, tipo ENUM('Receita','Despesa'), valor DECIMAL(8,2), data` | `migration.sql:608-616` |
| `associados` | `status, status_associado ENUM(Ativo/Inadimplente/Afastado/Desligado/Falecido), vinculo, cluster_comunicacao, registro_cnpo, estado, microrregiao_id` | `migration.sql:322-383` |
| `associado_associacao` (pivot) | usada por `gerarLote()` e pelo filtro de comunicação | `migration.sql:311` |
| `carteirinhas` | `associado_id, token UNIQUE, validade` | `migration.sql:466-476` (citada por estar no mesmo script de migração financeira) |

Observações de schema:
- `caixa` ganhou `referencia_type/referencia_id` **duas vezes** no mesmo script (`sql/create_associacao_financeiro.sql:32-33` no CREATE e `:71-72` no ALTER) — redundante mas idempotente.
- `mensalidades.status_mensalidade` prevê `'isenta'` e `'cancelada'`, mas **nenhum código escreve esses valores** (busca nos módulos/classes financeiras: só `pendente/pago/atrasado`). Status de negócio não implementados.
- `mensalidades.id` e `pagamentos.id` são `BIGINT UNSIGNED`, enquanto `associados.id` é `INTEGER`: o cast `(int)` usado em `classes/Mensalidade.php:76,112` é ok em 64 bits, mas o desalinhamento de tipos impede FKs consistentes.

## 3.6 Gaps e riscos — FINANCEIRO (priorizados)

| # | Severidade | Achado | Evidência |
|---|---|---|---|
| F1 | **Crítico (bloqueante)** | `mensalidades.txid` não existe; `gerarPix()` e `processarWebhookPix()` lançam `PDOException` não capturada → HTTP 500 | `classes/Mensalidade.php:698,711`; `migration.sql:1082-1099`; `classes/Database.php:15` |
| F2 | **Crítico (bloqueante)** | `PIX_PROVIDER` nunca é definido → botão "Gerar Cobrança PIX" nunca renderiza; nenhuma constante `ASAAS_*`/`GN_*`/`MP_*` existe em `config/` | `modulos/mensalidades/baixa.php:92`; `classes/PixProvider.php:18`; busca global |
| F3 | **Alto** | Webhooks PIX sem assinatura, sem allowlist de IP e com CORS `*`; erro ecoa o txid (sondagem) | `api/pix/webhook/*.php:1-38`; `api/.htaccess:14`; `classes/Mensalidade.php:716` |
| F4 | **Alto** | Idempotência só por checagem de status; sem `FOR UPDATE`/UNIQUE → baixa duplicada (2 pagamentos + 2 lançamentos de caixa) sob concorrência | `classes/Mensalidade.php:719-721,237-283`; `migration.sql:1222-1242` |
| F5 | **Alto** | Estorno faz `DELETE` físico de `pagamentos` e `caixa` (sem trilha, sem auditoria, sem `AuditLog`) | `classes/Mensalidade.php:318-326` |
| F6 | **Médio-Alto** | `gerarLote()` não é atômico e ignora valor/vencimento do plano; o seletor de planos não é filtrado pela associação | `classes/Mensalidade.php:165-200`; `modulos/mensalidades/gerar.php:40` |
| F7 | **Médio-Alto** | `contas` (cadastrar/editar) **sem CSRF** e sem sanitização de valor; erro de banco ecoado | `modulos/contas/cadastrar.php:13-25`; `modulos/contas/editar.php:36-52` |
| F8 | **Médio** | `modulos/contas/excluir.php` sem rota, com redirect relativo quebrado e DELETE físico — código órfão ativo por URL direta | `modulos/contas/excluir.php:21-24`; ausência de rota em `index.php` |
| F9 | **Médio** | XSS refletido no aviso de comunicação (cluster do POST) e HTML injection no corpo do e-mail | `modulos/comunicacao/index.php:119,188,89-91` |
| F10 | **Médio** | `cobranca.php` usa `$associacoes` antes de defini-lo → sigla sempre vazia no e-mail | `modulos/caixa/cobranca.php:66` vs `:139` |
| F11 | **Médio** | Permissão inconsistente no par tela/API de comunicação: tela aceita diretoria, API exige staff (403 para diretor) | `modulos/comunicacao/index.php:14`; `api/comunicacao/whatsapp_links.php:14`; `classes/ApiAuth.php:145-151` |
| F12 | **Médio** | DOM-XSS ao renderizar nomes/links de WhatsApp com `innerHTML` | `modulos/comunicacao/index.php:406-416` |
| F13 | **Médio** | Pagamento parcial aceito (valor livre) sem controle de saldo; mensalidade vira `pago` mesmo com valor menor | `classes/Mensalidade.php:233,260-263` |
| F14 | **Baixo-Médio** | `getPrevisaoReceita()` agrupa por mês de **referência**, não por vencimento → "previsão" inclui vencidos | `classes/Mensalidade.php:606-624` |
| F15 | **Baixo** | QR Code gerado via serviço externo (`api.qrserver.com`) com o payload PIX no query string | `modulos/mensalidades/baixa.php:124` |
| F16 | **Baixo** | `modulos/mensalidades/excluir.php` exclui a mensalidade sem limpar `pagamentos`/`caixa` | `modulos/mensalidades/excluir.php:27-34` |
| F17 | **Baixo** | Tabelas financeiras sem `associacao_id` em `contas`: impossível separar receita/despesa por associação | `migration.sql:608-616` |
| F18 | **Baixo** | N+1 de `COUNT(*)` por cluster na tela de comunicação | `modulos/comunicacao/index.php:316-329` |
| F19 | **Info** | Status `'isenta'`/`'cancelada'` previstos no ENUM e nunca usados | `migration.sql:1093` |

---


---


# PARTE 4 — PRODUÇÃO: TRANSVERSAIS E NÚCLEO VERIFICADO

## 4.1 Modelo de permissão e escopo dos módulos de produção

Todas as telas de `agroindustria`, `producao_animal`, `manejo_animal` e `fornecedor` seguem o mesmo padrão:
1. Checagem de sessão no topo: `if (!$user->isLoggedIn()) { header('Location: '.SITE_URL.'/login'); exit; }` (ex.: `agroindustria/atividades_listar.php:10`, `producao_animal/painel.php:8`, `manejo_animal/animais_listar.php:10`, `fornecedor/listar.php:8`).
2. Escopo por produtor: `$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null);` e repasse às classes, que só aplicam o filtro `if ($filtroAssociado)`.

Mapa de escopo (linhas citadas):

| Módulo | Arquivos com filtro de escopo | Linha |
|---|---|---|
| agroindustria | `atividades_listar` | `:32` |
| | `receitas_listar` / `receitas_cadastrar` / `receitas_editar` | `:27` / `:18` / `:28` |
| | `ordens_listar` / `ordens_cadastrar` | `:25` / `:24` |
| | `higienizacao_listar` | `:25` |
| | `ocorrencias_listar` | `:32` |
| | `setores_listar` | `:32` |
| producao_animal | `alimentacao_listar/editar/cadastrar` | `:26` / `:28` / `:16` |
| | `sanidade_listar/editar/cadastrar` | `:26` / `:28` / `:16` |
| | `reproducao_listar/editar/cadastrar` | `:26` / `:28` / `:16` |
| | `ambientes_listar` / `lotes_listar` | `:26` / `:26` |
| manejo_animal | `animais_listar` | `:25` |
| | `animais_cadastrar` / `animais_editar` (escopoLotes + lista de associados) | `:19,22` / `:31,35` |
| fornecedor | `listar` (gestor vê todos; nível 3 vê "Meus Fornecedores") | `:16,26-27` |
| | `editar` (bloqueios de gestor em `:37,53,81,136`), `excluir` (`:17`), `cadastrar` (exige `NIVEL_USUARIO`, `:18`) | — |

- **Defesa anti-IDOR pontual verificada**: `manejo_animal/animais_visualizar.php:27` compara `!(int)$animal['associado_id'] !== (int)$user->getAssociadoId()`; `agroindustria/ordens_visualizar.php:25-31` compara `ordens_producao.associado_id` com `getAssociadoIdResolvido()` e `isGestorOuAdmin()`.
- ⚠️ **Falha estrutural de escopo (IDOR)**: `getAssociadoId()` (`classes/User.php:259-272`) retorna `null` quando o vínculo existe só via `associados.user_id` (caso 4 de `getAssociadoIdResolvido()`, `classes/User.php:321-325`). Nesse cenário `$escopo` vira `null` e as classes **deixam de filtrar** (`if ($filtroAssociado)`), expondo registros de **todos** os produtores a um usuário de nível 3. As telas de detalhe que usam a versão robusta negam, mas as **listas** vazam. Correção mínima: usar `getAssociadoIdResolvido()` nos mesmos moldes de `ordens_visualizar.php:26-31`.

## 4.2 AGROINDÚSTRIA — núcleo de cálculo (verificado por leitura integral)

### `classes/ReceitaAgroindustria.php` (199) — receitas e selo orgânico
- **Tabelas**: `receitas_agroindustria` (soft delete via `excluido`, `:18,32,196`), `ingredientes_receita` (`:16,39,156,171`).
- `getAll($filtroAssociado)` `:13-28`: subquery `(SELECT COUNT(*) FROM ingredientes_receita i WHERE i.receita_id = r.id) AS total_ingredientes`, `WHERE r.excluido = 0`, filtro opcional por `r.associado_id`, ordem `criado_em DESC`.
- `getIngredientes()` `:37-42`; `softDelete()` `:194-198` (`UPDATE ... SET excluido = 1`).
- **`fatorConversao($unidade)` `:47-59`** — base comum gramas/mL:
  `kg → 1000`, `mg → 0.001`, `t → 1000000`, `l → 1000`, `ml → 1`, `un/und → 1`, **default `1`** (g/mL/outro).
- **`calcularPercentualOrganico(array $ingredientes)` `:65-81`**:
  `qtd = (float)($ing['quantidade'] ?? $ing['proporcao_porcentagem'] ?? 0)`;
  `peso = qtd * fatorConversao($ing['unidade_medida'] ?? 'kg')`;
  `somaPeso += peso`; se `!empty($ing['eh_organico']) → somaOrganico += peso`;
  retorno `round((somaOrganico / somaPeso) * 100, 2)` (`:80`); `0.0` se `somaPeso <= 0`.
  → Métrica é **massa (g)**, não "proporção declarada". Massa e volume são somados no mesmo total (kg→1000 g e l→1000 mL), o que **mistura unidades** quando há ingredientes líquidos e sólidos — o percentual orgânico fica metodologicamente inconsistente.
- **`classificarSelo($percentual)` `:89-99`** (Portaria 52/2021): `>= 95.0 → 'PRODUTO_ORGANICO_BRASIL'`; `>= 70.0 → 'INGREDIENTES_ORGANICOS'`; senão `'SEM_SELO'`.
- **`create($dados,$ingredientes)` `:101-131`**: calcula percentual e selo **antes** de abrir a transação (`:103-104`), insere a receita (`:108-121`), grava ingredientes (`:124`) e faz commit; rollback + rethrow em exceção (`:127-130`).
- **`salvarIngredientes()` `:168-192`**: ignora item quando `nome === '' || peso <= 0` (`:179-181`); grava `quantidade` **e** `proporcao_porcentagem = $peso` (`:188`) — ou seja, a coluna chamada "proporcao_porcentagem" armazena **peso em base g/mL**, não percentual. A página pública trata esse campo como gramas (`agroindustria/rastreabilidade.php:131`, imprime "`g`"), confirmando a sobrecarga semântica.
- **`update()` `:133-166`**: recalcula percentual/selo e faz `DELETE FROM ingredientes_receita WHERE receita_id = ?` + reinserção (`:156-158`) dentro de transação.
- **Enums do schema**: `selo_autorizado ENUM('PRODUTO_ORGANICO_BRASIL','INGREDIENTES_ORGANICOS','SEM_SELO')`, `status ENUM('Rascunho','Ativa','Inativa')`, `percentual_organico_calculado DECIMAL(5,2)` (`migration.sql:1436-1449`).

### `classes/OrdemProducao.php` (160) — ordem de produção e balanço de massas
- **Tabelas**: `ordens_producao o` JOIN `receitas_agroindustria r` (`:15-18,32-36`); `rastreabilidade_insumos_processo ri` LEFT JOIN `lotes_producao`, `produtos`, `insumos`, `fornecedores` (`:44-54`); `excluido = 0` em todas as leituras.
- `getAll($filtroAssociado)` `:13-28` (ordem `data_processamento DESC`); `getById($id)` `:30-40` (traz `r.selo_autorizado AS selo_receita`); `getItensRastreabilidade($ordemId)` `:42-57` (traz `nome_lote`, `nome_produto`, `nome_insumo`, `lote_insumo`, `tipo_insumo`, `fornecedor`, `fornecedor_cnpj`).
- **`calcularBalanco(array $itens, $quantidadeFinal, $toleranciaPct = 10.0)` `:64-94`** — implementação literal:
  - `totalEntrada = Σ (float)$item['quantidade_utilizada']` (`:66-69`).
  - Se `totalEntrada <= 0` → `divergencia_pct = 0`, `status = 'EM_ANALISE'`, mensagem "Nenhum lote primário informado para o balanço." (`:72-80`).
  - `divergenciaPct = round((abs(totalEntrada - quantidadeFinal) / totalEntrada) * 100, 2)` (`:82`).
  - `status = divergenciaPct > toleranciaPct ? 'ALERTA_FRAUDE' : 'APROVADO'` (`:83`) — **tolerância de 10%**; a mensagem de alerta cita kg e pede auditoria (`:90-92`).
- **`create($dados, array $itens)` `:101-153`**:
  - Calcula o balanço (`:103`) e **grava o status resultante** em `ordens_producao.status_auditoria` (`:121`).
  - Campos: `receita_id`, `higienizacao_previa_id` (opcional, `:116`), `lote_processado_final`, `data_processamento`, `quantidade_final_obtida`, `unidade_medida` (default `'KG'`), `percentual_organico`, `selo_autorizado`, `associado_id`, `observacoes`, `criado_por` (`:108-127`).
  - Itens em `rastreabilidade_insumos_processo`: `lote_primario_id`, `produto_id`, `insumo_id`, `numero_nf`, `quantidade_utilizada`, `unidade_medida` (`:130-145`).
  - Transação com rollback + rethrow (`:147-152`).
  - ⚠️ **O docblock (`:96-100`) afirma "Bloqueia se não houver higienização válida nas últimas 24h (RF08)" — mas `create()` não chama `haHigienizacaoValida()`**. A trava existe apenas no controlador (`modulos/agroindustria/ordens_cadastrar.php:149`).
- `softDelete()` `:155-159` (`UPDATE ... excluido = 1`).

### `classes/ControleHigienizacao.php` (94) — higienização / RF08
- `getAll($filtroAssociado)` `:13-25` (`excluido = 0`, ordem `data_hora_inicio DESC`); `getById()` `:27-32`.
- **`haHigienizacaoValida($dataProcessamento, $equipamentoLinha, $associadoId, $intervaloHoras = 24)` `:38-50`**:
  ```sql
  SELECT COUNT(*) FROM controle_higienizacao
   WHERE excluido = 0 AND data_hora_fim IS NOT NULL
     AND equipamento_linha = ? AND data_hora_fim <= ?
     AND data_hora_fim >= DATE_SUB(?, INTERVAL ? HOUR)
  ```
  Parâmetros enviados: `[equipamentoLinha, dataProcessamento, dataProcessamento, (int)intervaloHoras]` (`:48`).
  ⚠️ **`$associadoId` é recebido mas NUNCA usado na query** — a higienização de outro produtor satisfaz a trava.
- **`listarSanitizantesPermitidos()` `:58-67`**: `SELECT id, nome, subcategoria, restricoes, normativa FROM insumos_organicos_aprovados WHERE categoria = 'Higienização' AND permitido = 1 ORDER BY nome` — lista oficial (Anexo I da Portaria 52/2021, redação da Portaria SDA 811/2023).
- `create($dados)` `:69-87`: `data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, criado_por`; `softDelete()` `:89-93`.

### `modulos/agroindustria/higienizacao_cadastrar.php` (110) — rota `/agroindustria/higienizacao/nova`
- **Guarda**: `isLoggedIn()` (`:10-13`) — **qualquer nível**, sem restrição a gestor.
- **Campos** (`:64-100`): `data_hora_inicio` (datetime-local, required, default agora), `data_hora_fim` (opcional), `equipamento_linha` (**texto livre**, placeholder "Ex.: Linha de envase / Moinho / Tanque 03", `:75`), `sanitizante_utilizado` (**select** populado por `listarSanitizantesPermitidos()`), `responsavel_nome` (text required), `observacoes`. **Não há select de `associado_id`** — ele vem de `$_POST['associado_id']` ou `$user->getAssociadoId()` (`:30`).
- **Regra de conformidade** (`:34-39`): obrigatórios início+equipamento+sanitizante+responsável; e o sanitizante **precisa estar na lista permitida** (`in_array(trim(...), $sanitizantesNomes, true)`) senão bloqueia com mensagem normativa explícita.
- **Ação**: `create()` + `AuditLog::registrar('HIGIENIZACAO_REGISTRADA','controle_higienizacao',$id,'Equipamento/linha: ...')` (`:41-42`) e redirect `/agroindustria/higienizacao?registrada=1` (`:43`).
- **Sem CSRF** no formulário (não há `CSRFProtection` neste arquivo) — inconsistente com o resto do sistema.

### `modulos/agroindustria/ordens_cadastrar.php` (357) — rota `/agroindustria/ordens/nova`
- **Guarda**: `isLoggedIn()` (`:14`); escopo `$filtroAssociado` (`:24`).
- **Montagem dos dados** (`:101-111`): `receita_id`, `higienizacao_previa_id`, `lote_processado_final`, `data_processamento`, `quantidade_final_obtida`, `unidade_medida` (default `'KG'`), `associado_id` (fallback `getAssociadoId()`), `observacoes`, `criado_por`.
- **Itens do balanço** (`:113-134`): arrays paralelos `item_insumo[]`, `item_quantidade[]`, `item_unidade[]`, `item_nf[]`; **ignora** linhas sem insumo ou com `qtd <= 0`; monta `['insumo_id','lote_primario_id'=>null,'produto_id'=>null,'quantidade_utilizada','unidade_medida','numero_nf']`. → **`lote_primario_id` e `produto_id` são sempre `null` pela UI**, apesar de existirem no schema (`migration.sql:1424-1425`).
- **Validações** (`:136-142`): exige receita, lote final, data e quantidade final; exige ≥1 item ("Informe ao menos um insumo utilizado com quantidade para o balanço de massas").
- **RF08 (trava 24h)** (`:144-170`):
  - Valida a receita (`:145-148`).
  - Chama `haHigienizacaoValida($dados['data_processamento'], 'Qualquer linha', associado_id, 24)` (`:149`) — **a linha é hardcoded como `'Qualquer linha'`**, enquanto o cadastro de higienização grava texto livre (`higienizacao_cadastrar.php:75`). Logo a checagem só passa se alguém tiver digitado literalmente "Qualquer linha".
  - Fallback (`:151-169`): se não há higienização "Qualquer linha" e o usuário **não** escolheu `higienizacao_previa_id` → bloqueia; se escolheu, valida que ela está concluída (`data_hora_fim` não nula) e que `proc - fim <= 24h` e `fim <= proc` (`:160-164`). A variável `$thisOp = null` (`:166`) é código morto.
  - ⚠️ Não valida que a higienização escolhida pertence ao **mesmo associado** nem à mesma linha do processamento.
- **Persistência** (`:172-187`): copia `percentual_organico_calculado` e `selo_autorizado` **da receita** para a ordem (`:175-176`), chama `OrdemProducao::create()` (`:177`), registra `AuditLog::registrar('ORDEM_PRODUCAO_CADASTRADA', ...)` com o status de auditoria (`:179`) e redireciona para `/agroindustria/ordens/{id}?criada=1` (`:180`). Erros são logados e convertidos em mensagem genérica (`:182-186`).
- **Sem CSRF** no POST.

### `modulos/agroindustria/ordens_visualizar.php` (215) — rota `/agroindustria/ordens/{id}`
- **Guarda**: `isLoggedIn()` (`:9-12`) + **anti-IDOR** (`:23-31`): `$donoOp = (int)$op['associado_id']`; se `!isGestorOuAdmin()` e (`$donoOp <= 0` ou `!== getAssociadoIdResolvido()`) → "Acesso negado: esta ordem de produção pertence a outro produtor.".
- **Recalcula o balanço em tempo real** (`:33-34`) a partir dos itens gravados — o número exibido pode divergir do `status_auditoria` armazenado se os itens forem alterados depois.
- **Blocos**: Dados da Ordem (`:66-81`); **RF11 — Balanço de Massas** com Entrada (lotes) / Saída (produto final) / Divergência % e alerta (`:83-105`); **Lotes Primários Processados (Campo)** (`:107-130`) — que, como a UI grava `lote_primario_id = null`, mostra "—"/"—" e o rótulo "Entrada (lotes)" na verdade soma **insumos**; QR Code do produto final via `qrcodejs` (`:134-143`, `:151-160`) e etiqueta de impressão 60×90 mm com população do QR por canvas→dataURL (`:162-214`).
- Link público: `/agroindustria/rastreabilidade?lote=<lote_processado_final>` (`:48`).

### `modulos/agroindustria/rastreabilidade.php` (232) — rota pública
- **Pública por design** (comentário `:2` "Não requer login"); acessível porque o roteador não tem guarda (`index.php:1-8`) e este arquivo não checa sessão.
- **Chave de consulta**: `?lote=` comparado a `ordens_producao.lote_processado_final` (`:14-23`) — **igualdade exata**, com `o.excluido = 0`.
- **Dados expostos**: produto final, lote, data de processamento, **nome do produtor e `registro_cnpo`** (`:15-19`, `:96`), `status_auditoria` (`:86-88`), `percentual_organico`, `selo_autorizado` (`:99-105`), quantidade final (`:107-109`); receita completa e ingredientes com flag orgânico e quantidade (`:116-139`); **origem dos insumos com fornecedor e CNPJ e número da nota fiscal** (`:141-188`).
- **Regra de exibição de quantidade** (`:128-132`): se `quantidade` vazia, imprime `proporcao_porcentagem` com sufixo "`g`" — confirma que a coluna guarda peso.
- **Riscos**: qualquer pessoa com o código do lote obtém nome do produtor, CNPJ do fornecedor e NFs; a página não aplica `noindex` nem rate-limit. `htmlspecialchars()` é usado em todas as saídas textuais verificadas (`:80,91,92,96,104,120,125,155,157,166,168,174,176`), sem XSS evidente.

### `classes/Agroindustria.php`, `AgroSetor.php`, `AgroCategoriaAtividade.php`, `AgroAtividadeDiaria.php`, `AgroOcorrencia.php`
- Persistem respectivamente em `agroindustrias`, `agro_setores`, `agro_categorias_atividades`, `agro_atividades_diarias`, `agro_ocorrencias` (schema em `migration.sql:1-149`), todos com `associado_id`, `excluido` (soft delete) e `criado_em`.
- `AgroAtividadeDiaria` grava `insumo_utilizado` (`classes/AgroAtividadeDiaria.php:55,58,71,88,100`), coluna **existente** em `agro_atividades_diarias.insumo_utilizado VARCHAR(255)` (`migration.sql:15`) — **não confundir** com `atividades_diarias.insumo_utilisado VARCHAR(100)` (`migration.sql:401`, erro de digitação legado) usado por `classes/LoteProducao.php:161` e `rastreabilidade.php:43`; ambos estão corretos para suas respectivas tabelas.

### `classes/LoteProducao.php` (220) — lotes de produção (produção vegetal/agroindustrial)
- **Tabelas**: `lotes_producao` (`:32,58,79,104,121,186,209`), JOIN `produtos` (`:60,81,140,163`), `lavouras`/`propriedades` (`:82-83`), `atividades_diarias` (`:141,164`).
- `create()` `:17-50`: gera **UUID v4 via `mt_rand`** (`:23-29`) — gerador **não criptográfico** para um identificador público de rastreabilidade (previsível/testável); grava `uuid, nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes`.
- `getAll()` `:56-70` — **sem filtro de associação** (só `excluido = 0`); usado onde? Ver Parte 5.
- `getByAssociadoId($id)` `:77-95`: `WHERE (lp.associado_id = :associado_id OR (lp.associado_id IS NULL AND p.associado_id = :associado_id))` — resolve lotes órfãos pela propriedade da lavoura.
- `getByUuid()` `:102-112`; `getById()` `:119-129`.
- `getAtividadesByLoteId($lote_id)` `:136-152`: liga o lote às atividades **pela lavoura do produto** (`produtos.lavoura_id = atividades_diarias.lavoura_id`), não por vínculo direto.
- `getInsumosByLoteId()` `:159-175`: `SELECT DISTINCT ad.insumo_utilisado, ad.data_atividade, ad.tipo_atividade` com o mesmo join — coluna correta (ver acima).
- `update()` `:183-200` (**não atualiza `uuid` nem `associado_id`**); `delete()` `:207-216` (`excluido = 1`).

## 4.3 PRODUÇÃO ANIMAL — schema e travas verificadas

### Tabelas (baseline `migration.sql`)
| Tabela | Linhas | Campos-chave |
|---|---|---|
| `animal_lotes` | `:214-228` | `associado_id, nome_lote, especie, categoria, quantidade, descricao, excluido, criado_por` |
| `animais` | `:187-211` | `associado_id, nome_animal, especie, raca, identificacao, sexo ENUM('M','F'), data_nascimento, status ENUM('ATIVO','VENDIDO','ABATIDO','MORTO'), origem_conversao ENUM('ORGANICO','EM_CONVERSAO','CONVENCIONAL'), lote_id, peso_kg, tipo_entrada ENUM('NASCIDO_PROPRIEDADE','COMPRADO_ORGANICO','COMPRA_CONVENCIONAL','TRANSFERIDO_OCS'), fornecedor_origem, data_entrada, observacoes_origem` |
| `ambiente_criacao_animal` | `:152-170` | `associado_id, nome_instalacao, especie ENUM(BOVINOS..APICULTURA), sistema ENUM('EXTENSIVO','SEMI_EXTENSIVO','INTENSIVO','ROTACIONADO'), tipo_instalacao, area_total_m2, quantidade_animais, densidade_referencia, tem_sombreamento_abrigo` |
| `ambiente_criacao_membros` | `:173-184` | pivot `(ambiente_id, associado_id)` UNIQUE |
| `controle_alimentacao_animal` | `:619-639` | `associado_id, animal_id, lote_id, data_alimentacao, tipo_alimento ENUM('FORRAGEM','CONCENTRADO','SUPLEMENTO_MINERAL','ADITIVO_TECNOLOGICO','LEITE_COLOSTRO','APICULTURA'), produto_utilizado, quantidade_kg, percentual_materia_seca, origem ENUM('ORGANICO','EM_CONVERSAO','NAO_ORGANICO_AUTORIZADO')` |
| `controle_sanidade_animal` | `:682-708` | `data_tratamento, tipo_tratamento ENUM('VACINACAO','TRATAMENTO_TERAPEUTICO','CONTROLE_PARASITARIO','PREVENTIVO','EXAME_LABORATORIAL'), produto_utilizado, motivo, dose_quantidade, via_aplicacao, responsavel, produto_normatizado, periodo_carencia_horas, data_liberacao_organica, aprovado_ocs, numero_tratamento` |
| `controle_reproducao_animal` | `:659-679` | `associado_id, animal_id, **grupo_reprodutivo**, tipo_evento ENUM('COBERTURA','INSEMINACAO','PARTO','NASCIMENTO','DESMAME'), data_evento, macho_reprodutor_id, previsao_parto, nascidos_vivos, natimortos, periodo_aleitamento_dias` — **não possui `lote_id`** |
| `manejo_animal_registros` | `:1063-1079` | `animal_id, tipo_registro ENUM('CLINICO','VACINACAO','ALIMENTACAO','REPRODUCAO','PARASITARIO','BEM_ESTAR','OBITO'), data_registro, descricao, produto_utilizado, dose_quantidade, periodo_carencia_horas, responsavel` |
| `densidades_maximas_animais` | `:727-738` | `especie, fase, densidade VARCHAR(80), referencia, ativo`, UNIQUE `(especie, fase)` |

### `classes/ProducaoAnimal.php` (461) — inventário de métodos
`getLotes`(`:17`), `getLoteById`(`:32`), `criarLote`(`:39`), `atualizarLote`(`:57`), `softDeleteLote`(`:73`), `atualizarAnimal`(`:81`), `getAlimentacoes`(`:110`), `getAlimentacaoById`(`:128`), `criarAlimentacao`(`:135`), `atualizarAlimentacao`(`:159`), `softDeleteAlimentacao`(`:180`), `getSanidades`(`:188`), `getSanidadeById`(`:206`), `criarSanidade`(`:213`), `atualizarSanidade`(`:245`), `softDeleteSanidade`(`:274`), `getReproducoes`(`:282`), `getReproducaoById`(`:299`), `criarReproducao`(`:306`), `atualizarReproducao`(`:331`), `softDeleteReproducao`(`:353`), `getAmbientes`(`:361`), `getAmbienteById`(`:375`), `criarAmbiente`(`:382`), `atualizarAmbiente`(`:406`), `softDeleteAmbiente`(`:427`), `getDensidades($especie)`(`:435`), `estatisticas($filtroAssociado)`(`:451`).
- **Não existe cálculo de produção de leite / curva de lactação / dias em lactação** em lugar nenhum do módulo: busca por `lacta`, `litro`, `leite`, `curva`, `producao_leite` em todo o diretório `modulos/producao_animal/` retorna **0 ocorrências**, e o mesmo vale em `classes/ProducaoAnimal.php`. O sistema registra reprodução (eventos, `previsao_parto`, `nascidos_vivos`, `natimortos`, `periodo_aleitamento_dias`) mas **não calcula curva de lactação nem volume de leite**; "leite" só aparece no schema como tipo de alimento (`LEITE_COLOSTRO`, `migration.sql:625`).
- **`estatisticas($filtroAssociado)`** `:451-502` — contadores para dashboard: de `animais` → `total`, `ativos` (`status='ATIVO'`), `bovinos` (`especie='BOVINOS' OR especie LIKE '%bovino%'`), `aves`, `suinos` (aceita 'suíno'/'suino'), `convencionais` (`origem_conversao='CONVENCIONAL'`); de `animal_lotes` → `total` e `SUM(quantidade) AS cabecas`; de `controle_sanidade_animal` → `total`; de `controle_reproducao_animal` → `total`; de `manejo_animal_registros` (INNER JOIN `animais` filtrando por `a.associado_id` e `a.excluido = 0`) → `registros`. Reutiliza o mesmo `$where = " WHERE excluido = 0"` (+ `associado_id`) para todas as tabelas — funciona porque **todas** as tabelas consultadas têm as duas colunas (`migration.sql:214-228,619-708,1063-1079`).
- Padrão de exclusão: todos os `softDelete*` são lógicos.

### `modulos/producao_animal/rastreabilidade.php` (333) — rota pública `/producao-animal/rastreabilidade`
- **Pública** (comentário `:2`, "Não requer login"; sem checagem de sessão no arquivo).
- **Duas chaves**: `?uuid=` comparado a `CONCAT('AL-', LPAD(al.id, 6, '0'))` (`:19-24`) e `?lote=` (id numérico) (`:27-35`); sempre com `al.excluido = 0`. O comentário `:18` menciona formato "SINGER-lote-XXXXXX ou uuid", mas o único formato aceito é `AL-######` — o campo do formulário anuncia "AL-000001" (`:296`), coerente com o SQL; o comentário está desatualizado.
- **Blocos**: identificação do lote + produtor e `registro_cnpo` (`:114-169`); **Origem dos Animais (Recepção)** — `animais` do lote com `raca, identificacao, tipo_entrada, fornecedor_origem, data_entrada` (`:38-45`, `:171-198`); **Alimentação** — `controle_alimentacao_animal WHERE lote_id = ?` (`:48-55`, `:200-224`); **Sanidade** — `controle_sanidade_animal WHERE lote_id = ?` (`:58-65`, `:226-262`), com carência e `data_liberacao_organica`; **Reprodução** (`:264-289`).
- ⚠️ **Bug confirmado**: a consulta de reprodução (`:69-72`) usa `WHERE lote_id = ?` em `controle_reproducao_animal`, **tabela que não tem coluna `lote_id`** (`migration.sql:659-679`). A exceção é engolida pelo `try/catch` (`:68-75`) e a seção de reprodução **nunca aparece**.
- ⚠️ **Rótulos incompatíveis com os ENUMs**: `$tipoEntradaLabels` (`:78-83`) mapeia `NASCIDO_PROPRIEDADE/COMPRADO/TRANSFERIDO/RECEBIDO`, mas o schema usa `COMPRADO_ORGANICO/COMPRA_CONVENCIONAL/TRANSFERIDO_OCS` → esses três aparecem **crus** (`:186`). `$origemLabels` (`:84-88`) define `ORGANICO/CONVENCIONAL/DESCONHECIDO`, também divergente do ENUM (`EM_CONVERSAO`), e **não é usado** — a tabela de alimentação imprime `$al['origem']` sem tradução (`:216`).
- **Exposição pública**: nome do produtor, CNPJ/registro CNPO, lista nominal de animais com identificação, tratamentos sanitários e fornecedor de origem. IDs são **sequenciais** (`AL-000001`, `AL-000002`, …) → **enumerável** por qualquer anônimo.
- `htmlspecialchars()` presente nas saídas de texto; o conteúdo de `$lote['descricao']` passa por `nl2br(htmlspecialchars(...))` (`:155`).

## 4.4 MANEJO ANIMAL e FORNECEDOR — verificações transversais
- `manejo_animal` opera a tabela `animais` (cadastro/edição/visualização) e usa `LoteProducao`/lotes para o `select` de lote (`animais_cadastrar.php:19`, `animais_editar.php:31`), restringindo as opções ao produtor quando nível 3.
- `fornecedor` distingue a visão por perfil: gestor vê "Fornecedores Cadastrados" e todos os registros; nível 3 vê "Meus Fornecedores" — vinculados às suas unidades (`fornecedor/listar.php:16,26-27`). O vínculo fornecedor↔unidade é a tabela `fornecedor_unidades` com `unidade_tipo ENUM('propriedade','agroindustria','animal')` + `unidade_id` e UNIQUE `(fornecedor_id, unidade_tipo, unidade_id)` (`migration.sql:874-888`).
- `fornecedores` tem `UNIQUE(cnpj)` e `UNIQUE(email)` (`migration.sql:908-909`) e **não tem `excluido`** — exclusão é tratada em `fornecedor/excluir.php` (ver Parte 5).
- `fornecedores` é referenciada pela cadeia de rastreabilidade da agroindústria via `insumos.fornecedor_id` → `OrdemProducao::getItensRastreabilidade()` (`classes/OrdemProducao.php:52`) e `agroindustria/rastreabilidade.php:34`.

---


---


# PARTE 5 — AGROINDÚSTRIA (24 telas + classes)

> Seção produzida por análise dedicada de `modulos/agroindustria/*` (24 arquivos) e das classes `Agroindustria`, `AgroSetor`, `AgroCategoriaAtividade`, `AgroAtividadeDiaria`, `AgroOcorrencia`, `ReceitaAgroindustria`, `OrdemProducao`, `ControleHigienizacao` — leitura integral. **Sobreposição intencional** com a PARTE 4 (núcleo verificado pelo autor) e com a PARTE 6 (classes `ReceitaAgroindustria`/`OrdemProducao`/`ControleHigienizacao`): as três leituras foram feitas de forma independente e reconciliadas na PARTE 8.



---

# Relatório de Engenharia Reversa — Módulo AGROINDÚSTRIA (PHP legado / XAMPP / MySQL)

Convenções: todas as referências são 'caminho:linha' ou 'caminho:linha-linha', verificadas por leitura integral dos arquivos. Quando algo não existe no código, está marcado explicitamente como NÃO ENCONTRADO NO CÓDIGO.

---

# 0. Contexto transversal (roteamento, guards, helpers) — verificado no código

## 0.1 Roteamento (roteador único 'index.php', URL amigável via $_GET['url'])

| Rota | Arquivo | Linhas |
|---|---|---|
| /agroindustria/minhas | modulos/agroindustria/minhas_listar.php | index.php:920-922 |
| /agroindustria/minha/nova | modulos/agroindustria/minhas_cadastrar.php | index.php:923-925 |
| /agroindustria/minha/{id}/editar | modulos/agroindustria/minhas_editar.php ($_GET['id']=parts[2]) | index.php:926-928 |
| /agroindustria/setores | modulos/agroindustria/setores_listar.php | index.php:929-931 |
| /agroindustria/setor/novo | modulos/agroindustria/setores_cadastrar.php | index.php:932-934 |
| /agroindustria/setor/{id}/editar | modulos/agroindustria/setores_editar.php | index.php:935-937 |
| /agroindustria/categorias | modulos/agroindustria/categorias_listar.php | index.php:938-940 |
| /agroindustria/categoria/novo | modulos/agroindustria/categorias_cadastrar.php | index.php:941-943 |
| /agroindustria/categoria/{id}/editar | modulos/agroindustria/categorias_editar.php | index.php:944-946 |
| /agroindustria/atividades | modulos/agroindustria/atividades_listar.php | index.php:947-949 |
| /agroindustria/atividade/novo | modulos/agroindustria/atividades_cadastrar.php | index.php:950-952 |
| /agroindustria/atividade/{id}/editar | modulos/agroindustria/atividades_editar.php | index.php:953-955 |
| /agroindustria/ocorrencias | modulos/agroindustria/ocorrencias_listar.php | index.php:956-958 |
| /agroindustria/ocorrencia/novo | modulos/agroindustria/ocorrencias_cadastrar.php | index.php:959-961 |
| /agroindustria/ocorrencia/{id}/editar | modulos/agroindustria/ocorrencias_editar.php | index.php:962-964 |
| /agroindustria/receitas | modulos/agroindustria/receitas_listar.php | index.php:965-967 |
| /agroindustria/receitas/nova | modulos/agroindustria/receitas_cadastrar.php | index.php:968-970 |
| /agroindustria/receitas/{id}/editar | modulos/agroindustria/receitas_editar.php | index.php:971-973 |
| /agroindustria/higienizacao | modulos/agroindustria/higienizacao_listar.php | index.php:974-976 |
| /agroindustria/higienizacao/nova | modulos/agroindustria/higienizacao_cadastrar.php | index.php:977-979 |
| /agroindustria/ordens | modulos/agroindustria/ordens_listar.php | index.php:980-982 |
| /agroindustria/ordens/nova | modulos/agroindustria/ordens_cadastrar.php | index.php:983-985 |
| /agroindustria/ordens/{id} | modulos/agroindustria/ordens_visualizar.php | index.php:986-988 |
| /agroindustria/rastreabilidade[...] | modulos/agroindustria/rastreabilidade.php | index.php:989-991 |

Rotas do módulo NÃO passam por nenhum middleware de permissão no roteador: cada arquivo implementa o próprio guard (index.php:919-991).
Menu lateral (visível apenas se a área de produção incluir 'agroindustria'): includes/menu.php:118-137 (guarda 'temAreaAgro' em menu.php:19); links das telas: menu.php:125 (minhas), 127 (setores), 128 (categorias), 129 (atividades), 130 (ocorrências), 132 (receitas), 133 (higienização), 134 (ordens), 135 (rastreabilidade).

## 0.2 Bootstrap de sessão e permissão

- config: NIVEL_ADMIN=1, NIVEL_GESTOR=2, NIVEL_USUARIO=3 (config/config.php:23-25).
- includes/header.php:2-5 -> require config + User, instancia '$user = new User()'.
- includes/header.php:13-19 -> se não logado (e sem flag de exceção) redireciona para SITE_URL/login e encerra. Isso significa que qualquer arquivo que já incluiu header.php tem o guard de sessão cumprido dentro do header.
- User::isLoggedIn() -> isset($_SESSION['user_id']) (classes/User.php:106-108).
- User::hasPermission($nivel) -> exige 'user_nivel' numérico >0; admin (1) passa sempre; retorna $nivel <= $required_level (classes/User.php:114-141).
- User::isGestorOuAdmin() (User.php:147-149).
- User::temAgroindustria() -> true para ADMIN/GESTOR; true se tipo_usuario in ('produtor_agroindustria','agroindustria'); true se associados.tem_agroindustria=1; senão COUNT(*) em agroindustrias por associado_id (User.php:177-200).
- User::getAssociadoId() -> id do associado logado (tabela associados) ou currentUser['associado_id'] (User.php:259-272).
- User::getAssociadoIdResolvido() -> int, cobre sessão do portal, tabela associados, usuarios.associado_id e associados.user_id (User.php:299-329).
- Já existia correção de escalonamento de privilégio em hasPermission (comentário B-04 em User.php:119-133).

## 0.3 Helpers usados pelos models

- classes/Sanitizer.php:15-21 sanitizeString = strip_tags + trim + remoção de caracteres de controle; sanitizeArray:119-131 aplica em cada escalar. Usado por Agroindustria, AgroSetor, AgroCategoriaAtividade, AgroAtividadeDiaria, AgroOcorrencia. NÃO usado por ReceitaAgroindustria, OrdemProducao e ControleHigienizacao (entrada crua do POST).
- classes/CSRFProtection.php:15 getTokenField(); :24 validatePostRequest().
- classes/AuditLog.php:13 registrar($acao, $tabela=null, $registro_id=null, $descricao=null).
- classes/Database.php:10-19 conexão PDO mysql, ERRMODE_EXCEPTION; ATTR_EMULATE_PREPARES NÃO é definido (default do driver mysql é emular) — relevante porque AgroSetor::getSetoresDoAssociado:55-57 repete o placeholder nomeado ':assoc' duas vezes.

## 0.4 Regra de negócio/regulatória referenciada no código

- modulos/TODO_Agroind.md:56-66 (documento de especificação, não é código em execução): RF07 receitas, RF08 higienização prévia obrigatória, RF09 percentual orgânico (>=95% selo "Produto Orgânico Brasil"; 70-95% frase "ingredientes orgânicos"; <70% bloqueia menção a orgânico), RF10 vínculo campo-agroindústria, RF11 balanço de massas com alerta de fraude, RF12 QR Code de lote.
- A portaria citada no código é a Portaria 52/2021 (e Portaria SDA 811/2023) em classes/ControleHigienizacao.php:52-57 e modulos/agroindustria/higienizacao_cadastrar.php:38,88-89.

---

# 1. modulos/agroindustria/

### modulos/agroindustria/setores_listar.php
- **Rota/acesso**: GET /agroindustria/setores (index.php:929-931); link de menu em includes/menu.php:127. Guard: require config+User (3-4); isLoggedIn -> redirect /login (10-13); temAgroindustria -> alerta e exit (15-19). 'AgroSetor' e 'AuditLog' requeridos (5-6).
- **Propósito de negócio**: listar os setores da agroindústria que substituem as lavouras do Caderno de Campo (texto explicativo em 46: recebimento, processamento, embalagem, expedição).
- **Campos/formulário**: nenhum formulário. Única entrada é 'GET ?excluir=<id>' (23-30).
- **Regras/validações/cálculos**: filtro da listagem = 'null' para gestor, senão getAssociadoId() (32). Sem paginação e sem filtro por agroindústria/unidade na tela (o model suporta associado_id apenas).
- **Tabelas SQL usadas** (via classes/AgroSetor.php): SELECT * FROM agro_setores WHERE excluido = 0 [AND associado_id = ?] ORDER BY nome ASC (AgroSetor.php:14-22); UPDATE agro_setores SET excluido = 1 WHERE id = :id (AgroSetor.php:110).
- **Ações/POST/redirects**: exclusão por GET -> softDelete + AuditLog::registrar('AGRO_SETOR_EXCLUIDO','agro_setores',$id,...) (26) -> header Location /agroindustria/setores (28).
- **Riscos/gaps**: (1) IDOR destrutivo — a exclusão em 23-30 não verifica dono, nível nem vínculo com o usuário: qualquer sessão autenticada com temAgroindustria() apaga setor de qualquer associado; (2) exclusão por GET sem CSRF e sem confirmação server-side (a confirmação é só 'onclick=confirm' em 69); (3) o retorno de softDelete é checado (25) mas AuditLog é registrado em caso de sucesso sem informar qual produtor era dono; (4) não há bloqueio de exclusão quando existem agro_atividades_diarias/agro_ocorrencias apontando para o setor (tabelas sem FK).

### modulos/agroindustria/setores_cadastrar.php
- **Rota/acesso**: GET /agroindustria/setor/novo (index.php:932-934); link em setores_listar.php:41. Guard: isLoggedIn (12-15); temAgroindustria (17-21). Requires: AgroSetor, Agroindustria, Associado, AuditLog (5-8).
- **Propósito de negócio**: cadastrar um setor e, opcionalmente, vinculá-lo a uma unidade (agroindústria) e a um associado.
- **Campos/formulário**: 'nome' (text, required) linha 71; 'associado_id' (select) 75-82 — populado com todos os associados (gestor) ou apenas o próprio (30); 'agroindustria_id' (select, opcional) 86-93 — de Agroindustria::getAll(gestor?null:associadoId) (29); 'descricao' (textarea) 98.
- **Regras/validações/cálculos**: nome obrigatório (37-39) -> 'Informe o nome do setor.'; 'associado_id' = POST se preenchido, senão o da sessão (41); nenhuma validação de que 'agroindustria_id' pertence ao associado informado.
- **Tabelas SQL usadas**: INSERT INTO agro_setores (uuid, associado_id, agroindustria_id, nome, descricao, criado_em) VALUES (:uuid,...,NOW()) (AgroSetor.php:75-83); SELECT * FROM agroindustrias ... (Agroindustria.php:19-32); associados via Associado::getAll/getById (30).
- **Ações/POST/redirects**: POST -> create (42) -> AuditLog 'AGRO_SETOR_CADASTRADO' com registro_id null (43) -> redirect /agroindustria/setores?cadastrado=1 (44).
- **Riscos/gaps**: (1) 'associado_id' vem do POST (41) -> escrita cross-tenant: um usuário nível 3 pode criar setor atribuído a outro associado; (2) 'agroindustria_id' aceito sem validação de posse (não há FK em agro_setores — dev-utils/migrate_agro_producao.php:65-76 define apenas índices); (3) AuditLog sem ID do registro criado (43) porque AgroSetor::create retorna bool (AgroSetor.php:84) — rastro de auditoria fraco; (4) AgroSetor::create usa bindParam(':nome'/'descricao') sem null-coalesce (AgroSetor.php:82-83) -> POST forjado sem essas chaves gera warning PHP 8 e grava NULL em coluna NOT NULL (nome); (5) sem CSRF; (6) htmlspecialchars($ag['nome_fantasia']) em 90 sem ?? -> nome_fantasia NULL (permitido: Agroindustria.php:174) gera deprecation no PHP 8.1 e opção vazia.

### modulos/agroindustria/setores_editar.php
- **Rota/acesso**: GET /agroindustria/setor/{id}/editar (index.php:935-937). Guards: isLoggedIn (11-14), temAgroindustria (16-20), id>0 (22-26), existência do setor (33-37), anti-IDOR 39-43: se não gestor e setor.associado_id != associadoId -> mensagem 'Você não tem permissão para editar este setor.' e exit.
- **Propósito de negócio**: alterar nome, unidade vinculada e descrição do setor.
- **Campos/formulário**: 'nome' (text, required) 84; 'agroindustria_id' (select) 88-95; 'descricao' (textarea) 99. O formulário NÃO envia associado_id.
- **Regras/validações/cálculos**: nome obrigatório (51-53); sem validação de posse da agroindústria selecionada; após erro, '$setor = array_merge($setor,$_POST)' (62) apenas para repopular.
- **Tabelas SQL usadas**: SELECT * FROM agro_setores WHERE id=:id AND excluido=0 (AgroSetor.php:32); UPDATE agro_setores SET agroindustria_id=:agroindustria_id, nome=:nome, descricao=:descricao WHERE id=:id AND excluido=0 (AgroSetor.php:95) — não altera associado_id nem uuid.
- **Ações/POST/redirects**: POST -> update (54) -> AuditLog 'AGRO_SETOR_ATUALIZADO' (55) -> redirect /agroindustria/setores?atualizado=1 (56).
- **Riscos/gaps**: sem CSRF; bindParam sem ?? em AgroSetor.php:98-99 (nome vazio é barrado na tela, mas descricao pode vir ausente); comparação frouxa '==' em 91; o anti-IDOR usa getAssociadoId() enquanto o módulo de ordens usa getAssociadoIdResolvido() (User.php:299) — critérios diferentes de dono no mesmo módulo.

### modulos/agroindustria/categorias_listar.php
- **Rota/acesso**: GET /agroindustria/categorias (index.php:938-940); menu.php:128. Guards: isLoggedIn (10-13), temAgroindustria (15-19).
- **Propósito de negócio**: listar categorias usadas para classificar as atividades diárias (46).
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (23-30).
- **Regras/validações/cálculos**: listagem SEM filtro de associado — getAll() (32).
- **Tabelas SQL usadas**: SELECT id, nome, descricao, cor FROM agro_categorias_atividades WHERE excluido=0 ORDER BY nome ASC (AgroCategoriaAtividade.php:14); UPDATE ... SET excluido=1 (76).
- **Ações/POST/redirects**: exclusão GET -> softDelete + AuditLog 'AGRO_CATEGORIA_EXCLUIDA' (26) -> redirect /agroindustria/categorias.
- **Riscos/gaps**: (1) IDOR destrutivo sem checagem (23-30) — permite excluir inclusive as categorias GLOBAIS (associado_id NULL, criadas pelo seed dev-utils/migrate_agro_producao.php:100-112) que o próprio módulo proíbe editar (categorias_editar.php:37-43) -> contradição direta entre guard de edição e de exclusão; (2) 'cor' é gravada sem validação (AgroCategoriaAtividade.php:49) e injetada em style="background-color:..." (66) — htmlspecialchars impede quebra de atributo mas permite CSS arbitrário; (3) sem CSRF; (4) getAll() global expõe categorias criadas por outros produtores.

### modulos/agroindustria/categorias_cadastrar.php
- **Rota/acesso**: GET /agroindustria/categoria/novo (index.php:941-943); link categorias_listar.php:41. Guards: isLoggedIn (10-13), temAgroindustria (15-19).
- **Propósito de negócio**: cadastrar categoria de atividade.
- **Campos/formulário**: 'nome' (text, required) 63; 'cor' (input type=color) 67; 'descricao' (textarea) 71.
- **Regras/validações/cálculos**: nome obrigatório (28-30); 'associado_id' forçado para o da sessão (32) — nem gestor pode criar para outro associado; 'cor' default '#0d6efd' (33).
- **Tabelas SQL usadas**: INSERT INTO agro_categorias_atividades (uuid, associado_id, nome, descricao, cor, criado_em) VALUES (..., NOW()) (AgroCategoriaAtividade.php:42-49).
- **Ações/POST/redirects**: create (34) -> AuditLog 'AGRO_CATEGORIA_CADASTRADA' com id null (35) -> redirect /agroindustria/categorias?cadastrada=1.
- **Riscos/gaps**: 'cor' sem validação de formato (hex) em nenhuma camada; uuid gerado com mt_rand (AgroCategoriaAtividade.php:85-93) — previsível (baixo impacto: o uuid não é usado em nenhuma rota do módulo); sem unicidade de nome (nenhum UNIQUE no schema, dev-utils/migrate_agro_producao.php:87-97); sem CSRF; AuditLog sem ID.

### modulos/agroindustria/categorias_editar.php
- **Rota/acesso**: GET /agroindustria/categoria/{id}/editar (index.php:944-946). Guards: isLoggedIn (10-13), temAgroindustria (15-19), id (21-25), existência (28-32), anti-IDOR 34-43.
- **Propósito de negócio**: editar categoria.
- **Regras/validações/cálculos**: anti-IDOR explícito (comentário 34-36): '$donoCategoria = (int)($categoria['associado_id'] ?? 0)'; nega se '!isGestorOuAdmin()' e ('donoCategoria <= 0' ou 'donoCategoria !== (int)getAssociadoIdResolvido()') -> categorias globais (associado_id NULL/0) só para equipe (37-43). Nome obrigatório (49-51).
- **Campos/formulário**: 'nome' (required) 82; 'cor' (color) 86; 'descricao' 90.
- **Tabelas SQL usadas**: SELECT * FROM agro_categorias_atividades WHERE id=:id AND excluido=0 (AgroCategoriaAtividade.php:25); UPDATE agro_categorias_atividades SET nome=:nome, descricao=:descricao, cor=:cor WHERE id=:id AND excluido=0 (61) — não altera associado_id/uuid.
- **Ações/POST/redirects**: update (52) -> AuditLog 'AGRO_CATEGORIA_ATUALIZADA' (53) -> redirect ?atualizada=1 (54).
- **Riscos/gaps**: o guard de edição não é replicado na exclusão (categorias_listar.php:23-30) — usuário nível 3 não pode editar uma categoria global, mas pode excluí-la; sem CSRF; sem validação de cor.

### modulos/agroindustria/atividades_listar.php
- **Rota/acesso**: GET /agroindustria/atividades (index.php:947-949); menu.php:129. Guards: isLoggedIn (10-13), temAgroindustria (15-19).
- **Propósito de negócio**: listar as atividades diárias de produção da agroindústria (data, atividade, setor, categoria, status).
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (23-30).
- **Regras/validações/cálculos**: filtro por associado para não gestor (32); data formatada com date('d/m/Y', strtotime(...)) (67); janela de horas com substr 0,5 (69); badge de status por comparação exata com 'Concluída'/'Em Andamento' (93).
- **Tabelas SQL usadas** (AgroAtividadeDiaria.php:14-18):
    SELECT ad.*, s.nome AS setor_nome, c.nome AS categoria_nome, c.cor AS categoria_cor
    FROM agro_atividades_diarias ad
    LEFT JOIN agro_setores s ON ad.setor_id = s.id
    LEFT JOIN agro_categorias_atividades c ON ad.categoria_id = c.id
    WHERE ad.excluido = 0 [AND ad.associado_id = ?]
    ORDER BY ad.data_atividade DESC, ad.hora_inicio DESC
  Exclusão: UPDATE agro_atividades_diarias SET excluido = 1 WHERE id = :id (AgroAtividadeDiaria.php:112).
- **Ações/POST/redirects**: exclusão GET -> softDelete + AuditLog 'AGRO_ATIVIDADE_EXCLUIDA' (26) -> redirect.
- **Riscos/gaps**: (1) IDOR destrutivo (23-30); (2) os LEFT JOINs não filtram 'excluido' das tabelas relacionadas (16-17) -> setores/categorias já excluídos continuam aparecendo com nome; (3) 'categoria_cor' injetada em style (87) sem validação de cor; (4) sem CSRF; (5) strtotime sem validação de data.

### modulos/agroindustria/atividades_cadastrar.php
- **Rota/acesso**: GET /agroindustria/atividade/novo (index.php:950-952); link atividades_listar.php:41. Guards: isLoggedIn (12-15), temAgroindustria (17-21).
- **Propósito de negócio**: registrar atividade diária da agroindústria (equivalente ao caderno de campo, mas por setor).
- **Campos/formulário**: 'nome' (text, required) 84; 'data_atividade' (date, required, default hoje) 88; 'status' (select: Concluída/Em Andamento/Planejada/Cancelada) 92-96; 'setor_id' (select) 101-108; 'categoria_id' (select) 112-119; 'tipo_atividade' (select dos 8 tipos do mapa 54-63) 123-128; 'hora_inicio' (time) 133; 'hora_fim' (time) 137; 'insumo_utilizado' (text) 141; 'descricao' (textarea) 146; 'observacoes' (textarea) 150.
- **Regras/validações/cálculos**: nome e data obrigatórios (38-40); 'associado_id' = POST || sessão (42); status default 'Concluída' (43); nenhuma validação de intervalo de horas ou de data válida.
- **Tabelas SQL usadas**: INSERT INTO agro_atividades_diarias (uuid, associado_id, setor_id, categoria_id, nome, data_atividade, hora_inicio, hora_fim, descricao, status, tipo_atividade, insumo_utilizado, observacoes, criado_em) VALUES (..., NOW()) (AgroAtividadeDiaria.php:52-72); setores via AgroSetor::getAll (30); categorias via AgroCategoriaAtividade::getAll() SEM filtro (31).
- **Ações/POST/redirects**: create (44) -> AuditLog 'AGRO_ATIVIDADE_CADASTRADA' com id null (45) -> redirect ?cadastrada=1 (46).
- **Riscos/gaps**: (1) 'associado_id' do POST (42) -> cross-tenant; (2) 'setor_id'/'categoria_id' sem validação de posse (tabelas sem FK, dev-utils/migrate_agro_producao.php:122-143); (3) 'insumo_utilizado' é texto livre VARCHAR(255) (migrate_agro_producao.php:135), sem vínculo com a tabela 'insumos' -> o insumo da atividade diária NÃO é rastreável (contraste com a corrente de rastreabilidade das ordens); (4) categorias listadas globalmente (31) expõem categorias de outros produtores; (5) sem CSRF; (6) binds sem null-coalesce em AgroAtividadeDiaria.php:64-72 (descricao/status ausentes -> warning/NULL).

### modulos/agroindustria/atividades_editar.php
- **Rota/acesso**: GET /agroindustria/atividade/{id}/editar (index.php:953-955). Guards: isLoggedIn (12-15), temAgroindustria (17-21), id (23-27), existência (33-37), anti-IDOR 39-43 (associado_id vs sessão, gestor passa).
- **Propósito de negócio**: corrigir atividade lançada.
- **Campos/formulário**: idênticos ao cadastrar (99-165); mapa de tipos duplicado literalmente em 69-78 (duplicação de manutenção em relação a atividades_cadastrar.php:54-63).
- **Regras/validações/cálculos**: nome+data obrigatórios (55-57); status comparado com '===' em 109; setor/categoria com '==' em 119,130.
- **Tabelas SQL usadas**: UPDATE agro_atividades_diarias SET setor_id, categoria_id, nome, data_atividade, hora_inicio, hora_fim, descricao, status, tipo_atividade, insumo_utilizado, observacoes WHERE id=:id AND excluido=0 (AgroAtividadeDiaria.php:84-89) — não altera associado_id.
- **Ações/POST/redirects**: update (58) -> AuditLog 'AGRO_ATIVIDADE_ATUALIZADA' (59) -> redirect ?atualizada=1 (60).
- **Riscos/gaps**: mesmo pacote do cadastrar (sem CSRF, sem validação de posse de setor/categoria, insumo texto livre, sem validação de hora/data); binds sem ?? (AgroAtividadeDiaria.php:93-101).

### modulos/agroindustria/ocorrencias_listar.php
- **Rota/acesso**: GET /agroindustria/ocorrencias (index.php:956-958); menu.php:130. Guards: isLoggedIn (10-13), temAgroindustria (15-19).
- **Propósito de negócio**: listar ocorrências (contaminação, falha de processo, qualidade, higienização, equipamento, rastreabilidade) com severidade e status.
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (23-30).
- **Regras/validações/cálculos**: filtro por associado (32); mapa de cores de severidade 34-38 (Baixa/Média/Alta); badge de status 92 (Aberta vermelho, Em Tratamento amarelo, senão verde); descrição truncada em 80 chars (77).
- **Tabelas SQL usadas**: SELECT o.*, s.nome AS setor_nome FROM agro_ocorrencias o LEFT JOIN agro_setores s ON o.setor_id = s.id WHERE o.excluido = 0 [AND o.associado_id = ?] ORDER BY o.data_ocorrencia DESC, o.criado_em DESC (AgroOcorrencia.php:14-23); UPDATE agro_ocorrencias SET excluido=1 (101).
- **Ações/POST/redirects**: exclusão GET -> softDelete + AuditLog 'AGRO_OCORRENCIA_EXCLUIDA' -> redirect.
- **Riscos/gaps**: IDOR destrutivo (23-30); join sem filtro 'excluido' do setor; sem CSRF; a ocorrência não pode ser vinculada a uma ordem/lote (não existe coluna de ordem em agro_ocorrencias — dev-utils/migrate_agro_producao.php:154-171) -> gap de rastreabilidade.

### modulos/agroindustria/ocorrencias_cadastrar.php
- **Rota/acesso**: GET /agroindustria/ocorrencia/novo (index.php:959-961); link ocorrencias_listar.php:47. Guards: isLoggedIn (11-14), temAgroindustria (16-20).
- **Propósito de negócio**: registrar ocorrência e ação de manejo.
- **Campos/formulário**: 'titulo' (text, required) 82; 'data_ocorrencia' (date, required, default hoje) 86; 'tipo' (select, mapa 53-61) 90-94; 'setor_id' (select) 99-106; 'severidade' (select Baixa/Média/Alta) 110-114; 'status' (select Aberta/Em Tratamento/Resolvida) 118-122; 'descricao' (textarea) 127; 'acao_manejo' (textarea) 131.
- **Regras/validações/cálculos**: titulo+data obrigatórios (35-37); defaults por '??' em 40-42 ('Outro','Baixa','Aberta') — '??' só cobre null, não string vazia: POST com 'severidade=' grava '' em ENUM('Baixa','Média','Alta') -> erro MySQL em strict mode, PDOException capturada em AgroOcorrencia.php:68-71 -> retorno false -> mensagem genérica; 'associado_id' = POST || sessão (39).
- **Tabelas SQL usadas**: INSERT INTO agro_ocorrencias (uuid, associado_id, setor_id, tipo, titulo, data_ocorrencia, severidade, descricao, acao_manejo, status, criado_em) VALUES (..., NOW()) (AgroOcorrencia.php:52-66).
- **Ações/POST/redirects**: create (43) -> AuditLog 'AGRO_OCORRENCIA_CADASTRADA' com id null (44) -> redirect ?cadastrada=1 (45).
- **Riscos/gaps**: 'associado_id' do POST (39); 'setor_id' sem validação de posse; sem CSRF; sem vínculo com ordem/lote/insumo (não existe coluna); binds sem ?? em AgroOcorrencia.php:60-66.

### modulos/agroindustria/ocorrencias_editar.php
- **Rota/acesso**: GET /agroindustria/ocorrencia/{id}/editar (index.php:962-964). Guards: isLoggedIn (11-14), temAgroindustria (16-20), id (22-26), existência (32-36), anti-IDOR 38-42.
- **Propósito de negócio**: atualizar ocorrência/ação de manejo/status.
- **Campos/formulário**: 94-143 (mesmos campos do cadastrar; mapa de tipos duplicado em 65-73).
- **Regras/validações/cálculos**: titulo+data obrigatórios (51-53); comparações '===' com os valores das listas (104,124,132).
- **Tabelas SQL usadas**: UPDATE agro_ocorrencias SET setor_id, tipo, titulo, data_ocorrencia, severidade, descricao, acao_manejo, status WHERE id=:id AND excluido=0 (AgroOcorrencia.php:78-81).
- **Ações/POST/redirects**: update (54) -> AuditLog 'AGRO_OCORRENCIA_ATUALIZADA' (55) -> redirect ?atualizada=1 (56).
- **Riscos/gaps**: sem CSRF; setor sem validação de posse; 'severidade' POST livre até o ENUM do banco.

### modulos/agroindustria/receitas_listar.php
- **Rota/acesso**: GET /agroindustria/receitas (index.php:965-967); menu.php:132. Guard: SOMENTE isLoggedIn (10-13) — NÃO exige temAgroindustria(), ao contrário de setores/atividades/ocorrências.
- **Propósito de negócio**: listar receitas (produto final, percentual orgânico, selo autorizado, nº de ingredientes, status).
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (18-25).
- **Regras/validações/cálculos**: filtro por associado (27); mapa de rótulos de selo 29-33; cores por faixa de percentual (74: >=95 verde, >=70 amarelo, senão cinza); badges de status 85 (Ativa verde / Rascunho cinza / outros vermelho).
- **Tabelas SQL usadas** (ReceitaAgroindustria.php:15-18): SELECT r.*, (SELECT COUNT(*) FROM ingredientes_receita i WHERE i.receita_id = r.id) AS total_ingredientes FROM receitas_agroindustria r WHERE r.excluido = 0 [AND r.associado_id = ?] ORDER BY r.criado_em DESC; softDelete: UPDATE receitas_agroindustria SET excluido = 1 WHERE id = ? (196).
- **Ações/POST/redirects**: exclusão GET -> softDelete + AuditLog 'RECEITA_EXCLUIDA' em 'receitas_agroindustria' (21) -> redirect /agroindustria/receitas (23). Não há checagem de retorno nem de dono.
- **Riscos/gaps**: (1) IDOR destrutivo — qualquer sessão autenticada (inclusive perfil sem agroindústria, pois não há guard temAgroindustria) exclui qualquer receita por id (18-25); (2) exclusão permitida mesmo com ordens de produção vinculadas: a FK 'fk_op_receita' não tem ON DELETE (dev-utils/migrate_conformidade.php:118) e a ordem continua válida apontando para receita soft-deleted; (3) a rastreabilidade pública ainda exibe a receita excluída (rastreabilidade.php:18 e :40 não filtram 'excluido'); (4) sem CSRF.

### modulos/agroindustria/receitas_cadastrar.php
- **Rota/acesso**: GET /agroindustria/receitas/nova (index.php:968-970); link receitas_listar.php:42. Guard: SOMENTE isLoggedIn (11-14).
- **Propósito de negócio**: cadastrar receita com ingredientes e calcular automaticamente o percentual orgânico e o selo (RF09).
- **Campos/formulário**: 'nome_produto_final' (text, required) 103; 'associado_id' (select) 107-114; 'status' (select Ativa/Rascunho/Inativa) 118-122; 'descricao' (textarea) 126; ingredientes como arrays paralelos: 'ing_nome[]' (text) 140, 'ing_quantidade[]' (number step=0.001 min=0) 143, 'ing_unidade[]' (select kg/g/mg/l/ml/un) 146-153, 'ing_organico[]' (select 1=Orgânico / 0=Convencional) 156-159, 'ing_obs[]' (text) 162; botões add/remove (134, 165) e card de pré-visualização do percentual (170-184).
- **Regras/validações/cálculos**:
  - Montagem server-side dos ingredientes (28-47): ignora nome vazio (37-39); 'eh_organico' = !empty($organicos[$i]) ? 1 : 0 (42); quantidade = (float) (43); unidade default 'kg' se vazia (44); observações trim (45).
  - Validações (49-55): nome_produto_final obrigatório -> 'Informe o nome do produto final.'; 'empty($ingredientes)' -> 'Adicione ao menos um ingrediente com quantidade maior que zero.' (a exclusão real de quantidade <= 0 só ocorre no model, ReceitaAgroindustria.php:179-181).
  - Pós-POST recalcula o preview: '$percentual = calcularPercentualOrganico($ingredientes)' (70) e '$selo = classificarSelo($percentual)' (71).
  - Defaults de persistência: associado_id = POST || sessão (57); status default 'Ativa' (58); criado_por = (int)$_SESSION['user_id'] (59).
- **Tabelas SQL usadas** (ReceitaAgroindustria.php): INSERT INTO receitas_agroindustria (nome_produto_final, descricao, percentual_organico_calculado, selo_autorizado, associado_id, status, criado_por) VALUES (7 placeholders) (109-112); INSERT INTO ingredientes_receita (receita_id, nome_ingrediente, eh_organico, quantidade, unidade_medida, proporcao_porcentagem, observacoes) (171-173); transação em 106/125/128.
- **Ações/POST/redirects**: create (60) -> AuditLog 'RECEITA_CADASTRADA' com o ID retornado (61) -> redirect /agroindustria/receitas?cadastrada=1 (62); exceção -> mensagem genérica (64-68).
- **Fórmulas literais** (classes/ReceitaAgroindustria.php):
  - fatorConversao (47-59): 'kg'=>1000; 'mg'=>0.001; 't'=>1000000; 'l'=>1000; 'ml'=>1; 'un'/'und'=>1; default 1 (g/mL/un).
  - calcularPercentualOrganico (65-81): '$qtd = (float)($ing['quantidade'] ?? $ing['proporcao_porcentagem'] ?? 0)'; '$peso = $qtd * $this->fatorConversao($ing['unidade_medida'] ?? 'kg')'; '$somaPeso += $peso'; se 'eh_organico' então '$somaOrganico += $peso'; se 'somaPeso <= 0' retorna 0.0; senão 'round(($somaOrganico / $somaPeso) * 100, 2)'.
  - classificarSelo (89-99): '>= 95.0' -> PRODUTO_ORGANICO_BRASIL; '>= 70.0' -> INGREDIENTES_ORGANICOS; senão SEM_SELO.
  - salvarIngredientes (168-192): pula se nome vazio ou '$peso <= 0' (179-181); grava 'proporcao_porcentagem' igual ao PESO em gramas/mL (188) — confirmado por dev-utils/migrate_receita_quantidade.php:16.
  - Espelho JS (196-254): 'fatorConversao' em JS (200-209) com kg/mg/t/l/ml e default 1 (não trata 'g'/'un' explicitamente, cai no default — mesmo resultado); limiar de selo idêntico (228-230); arredondamento JS para 1 casa (221) contra 2 casas do PHP (80) -> divergência de exibição.
- **Riscos/gaps**: (1) escrita cross-tenant via 'associado_id' do POST (57) — não gestor pode gravar receita para outro associado; (2) unidade 'un' convertida para 1 "grama" (fatorConversao:55-56) distorce o percentual orgânico de receitas com itens contados em unidades; (3) mistura massa (kg/g) e volume (l/ml) na mesma base com razão 1:1 (47-59) — assume densidade 1; (4) quantidade negativa é enviada pelo POST e simplesmente descartada no model (179-181), alterando o percentual silenciosamente; (5) sem CSRF; (6) sem guard temAgroindustria; (7) 'status' default 'Ativa' no código (58/119) enquanto o schema tem DEFAULT 'Rascunho' (dev-utils/migrate_conformidade.php:73); (8) nenhum cálculo de custo/rendimento/perda — NÃO ENCONTRADO NO CÓDIGO.

### modulos/agroindustria/receitas_editar.php
- **Rota/acesso**: GET /agroindustria/receitas/{id}/editar (index.php:971-973). Guard: SOMENTE isLoggedIn (11-14). Carrega a receita (16-25); se inexistente, alerta 'Receita não encontrada.' (22).
- **Propósito de negócio**: alterar receita e ingredientes.
- **Campos/formulário**: iguais ao cadastrar (113-137 dados gerais; 148-189 linhas de ingredientes geradas a partir do banco, com fallback de linha vazia em 180-189).
- **Regras/validações/cálculos**: mesmas do cadastrar (56-75); preview recalculado em 90-91.
- **Tabelas SQL usadas** (ReceitaAgroindustria.php): SELECT * FROM receitas_agroindustria WHERE id = ? AND excluido = 0 (32); SELECT * FROM ingredientes_receita WHERE receita_id = ? ORDER BY id ASC (39); UPDATE receitas_agroindustria SET nome_produto_final=?, descricao=?, percentual_organico_calculado=?, selo_autorizado=?, associado_id=?, status=? WHERE id = ? (140-144) — SEM 'AND excluido = 0'; DELETE FROM ingredientes_receita WHERE receita_id = ? (156) seguido de reinserção (158).
- **Ações/POST/redirects**: update (80) -> AuditLog 'RECEITA_EDITADA' (81) -> redirect /agroindustria/receitas?editada=1 (82).
- **Riscos/gaps**: (1) NÃO HÁ verificação de posse — qualquer usuário autenticado edita qualquer receita (IDOR de escrita), ao contrário de setores/atividades/ocorrências que checam associado_id; (2) 'associado_id' do POST (78) permite transferir a receita para outro produtor; (3) o UPDATE ignora 'excluido' (144) -> permite editar receita já soft-deletada; (4) o DELETE+INSERT de ingredientes (156-158) destrói o histórico: uma ordem de produção antiga passa a exibir a receita ATUAL na rastreabilidade pública (rastreabilidade.php:40-46) — não há versionamento de receita; (5) destruição de histórico é agravada porque a ordem não guarda snapshot dos ingredientes (apenas percentual_organico e selo, OrdemProducao.php:110-111); (6) sem CSRF; (7) sem temAgroindustria.

### modulos/agroindustria/ordens_listar.php
- **Rota/acesso**: GET /agroindustria/ordens (index.php:980-982); menu.php:134. Guard: SOMENTE isLoggedIn (10-13).
- **Propósito de negócio**: listar ordens de produção com lote final, produto, data, quantidade, % orgânico e status de auditoria.
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (17-23).
- **Regras/validações/cálculos**: filtro por associado (25); mapa statusBadge 27-31: APROVADO->bg-success/'Aprovado', ALERTA_FRAUDE->bg-danger/'Alerta de Fraude', EM_ANALISE->bg-warning/'Em análise'.
- **Tabelas SQL usadas** (OrdemProducao.php:15-18): SELECT o.*, r.nome_produto_final FROM ordens_producao o JOIN receitas_agroindustria r ON o.receita_id = r.id WHERE o.excluido = 0 [AND o.associado_id = ?] ORDER BY o.data_processamento DESC; softDelete: UPDATE ordens_producao SET excluido = 1 WHERE id = ? (157).
- **Ações/POST/redirects**: '?excluir=' -> '$opClass->softDelete($id)' com RETORNO IGNORADO (19), AuditLog 'ORDEM_PRODUCAO_EXCLUIDA' sempre registrado (20), redirect (21).
- **Riscos/gaps**: (1) IDOR destrutivo sem qualquer verificação de dono/nível (17-23) — qualquer autenticado apaga qualquer OP; (2) sem CSRF; (3) JOIN em receitas não filtra r.excluido (OrdemProducao.php:17); (4) o AuditLog é gravado mesmo se nada foi excluído (id inexistente).

### modulos/agroindustria/ordens_cadastrar.php
- **Rota/acesso**: GET /agroindustria/ordens/nova (index.php:983-985); link ordens_listar.php:40. Guard: SOMENTE isLoggedIn (14-17). Requires: OrdemProducao, Agroindustria, ReceitaAgroindustria, ControleHigienizacao, Database, AuditLog (5-10).
- **Propósito de negócio**: registrar ordem de produção (RF10/RF11): vincula receita, lote processado, higienização prévia (RF08), insumos consumidos com NF e calcula o balanço de massas.
- **Dados carregados para a tela**:
  - receitas = ReceitaAgroindustria::getAll(filtroAssociado) (24-25).
  - higienizações concluídas nas últimas 24h (28-36), SQL literal (30 e 33):
      SELECT * FROM controle_higienizacao WHERE excluido = 0 AND data_hora_fim IS NOT NULL AND data_hora_fim >= DATE_SUB(NOW(), INTERVAL 24 HOUR) [AND associado_id = ?] ORDER BY data_hora_fim DESC
  - lotes de campo (40-50): SELECT l.id, l.nome_lote, l.quantidade, l.unidade_medida, p.nome AS nome_produto FROM lotes_producao l LEFT JOIN produtos p ON l.produto_id = p.id WHERE l.excluido = 0 AND p.lavoura_id IS NOT NULL AND p.lavoura_id != 0 [AND l.associado_id = :assoc] ORDER BY l.data_producao DESC -> a variável $lotesCampo NÃO é usada em nenhum campo do formulário: código morto / RF10 (vínculo com o lote primário) não implementado na tela.
  - lotes finais (53-63) com '(p.lavoura_id IS NULL OR p.lavoura_id = 0)' -> alimentam o select 'lote_processado_final' (224-231), que envia apenas o NOME do lote como value (227).
  - insumos (67-95): para não gestor, monta a lista de associados da MESMA agroindústria (Agroindustria::getAssociadosDaMesmaAgroindustria, 70) e consulta com 'IN ($in)' concatenado após intval (72,79):
      SELECT i.id, i.nome, i.lote, i.tipo_insumo, i.quantidade_estoque, CONCAT(ass.nome,' ',COALESCE(ass.sobrenome,'')) AS registrado_por, f.nome AS fornecedor, f.cnpj AS fornecedor_cnpj FROM insumos i LEFT JOIN associados ass ON i.associado_id = ass.id LEFT JOIN fornecedores f ON i.fornecedor_id = f.id WHERE i.excluido = 0 [AND i.associado_id IN ($in)] ORDER BY i.nome ASC
    gestor: mesma consulta sem filtro de associado (85-94).
- **Campos/formulário**: 'receita_id' (select, required) 213-220 com data-percentual; 'lote_processado_final' (select, required) 224-231; 'data_processamento' (datetime-local, required, default agora) 238; 'quantidade_final_obtida' (number step=0.01 min=0, required) 242; 'unidade_medida' (select KG/L/UN) 246-250; 'higienizacao_previa_id' (select das higienizações <24h) 254-261; itens em arrays paralelos: 'item_insumo[]' (select, required) 279-288, 'item_quantidade[]' (number) 291, 'item_unidade[]' (text, default KG) 294, 'item_nf[]' (text) 297; 'observacoes' (textarea) 313; botões add/remove de item (272, 300); painel de balanço em tempo real (316-319).
- **Regras/validações/cálculos**:
  - Montagem POST (100-134): campos em 101-111; criado_por = (int)$_SESSION['user_id'] (110); itens em 113-134 ignorando insumo vazio (119-121) e quantidade <= 0 (123-125); 'lote_primario_id' e 'produto_id' gravados SEMPRE como null (128-129).
  - Obrigatoriedade (137-139): receita, lote processado, data e quantidade final; senão 'Preencha os campos obrigatórios...'. Exige ao menos um item (140-142).
  - RF08 (144-170): receita precisa existir (145-148); em seguida
      'elseif (!$higClass->haHigienizacaoValida($dados['data_processamento'], 'Qualquer linha', (int)($dados['associado_id'] ?? 0), 24))' (149)
    e, dentro do bloco, se não houver 'higienizacao_previa_id' -> BLOQUEADO (RF08) (151-153); senão carrega a higienização (155) e valida apenas fim concluído (156-158) e janela temporal:
      '$fim = strtotime($hig['data_hora_fim']); $proc = strtotime($dados['data_processamento']); if ($proc - $fim > 24 * 3600 || $fim > $proc) -> BLOQUEADO' (160-164)
    seguido de '$thisOp = null;' (166-167) — atribuição morta.
  - Snapshot do percentual/selo da receita (175-176) e persistência (177).
- **Tabelas SQL usadas** (OrdemProducao.php): INSERT INTO ordens_producao (receita_id, higienizacao_previa_id, lote_processado_final, data_processamento, quantidade_final_obtida, unidade_medida, status_auditoria, percentual_organico, selo_autorizado, associado_id, observacoes, criado_por) VALUES (12 placeholders) (108-127); INSERT INTO rastreabilidade_insumos_processo (ordem_producao_id, lote_primario_id, produto_id, insumo_id, numero_nf, quantidade_utilizada, unidade_medida) VALUES (7 placeholders) (131-144); transação 105/147/150. Também controle_higienizacao (30/33) e insumos/associados/fornecedores (73-80).
- **Ações/POST/redirects**: create (177) -> getById (178) -> AuditLog 'ORDEM_PRODUCAO_CADASTRADA' com o status_auditoria calculado (179) -> redirect /agroindustria/ordens/{id}?criada=1 (180); exceção -> mensagem genérica (182-186).
- **Cálculo do balanço (JS espelhado, 361-383)**: soma 'item_quantidade[]' de todos os '.item-row' (363-365) = entrada; saída = 'quantidade_final_obtida' (366); 'const div = Math.abs(entrada - saida) / entrada * 100' (371); 'div > 10' -> 'ALERTA FRAUDE' (372-378). Limiar 10% duplicado em dois lugares (aqui e OrdemProducao.php:64).
- **Riscos/gaps**: (1) 'haHigienizacaoValida' é chamada com o literal 'Qualquer linha' (149) e o método compara equipamento_linha = ? exatamente (ControleHigienizacao.php:44): nenhuma higienização real ('Linha de envase', 'Moinho'...) casa, então o primeiro ramo é praticamente sempre falso; (2) o 3º argumento ($associadoId) passado em 149 é ignorado pelo SQL (ControleHigienizacao.php:38-48 não usa o parâmetro) -> não há filtro por produtor na validação; (3) se o ramo 'haHigienizacaoValida' retornar TRUE (registro literal 'Qualquer linha' cadastrado por qualquer produtor), NENHUMA validação da higienização escolhida é executada — a trava some; (4) a higienização escolhida (155-164) não é verificada quanto a associado_id -> uso cross-tenant de registro de higienização de outro produtor (a FK fk_op_higienizacao, dev-utils/migrate_conformidade.php:119, só garante existência); (5) 'associado_id' vem do POST (108); (6) 'data_processamento' chega no formato HTML5 datetime-local com separador 'T' e é gravado sem normalização (também usado em strtotime em 161) — depende da tolerância do MySQL a 'T' em literais DATETIME; (7) a tela exige 'item_insumo' mas o modelo aceita qualquer insumo_id existente (nenhuma checagem de tenant); (8) a quantidade final não é comparada com a capacidade do lote escolhido, e o lote é gravado apenas como TEXTO (não há lote_id em ordens_producao — dev-utils/migrate_conformidade.php:105); (9) $lotesCampo carregado e não usado (código morto); (10) sem CSRF; (11) sem guard temAgroindustria; (12) mensagens e painel usam 'kg' fixo independentemente da unidade escolhida (317).

### modulos/agroindustria/ordens_visualizar.php
- **Rota/acesso**: GET /agroindustria/ordens/{id} (index.php:986-988). ORDEM DE INCLUDES ANÔMALA: header.php e menu.php são incluídos ANTES de qualquer guard (3-4); '$user' é criado dentro de includes/header.php:5, e o header já redireciona não autenticados (includes/header.php:16-19) — logo o 'if (!$user->isLoggedIn())' em 9-12 é CÓDIGO MORTO.
- **Propósito de negócio**: ficha da ordem de produção: dados, balanço de massas RF11, lotes/insumos processados, QR Code e etiqueta de impressão.
- **Guard de permissão**: 23-31, anti-IDOR: '$donoOp = (int)($op['associado_id'] ?? 0); if (!$user->isGestorOuAdmin() && ($donoOp <= 0 || $donoOp !== (int)$user->getAssociadoIdResolvido()))' -> 'Acesso negado'. Membro da agroindústria (não dono) também é negado.
- **Campos/formulário**: nenhum input; consulta por '?id=' (18) e '?criada=1' (60); botões Nova/Voltar/Imprimir (55-56, 140).
- **Regras/validações/cálculos**: '$itens = getItensRastreabilidade($id)' (33) e '$balanco = calcularBalanco($itens, $op['quantidade_final_obtida'])' (34) com tolerância padrão 10.0 (OrdemProducao.php:64) — o status exibido no badge (46) vem da COLUNA 'status_auditoria' gravada, enquanto o bloco RF11 (100-103) exibe o status RECALCULADO no momento da leitura; os rótulos das quantidades são fixos em 'kg' (89, 93).
- **Tabelas SQL usadas** (OrdemProducao.php): SELECT o.*, r.nome_produto_final, r.selo_autorizado AS selo_receita FROM ordens_producao o JOIN receitas_agroindustria r ON o.receita_id = r.id WHERE o.id = ? AND o.excluido = 0 (32-37); SELECT ri.*, lp.nome_lote, p.nome AS nome_produto, ins.nome AS nome_insumo, ins.lote AS lote_insumo, ins.tipo_insumo, f.nome AS fornecedor, f.cnpj AS fornecedor_cnpj FROM rastreabilidade_insumos_processo ri LEFT JOIN lotes_producao lp ON ri.lote_primario_id = lp.id LEFT JOIN produtos p ON ri.produto_id = p.id LEFT JOIN insumos ins ON ri.insumo_id = ins.id LEFT JOIN fornecedores f ON ins.fornecedor_id = f.id WHERE ri.ordem_producao_id = ? (44-54).
- **Ações/redirects**: nenhuma escrita; a única ação é a impressão client-side.
- **Riscos/gaps**: (1) a tabela rotulada 'Lotes Primários Processados (Campo)' (107-130) lista, na prática, INSUMOS: como ordens_cadastrar.php:128 grava lote_primario_id = null sempre, a coluna 'Lote (Campo)' exibe '—' em todos os registros criados por essa tela; (2) selo exibido sempre com 'badge bg-success' (74) mesmo quando SEM_SELO; (3) QR/etiqueta dependem de CDN externo (151, sem SRI) e a etiqueta é montada por document.write em janela nova (162-214), com valores escapados por htmlspecialchars; (4) balanço recalculado pode divergir do status gravado se a tolerância padrão mudar (34 vs registro em ordens_cadastrar.php:179); (5) exibe 'kg' fixo mesmo com unidade L/UN; (6) a higienização vinculada (higienizacao_previa_id) não é lida nem exibida na tela.

### modulos/agroindustria/higienizacao_listar.php
- **Rota/acesso**: GET /agroindustria/higienizacao (index.php:974-976); menu.php:133. Guard: SOMENTE isLoggedIn (10-13).
- **Propósito de negócio**: listar registros de higienização (RF08) por equipamento/linha, com a regra destacada em 39-41: 'nenhuma ordem de produção pode ser lançada sem higienização concluída do equipamento/linha nas últimas 24 horas'.
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (17-23).
- **Regras/validações/cálculos**: filtro por associado (25); status 'Em andamento' quando data_hora_fim é nulo (66).
- **Tabelas SQL usadas**: SELECT * FROM controle_higienizacao WHERE excluido = 0 [AND associado_id = ?] ORDER BY data_hora_inicio DESC (ControleHigienizacao.php:15-21); UPDATE controle_higienizacao SET excluido = 1 WHERE id = ? (91).
- **Ações/POST/redirects**: exclusão GET -> softDelete (retorno ignorado, 19) + AuditLog 'HIGIENIZACAO_EXCLUIDA' (20) -> redirect.
- **Riscos/gaps**: (1) IDOR destrutivo (17-23); (2) sem CSRF; (3) NÃO EXISTE tela de edição no escopo (apenas _cadastrar e _listar) -> erro de digitação só pode ser resolvido por exclusão, comprometendo a trilha de auditoria; (4) excluir um registro referenciado por ordens_producao.higienizacao_previa_id é permitido (FK sem ON DELETE, dev-utils/migrate_conformidade.php:119) e a ordem passa a apontar para registro invisível.

### modulos/agroindustria/higienizacao_cadastrar.php
- **Rota/acesso**: GET /agroindustria/higienizacao/nova (index.php:977-979); link higienizacao_listar.php:34. Guard: SOMENTE isLoggedIn (10-13).
- **Propósito de negócio**: registrar procedimento de higienização com sanitizante restrito à lista permitida para produção orgânica.
- **Campos/formulário**: 'data_hora_inicio' (datetime-local, required, default agora) 67; 'data_hora_fim' (datetime-local, opcional) 71; 'equipamento_linha' (text, required) 75; 'sanitizante_utilizado' (select, required) 79-86, populado por listarSanitizantesPermitidos() (16-17); 'responsavel_nome' (text, required) 94; 'observacoes' (textarea) 98.
- **Regras/validações/cálculos**: presença de início/equipamento/sanitizante/responsável (34-36); whitelist do sanitizante: '!in_array(trim($dados['sanitizante_utilizado']), $sanitizantesNomes, true)' -> mensagem BLOQUEADO (conformidade) citando Anexo I da Portaria 52/2021 com redação da Portaria SDA 811/2023 (37-39); 'associado_id' = POST || sessão (30); criado_por = $_SESSION['user_id'] (31); NÃO valida se data_hora_fim > data_hora_inicio.
- **Tabelas SQL usadas** (ControleHigienizacao.php):
  - listarSanitizantesPermitidos (60-65): SELECT id, nome, subcategoria, restricoes, normativa FROM insumos_organicos_aprovados WHERE categoria = 'Higienização' AND permitido = 1 ORDER BY nome ASC.
  - create (71-75): INSERT INTO controle_higienizacao (data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, criado_por) VALUES (8 placeholders).
- **Ações/POST/redirects**: create (41) -> AuditLog 'HIGIENIZACAO_REGISTRADA' com o ID retornado (42) -> redirect /agroindustria/higienizacao?registrada=1 (43-44).
- **Riscos/gaps**: (1) dependência dura da base 'insumos_organicos_aprovados': se o ENUM não incluir 'Higienização' (adicionado em sql/seed_insumos_aprovados_animal.sql:18 e sql/seed_insumos_aprovados_processamento.sql:16) ou o seed de sanitizantes não tiver sido executado (sql/seed_insumos_aprovados_animal.sql:149+), a lista vem vazia e TODOS os cadastros são bloqueados em 37-39 — como a criação de OP depende de higienização (ordens_cadastrar.php:149-170), o módulo de produção fica inoperante; (2) o sanitizante é gravado por NOME (VARCHAR(100)) sem FK para insumos_organicos_aprovados (dev-utils/migrate_conformidade.php:93) -> renomear o insumo invalida o histórico; (3) 'responsavel_nome' é texto livre, sem FK para usuarios/associados -> sem responsabilização rastreável; (4) nenhuma coluna de POP/procedimento, concentração, tempo de contato ou temperatura — NÃO ENCONTRADO NO CÓDIGO (schema em dev-utils/migrate_conformidade.php:88-100 e sinporg/prisma/migrations/0_baseline/migration.sql:642-656); (5) 'associado_id' do POST (30); (6) sem CSRF; (7) sem guard temAgroindustria; (8) 'equipamento_linha' é texto livre, sem cadastro de equipamentos/linhas (não há tabela de equipamentos no módulo).

### modulos/agroindustria/rastreabilidade.php
- **Rota/acesso**: /agroindustria/rastreabilidade[...] (index.php:989-991); PÚBLICA: comentário explícito na linha 2 ('Não requer login'); não inclui header/menu. Entrada: '$_GET['lote']' (6).
- **Propósito de negócio**: página pública do consumidor/fiscal (RF12) que, pelo código do lote impresso, apresenta produto final, produtor, percentual orgânico, selo, receita e a origem dos insumos (fornecedor, CNPJ, NF).
- **Campos/formulário**: formulário público de consulta (194-197): 'lote' (text, required) — GET.
- **Regras/validações/cálculos**: ('$lote !== '''') gate (13); se não encontrado e lote vazio mostra o formulário (189-199), se lote informado e não achado mostra 'Lote não localizado' (200-205). Nenhum cálculo próprio: exibe percentual/selo GRAVADOS na ordem.
- **Tabelas SQL usadas**:
  - (14-21): SELECT o.*, r.nome_produto_final, r.descricao AS receita_descricao, a.nome AS associado_nome, a.sobrenome AS associado_sobrenome, a.registro_cnpo FROM ordens_producao o JOIN receitas_agroindustria r ON o.receita_id = r.id LEFT JOIN associados a ON o.associado_id = a.id WHERE o.lote_processado_final = ? AND o.excluido = 0 — com 'fetch()' (23), ou seja, apenas a PRIMEIRA ordem que tiver aquele lote.
  - (26-35): SELECT ri.*, lp.nome_lote, lp.uuid AS lote_uuid, lp.data_producao, p.nome AS nome_produto, ins.nome AS nome_insumo, ins.lote AS lote_insumo, ins.tipo_insumo, f.nome AS fornecedor, f.cnpj AS fornecedor_cnpj FROM rastreabilidade_insumos_processo ri LEFT JOIN lotes_producao lp ... LEFT JOIN insumos ins ... LEFT JOIN fornecedores f ... WHERE ri.ordem_producao_id = ?.
  - (40): SELECT * FROM receitas_agroindustria WHERE id = ? — SEM filtro 'excluido'.
  - (44): SELECT * FROM ingredientes_receita WHERE receita_id = ?.
- **Ações/POST/redirects**: nenhuma escrita; apenas GET e iframe de PDF externo (220-223: /doc/Guia-do-Produtor-Organico-CI-Organicos-SNA-OrganicsNet.pdf).
- **Riscos/gaps**: (1) exposição pública (sem autenticação, sem rate-limit, sem token) de nome do produtor, nome/CNPJ do fornecedor e números de nota fiscal (148-177) — dados pessoais e comerciais enumeráveis; (2) 'lote_processado_final' é texto livre VARCHAR(50) SEM UNIQUE (dev-utils/migrate_conformidade.php:105; sinporg/prisma/migrations/0_baseline/migration.sql:1203) -> dois produtores podem usar o mesmo código e a consulta devolve a primeira ordem, gerando rastreabilidade ERRADA (falha central de RF12); (3) a receita exibida é sempre a ATUAL e sem filtro de exclusão (18, 40), e os ingredientes são apagados/recriados na edição (ReceitaAgroindustria.php:156-158) -> edição retroativa altera o que o consumidor vê; (4) exibe fallback 'g' para 'proporcao_porcentagem' quando 'quantidade' está vazia (128-132), misturando a semântica de peso-base; (5) 'a.registro_cnpo' é selecionado (16) mas NUNCA renderizado — campo CNPO não é exibido na página pública; (6) CDN externo de Bootstrap (67) e link para PDF externo; (7) não há validação/limite de tamanho em 'lote' (6) — apenas comparação parametrizada (sem SQLi).

### modulos/agroindustria/minhas_listar.php
- **Rota/acesso**: GET /agroindustria/minhas (index.php:920-922); menu.php:125. Guard: SOMENTE isLoggedIn (10-13) — coerente, pois é a tela para cadastrar a primeira unidade.
- **Propósito de negócio**: listar as unidades de agroindústria do produtor (ou todas, para gestor).
- **Campos/formulário**: nenhum; 'GET ?excluir=<id>' (20-29).
- **Regras/validações/cálculos**: '$filtroAssociado = $isGestor ? null : getAssociadoId()' (17); EXCLUSÃO COM ANTI-IDOR (23-25): só exclui se '$isGestor || (int)$item['associado_id'] === (int)$filtroAssociado' — o único '_listar' do módulo com essa verificação; cidade/estado montados em 87-90 (usa '$a['estado']' sem ?? no operador ternário).
- **Tabelas SQL usadas** (Agroindustria.php): getAll (19-23) SELECT a.*, CONCAT(ass.nome,' ',COALESCE(ass.sobrenome,'')) AS associado_nome FROM agroindustrias a JOIN associados ass ON a.associado_id = ass.id WHERE a.excluido = 0 [AND a.associado_id = ?] ORDER BY a.nome_fantasia ASC, a.criado_em DESC; getById (37); softDelete: UPDATE agroindustrias SET excluido = 1 WHERE id = ? (248).
- **Ações/POST/redirects**: exclusão -> softDelete + AuditLog 'AGROINDUSTRIA_EXCLUIDA' em 'agroindustrias' (25) -> redirect (27).
- **Riscos/gaps**: (1) getAll filtra por 'a.associado_id = ?' e IGNORA agroindustria_membros -> o MEMBRO de uma unidade compartilhada (Agroindustria.php:53-65, 71-88) não vê essa unidade em 'Minhas Agroindústrias' nem pode gerenciá-la, contradizendo o modelo de membros; (2) softDelete não cascateia (setores/receitas/ordens continuam ativos apontando para a unidade excluída) e não reseta 'associados.tem_agroindustria' (Agroindustria.php:246-250, 252-263) -> o usuário mantém acesso ao módulo sem nenhuma unidade; (3) status 'Inativa' não bloqueia nenhum fluxo do módulo (nenhuma verificação de status em receitas/ordens); (4) exclusão por GET sem CSRF.

### modulos/agroindustria/minhas_cadastrar.php
- **Rota/acesso**: GET /agroindustria/minha/nova (index.php:923-925); links em minhas_listar.php:40,58. Guard: isLoggedIn (12-15). Requer CSRFProtection (7).
- **Propósito de negócio**: cadastrar unidade de agroindústria (com CNPJ, endereço, RT) e marcar o associado como possuidor de agroindústria.
- **Campos/formulário**: 'associado_id' (select, required, SOMENTE gestor) 87-94; 'nome_fantasia' (text, required) 100; 'razao_social' (text) 106; 'cnpj' (text + máscara JS 205-219) 112; 'inscricao_estadual' (text) 118; 'status' (select Ativa/Inativa) 123-126; 'endereco' 133; 'numero' 138; 'bairro' 143; 'cidade' 148; 'estado' (select com 26 UFs hardcoded, 156) 153-161; 'cep' 165; 'telefone' 171; 'email' (type=email) 177; 'responsavel_tecnico' 185; 'observacoes' 191; token CSRF (81).
- **Regras/validações/cálculos**: CSRF obrigatório (24-26); 'associadoId = $isGestor ? (int)$_POST['associado_id'] : (int)$meuAssociado' (28-30) — não gestor NÃO pode forjar o dono; se <=0 -> 'Selecione o associado dono da agroindústria.' (32-34); criado_por = $_SESSION['user_id'] (38); resultado numérico = sucesso (43-47), string = mensagem de erro do model (48-49).
- **Tabelas SQL usadas**: (Agroindustria.php) INSERT INTO agroindustrias (associado_id, razao_social, nome_fantasia, cnpj, inscricao_estadual, telefone, email, responsavel_tecnico, endereco, numero, bairro, cidade, estado, cep, status, observacoes, criado_por) VALUES (17 placeholders) (165-170) após Sanitizer::sanitizeArray (151) e validação de CNPJ (153-156); UPDATE associados SET tem_agroindustria = 1 WHERE id = ? (258); lista de associados para gestor: SELECT id, nome, sobrenome FROM associados WHERE status = 'ativo' ORDER BY nome ASC (58).
- **Validações de CNPJ** (Agroindustria.php:265-306): soDigitos remove tudo que não for dígito via preg_replace (267); validarCnpj (280-306) exige 14 dígitos, rejeita CNPJ com todos os dígitos repetidos (286-288) e confere os dois dígitos verificadores com pesos [5,4,3,2,9,8,7,6,5,4,3,2] / [6,5,4,3,2,9,8,7,6,5,4,3,2] e a regra 'resto < 2 ? 0 : 11 - resto' (298-299); formatarCnpj (270-278) aplica máscara 00.000.000/0000-00.
- **Ações/POST/redirects**: create (41) -> AuditLog 'AGROINDUSTRIA_CADASTRADA' com o ID (44) -> redirect /agroindustria/minhas?criada=1 (45).
- **Riscos/gaps**: (1) CNPJ sem restrição UNIQUE no schema (dev-utils/migrate_agroindustrias.php:41) -> duplicidade de unidades; (2) nenhuma validação de e-mail (formato), telefone, CEP ou UF além das opções do select; (3) 'status' normalizado apenas para 'Inativa'/'Ativa' (Agroindustria.php:186); (4) o gestor pode criar unidade para qualquer associado ativo (por design, 28-30); (5) htmlspecialchars($a['nome'].' '.$a['sobrenome']) em 91 sem ?? -> deprecation PHP 8.1 quando sobrenome é NULL; (6) mensagens de erro do model exibidas com htmlspecialchars (74) — ok.

### modulos/agroindustria/minhas_editar.php
- **Rota/acesso**: GET /agroindustria/minha/{id}/editar (index.php:926-928). Guards: isLoggedIn (12-15); id (17); existência (22-27); anti-IDOR 28-31 ('!$isGestor && (int)$agro['associado_id'] !== (int)$meuAssociado' -> redirect silencioso para /agroindustria/minhas).
- **Propósito de negócio**: editar a unidade e gerenciar os MEMBROS (associados que compartilham insumos/registros — texto 215).
- **Campos/formulário**: form principal 129-202: 'nome_fantasia' (required), 'razao_social', 'cnpj' (+máscara JS 282-297), 'inscricao_estadual', 'status', 'endereco', 'numero', 'bairro', 'cidade', 'estado', 'cep', 'telefone', 'email', 'responsavel_tecnico', 'observacoes' — valores via helper '$old' (101-103, htmlspecialchars com ENT_QUOTES); token CSRF (124). Form de remover membro (235-240): 'acao_membro=remover', 'associado_membro_id' (hidden) + CSRF (236). Form de adicionar membro (254-271): 'acao_membro=adicionar', 'associado_membro_id' (select, required) + CSRF (255).
- **Regras/validações/cálculos**: CSRF em todo POST (40-42); ramo de membros 45-71 (adicionar/remover); ramo de edição 72-85: '$dados['associado_id'] = $agro['associado_id']' (74) força o dono atual (não há transferência), chama update (76), sucesso exige '$resultado === true' (78); lista de candidatos a membro = TODOS os associados ativos (94) menos os já membros (95-99).
- **Tabelas SQL usadas**:
  - Agroindustria.php: getMembros (55-62) SELECT a.id, a.nome, a.sobrenome, a.tipo_usuario FROM agroindustria_membros m JOIN associados a ON m.associado_id = a.id WHERE m.agroindustria_id = ? AND m.excluido = 0 AND a.status = 'ativo' ORDER BY a.nome ASC; adicionarMembro: INSERT IGNORE INTO agroindustria_membros (agroindustria_id, associado_id, criado_por) VALUES (3 placeholders) (121); removerMembro: UPDATE agroindustria_membros SET excluido = 1 WHERE agroindustria_id = ? AND associado_id = ? AND associado_id <> (SELECT associado_id FROM agroindustrias WHERE id = ? AND excluido = 0) (137-139); update da unidade: UPDATE agroindustrias SET razao_social, nome_fantasia, cnpj, inscricao_estadual, telefone, email, responsavel_tecnico, endereco, numero, bairro, cidade, estado, cep, status, observacoes WHERE id = ? AND excluido = 0 (211-217) — NÃO altera associado_id — e em seguida UPDATE associados SET tem_agroindustria = 1 WHERE id = ? usando o associado_id do POST (237, 258).
  - Consulta direta: SELECT id, nome, sobrenome FROM associados WHERE status = 'ativo' ORDER BY nome ASC (94).
- **Ações/POST/redirects**: adicionar membro -> mensagem 'Membro adicionado com sucesso.' (55) e recarga de membros (57); remover -> 'Membro removido.' (64); edição -> AuditLog 'AGROINDUSTRIA_EDITADA' (79) -> redirect /agroindustria/minhas?editada=1 (80); AuditLog de membro adicionado (53) com tabela 'agroindustria_membros' e registro_id = id da AGROINDÚSTRIA (não do vínculo).
- **Riscos/gaps**: (1) Agroindustria::adicionarMembro retorna SEMPRE true — 'return $stmt->rowCount() > 0 || true;' (Agroindustria.php:123) -> a tela informa sucesso mesmo quando o INSERT IGNORE não inseriu nada (duplicado, FK inválida etc.); (2) removerMembro retorna o resultado de execute(), que é true mesmo com 0 linhas afetadas -> 'Membro removido.' é exibido sem remoção real, inclusive no caso da subquery retornar NULL (agroindústria excluída) onde 'associado_id <> NULL' é NULL; (3) a lista de candidatos (94) expõe NOMES DE TODOS OS ASSOCIADOS ATIVOS do sistema a qualquer produtor com agroindústria e permite vinculá-los como membros da própria unidade (46-51) — a tabela agroindustria_membros NÃO tem FK em associado_id (dev-utils/migrate_agro_membros.php:36-46), permitindo vínculo arbitrário/orfandade; (4) o mesmo formulário não valida se o associado alvo está ativo (POST não é conferido contra a lista); (5) $agroClass->getById($id) é chamado de novo dentro do POST (45) — consulta redundante; (6) a remoção de membro não registra log (só a adição, 53).

## Síntese do grupo — modulos/agroindustria

**Fluxos ponta-a-ponta**

1. Unidade: /agroindustria/minhas (minhas_listar.php:31) -> nova/editar (minhas_cadastrar.php:41 / minhas_editar.php:76) -> 'agroindustrias' + 'agroindustria_membros' (proprietário auto-vinculado pelo INSERT IGNORE e pelo backfill de dev-utils/migrate_agro_membros.php:52-62); 'associados.tem_agroindustria' é marcado em Agroindustria.php:258. O cadastro é o ÚNICO ponto do módulo protegido por CSRF.
2. Estrutura produtiva: setores (setores_*.php) -> categorias (categorias_*.php) -> atividades diárias (atividades_*.php), que referenciam 'agro_setores.id' e 'agro_categorias_atividades.id'; ocorrências referenciam 'agro_setores.id'. Todas as quatro tabelas foram criadas SEM FOREIGN KEY (dev-utils/migrate_agro_producao.php:65-76, 87-97, 122-143, 154-171) — a integridade é apenas de aplicação e não é verificada em nenhuma tela.
3. Receita -> percentual/selo: receitas_cadastrar.php:60 -> ReceitaAgroindustria::create (109-127) calcula percentual (calcularPercentualOrganico:65-81) e selo (classificarSelo:89-99) a partir das quantidades convertidas (fatorConversao:47-59) e persiste 'receitas_agroindustria' + 'ingredientes_receita'. A edição (receitas_editar.php:80) recalcula e APAGA/RECRIA os ingredientes (156-158).
4. Higienização -> ordem: higienizacao_cadastrar.php:41 grava 'controle_higienizacao' com sanitizante validado contra 'insumos_organicos_aprovados' (categoria='Higienização', permitido=1); ordens_cadastrar.php:28-36 lista as concluídas <24h; a trava RF08 é aplicada em ordens_cadastrar.php:144-170 (não no model).
5. Ordem de produção -> rastreabilidade: ordens_cadastrar.php:177 -> OrdemProducao::create (108-145) grava 'ordens_producao' (snapshot de percentual/selo da receita em ordens_cadastrar.php:175-176) + N linhas em 'rastreabilidade_insumos_processo'; o balanço é calculado por OrdemProducao::calcularBalanco (64-94) e o status fica em 'ordens_producao.status_auditoria'; ordens_visualizar.php exibe e RECALCULA o balanço (34) e gera QR/link (48) para a página pública.
6. Página pública: rastreabilidade.php resolve a ordem pelo TEXTO do lote (14-23) e mostra receita atual (40-46) e origem dos insumos (26-38) — sem login.

**Tabela consolidada de guards por arquivo**

| Arquivo | Guard de sessão | Guard de módulo (temAgroindustria) | Guard de dono | CSRF |
|---|---|---|---|---|
| setores_listar.php | 10-13 | 15-19 | NÃO (exclusão 23-30) | não |
| setores_cadastrar.php | 12-15 | 17-21 | só gestor/não gestor na lista | não |
| setores_editar.php | 11-14 | 16-20 | SIM (39-43) | não |
| categorias_listar.php | 10-13 | 15-19 | NÃO (exclusão 23-30) | não |
| categorias_cadastrar.php | 10-13 | 15-19 | força associado da sessão (32) | não |
| categorias_editar.php | 10-13 | 15-19 | SIM (37-43) | não |
| atividades_listar.php | 10-13 | 15-19 | NÃO (exclusão 23-30) | não |
| atividades_cadastrar.php | 12-15 | 17-21 | não (associado_id do POST, 42) | não |
| atividades_editar.php | 12-15 | 17-21 | SIM (39-43) | não |
| ocorrencias_listar.php | 10-13 | 15-19 | NÃO (exclusão 23-30) | não |
| ocorrencias_cadastrar.php | 11-14 | 16-20 | não (associado_id do POST, 39) | não |
| ocorrencias_editar.php | 11-14 | 16-20 | SIM (38-42) | não |
| receitas_listar.php | 10-13 | NÃO | NÃO (exclusão 18-25) | não |
| receitas_cadastrar.php | 11-14 | NÃO | não (associado_id do POST, 57) | não |
| receitas_editar.php | 11-14 | NÃO | NÃO (IDOR de escrita) | não |
| ordens_listar.php | 10-13 | NÃO | NÃO (exclusão 17-23) | não |
| ordens_cadastrar.php | 14-17 | NÃO | não (associado_id do POST, 108) | não |
| ordens_visualizar.php | header.php:16-19 (o 'if' local em 9-12 é morto) | NÃO | SIM (23-31) | n/a |
| higienizacao_listar.php | 10-13 | NÃO | NÃO (exclusão 17-23) | não |
| higienizacao_cadastrar.php | 10-13 | NÃO | não (associado_id do POST, 30) | não |
| rastreabilidade.php | nenhum (pública, 2) | n/a | n/a | n/a |
| minhas_listar.php | 10-13 | n/a (tela de cadastro) | SIM na exclusão (23) | não |
| minhas_cadastrar.php | 12-15 | n/a | SIM (força o próprio associado, 28-30) | SIM (24) |
| minhas_editar.php | 12-15 | n/a | SIM (28-31) | SIM (40) |

**Tabelas do módulo referenciadas pelos arquivos deste diretório**: agroindustrias, agroindustria_membros, associados, agro_setores, agro_categorias_atividades, agro_atividades_diarias, agro_ocorrencias, receitas_agroindustria, ingredientes_receita, ordens_producao, rastreabilidade_insumos_processo, controle_higienizacao, insumos_organicos_aprovados, insumos, fornecedores, lotes_producao, produtos, usuarios (via $_SESSION apenas).

---

# 2. classes/

### classes/Agroindustria.php
- **Rota/acesso**: não roteável; instanciada por minhas_listar/cadastrar/editar, setores_cadastrar/editar e ordens_cadastrar (6, 24, 29, 69). Requires Database e Sanitizer (2-3).
- **Propósito de negócio**: CRUD da unidade de agroindústria, vínculo de membros e consultas de compartilhamento de insumos entre membros.
- **Campos/formulário**: não possui tela; consome $_POST via 'create'/'update' (149-244).
- **Regras/validações/cálculos**: sanitização de todo o array (151, 203); CNPJ -> dígitos + validarCnpj e mensagem 'CNPJ inválido.' (153-156, 205-208); associado obrigatório '>0' no create (158-161); status normalizado 'Inativa'/'Ativa' (186, 232); após criar/atualizar marca 'associados.tem_agroindustria = 1' (192, 237, 258); retorno heterogêneo (int id | string erro | true em update, 147, 194, 197, 239, 242).
- **Tabelas SQL usadas**:
  - getAll (19-23): SELECT a.*, CONCAT(ass.nome,' ',COALESCE(ass.sobrenome,'')) AS associado_nome FROM agroindustrias a JOIN associados ass ON a.associado_id = ass.id WHERE a.excluido = 0 [AND a.associado_id = ?] ORDER BY a.nome_fantasia ASC, a.criado_em DESC.
  - getById (37): SELECT * FROM agroindustrias WHERE id = ? AND excluido = 0.
  - countByAssociado (44): SELECT COUNT(*) FROM agroindustrias WHERE associado_id = ? AND excluido = 0.
  - getMembros (56-61): SELECT a.id, a.nome, a.sobrenome, a.tipo_usuario FROM agroindustria_membros m JOIN associados a ON m.associado_id = a.id WHERE m.agroindustria_id = ? AND m.excluido = 0 AND a.status = 'ativo' ORDER BY a.nome ASC.
  - getAgroindustriasPorAssociado (76-84): SELECT DISTINCT a.id FROM agroindustrias a WHERE a.excluido = 0 AND ((a.associado_id = ? AND a.associado_id IS NOT NULL) OR EXISTS (SELECT 1 FROM agroindustria_membros m WHERE m.agroindustria_id = a.id AND m.associado_id = ? AND m.excluido = 0)).
  - getAssociadosDaMesmaAgroindustria (104-108): SELECT DISTINCT associado_id FROM agroindustria_membros WHERE excluido = 0 AND agroindustria_id IN ($in) — '$in' montado por implode de array_map('intval', $agros) (104).
  - adicionarMembro (121): INSERT IGNORE INTO agroindustria_membros (agroindustria_id, associado_id, criado_por) VALUES (3 placeholders).
  - removerMembro (137-139): UPDATE agroindustria_membros SET excluido = 1 WHERE agroindustria_id = ? AND associado_id = ? AND associado_id <> (SELECT associado_id FROM agroindustrias WHERE id = ? AND excluido = 0).
  - create (165-170) e update (211-217) conforme 1.x; softDelete (248): UPDATE agroindustrias SET excluido = 1 WHERE id = ?; marcarAssociadoComAgroindustria (258): UPDATE associados SET tem_agroindustria = 1 WHERE id = ?.
- **Ações/redirects**: nenhuma (camada de dados).
- **Riscos/gaps**: (1) concatenação de SQL em 104-107 (mitigada por intval, mas é o padrão de risco do projeto); (2) 'adicionarMembro' sempre retorna true (123) — falha silenciosa; (3) 'removerMembro' sem checagem de linhas afetadas (141) — falso sucesso; (4) 'update' não altera associado_id (212-216) mas marca tem_agroindustria com base no POST (237) — se chamado fora de minhas_editar (que força o valor em 74), marca o associado errado; (5) 'softDelete' sem cascata e sem desmarcar tem_agroindustria (246-263); (6) sem UNIQUE em cnpj (dev-utils/migrate_agroindustrias.php:41) e sem validação de e-mail; (7) getAgroindustriasPorAssociado/getAssociadosDaMesmaAgroindustria (usados por ordens_cadastrar.php:70) são a única base do compartilhamento de insumos entre membros — e o vínculo de membro é auto-serviço (minhas_editar.php:94-99, 46-51), o que permite ampliar o acesso a insumos de terceiros vinculando-se como membro.

### classes/AgroSetor.php
- **Rota/acesso**: instanciada por setores_listar/cadastrar/editar, atividades_cadastrar/editar, ocorrencias_cadastrar/editar e por modulos/caderno_campo/produtos/cadastrar.php:46 e editar.php:65.
- **Propósito de negócio**: CRUD de setores da agroindústria e resolução dos setores acessíveis ao associado (dono ou membro).
- **Campos/formulário**: uuid, associado_id, agroindustria_id, nome, descricao (create 76-83).
- **Regras/validações/cálculos**: uuid gerado se ausente (72-74) com mt_rand (119-127); 'agroindustria_id' vazio => NULL (81, 97); nenhuma validação de nome/unicidade/posse.
- **Tabelas SQL usadas**:
  - getAll (14-22): SELECT * FROM agro_setores WHERE excluido = 0 [AND associado_id = ?] ORDER BY nome ASC.
  - getById (32): SELECT * FROM agro_setores WHERE id = :id AND excluido = 0.
  - getSetoresDoAssociado (49-59): SELECT s.id, s.nome, s.descricao, s.agroindustria_id, COALESCE(NULLIF(ag.nome_fantasia,''), NULLIF(ag.razao_social,''), CONCAT('Agroindústria #', ag.id)) AS agroindustria_nome FROM agro_setores s JOIN agroindustrias ag ON s.agroindustria_id = ag.id AND ag.excluido = 0 WHERE s.excluido = 0 AND (ag.associado_id = :assoc OR EXISTS (SELECT 1 FROM agroindustria_membros am WHERE am.agroindustria_id = ag.id AND am.associado_id = :assoc AND am.excluido = 0)) ORDER BY ag.id ASC, s.nome ASC.
  - create (76-77): INSERT INTO agro_setores (uuid, associado_id, agroindustria_id, nome, descricao, criado_em) VALUES (:uuid,:associado_id,:agroindustria_id,:nome,:descricao,NOW()).
  - update (95): UPDATE agro_setores SET agroindustria_id = :agroindustria_id, nome = :nome, descricao = :descricao WHERE id = :id AND excluido = 0.
  - softDelete (110): UPDATE agro_setores SET excluido = 1 WHERE id = :id.
- **Riscos/gaps**: (1) a query de getSetoresDoAssociado repete ':assoc' (55-57) — só funciona porque o PDO do projeto não desabilita prepared emulados (classes/Database.php:10-19 não define ATTR_EMULATE_PREPARES); se alguém setar 'false', todas as chamadas quebram com HY093; (2) 'agro_setores' sem NENHUMA FK (dev-utils/migrate_agro_producao.php:65-76) -> associado_id/agroindustria_id podem referenciar registros inexistentes e/ou de outro tenant; (3) create grava associado_id = 0 se ausente (80) apesar de a coluna ser NOT NULL (68) — setor órfão; (4) todos os métodos engolem PDOException retornando [] ou false (24-27, 36-39, 63-66, 85-88, 102-105, 113-116), transformando erro de banco em 'lista vazia'/'falha genérica'; (5) bindParam sem null-coalesce em 82-83 e 98-99 (chaves ausentes => warning PHP 8 e possível violação de NOT NULL em 'nome').

### classes/AgroCategoriaAtividade.php
- **Propósito de negócio**: CRUD das categorias de atividade da agroindústria (categorias padrão globais criadas pelo seed dev-utils/migrate_agro_producao.php:100-112).
- **Campos/formulário**: uuid, associado_id (nullable), nome, descricao, cor.
- **Tabelas SQL usadas**: SELECT id, nome, descricao, cor FROM agro_categorias_atividades WHERE excluido = 0 ORDER BY nome ASC (14) — SEM filtro de associado; SELECT * ... WHERE id = :id AND excluido = 0 (25); INSERT INTO agro_categorias_atividades (uuid, associado_id, nome, descricao, cor, criado_em) VALUES (..., NOW()) (42-43); UPDATE ... SET nome, descricao, cor WHERE id AND excluido = 0 (61) — não altera associado_id/uuid; UPDATE ... SET excluido = 1 (76).
- **Regras/validações/cálculos**: nenhuma (sem validação de cor hex, sem unicidade, sem checagem de tenant no SQL); uuid mt_rand (85-93).
- **Riscos/gaps**: getAll global (14) enquanto as categorias criadas recebem dono (46) -> o modelo de tenancy é incoerente: a listagem (categorias_listar.php:32) e o select de atividades (atividades_cadastrar.php:31) expõem categorias de outros produtores, e a exclusão (categorias_listar.php:23-30) não é protegida; catálogo de cores sem validação ('cor' VARCHAR(7), dev-utils/migrate_agro_producao.php:93); exceções silenciadas (17-20, 29-32, 51-54, 68-71, 79-82).

### classes/AgroAtividadeDiaria.php
- **Propósito de negócio**: CRUD das atividades diárias da agroindústria (setor + categoria + tipo + insumo em texto + horários).
- **Campos/formulário**: uuid, associado_id, setor_id, categoria_id, nome, data_atividade, hora_inicio, hora_fim, descricao, status, tipo_atividade, insumo_utilizado, observacoes.
- **Tabelas SQL usadas**: (14-18) join com agro_setores e agro_categorias_atividades conforme 1.x; getById (36) SELECT * ... WHERE id = :id AND excluido = 0; create (53-58) INSERT INTO agro_atividades_diarias (uuid, associado_id, setor_id, categoria_id, nome, data_atividade, hora_inicio, hora_fim, descricao, status, tipo_atividade, insumo_utilizado, observacoes, criado_em) VALUES (..., NOW()); update (84-89) UPDATE ... WHERE id = :id AND excluido = 0; softDelete (112) UPDATE ... SET excluido = 1.
- **Regras/validações/cálculos**: nenhuma validação de intervalo de horas, de data ou de posse de setor_id/categoria_id; 'hora_inicio'/'hora_fim'/'tipo_atividade'/'insumo_utilizado'/'observacoes' viram NULL quando vazios (66-72, 95-101).
- **Riscos/gaps**: unidade de tempo/insumo não rastreável ('insumo_utilizado' VARCHAR(255), dev-utils/migrate_agro_producao.php:135); joins sem filtro de 'excluido'; binds sem ?? em 64-72 e 93-101; exceções silenciadas (28-31, 40-43, 74-77, 104-107).

### classes/AgroOcorrencia.php
- **Propósito de negócio**: CRUD das ocorrências da agroindústria com severidade e ação de manejo.
- **Campos/formulário**: uuid, associado_id, setor_id, tipo, titulo, data_ocorrencia, severidade ('Baixa','Média','Alta'), descricao, acao_manejo, status.
- **Tabelas SQL usadas**: getAll (14-17) SELECT o.*, s.nome AS setor_nome FROM agro_ocorrencias o LEFT JOIN agro_setores s ON o.setor_id = s.id WHERE o.excluido = 0 [AND o.associado_id = ?] ORDER BY o.data_ocorrencia DESC, o.criado_em DESC; getById (35); create (52-55) INSERT INTO agro_ocorrencias (uuid, associado_id, setor_id, tipo, titulo, data_ocorrencia, severidade, descricao, acao_manejo, status, criado_em) VALUES (..., NOW()); update (78-81); softDelete (101).
- **Regras/validações/cálculos**: nenhuma validação de enum no PHP (severidade/status vão direto para o banco; erros de ENUM viram 'false' genérico).
- **Riscos/gaps**: não existe vínculo com ordens de produção/lotes (a tabela não possui ordem_id/produto_id/lote_id — dev-utils/migrate_agro_producao.php:154-171), o que impede relacionar uma não conformidade ao lote afetado (requisito prático de rastreabilidade); joins sem filtro 'excluido'; binds sem ?? em 60-66; exceções silenciadas.

### classes/ReceitaAgroindustria.php
- **Propósito de negócio**: CRUD de receitas e ingredientes + cálculo do percentual orgânico e do selo (RF07/RF09).
- **Campos/formulário**: nome_produto_final, descricao, percentual_organico_calculado, selo_autorizado, associado_id, status, criado_por (109-111); ingredientes: nome_ingrediente, eh_organico, quantidade, unidade_medida, proporcao_porcentagem (peso em g/mL), observacoes (171-172).
- **Regras/validações/cálculos** (fórmulas literais):
  - fatorConversao (47-59): kg=1000 | mg=0.001 | t=1000000 | l=1000 | ml=1 | un/und=1 | default=1.
  - calcularPercentualOrganico (65-81): peso_i = quantidade_i * fator(unidade_i); percentual = round(sum(peso dos orgânicos)/sum(peso total)*100, 2); 0.0 se soma <= 0.
  - classificarSelo (89-99): >=95 PRODUTO_ORGANICO_BRASIL; >=70 INGREDIENTES_ORGANICOS; <70 SEM_SELO.
  - salvarIngredientes (168-192): descarta nome vazio ou peso <= 0; grava 'proporcao_porcentagem' = peso convertido (188).
- **Tabelas SQL usadas**: getAll (15-18) com subquery COUNT(*) FROM ingredientes_receita (SEM filtro de receita/excluido na subquery); getById (32); getIngredientes (39); INSERT receitas_agroindustria (109-112); UPDATE receitas_agroindustria ... WHERE id = ? (141-144, sem excluido); DELETE FROM ingredientes_receita WHERE receita_id = ? (156); INSERT ingredientes_receita (171-173); UPDATE receitas_agroindustria SET excluido = 1 (196). Transações: beginTransaction 106/138, commit 125/160, rollBack 128/163.
- **Riscos/gaps**: (1) NÃO usa Sanitizer — entrada crua do POST em nome/descrição/observações; a segurança depende exclusivamente do htmlspecialchars das telas; (2) update apaga e recria ingredientes (156-158) -> perda de histórico e alteração retroativa da rastreabilidade pública; (3) o UPDATE de receita não filtra 'excluido' (144) -> edição de registro excluído; (4) 'associado_id' aceito do chamador (118, 151) — as telas repassam o POST; (5) fatorConversao trata 'un' como 1 g (55-56) e mistura volume com massa 1:1 — distorce o percentual; (6) create assume status 'Ativa' (119) contra DEFAULT 'Rascunho' do schema (dev-utils/migrate_conformidade.php:73); (7) FK existente: 'fk_ing_receita' com ON DELETE CASCADE (dev-utils/migrate_conformidade.php:86; baseline migration.sql:941) — mas o soft delete de receitas NÃO apaga ingredientes, então ingredientes de receitas excluídas continuam acessíveis pela página pública (rastreabilidade.php:44); (8) sem limite de número de ingredientes.

### classes/OrdemProducao.php
- **Propósito de negócio**: CRUD parcial da ordem de produção, carregamento da rastreabilidade de insumos e cálculo do BALANÇO DE MASSAS (RF11).
- **Campos/formulário**: receita_id, higienizacao_previa_id, lote_processado_final (texto), data_processamento, quantidade_final_obtida, unidade_medida, status_auditoria, percentual_organico, selo_autorizado, associado_id, observacoes, criado_por (108-111); itens: ordem_producao_id, lote_primario_id, produto_id, insumo_id, numero_nf, quantidade_utilizada, unidade_medida (131-132).
- **Regras/validações/cálculos** (fórmulas literais de calcularBalanco, 64-94):
  - totalEntrada = soma de (float)$item['quantidade_utilizada'] de TODOS os itens (66-69).
  - se totalEntrada <= 0 -> status 'EM_ANALISE', divergencia_pct 0, mensagem 'Nenhum lote primário informado para o balanço.' (72-80).
  - divergenciaPct = round((abs(totalEntrada - quantidadeFinal) / totalEntrada) * 100, 2) (82).
  - status = divergenciaPct > toleranciaPct (default 10.0) ? 'ALERTA_FRAUDE' : 'APROVADO' (83).
  - mensagens (90-92) com interpolação PHP: 'Divergência de {${divergenciaPct}}% entre insumos processados ({${totalEntrada}} kg) e produto final ({${quantidadeFinal}} kg). Verificar auditoria.' / 'Balanço de massas dentro da tolerância ({${divergenciaPct}}%).' — unidade 'kg' fixa.
- **Tabelas SQL usadas**: getAll (15-18) SELECT o.*, r.nome_produto_final FROM ordens_producao o JOIN receitas_agroindustria r ON o.receita_id = r.id WHERE o.excluido = 0 [AND o.associado_id = ?] ORDER BY o.data_processamento DESC; getById (32-37) acrescenta r.selo_autorizado AS selo_receita; getItensRastreabilidade (44-54) com LEFT JOINs em lotes_producao, produtos, insumos, fornecedores (fornecedor vem de 'ins.fornecedor_id'); create (108-127) INSERT ordens_producao (12 colunas); (131-133) INSERT rastreabilidade_insumos_processo (7 colunas); softDelete (157) UPDATE ordens_producao SET excluido = 1 WHERE id = ?. Transação 105/147/150.
- **Cardinalidades/FKs**: ordens_producao.receita_id -> receitas_agroindustria.id (fk_op_receita, SEM ON DELETE); ordens_producao.higienizacao_previa_id -> controle_higienizacao.id (fk_op_higienizacao, SEM ON DELETE); rastreabilidade_insumos_processo.ordem_producao_id -> ordens_producao.id (fk_rast_op, ON DELETE CASCADE) — todas em dev-utils/migrate_conformidade.php:117-129 e replicadas em sinporg/prisma/migrations/0_baseline/migration.sql:1216-1218 (apenas índices, sem cláusulas de FK no baseline).
- **Riscos/gaps**: (1) o docblock de create (96-100) afirma 'Bloqueia se não houver higienização válida nas últimas 24h (RF08)' — NÃO IMPLEMENTADO: create (101-153) não faz nenhuma verificação de higienização, a trava existe só na tela (ordens_cadastrar.php:144-170) e é contornável chamando o model ou explorando o ramo morto de 'Qualquer linha'; (2) o balanço soma quantidades de unidades heterogêneas (KG, L, UN conforme 'unidade_medida' do item, default 'KG' em 120 e 143) sem normalização; (3) usa abs() (82) — ganho de massa (produzir mais do que entrou) não é distinguido de perda, apenas a magnitude; (4) 'totalEntrada <= 0' (72-80) é inalcançável pela tela, que exige item com quantidade > 0 (ordens_cadastrar.php:140-142) — ramo morto; (5) create não valida receita_id > 0 (115) -> violação de FK propagada como exception; (6) NÃO há baixa de estoque: nenhum UPDATE em 'insumos.quantidade_estoque' nem em 'lotes_producao.quantidade' -> o consumo é apenas declarativo; (7) não existe coluna de custo/valor em nenhuma das tabelas do fluxo (dev-utils/migrate_conformidade.php:101-130) — cálculo de custo NÃO ENCONTRADO NO CÓDIGO; (8) não há campo de perdas/rendimento: a única métrica é divergencia_pct (82); (9) o lote final é gravado como STRING (117) sem FK para lotes_producao -> a rastreabilidade depende de igualdade textual (rastreabilidade.php:20) e não há UNIQUE no código do lote; (10) 'lote_primario_id'/'produto_id' gravados como null pela tela (ordens_cadastrar.php:128-129) tornam as colunas e os LEFT JOINs de lotes_producao/produtos inúteis nessa prática — RF10 (link campo-agroindústria, modulos/TODO_Agroind.md:64) NÃO implementado de fato; (11) tolerância 10.0 duplicada no JS (ordens_cadastrar.php:371-378).

### classes/ControleHigienizacao.php
- **Propósito de negócio**: CRUD dos registros de higienização (RF08) e resolução da lista oficial de sanitizantes permitidos em sistemas orgânicos.
- **Campos/formulário**: data_hora_inicio, data_hora_fim (nullable), equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, criado_por (72-74).
- **Regras/validações/cálculos**: haHigienizacaoValida($dataProcessamento, $equipamentoLinha, $associadoId, $intervaloHoras = 24) (38-50): conta registros 'excluido = 0 AND data_hora_fim IS NOT NULL AND equipamento_linha = ? AND data_hora_fim <= ? AND data_hora_fim >= DATE_SUB(?, INTERVAL ? HOUR)' e retorna COUNT(*) > 0 (49). O parâmetro $associadoId NÃO é usado na query.
- **Tabelas SQL usadas**: getAll (15) SELECT * FROM controle_higienizacao WHERE excluido = 0 [AND associado_id = ?] ORDER BY data_hora_inicio DESC; getById (29); haHigienizacaoValida (41-46); listarSanitizantesPermitidos (61-64) SELECT id, nome, subcategoria, restricoes, normativa FROM insumos_organicos_aprovados WHERE categoria = 'Higienização' AND permitido = 1 ORDER BY nome ASC; create (72-74) INSERT INTO controle_higienizacao (data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, criado_por) VALUES (8 placeholders); softDelete (91) UPDATE controle_higienizacao SET excluido = 1 WHERE id = ?.
- **Ações/redirects**: nenhuma (camada de dados).
- **Riscos/gaps**: (1) O FILTRO POR PRODUTOR EM haHigienizacaoValida NÃO EXISTE — $associadoId é declarado (38) e ignorado (41-48): a validação (se fosse usada com um equipamento real) aceitaria a higienização de QUALQUER produtor; (2) a comparação literal de 'equipamento_linha' combinada com a chamada de ordens_cadastrar.php:149 ('Qualquer linha') faz a validação retornar sempre falso para equipamentos reais — a trava RF08 efetivamente nunca é satisfeita por esse ramo; (3) create não valida data_hora_fim > data_hora_inicio (76-85) -> durações negativas/bloqueios espúrios; (4) trim em chaves que podem não existir (79-81) -> warning PHP 8 se o model for chamado sem essas chaves; (5) 'sanitizante_utilizado' é o NOME do insumo (VARCHAR 100) sem FK -> renomeação/exclusão do insumo invalida o histórico e a whitelist; (6) a lista de sanitizantes depende de 'insumos_organicos_aprovados' com ENUM contendo 'Higienização' (sql/seed_insumos_aprovados_animal.sql:18; sql/seed_insumos_aprovados_processamento.sql:16) e do seed de linhas (sql/seed_insumos_aprovados_animal.sql:149+); sem isso o cadastro fica totalmente bloqueado (higienizacao_cadastrar.php:37-39); (7) POP/produto químico normalizado/concentração/tempo de contato/temperatura/EPI: NÃO ENCONTRADO NO CÓDIGO nas colunas da tabela (dev-utils/migrate_conformidade.php:88-100; sinporg/prisma/migrations/0_baseline/migration.sql:642-656 — só observacoes TEXT); (8) não há verificação de vínculo entre 'equipamento_linha' e qualquer cadastro de equipamentos (inexistente no módulo).

## Síntese do grupo — classes/

**Responsabilidades e acoplamento**
- Camada de dados sem ORM: cada classe faz 'new PDO' via singleton Database::getInstance()->getConnection() (padrão idêntico em todas as 8 classes, ex.: Agroindustria.php:9-12, OrdemProducao.php:8-11).
- Dois estilos de tratamento de erro: (a) try/catch que engole PDOException e retorna [] / false (AgroSetor, AgroCategoriaAtividade, AgroAtividadeDiaria, AgroOcorrencia; Agroindustria apenas em membros/CNPJ); (b) exceções propagadas e tratadas na tela (ReceitaAgroindustria, OrdemProducao, ControleHigienizacao). O estilo (a) transforma falha de banco em 'lista vazia'/'erro genérico' e esconde diagnósticos.
- Cinco classes usam Sanitizer (Agroindustria:151,203; AgroSetor:71,93; AgroCategoriaAtividade:37,59; AgroAtividadeDiaria:48,82; AgroOcorrencia:47,76) e três NÃO usam (ReceitaAgroindustria, OrdemProducao, ControleHigienizacao) — assimetria de defesa.
- Somente 3 classes usam transação: ReceitaAgroindustria (106/125/128, 138/160/163) e OrdemProducao (105/147/150).

**Cálculos consolidados (únicos existentes no módulo)**
| Cálculo | Fórmula literal | Arquivo:linhas |
|---|---|---|
| Fator de conversão (base massas/volume) | kg=1000, mg=0.001, t=1000000, l=1000, ml=1, un/und=1, default=1 | classes/ReceitaAgroindustria.php:47-59 |
| Percentual orgânico | round((soma das quantidades orgânicas convertidas / soma das quantidades convertidas) * 100, 2); 0 se soma <= 0 | classes/ReceitaAgroindustria.php:65-81 |
| Selo (RF09) | >=95 PRODUTO_ORGANICO_BRASIL; >=70 INGREDIENTES_ORGANICOS; senão SEM_SELO | classes/ReceitaAgroindustria.php:89-99 |
| Peso de ingrediente persistido | peso = quantidade * fator(unidade) gravado em 'proporcao_porcentagem' | classes/ReceitaAgroindustria.php:178-188 |
| Balanço de massas (RF11) | divergência% = round(abs(soma quantidade_utilizada - quantidade_final) / soma quantidade_utilizada * 100, 2); status >10% => ALERTA_FRAUDE | classes/OrdemProducao.php:64-94 |
| Balanço no cliente (espelho) | Math.abs(entrada - saida)/entrada*100 > 10 => 'ALERTA FRAUDE' | modulos/agroindustria/ordens_cadastrar.php:361-383 |
| Validação CNPJ | 14 dígitos + 2 DV com pesos [5,4,3,2,9,8,7,6,5,4,3,2]/[6,5,4,3,2,9,8,7,6,5,4,3,2], resto<2=>0 senão 11-resto | classes/Agroindustria.php:280-306 |
| Higienização válida (RF08) | COUNT(*) > 0 com equipamento_linha = ?, fim <= data e fim >= DATE_SUB(data, INTERVAL 24 HOUR) | classes/ControleHigienizacao.php:38-50 |
| Rendimento / perdas / custo | NÃO ENCONTRADO NO CÓDIGO (nenhuma ocorrência de custo/rendimento/perda nos modulos/agroindustria nem em OrdemProducao.php) | — |

**Dicionário consolidado das tabelas do módulo** (colunas confirmadas nos DDLs de dev-utils/migrate_conformidade.php:64-131, dev-utils/migrate_agroindustrias.php:34-68, dev-utils/migrate_agro_membros.php:36-46, dev-utils/migrate_agro_producao.php:65-171 e sinporg/prisma/migrations/0_baseline/migration.sql:642-656, 931-943, 1199-1219, 1421-1449)

| Tabela | Colunas principais | Chaves/Índices | FK existentes |
|---|---|---|---|
| agroindustrias | id, associado_id, razao_social, nome_fantasia, cnpj, inscricao_estadual, telefone, email, responsavel_tecnico, endereco, numero, bairro, cidade, estado, cep, status ENUM(Ativa,Inativa), observacoes, excluido, criado_por, criado_em, atualizado_em | PK id; INDEX associado_id, status | FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE (migrate_agroindustrias.php:65) |
| agroindustria_membros | id, agroindustria_id, associado_id, excluido, criado_por, criado_em | UNIQUE (agroindustria_id, associado_id); INDEX associado_id | FK agroindustria_id -> agroindustrias(id) ON DELETE CASCADE; SEM FK em associado_id (migrate_agro_membros.php:36-46) |
| agro_setores | id, uuid, associado_id, agroindustria_id, nome, descricao, excluido, criado_em | INDEX associado_id, agroindustria_id | NENHUMA |
| agro_categorias_atividades | id, uuid, associado_id (NULL), nome, descricao, cor, excluido, criado_em | INDEX associado_id | NENHUMA |
| agro_atividades_diarias | id, uuid, associado_id, setor_id, categoria_id, nome, data_atividade, hora_inicio, hora_fim, descricao, status, tipo_atividade, insumo_utilizado, observacoes, excluido, criado_em | INDEX associado_id, setor_id, categoria_id, data_atividade | NENHUMA |
| agro_ocorrencias | id, uuid, associado_id, setor_id, tipo, titulo, data_ocorrencia, severidade ENUM(Baixa,Média,Alta), descricao, acao_manejo, status, excluido, criado_em | INDEX associado_id, setor_id, data_ocorrencia | NENHUMA |
| receitas_agroindustria | id, nome_produto_final, descricao, percentual_organico_calculado DECIMAL(5,2), selo_autorizado ENUM(PRODUTO_ORGANICO_BRASIL,INGREDIENTES_ORGANICOS,SEM_SELO), associado_id, status ENUM(Rascunho,Ativa,Inativa), excluido, criado_por, criado_em | PK id | NENHUMA (migrate_conformidade.php:66-77) |
| ingredientes_receita | id, receita_id, nome_ingrediente, eh_organico, quantidade DECIMAL(10,3) NULL, unidade_medida VARCHAR(10) DEFAULT 'kg', proporcao_porcentagem DECIMAL(12,3) (peso em g/mL), observacoes | INDEX receita_id | FK receita_id -> receitas_agroindustria(id) ON DELETE CASCADE (migrate_conformidade.php:86; migrate_receita_quantidade.php:12-17 alterou quantidade/unidade/proporcao) |
| controle_higienizacao | id, data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, excluido, criado_por, criado_em | PK id | NENHUMA |
| ordens_producao | id, receita_id, higienizacao_previa_id, lote_processado_final VARCHAR(50), data_processamento, quantidade_final_obtida DECIMAL(10,2), unidade_medida, status_auditoria ENUM(APROVADO,ALERTA_FRAUDE,EM_ANALISE), percentual_organico DECIMAL(5,2), selo_autorizado VARCHAR(30), associado_id, observacoes, excluido, criado_por, criado_em | INDEX receita_id, fk_op_higienizacao | FK receita_id -> receitas_agroindustria(id) e FK higienizacao_previa_id -> controle_higienizacao(id), ambos SEM ON DELETE (migrate_conformidade.php:117-119) |
| rastreabilidade_insumos_processo | id, ordem_producao_id, lote_primario_id (NULL), produto_id (NULL), insumo_id (NULL, add migrate_agro_membros.php:70), numero_nf VARCHAR(30) (add migrate_agro_membros.php:76), quantidade_utilizada DECIMAL(10,2), unidade_medida | INDEX ordem_producao_id | FK ordem_producao_id -> ordens_producao(id) ON DELETE CASCADE (migrate_conformidade.php:129) |
| insumos_organicos_aprovados | id, nome, nome_comercial, fabricante, categoria ENUM(...'Higienização'...), tipo, subcategoria, permitido, restricoes, normativa, data_inclusao_normativa, composicao, principio_ativo, observacoes | PK id | NENHUMA (sql/schema_organico_sprint1.sql:316-340; ENUM ampliado em sql/seed_insumos_aprovados_animal.sql:18) |
| insumos (origem dos insumos usados na OP) | id, uuid, associado_id, nome, descricao, fornecedor_id, estoq_anterior, quant_recebida, quantidade_estoque, tipo_insumo ENUM(Orgânico,Em Conversão,Convencional,Não Permitido), lote VARCHAR(100) | — | fornecedor_id -> fornecedores (sinporg/prisma/migrations/0_baseline/migration.sql:946-963) |
| lotes_producao | id, uuid, nome_lote, produto_id, associado_id (add migrate_agro_producao.php:42), quantidade, unidade_medida, data_producao, excluido | — | produto_id -> produtos; associado_id backfilled por produto->lavoura->propriedade (migrate_agro_producao.php:48-58) |

**Relações do módulo (confirmadas em código)**
- ordens_producao.receita_id -> receitas_agroindustria.id (OrdemProducao.php:17, 35; migrate_conformidade.php:118).
- ordens_producao.higienizacao_previa_id -> controle_higienizacao.id (OrdemProducao.php:116; migrate_conformidade.php:119).
- rastreabilidade_insumos_processo.ordem_producao_id -> ordens_producao.id (OrdemProducao.php:131-133; migrate_conformidade.php:129).
- rastreabilidade_insumos_processo.insumo_id -> insumos.id (OrdemProducao.php:51; migrate_agro_membros.php:70).
- rastreabilidade_insumos_processo.lote_primario_id -> lotes_producao.id e .produto_id -> produtos.id (OrdemProducao.php:49-50) — COLUNAS SEMPRE GRAVADAS COMO NULL pela única tela que cria (ordens_cadastrar.php:128-129).
- agroindustria_membros.agroindustria_id -> agroindustrias.id; agroindustria_membros.associado_id SEM FK.
- agro_setores.agroindustria_id / agro_setores.associado_id SEM FK; agro_atividades_diarias.setor_id/categoria_id SEM FK; agro_ocorrencias.setor_id SEM FK.
- Ordens <-> setores <-> atividades: NÃO EXISTE LIGAÇÃO no código. ordens_producao não possui setor_id nem atividade_id; agro_atividades_diarias não possui ordem_id; a única ponte entre o 'chão de fábrica' (setores/atividades) e o processamento (ordens) é inexistente em colunas — portanto a relação 'ordens de produção <-> receitas <-> setores <-> atividades' está implementada apenas para RECEITAS (FK real); setores e atividades ficam ilhados (sem FK nem referência a partir das ordens).
- Balanço de massas <-> rastreabilidade: o balanço usa exclusivamente 'rastreabilidade_insumos_processo.quantidade_utilizada' (OrdemProducao.php:66-69) e 'ordens_producao.quantidade_final_obtida'; a origem dos insumos (fornecedor/CNPJ/NF) vem de 'insumos' -> 'fornecedores' (OrdemProducao.php:52) e é o que a página pública exibe (rastreabilidade.php:26-38, 148-177).

**Riscos transversais priorizados (todos com citação)**
1. Exclusão por GET sem posse em 7 telas de listagem: setores_listar.php:23-30, categorias_listar.php:23-30, atividades_listar.php:23-30, ocorrencias_listar.php:23-30, receitas_listar.php:18-25, ordens_listar.php:17-23, higienizacao_listar.php:17-23 (somente minhas_listar.php:23-25 valida). Combinado com a ausência de CSRF em todo o módulo (apenas minhas_cadastrar.php:24 e minhas_editar.php:40 usam CSRFProtection), permite destruição cross-tenant por visita a URL ou por tag de imagem com src apontando para a URL.
2. IDOR de escrita em receitas_editar.php (sem guard de dono) e escrita cross-tenant por 'associado_id' vindo do POST em setores_cadastrar.php:41, atividades_cadastrar.php:42, ocorrencias_cadastrar.php:39, receitas_cadastrar.php:57, receitas_editar.php:78, ordens_cadastrar.php:108 e higienizacao_cadastrar.php:30.
3. RF08 inoperante/contornável: ControleHigienizacao::haHigienizacaoValida ignora o produtor (38-48) e é chamada com o literal 'Qualquer linha' (ordens_cadastrar.php:149); a higienização efetivamente escolhida não é validada quanto a dono (ordens_cadastrar.php:155-164); o docblock de OrdemProducao::create:96-100 promete uma trava que não existe no código.
4. Rastreabilidade por string: 'lote_processado_final' VARCHAR(50) sem UNIQUE (migrate_conformidade.php:105; baseline migration.sql:1203) e sem FK para lotes_producao; a página pública pega a PRIMEIRA ordem com o texto (rastreabilidade.php:14-23) -> QR Code pode apontar para o produto errado (falha direta de RF12).
5. Receita sem versionamento: ReceitaAgroindustria::update apaga e recria ingredientes (156-158) e a rastreabilidade pública lê a receita atual sem filtro de exclusão (rastreabilidade.php:18, 40) -> o histórico publicado pode ser reescrito; a ordem guarda apenas o snapshot de percentual/selo (ordens_cadastrar.php:175-176).
6. Balanço de massas frágil: soma unidades heterogêneas (OrdemProducao.php:66-69, 143), usa abs() (82), exibe 'kg' fixo (ordens_visualizar.php:89-93; OrdemProducao.php:91-92) e não registra perdas nem custo (não encontrados).
7. Membro de agroindústria é auto-serviço e irrestrito: minhas_editar.php:94 lista todos os associados ativos e :46-51 vincula qualquer id; Agroindustria::adicionarMembro sempre retorna true (123) e a tabela não tem FK em associado_id (migrate_agro_membros.php:36-46); isso amplia o conjunto de insumos visíveis na criação de OP (Agroindustria.php:95-112 -> ordens_cadastrar.php:70-83).
8. Sanitizantes: whitelist por NOME sem FK (ControleHigienizacao.php:58-67; higienizacao_cadastrar.php:37-39) e dependência de seed/ENUM (sql/seed_insumos_aprovados_animal.sql:18,149+) — quebra total do fluxo de produção se ausente.
9. Ausência total de cálculo de custo, rendimento e perdas no módulo: NÃO ENCONTRADO NO CÓDIGO (nenhuma coluna de valor/custo nas tabelas de dev-utils/migrate_conformidade.php:64-131 e nenhuma ocorrência dos termos nos arquivos do escopo).
10. Auditoria incompleta: AuditLog sem registro_id em setores_cadastrar.php:43, categorias_cadastrar.php:35, atividades_cadastrar.php:45, ocorrencias_cadastrar.php:44 (porque os models retornam bool) e registro de exclusão mesmo quando nada foi excluído (ordens_listar.php:20).


---


# PARTE 6 — FORNECEDOR (telas + classes) e CLASSES DA AGROINDÚSTRIA

> Seção produzida por análise dedicada de `modulos/fornecedor/*` e das classes `Fornecedor`, `OrdemProducao`, `ReceitaAgroindustria`, `ControleHigienizacao`, `LoteProducao`, `Agroindustria` — leitura integral + conferência de schema no banco `sisagro`.



---

## Base de verificação

- Leitura integral dos arquivos: modulos/fornecedor/{cadastrar,editar,excluir,listar}.php e classes/{Fornecedor,OrdemProducao,ReceitaAgroindustria,ControleHigienizacao,LoteProducao,Agroindustria}.php.
- Schema de referência: sinporg/prisma/migrations/0_baseline/migration.sql (baseline Prisma).
- Schema real conferido no MySQL local (banco sisagro, via information_schema.COLUMNS e SHOW CREATE TABLE).
- Comportamento verificado em runtime com o PHP do XAMPP (scripts de probe removidos após uso). Resultados citados como "verificado em runtime".

---

# Grupo 1 — modulos/fornecedor

### modulos/fornecedor/cadastrar.php

- **Rota/acesso**: rota amigável em index.php:743-746 (**fornecedor/novo** -> require de modulos/fornecedor/cadastrar.php). Link de menu em includes/menu.php:172 (**SITE_URL/fornecedor**), dentro do bloco "Produção Vegetal" cujo gate é **$temAreaVegetal** (includes/menu.php:18, bloco 160-180). Link "Adicionar Novo Fornecedor" em modulos/fornecedor/listar.php:32 (**SITE_URL/fornecedor/novo**).
- **Guard de sessão/permissão**: guard indireto em includes/header.php:16-19 (redireciona para /login se **!$user->isLoggedIn()**); guard próprio em cadastrar.php:18 — **if (!$user->hasPermission(NIVEL_USUARIO))**. Como NIVEL_USUARIO=3 (config/config.php:25) e User::hasPermission retorna **$nivel <= $required_level** (classes/User.php:114-141), o teste é verdadeiro para qualquer usuário logado de nível 1, 2 ou 3. O bloco 144-145 ("Acesso negado") é inalcançável na prática, pois sem login o header.php já redirecionou. **Observação**: o bloco POST (49-74) executa antes do teste de renderização ($hasAccess, 144).
- **Propósito de negócio**: cadastrar fornecedor (pessoa física/jurídica) e vinculá-lo às unidades produtivas do associado (propriedade / agroindústria / criação animal) para que apareça nas telas dos associados dessas unidades e nas telas de insumo.
- **Campos/formulário**: definidos no array $formFields (90-111), renderizados em 155-182. Campos com **name=**: nome (text, **required**, col md-8, 92); cnpj (text, col md-4, placeholder 00.000.000/0000-00, 93); razao_social (text, md-6, 96); representante (text, md-6, 97); email (type=email, md-6, 100); telefone (declarado tipo "tel" mas renderizado como **type=text**, pois o teste da linha 176 só preserva email/password/date; md-6, 101); produto_fornecido (text, md-12, 104); endereco (text, md-6, 107); bairro (text, md-3, 108); estado (**select, required**, md-3, opções = array_column(Associado::getEstados(),'nome','id'), 109 + 77).
  - Bloco "Unidades atendidas" (184-211): **name="unidade_tipo[]"** (select, 189) e **name="unidade_id[]"** (select com optgroups por tipo, 194), botões "+ Adicionar unidade" (209) e "Remover" (206). CSRF: **CSRFProtection::getTokenField()** na linha 153.
  - **Campo cidade ausente**: não existe input name="cidade" no formulário, apesar de Fornecedor::create fazer bind de :cidade (classes/Fornecedor.php:39) a partir de $dados_sanitizados['cidade']. Verificado em runtime: Sanitizer::sanitizeArray(['nome'=>'Teste','estado'=>23]) não contém a chave 'cidade' -> PHP 8 emite "Undefined array key 'cidade'" e grava NULL.
- **Regras/validações/cálculos**: praticamente inexistentes no arquivo.
  - Guard de permissão (18) e eleição de tipos/unidades (27-47): gestor/admin usa Fornecedor::getTodasUnidades() e $todosTipos fixo ['propriedade','agroindustria','animal'] (28-31); usuário comum usa Produto::getUnidadesDoAssociado($user->getAssociadoId() ?? -1) e $todosTipos derivado das unidades retornadas (33-36). $tipoPadrao (37-46): 'agroindustria' se o perfil dashboard for agroindustria e houver agroindústria; senão 'propriedade' se houver; senão o único tipo; senão o primeiro.
  - Whitelist de unidades no POST (54-69): monta $permitidas como chave "tipo:id" a partir de $unidadesDisponiveis e só aceita pares presentes (64) — validação correta contra adulteração de POST.
  - Nenhuma validação de CNPJ/CPF, e-mail, telefone ou obrigatoriedade server-side além de "nome" (NOT NULL no banco). Não há checagem de duplicidade prévia (só a UNIQUE do banco).
- **Tabelas SQL usadas**: indiretas (via classes). Escrita: INSERT em **fornecedores** (classes/Fornecedor.php:22-23) e INSERT/UPDATE em **fornecedor_unidades** (classes/Fornecedor.php:244, 248-250). Leitura: **propriedades**, **agroindustrias**, **ambiente_criacao_animal** (classes/Fornecedor.php:92-110), **estados** (classes/Associado.php:199), **propriedades/propriedade_membros**, **agroindustrias/agroindustria_membros**, **ambiente_criacao_animal/ambiente_criacao_membros** (classes/Produto.php:167-204).
- **Ações/POST/redirects**:
  - POST recebido no próprio arquivo (49): $fornecedor->create($_POST) (51). Se o retorno for numérico (53), monta $vincular validado (58-69) e chama **$fornecedor->vincularUnidades($resultado, $vincular)** (70) — método que **não existe** em Fornecedor.php (grep em todo o repositório retorna apenas essa chamada; verificado em runtime: method_exists(Fornecedor,'vincularUnidades') === false). Resultado: **PHP Fatal error "Call to undefined method Fornecedor::vincularUnidades()"** sempre que o INSERT funciona. O cadastro morre após gravar o fornecedor, deixando o registro sem vínculo de unidade.
  - Se create() falhar (retorno string), **nenhuma mensagem é exibida** (53 só trata is_numeric), incluindo o caso de CNPJ/e-mail duplicado.
  - action do formulário: **SITE_URL + '/fornecedor/cadastrar'** (83, repetido em 115) — **rota inexistente**: o roteador só trata fornecedor, fornecedor/novo e fornecedor/<id>/editar (index.php:739-751); qualquer outra URL cai no fallback 404.php (index.php:1175-1177). Ou seja, o POST do formulário nem chega ao arquivo pelo roteador; apenas a rota /fornecedor/novo renderiza a tela.
  - Sem redirect após sucesso (renderiza a mesma página com alert success, 71-72).
- **Riscos/gaps**:
  1. **Crítico — fatal error** por chamada a método inexistente (cadastrar.php:70 x classes/Fornecedor.php, sem vincularUnidades).
  2. **Crítico — POST aponta para rota inexistente** (cadastrar.php:83/115 vs index.php:739-751) => 404 no envio.
  3. **CSRF não validado**: token é impresso (153) mas nenhuma chamada a CSRFProtection::validatePostRequest/validateToken existe no módulo (grep global lista apenas cadastrar.php:153 para o módulo fornecedor).
  4. **Cidade nunca coletada** no cadastro (sem input) => NULL e warning.
  5. Telefone renderizado como text (176), perdendo validação nativa; máscara só via JS externo (assets/js/masks.js, header.php:51), que aqui não é usada no campo telefone (sem classe mask-telefone, diferente de editar.php:190).
  6. **Redefinição duplicada** de $page_title/$page_subtitle/$formAction/$cancelUrl/$submitLabel/$message/$message_type/$hasAccess (81-88 e 113-120) e terceira definição de $page_title (122); require_once de header.php e menu.php repetido (6-7 e 123-124) — código morto, sintoma de merge.
  7. **XSS armazenado potencial via JS**: as unidades são embutidas por json_encode (235) e reinseridas por template string com ${u['nome']} sem escape (270) — nome de unidade controlável pelo usuário vira HTML/JS.
  8. Mensagens de erro de banco são silenciadas (nada exibido), mas Fornecedor::create loga e devolve $e->getMessage() (classes/Fornecedor.php:57) — vazamento de detalhe interno apenas no retorno, não renderizado aqui.
  9. Entrada não canonicalizada: CNPJ gravado como digitado (com ou sem máscara), sem padronização (evidência em banco: cnpj "45454654546541" e "98765432000100", ambos inválidos no dígito verificador — verificado com Agroindustria::validarCnpj).

### modulos/fornecedor/editar.php

- **Rota/acesso**: index.php:747-751 (**fornecedor/<id>/editar**, injeta $_GET['id'] = $parts[1]). Link em listar.php:65. action do formulário coerente: SITE_URL + '/fornecedor/' + $id + '/editar' (171).
- **Guard de sessão/permissão**: includes/header.php:3 dispara o guard de login (header.php:16-19). Guard próprio: **if (!$user->isLoggedIn())** (13-17) — libera qualquer nível, inclusive NIVEL_USUARIO. Validação de id em 19-23 e existência em 29-34.
  - **Checagem anti-IDOR explícita** em 37-49: se o usuário não tem NIVEL_GESTOR, carrega Fornecedor::getVisiveisParaAssociado($user->getAssociadoId() ?? -1) (39) e só permite se o id estiver na lista (41-48); senão exibe "Acesso Negado" e encerra (45-48). A checagem roda **antes** do bloco POST (101), portanto o escopo é respeitado na escrita.
- **Propósito de negócio**: editar dados cadastrais do fornecedor e reatribuir as unidades atendidas, com preservação dos vínculos de unidades de outros associados quando o editor é usuário comum.
- **Campos/formulário** (171-288): nome (text, **required**, 174); representante (178); razao_social (182); cnpj (text, classe mask-cnpj, 186); telefone (text, classe mask-telefone, 190); email (**type=email**, 194); produto_fornecido (198); endereco (202); bairro (206); estado (**select required**, 210-215, options de Associado::getEstados() com id/nome/uf); cidade (**select required disabled**, 219-221, populado via AJAX). Bloco de unidades 233-283 com name="unidade_tipo[]" (237, 260) e name="unidade_id[]" (242, 265). **Não há campo status** (não é possível inativar pelo formulário). **Não há token CSRF** no formulário.
- **Regras/validações/cálculos**:
  - Escolha de unidades disponíveis 52-73 (idêntica a cadastrar.php:27-47).
  - Separação dos vínculos em gerenciáveis x alheios para usuário comum (79-96): monta $chavesPermitidas com "tipo:id" (83-85) e classifica cada vínculo (86-93).
  - Validação do POST de vínculos (121-135): whitelist $permitidas, strtolower(trim(tipo)), uid>0, e só aceita chave existente (132).
  - Preservação dos vínculos alheios (136-142) para não-gestor.
  - Nenhuma validação de CNPJ/e-mail/telefone; nenhum uso de Sanitizer — valores de $_POST vão direto para bindParam (106-117).
- **Tabelas SQL usadas**: **UPDATE fornecedores** literal em 104: "UPDATE fornecedores SET nome = :nome, representante = :representante, razao_social = :razao_social, cnpj = :cnpj, telefone = :telefone, email = :email, endereco = :endereco, bairro = :bairro, cidade = :cidade, estado = :estado, produto_fornecido = :produto_fornecido WHERE id = :id" (binds 106-117). Vínculos: UPDATE fornecedor_unidades SET excluido = 1 ... e INSERT ... ON DUPLICATE KEY UPDATE (classes/Fornecedor.php:244 e 248-250). Leitura: fornecedores + cidades + estados (classes/Fornecedor.php:124-128), fornecedor_unidades + UNION de propriedades/agroindustrias/ambiente_criacao_animal (classes/Fornecedor.php:211-224), estados (classes/Associado.php:199).
- **Ações/POST/redirects**: POST tratado no próprio arquivo (101-159) com try/catch PDOException (154-158); não há redirect — apenas mensagem e re-render (145-149, 165-169). Se executa, chama substituirUnidades($id, $vincular, $user->getAssociadoId() ?? null) (143) e recarrega os vínculos (149). AJAX de cidades: fetch em SITE_URL + '/api/get_cidades.php?estado_id=' (383), com API real em api/get_cidades.php.
- **Riscos/gaps**:
  1. **Sem CSRF** (nenhum token no formulário 171-288; CSRFProtection não é requerido no arquivo; nenhuma validação). O POST de edição é aceito de qualquer origem autenticada.
  2. **substituirUnidades apaga todos os vínculos do fornecedor** antes de reinserir (classes/Fornecedor.php:244 — WHERE fornecedor_id = :fid, sem filtro por associado) e o ON DUPLICATE reescreve associado_id com o do usuário corrente (classes/Fornecedor.php:250, 260). Para um gestor, os vínculos de outros associados só sobrevivem se o formulário reenviar todas as linhas; linhas de unidades que não estão mais em $unidadesDisponiveis (ex.: agroindústria com excluido=1, que não aparece no optgroup 244-253) viram uid=0, são descartadas em 131 e o vínculo é permanentemente soft-deleted.
  3. **Corrupção de metadado associado_id**: mesmo para não-gestor, os vínculos "alheios" são reinjetados em $vincular (139-141) e regravados com associado_id = do usuário corrente (260) — perde-se a autoria original registrada no pivô.
  4. **Cidade pode ser apagada**: o select #cidade fica **disabled** até o JS carregar as cidades (219, 366-376, 424-426); campo disabled não é enviado no POST, então $_POST['cidade'] fica indefinido quando o fornecedor não tem cidade e o JS não habilita — warning + NULL (114). O mesmo não ocorre para estado (select ativo).
  5. Data exibida pode divergir do banco: array_merge($fornecedor, $_POST) (148) injeta o POST cru na variável de exibição, e o valor é escapado só na saída (htmlspecialchars 174-219) — aceitável, mas mascarado.
  6. **Indentação/aninhamento quebrados** em 118-150 (if($stmt->execute()){ colado à margem e blocos aninhados sem recuo) — alto risco de manutenção/merge.
  7. **Sem trilha de auditoria**: nenhuma chamada a AuditLog (diferente de módulos como agroindustria/ordens_cadastrar.php:179) — não se sabe quem alterou o quê.
  8. Escrita direta de SQL no arquivo (104-117) em vez de método da classe, burlando qualquer sanitização centralizada e duplicando a regra de negócio (Fornecedor::update não existe; a classe tem só create/getAll/getById/getVisiveisParaAssociado/getTodasUnidades/getUnidadesPorFornecedor/substituirUnidades).

### modulos/fornecedor/excluir.php

- **Rota/acesso**: **não há rota no roteador** — index.php:739-751 trata apenas fornecedor, fornecedor/novo e fornecedor/<id>/editar. Acesso somente por caminho físico direto: **SITE_URL/modulos/fornecedor/excluir.php?id=N** (o .htaccess raiz só reescreve quando o arquivo não existe; .htaccess:16-24). Padrão análogo em outros módulos, ex.: modulos/caderno_campo/lotes_producao/listar.php:73 aponta para /modulos/caderno_campo/lotes_producao/excluir.php.
- **Guard de sessão/permissão**: session_start() incondicional na linha 2 (antes de qualquer include). Guard 1 (3-12): se $_SESSION['user_tipo'] === 'usuario' encerra com modal de permissão negada. Guard 2 (17-21): **if (!$user->hasPermission(NIVEL_GESTOR))** — restrito a Admin/Gestor. includes em 13-15 (header antes da checagem de nível).
- **Propósito de negócio**: excluir fornecedor.
- **Campos/formulário**: nenhum (não há formulário; não há POST).
- **Regras/validações/cálculos**: valida id numérico em 23-27 (senão redirect para listar.php?msg=erro_id, 24) e converte com intval (28). Nada mais.
- **Tabelas SQL usadas**: **DELETE FROM fornecedores WHERE id = :id** (32-33, bind PDO::PARAM_INT) — **exclusão física**. Não remove nem faz soft delete em fornecedor_unidades (não há ON DELETE CASCADE declarado no baseline: migration.sql:874-888 não tem FOREIGN KEY), deixando vínculos órfãos na pivot.
- **Ações/POST/redirects**: apenas GET. Redireciona para listar.php?msg=excluido (35), listar.php?msg=erro_excluir (38, 42) e listar.php?msg=erro_id (24). **listar.php não lê $_GET['msg'] em nenhum ponto** — as mensagens nunca aparecem.
- **Riscos/gaps**:
  1. **Exclusão por GET sem CSRF** (23-40): qualquer imagem/link/pré-carregamento dispara a exclusão; um gestor logado é suficiente. Viola o padrão do próprio sistema, que em outros módulos exige POST + CSRF (ex.: modulos/caderno_campo/lotes_producao/excluir.php:20 e modulos/usuarios/excluir.php:13).
  2. **Contradição funcional**: listar.php:151-205 exibe modal dizendo que "a funcionalidade de exclusão de fornecedores foi temporariamente desabilitada", e listar.php:66 usa um botão sem ação de exclusão; porém o endpoint de exclusão física continua alcançável por URL direta. Não há segregação por status/inativação (não existe coluna excluido em fornecedores no banco real — confirmado via information_schema).
  3. **Perda de rastreabilidade**: DELETE físico sem AuditLog e sem verificação de dependências — insumos.fornecedor_id e rastreabilidade/relatórios passam a apontar para fornecedor inexistente (LEFT JOINs passam a NULL silenciosamente, ex.: classes/OrdemProducao.php:52, classes/Relatorio.php:153, classes/ReportConformidade.php:324/390, relatorios/form_insumos.php…).
  4. Evidência de uso real do endpoint: a tabela fornecedores tem ids 1,2,3,5,6,7,8 (id 4 ausente) — compatível com exclusão física já executada.
  5. session_start() na linha 2 sem checagem de session_status (config.php:88-103 já faz a checagem correta) — redundância que pode gerar notice de sessão já iniciada dependendo da ordem de inclusão.

### modulos/fornecedor/listar.php

- **Rota/acesso**: index.php:739-742 (**/fornecedor** -> modulos/fornecedor/listar.php). Guard: **if (!$user->isLoggedIn())** (8-12); comentário da linha 7 ("Apenas Admin ou Gestor podem ver a lista completa") **não corresponde ao código** — a segmentação real é por escopo de dados (16-23).
- **Propósito de negócio**: listar fornecedores; gestor vê todos, usuário comum vê apenas os vinculados às unidades que acessa (dono ou membro).
- **Campos/formulário**: nenhum formulário de entrada (apenas a tabela e o botão de advertência).
- **Regras/validações/cálculos**: seleção do conjunto (16-23): NIVEL_GESTOR -> $fornecedor_obj->getAll() (18); senão Fornecedor::getVisiveisParaAssociado($associado_id ?? -1) (21-22). Título/subtítulo condicionais (26-27). Para cada fornecedor, resolve unidades com getUnidadesPorFornecedor($forn['id']) (50) e monta badges com o mapa de rótulos ['propriedade'=>'Propriedade','agroindustria'=>'Agroindústria','animal'=>'Criação Animal'] (53); se não houver vínculo, exibe "Somente gestores" (61).
- **Tabelas SQL usadas**: leitura de **fornecedores** + **cidades** + **estados** (classes/Fornecedor.php:67-75 getAll; 155-194 getVisiveisParaAssociado, com JOIN em fornecedor_unidades e subconsultas em propriedades/propriedade_membros/agroindustrias/agroindustria_membros/ambiente_criacao_animal/ambiente_criacao_membros); **fornecedor_unidades** + UNION de propriedades/agroindustrias/ambiente_criacao_animal por linha da tabela (classes/Fornecedor.php:211-224).
- **Ações/POST/redirects**: nenhum POST. Ações de linha: botão Editar -> SITE_URL/fornecedor/<id>/editar (65); botão Excluir -> onclick mostrarAdvertenciaExclusao() (66, função 151-205), que apenas monta e exibe um modal Bootstrap via jQuery — **não há chamada ao endpoint de exclusão**.
- **Riscos/gaps**:
  1. **N+1 queries**: getUnidadesPorFornecedor dentro do foreach (49-69, chamada na linha 50) executa 1 query por fornecedor.
  2. **XSS armazenado potencial**: json_encode($fornecedores) impresso dentro de <script> (117) sem JSON_HEX_TAG/JSON_HEX_APOS, e os dados incluem nome e representante livres — um valor contendo </script> quebra o contexto e injeta script. (O restante da página usa htmlspecialchars, 54/58-63.)
  3. Comentário/UX inconsistente com o backend: o botão de exclusão é "desabilitado" na UI (66, CSS 84-112) mas o endpoint físico existe (modulos/fornecedor/excluir.php:32).
  4. Mensagens de retorno do excluir.php (?msg=...) nunca são tratadas (nenhuma leitura de $_GET['msg'] no arquivo).
  5. Dados sensíveis em console: console.log dos dados completos do fornecedor (118-122).
  6. Dependência de CDN para i18n do DataTables (133) — sem fallback offline.

## Síntese do grupo — modulos/fornecedor

**Fluxo ponta-a-ponta (estado real do código)**

1. Menu (includes/menu.php:172) -> **/fornecedor** (index.php:739-742) -> listar.php. Header.php:16-19 já garantiu login.
2. listar.php escolhe getAll() (gestor) ou getVisiveisParaAssociado() (comum) — 16-23 — e renderiza badges por unidade (50-55).
3. Botão "Adicionar Novo Fornecedor" (listar.php:32) -> **/fornecedor/novo** (index.php:743-746) -> cadastrar.php, que exibe o formulário com CSRF (153) e action **/fornecedor/cadastrar** (83) — URL que **não existe** no roteador (fallback 404.php, index.php:1175-1177). Mesmo que a rota existisse, o sucesso chamaria vincularUnidades() (70), método ausente -> fatal error. Portanto **o cadastro de fornecedor está funcionalmente quebrado de ponta a ponta**.
4. Botão Editar (listar.php:65) -> **/fornecedor/<id>/editar** (index.php:747-751) -> editar.php, com anti-IDOR por escopo de unidades (37-49), UPDATE literal em fornecedores (104) e rearranjo de vínculos via substituirUnidades (143), sem CSRF.
5. Exclusão: UI apenas informativa (listar.php:66, 151-205); o endpoint físico (excluir.php:32, DELETE por GET sem CSRF, restrito a NIVEL_GESTOR) só é alcançável por URL direta e não é roteado.
6. Fornecedores criados aparecem como opção de insumo (modulos/caderno_campo/insumos/cadastrar.php:39-44 e 183-190) e nos relatórios/rastreabilidade via insumos.fornecedor_id (classes/OrdemProducao.php:52, modulos/agroindustria/rastreabilidade.php:34).

**Tabelas consolidadas do grupo (com colunas efetivamente usadas)**

| Tabela | Colunas usadas | Onde |
|---|---|---|
| fornecedores | id, nome, razao_social, representante, cnpj, email, telefone, produto_fornecido, endereco, bairro, cidade, estado, status, data_cadastro (+abrangencia existe e nunca é lida/escrita por PHP) | Fornecedor.php:22-23, 67-75, 124-128; editar.php:104 |
| fornecedor_unidades | fornecedor_id, unidade_tipo ENUM('propriedade','agroindustria','animal'), unidade_id, associado_id, excluido | Fornecedor.php:161, 211-224, 244-250 |
| cidades / estados | cidades.id/nome, estados.id/nome/uf | Fornecedor.php:73-74, 126-127; Associado.php:199 |
| propriedades / propriedade_membros | id, propriedade, associado_id | Fornecedor.php:93-95, 166-171; Produto.php:167-173 |
| agroindustrias / agroindustria_membros | id, nome_fantasia, razao_social, associado_id, excluido | Fornecedor.php:98-102, 174-181; Produto.php:179-187 |
| ambiente_criacao_animal / ambiente_criacao_membros | id, nome_instalacao, associado_id, excluido | Fornecedor.php:105-109, 184-191; Produto.php:193-201 |

**Não encontrado no código**: campos/tabelas de categoria de fornecimento, certificações do fornecedor, CPF (só existe a coluna cnpj VARCHAR(18)), histórico de alterações do fornecedor, e qualquer validação de dígito verificador de CNPJ no fluxo de fornecedor (a validação existe apenas em Agroindustria::validarCnpj, classes/Agroindustria.php:280-306, e não é chamada por Fornecedor.php).

---

# Grupo 2 — classes

### classes/Fornecedor.php

- **Rota/acesso**: não é tela; instanciado por modulos/fornecedor/{cadastrar,listar,editar}.php (cadastrar.php:29, 50; listar.php:15; editar.php:26), modulos/caderno_campo/insumos/{cadastrar,editar}.php (cadastrar.php:38-44; editar.php:39), dev-utils/{backfill_fornecedor_unidades,migrate_fornecedor_unidades}.php.
- **Propósito de negócio**: persistência e consulta de fornecedores e do vínculo multi-tipo fornecedor <-> unidade produtiva (propriedade/agroindústria/criação animal), com escopo por associado dono ou membro.
- **Campos/formulário**: recebe array $dados com as chaves nome, razao_social, representante, cnpj, email, telefone, produto_fornecido, endereco, bairro, cidade, estado (create, 30-40). Não há validação de presença/formatos.
- **Regras/validações/cálculos**:
  - create (18-59): Sanitizer::sanitizeArray($dados) (20) => strip_tags+trim por valor (classes/Sanitizer.php:15-21, 119-131); **status fixo 'ativo'** (27, 41); INSERT com data_cadastro = NOW() (22-23); retorna (int)lastInsertId() (44).
  - Tratamento de duplicidade (46-55): captura PDOException com getCode() == 23000 e devolve mensagens específicas se a mensagem do driver contiver "cnpj" (49-51) ou "email" (52-54); caso contrário devolve a mensagem crua do banco (57) — informação interna exposta ao chamador.
  - getVisiveisParaAssociado (152-202): retorna [] se $associado_id falsy (153); exige **f.status = 'ativo'** (164) e vínculo ativo na pivot (**fu.excluido = 0**, 161); resolve o escopo de forma derivada (não usa fornecedor_unidades.associado_id) em três ramos: propriedade dono OU membro (166-171), agroindústria dono OU membro com ag.excluido=0 (174-181), ambiente de criação dono OU membro com amb.excluido=0 (184-191). ORDER BY f.nome (193).
  - getTodasUnidades (89-115): concatena 3 SELECTs (propriedades sem filtro de exclusão — a tabela não tem coluna excluido; agroindustrias e ambiente_criacao_animal com excluido=0). Nome da agroindústria = COALESCE(NULLIF(nome_fantasia,''), NULLIF(razao_social,''), CONCAT('Agroindústria #', id)) (99). Verificado em runtime: 19 unidades.
  - getUnidadesPorFornecedor (209-232): LEFT JOIN com UNION ALL dos três tipos (215-221), COALESCE(nome, 'Unidade #'.id) (213), WHERE fu.fornecedor_id = :fid AND fu.excluido = 0 (222).
  - substituirUnidades (240-270): transação (242); **UPDATE fornecedor_unidades SET excluido = 1 WHERE fornecedor_id = :fid** (244) sem filtro de associado; INSERT ... ON DUPLICATE KEY UPDATE excluido = 0, associado_id = VALUES(associado_id) (248-250); whitelist de tipo com in_array(['propriedade','agroindustria','animal'], true) e uid > 0 (254); bindValue com PARAM_NULL quando associado_id é null (260); commit (263) / rollback em PDOException (266).
- **Tabelas SQL usadas**:
  - INSERT INTO fornecedores (nome, razao_social, representante, cnpj, email, telefone, produto_fornecido, endereco, bairro, cidade, estado, status, data_cadastro) VALUES (..., NOW()) — 22-23.
  - SELECT f.id, f.nome, f.representante, c.nome AS cidade_nome, e.uf AS estado_sigla FROM fornecedores f LEFT JOIN cidades c ON f.cidade = c.id LEFT JOIN estados e ON f.estado = e.id ORDER BY f.nome ASC — 67-75 (getAll; **sem filtro de status**).
  - SELECT p.id, 'propriedade' AS tipo, p.propriedade AS nome FROM propriedades p — 92-95.
  - SELECT ag.id, 'agroindustria' AS tipo, COALESCE(...) AS nome FROM agroindustrias ag WHERE ag.excluido = 0 — 98-102.
  - SELECT amb.id, 'animal' AS tipo, amb.nome_instalacao AS nome FROM ambiente_criacao_animal amb WHERE amb.excluido = 0 — 105-109.
  - SELECT f.*, c.nome AS cidade_nome, e.uf AS estado_sigla FROM fornecedores f LEFT JOIN cidades c ... WHERE f.id = :id — 124-128.
  - getVisiveisParaAssociado (155-194) — usa fornecedores, fornecedor_unidades, cidades, estados, propriedades, propriedade_membros, agroindustrias, agroindustria_membros, ambiente_criacao_animal, ambiente_criacao_membros.
  - getUnidadesPorFornecedor (211-224) — fornecedor_unidades + UNION ALL (propriedades | agroindustrias WHERE excluido=0 | ambiente_criacao_animal WHERE excluido=0).
  - UPDATE/INSERT fornecedor_unidades (244, 248-250).
- **Ações/POST/redirects**: nenhum; a classe apenas retorna valores. create devolve int (id) ou string (erro), contrato ambíguo que o chamador trata com is_numeric (cadastrar.php:53).
- **Riscos/gaps**:
  1. **vincularUnidades não existe** (confirmado por leitura integral e por method_exists em runtime) enquanto cadastrar.php:70 a invoca.
  2. **substituirUnidades destrói vínculos globais**: o UPDATE de 244 ignora o associado dono e o ON DUPLICATE reescreve associado_id (250) — vínculos de terceiros podem ser soft-deleted ou ter autoria sobrescrita (ver análise de editar.php:136-143).
  3. **Exclusão lógica x física**: a pivot tem excluido (soft delete), mas a tabela fornecedores **não tem coluna excluido** (confirmado no banco real) — a única exclusão possível é o DELETE físico de excluir.php:32, que deixa a pivot órfã e não é tratado por esta classe.
  4. **getAll() ignora status** (67-75): fornecedores 'inativo' apareceriam para gestores; porém nada no sistema escreve status='inativo' (não há update de status em nenhum arquivo do módulo) — o enum e o filtro de 164 são parcialmente mortos.
  5. **CNPJ sem validação** no fluxo inteiro (18-59 não chama validador algum); evidência em banco: cnpj '98765432000100' (id 1) e '45454654546541' (id 3), ambos reprovados por Agroindustria::validarCnpj (verificado em runtime).
  6. **UNIQUE(cnpj)/UNIQUE(email) + string vazia**: existe uma linha com cnpj = '' e email = '' (fornecedor id 2, verificado via HEX). Como create() grava a string vazia quando o campo é deixado em branco, o segundo cadastro sem CNPJ colide na UNIQUE e recebe a mensagem enganosa "Erro: O CNPJ informado já está cadastrado." (49-51).
  7. **Escopo por join derivado é correto, mas custoso**: getVisiveisParaAssociado usa 3 subconsultas EXISTS por linha (155-194) e é chamado em telas de listagem e de insumo (listar.php:22, insumos/cadastrar.php:43).
  8. Vazamento de detalhe de erro de banco no retorno de create (57).

### classes/OrdemProducao.php

- **Rota/acesso**: usada por modulos/agroindustria/ordens_cadastrar.php:19 (nova), ordens_listar.php:15, ordens_visualizar.php:15; rotas /agroindustria/ordens, /ordens/nova e /ordens/<id> (index.php:980-988).
- **Propósito de negócio**: ordens de produção da agroindústria com rastreabilidade de insumos, balanço de massas (RF11) e status de auditoria antifraude; trava de higienização prévia (RF08) é atribuída ao fluxo.
- **Campos/formulário (contrato de entrada)**: create($dados, $itens) lê $dados em receita_id, higienizacao_previa_id, lote_processado_final, data_processamento, quantidade_final_obtida, unidade_medida (default 'KG'), status_auditoria (calculado), percentual_organico, selo_autorizado, associado_id, observacoes, criado_por (114-127). Cada item usa lote_primario_id, produto_id, insumo_id, numero_nf, quantidade_utilizada, unidade_medida (136-144). O chamador monta tudo de $_POST (ordens_cadastrar.php:101-134) e valida obrigatórios em ordens_cadastrar.php:137-142.
- **Regras/validações/cálculos (literais)**:
  - calcularBalanco (64-94): totalEntrada = soma de (float)item['quantidade_utilizada'] (67-69). Se totalEntrada <= 0 -> status 'EM_ANALISE' com mensagem "Nenhum lote primário informado para o balanço." (72-80). Divergência: **divergenciaPct = round((abs(totalEntrada - quantidadeFinal) / totalEntrada) * 100, 2)** (82). **status = divergenciaPct > $toleranciaPct ? 'ALERTA_FRAUDE' : 'APROVADO'**, com tolerância padrão **10.0%** (83, 64). Mensagens 90-92.
  - create (101-153): chama calcularBalanco com $dados['quantidade_final_obtida'] (103); abre transação (105); INSERT em ordens_producao com 12 colunas (108-113); normalizações: higienizacao_previa_id -> null se vazio (116), trim(lote_processado_final) (117), unidade_medida default 'KG' (120), percentual_organico/selo/associado_id/observacoes/criado_por -> null se vazios (122-126); INSERT por item em rastreabilidade_insumos_processo (130-134, 135-145); commit (147) / rollback + rethrow (149-152).
  - getItensRastreabilidade (42-57): expõe nome_lote, nome_produto, nome_insumo, lote_insumo, tipo_insumo e **fornecedor / fornecedor_cnpj** (47, 52).
  - softDelete (155-159): UPDATE ordens_producao SET excluido = 1 WHERE id = ?.
- **Tabelas SQL usadas**:
  - getAll (15-24): SELECT o.*, r.nome_produto_final FROM ordens_producao o JOIN receitas_agroindustria r ON o.receita_id = r.id WHERE o.excluido = 0 [AND o.associado_id = ?] ORDER BY o.data_processamento DESC.
  - getById (32-37): SELECT o.*, r.nome_produto_final, r.selo_autorizado AS selo_receita ... WHERE o.id = ? AND o.excluido = 0.
  - getItensRastreabilidade (44-54): FROM rastreabilidade_insumos_processo ri LEFT JOIN lotes_producao lp ON ri.lote_primario_id = lp.id LEFT JOIN produtos p ON ri.produto_id = p.id LEFT JOIN insumos ins ON ri.insumo_id = ins.id LEFT JOIN fornecedores f ON ins.fornecedor_id = f.id WHERE ri.ordem_producao_id = ?.
  - create: INSERT INTO ordens_producao (receita_id, higienizacao_previa_id, lote_processado_final, data_processamento, quantidade_final_obtida, unidade_medida, status_auditoria, percentual_organico, selo_autorizado, associado_id, observacoes, criado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) — 108-113. INSERT INTO rastreabilidade_insumos_processo (ordem_producao_id, lote_primario_id, produto_id, insumo_id, numero_nf, quantidade_utilizada, unidade_medida) VALUES (?,?,?,?,?,?,?) — 131-133.
  - softDelete: UPDATE ordens_producao SET excluido = 1 WHERE id = ? — 157.
- **Ações/POST/redirects**: a classe não redireciona. O chamador, em sucesso, registra AuditLog ('ORDEM_PRODUCAO_CADASTRADA') e redireciona para /agroindustria/ordens/<id>?criada=1 (ordens_cadastrar.php:179-181).
- **Riscos/gaps**:
  1. **Docblock ≠ implementação**: 96-100 afirma "Bloqueia se não houver higienização válida nas últimas 24h (RF08)", mas **não há nenhuma verificação de higienização dentro de create()** — a classe não referencia ControleHigienizacao. O bloqueio vive no chamador (ordens_cadastrar.php:149-170). Qualquer outro chamador futuro grava OP sem RF08.
  2. **Balanço de massas soma unidades heterogêneas**: 67-69 soma quantidade_utilizada sem converter KG/L/UN; e não considera água/rendimento — a divergência de 10% pode ser estrutural.
  3. **Selo e percentual dependem do chamador**: create() aceita percentual_organico e selo_autorizado vindos de $dados (122-123); ordens_cadastrar.php:175-176 copia da receita. Não há recálculo nem validação de coerência com os insumos da OP.
  4. **Sem validação de posse (IDOR de escrita)**: create() não verifica se receita_id pertence ao associado/agroindústria do usuário nem se associado_id do POST é o do usuário; ordens_cadastrar.php:108 aceita **$_POST['associado_id']** arbitrário. Um usuário comum pode atribuir a OP a outro associado.
  5. **getItensRastreabilidade não filtra o.excluido** (não faz JOIN com ordens_producao) — retorna itens de ordens excluídas se chamado por id.
  6. **Sem auditoria de exclusão**: softDelete (155-159) não registra AuditLog nem verifica dependentes (a tabela rastreabilidade_insumos_processo não é tocada).
  7. **lote_primario_id sempre NULL na prática**: o chamador grava null (ordens_cadastrar.php:128), então o LEFT JOIN de 49 e os campos nome_lote nunca resolvem — a rastreabilidade "do lote primário" não é preenchida apesar do schema permitir.

### classes/ReceitaAgroindustria.php

- **Rota/acesso**: modulos/agroindustria/receitas_{listar,cadastrar,editar}.php (linhas 15, 16, 17) e ordens_cadastrar.php:20; rotas /agroindustria/receitas, /receitas/nova, /receitas/<id>/editar (index.php:965-973).
- **Propósito de negócio**: receitas de produto final da agroindústria com ingredientes, cálculo de percentual orgânico e classificação de selo (Portaria 52/2021).
- **Campos/formulário (contrato de entrada)**: create/update recebem $dados com nome_produto_final, descricao, associado_id, status, criado_por (114-120, 147-153); $ingredientes com nome_ingrediente, eh_organico, quantidade, unidade_medida, observacoes, proporcao_porcentagem (70, 175-189). Origem dos campos no formulário: modulos/agroindustria/receitas_cadastrar.php:103 (nome_produto_final), 107 (associado_id), 118 (status), 126 (descricao) e arrays ing_nome[], ing_organico[], ing_quantidade[], ing_unidade[], ing_obs[] (29-33).
- **Regras/validações/cálculos (literais)**:
  - fatorConversao (47-59): kg=1000; mg=0.001; t=1000000; l=1000; ml=1; un/und=1; default=1.
  - calcularPercentualOrganico (65-81): **qtd = quantidade ?? proporcao_porcentagem ?? 0** (70); **peso = qtd * fatorConversao(unidade_medida)** (71); acumula somaPeso e, se !empty(eh_organico), somaOrganico (72-75); se somaPeso <= 0 retorna 0.0 (77-79); senão **round((somaOrganico / somaPeso) * 100, 2)** (80).
  - classificarSelo (89-99): **>= 95.0 -> 'PRODUTO_ORGANICO_BRASIL'**; **>= 70.0 -> 'INGREDIENTES_ORGANICOS'**; senão **'SEM_SELO'** (justificado no docblock 83-88 como Portaria 52/2021).
  - create (101-131): calcula percentual e selo (103-104); transação (106); INSERT com status default 'Ativa' (119) — **divergente do default do banco 'Rascunho'** (migration.sql:1443); salva ingredientes (124); commit (125).
  - update (133-166): recalcula (135-136); UPDATE (141-144); **DELETE FROM ingredientes_receita WHERE receita_id = ?** e reinsere (156-158) — estratégia destrutiva dentro da transação.
  - salvarIngredientes (168-192): trim/normaliza (175-177); **peso = quantidade * fatorConversao(unidade)** (178); descarta ingrediente com nome vazio OU peso <= 0 (179-181); insere 7 colunas (182-190) — **o valor gravado na coluna proporcao_porcentagem é o peso convertido** (188), apesar do nome/comment da coluna sugerir percentual; eh_organico normalizado para 1/0 (185).
- **Tabelas SQL usadas**:
  - getAll (15-18): SELECT r.*, (SELECT COUNT(*) FROM ingredientes_receita i WHERE i.receita_id = r.id) AS total_ingredientes FROM receitas_agroindustria r WHERE r.excluido = 0 [AND r.associado_id = ?] ORDER BY r.criado_em DESC.
  - getById (32): SELECT * FROM receitas_agroindustria WHERE id = ? AND excluido = 0.
  - getIngredientes (39): SELECT * FROM ingredientes_receita WHERE receita_id = ? ORDER BY id ASC.
  - create (109-111): INSERT INTO receitas_agroindustria (nome_produto_final, descricao, percentual_organico_calculado, selo_autorizado, associado_id, status, criado_por) VALUES (?,?,?,?,?,?,?).
  - update (141-144): UPDATE receitas_agroindustria SET nome_produto_final=?, descricao=?, percentual_organico_calculado=?, selo_autorizado=?, associado_id=?, status=? WHERE id=?.
  - salvarIngredientes (171-172): INSERT INTO ingredientes_receita (receita_id, nome_ingrediente, eh_organico, quantidade, unidade_medida, proporcao_porcentagem, observacoes) VALUES (?,?,?,?,?,?,?).
  - softDelete (196): UPDATE receitas_agroindustria SET excluido = 1 WHERE id = ?.
- **Ações/POST/redirects**: classe não redireciona; o chamador registra AuditLog 'RECEITA_CADASTRADA' e redireciona para /agroindustria/receitas?cadastrada=1 (receitas_cadastrar.php:61-62).
- **Riscos/gaps**:
  1. **Sem validação de posse (IDOR de escrita)**: update($id, ...) (133) e softDelete (194) não verificam se a receita pertence ao associado; getById (30-35) idem. Também não há validação de que ingredientes/quantidades vieram de um conjunto autorizado.
  2. **Vínculo com insumos/fornecedores inexistente**: ingredientes_receita guarda apenas nome livre (migration.sql:931-943); não há insumo_id/fornecedor_id. Logo a cadeia fornecedor -> insumo -> receita é textual e não auditável.
  3. **DELETE + INSERT de ingredientes em update** (156-158) apaga histórico/ids — qualquer referência externa a ingredientes_receita.id quebra.
  4. **Semântica enganosa da coluna proporcao_porcentagem**: recebe peso em g/mL (188), enquanto calcularPercentualOrganico lê primeiro 'quantidade' (70) — se um registro vier apenas com proporcao_porcentagem preenchida, o valor será interpretado como quantidade bruta e multiplicado pelo fator (ex.: 50 (peso g) * 1000 = 50000), distorcendo o cálculo.
  5. **Status default divergente** (código 'Ativa' em 119; banco 'Rascunho' em migration.sql:1443) — registros criados pela API/outros fluxos ficam Rascunho, pela tela ficam Ativa.
  6. Ingredientes com peso 0 ou nome vazio são silenciosamente descartados (179-181), sem avisar o usuário; o percentual é calculado sobre os ingredientes de entrada (103) **antes** do descarte, portanto percentual e ingredientes efetivamente gravados podem divergir.
  7. Sem AuditLog dentro da classe e sem controle de concorrência (dois updates simultâneos perdem ingredientes).

### classes/ControleHigienizacao.php

- **Rota/acesso**: modulos/agroindustria/higienizacao_cadastrar.php:15, higienizacao_listar.php:15, ordens_cadastrar.php:21, ordens_visualizar.php (via OrdemProducao); rotas /agroindustria/higienizacao e /higienizacao/nova (index.php:974-979).
- **Propósito de negócio**: registrar a higienização de equipamentos/linhas (RF08) e fornecer a lista de sanitizantes permitidos em produção orgânica; servir de pré-requisito para a ordem de produção.
- **Campos/formulário (contrato de entrada)**: create($dados) lê data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, criado_por (76-85). Formulário em higienizacao_cadastrar.php:67/71/75/79/94/98 com os mesmos names; obrigatoriedade validada no chamador (higienizacao_cadastrar.php:34-36) e o sanitizante contra a lista permitida (37-39).
- **Regras/validações/cálculos**:
  - haHigienizacaoValida($dataProcessamento, $equipamentoLinha, $associadoId, $intervaloHoras = 24) (38-50): COUNT(*) em controle_higienizacao com **excluido = 0**, **data_hora_fim IS NOT NULL**, **equipamento_linha = ?**, **data_hora_fim <= ?**, **data_hora_fim >= DATE_SUB(?, INTERVAL ? HOUR)** (41-47); bind de [equipamentoLinha, dataProcessamento, dataProcessamento, intervaloHoras] (48); retorna fetchColumn() > 0 (49).
  - **$associadoId é aceito mas nunca usado na query** (assinatura 38 vs SQL 41-48) — verificado em runtime: haHigienizacaoValida(fim, 'Linha de Preparo', 999999, 24) retornou **true** para uma higienização cujo associado_id real é 38.
  - listarSanitizantesPermitidos (58-67): SELECT id, nome, subcategoria, restricoes, normativa FROM insumos_organicos_aprovados WHERE categoria = 'Higienização' AND permitido = 1 ORDER BY nome ASC. Verificado em runtime: 29 registros (primeiro "Ácido acético").
  - create (69-87): INSERT direto, sem validação; data_hora_fim -> null se vazia (78); associado_id/criado_por -> null se vazios (82, 84); retorna lastInsertId (86).
  - softDelete (89-93): UPDATE controle_higienizacao SET excluido = 1 WHERE id = ?.
- **Tabelas SQL usadas**: controle_higienizacao (SELECT * em 15/29; COUNT em 41-47; INSERT em 72-74; UPDATE em 91) e insumos_organicos_aprovados (60-64).
- **Ações/POST/redirects**: não redireciona; o chamador grava AuditLog 'HIGIENIZACAO_REGISTRADA' e redireciona para /agroindustria/higienizacao?registrada=1 (higienizacao_cadastrar.php:42-43).
- **Riscos/gaps**:
  1. **Isolamento por associado ausente em haHigienizacaoValida** (38-50): higienização de qualquer produtor valida a OP de outro — falha de integridade/conformidade (comprovada em runtime).
  2. **Sem validação de sanitizante na classe**: create (69-87) não checa listarSanitizantesPermitidos; a checagem é só no chamador (higienizacao_cadastrar.php:37-39) — qualquer outro caminho grava sanitizante proibido.
  3. **Coerência temporal não validada**: não há exigência de data_hora_fim > data_hora_inicio, nem proibição de data futura; higienizacao_cadastrar.php:34 só exige não-vazio no início.
  4. **Sem validação de posse (IDOR)**: getById (27-32) e softDelete (89-93) não checam associado; não há update de higienização (correção exige novo registro).
  5. **Acoplamento frágil com a OP**: ordens_cadastrar.php:149 chama haHigienizacaoValida(..., 'Qualquer linha', ...) — valor que **nunca existe** em equipamento_linha (verificado em runtime: false para todos os registros), tornando o ramo 149-153 código morto e deslocando a validação efetiva para o bloco 154-170 (janela calculada em PHP: proc - fim > 24*3600 || fim > proc).
  6. Sem índice em equipamento_linha nem em data_hora_fim (migration.sql:655-656 só declara PRIMARY KEY) — COUNT de 41-47 faz varredura.

### classes/LoteProducao.php

- **Rota/acesso**: modulos/caderno_campo/lotes_producao/{listar,cadastrar,editar,rastrear}.php (linhas 15, 29, 25, 19) e index.php:369-372/388-391 (resolução de UUID para ID). Rotas /lotes, /lote/novo, /lote/<id|uuid>[/editar] e /caderno-campo/lote/... (index.php:335-402).
- **Propósito de negócio**: lotes de produção do caderno de campo (produção vegetal/agroindústria): identifica o lote por UUID, amarra produto, data, quantidade e fornece rastreabilidade de atividades e insumos usados na lavoura do produto.
- **Campos/formulário (contrato de entrada)**: create($dados) usa uuid, nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes (36-43). Formulário: modulos/caderno_campo/lotes_producao/cadastrar.php:64 (nome_lote, required), 68 (produto_id, required), 78 (data_producao, required), 82 (quantidade, required number step 0.01), 86 (unidade_medida), 91 (observacoes). update($id,$dados) usa nome_lote, produto_id, data_producao, quantidade, unidade_medida, observacoes (186-194) — **não altera associado_id**.
- **Regras/validações/cálculos**:
  - create (17-50): Sanitizer::sanitizeArray (19); **geração de UUID quando ausente com mt_rand e máscara de versão 4** sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', ... | 0x4000, ... | 0x8000, ...) (22-30); INSERT com criado_em = NOW() (32-33); associado_id só recebe int se não vazio (39). **Nenhuma validação de campos obrigatórios, formato de data, quantidade > 0 ou propriedade do produto_id.**
  - getByAssociadoId (77-95): filtra excluido = 0 AND (lp.associado_id = :a OR (lp.associado_id IS NULL AND p.associado_id = :a)), resolvendo o dono via produtos -> lavouras -> propriedades (81-83).
  - getAtividadesByLoteId (136-152): JOIN produtos p ON lp.produto_id = p.id JOIN atividades_diarias ad ON p.lavoura_id = ad.lavoura_id WHERE lp.id = ? AND ad.excluido = 0, retornando nome, data_atividade, tipo_atividade, descricao, hora_inicio, hora_fim, status (138-143).
  - getInsumosByLoteId (159-175): DISTINCT ad.insumo_utilisado, ad.data_atividade, ad.tipo_atividade, com filtro insumo_utilisado IS NOT NULL E != '' (161-166) — a coluna tem o typo "insumo_utilisado" no schema (migration.sql:401).
  - delete (207-216): UPDATE lotes_producao SET excluido = 1 (soft delete).
- **Tabelas SQL usadas**:
  - create (32-33): INSERT INTO lotes_producao (uuid, nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes, criado_em) VALUES (..., NOW()).
  - getAll (58-62): SELECT lp.id, lp.uuid, lp.nome_lote, lp.data_producao, lp.quantidade, lp.unidade_medida, p.nome AS produto_nome FROM lotes_producao lp LEFT JOIN produtos p ON lp.produto_id = p.id WHERE lp.excluido = 0 ORDER BY lp.data_producao DESC — **sem escopo por associado**.
  - getByAssociadoId (79-86): + LEFT JOIN lavouras l ON pr.lavoura_id = l.id LEFT JOIN propriedades p ON l.propriedade_id = p.id.
  - getByUuid (104) / getById (121): SELECT * FROM lotes_producao WHERE (uuid|id) = ... AND excluido = 0.
  - getAtividadesByLoteId (138-143) e getInsumosByLoteId (161-166): lotes_producao + produtos + atividades_diarias.
  - update (186): UPDATE lotes_producao SET nome_lote=?, produto_id=?, data_producao=?, quantidade=?, unidade_medida=?, observacoes=? WHERE id = ?.
  - delete (209): UPDATE lotes_producao SET excluido = 1.
- **Ações/POST/redirects**: a classe não redireciona. O chamador cadastrar.php:41-47 apenas exibe mensagem (não há redirect); a exclusão é feita por outro arquivo (modulos/caderno_campo/lotes_producao/excluir.php:29-31, POST + CSRF conforme listar.php:73).
- **Riscos/gaps (parte produção/estoque)**:
  1. **Nenhum tratamento de estoque**: não há ocorrência da palavra "estoque" em LoteProducao.php (verificado por grep). As tabelas de movimentação **lote_insumo** (migration.sql:1030) e **lote_atividade** (migration.sql:1020) não são usadas por esta classe — lote_insumo aparece apenas em api/relatorios/movimentacoes.php:40. As colunas **lotes_producao.atividades_id** e **lotes_producao.insumos_id** (migration.sql:1054-1055) nunca são preenchidas por create/update. O "estoque" do sistema é tratado em insumos (insumos.estoq_anterior, quant_recebida, quantidade_estoque — migration.sql:953-955), sem baixa automática a partir do lote/produção.
  2. **Sem checagem de posse em create/update**: produto_id vem do POST sem validação de que pertence ao associado (create 38; update 189) — IDOR de escrita; update e delete (183, 207) também não verificam dono (o anti-IDOR existe apenas no consumidor modulos/caderno_campo/lotes_producao/rastrear.php:28).
  3. **getAll() sem escopo por associado** (56-70) — se usado em tela de usuário comum, expõe lotes de terceiros; os fluxos atuais usam getByAssociadoId (verificar cada chamador).
  4. **UUID com mt_rand** (23-29): não é CSPRNG; a coluna tem UNIQUE (migration.sql:1058) e o UUID é usado como identificador de URL (index.php:367-375), portanto previsibilidade/colisão são relevantes.
  5. **Sem validação de campos obrigatórios** (nome_lote, produto_id, data_producao, quantidade são NOT NULL no banco): falha vira PDOException (capturada em 46-49) retornando false — o usuário vê apenas "Erro ao cadastrar lote de produção." (cadastrar.php:45).
  6. **Dupla codificação HTML**: o chamador aplica htmlspecialchars(strip_tags(trim(...))) a todo o POST (cadastrar.php:33) e a classe aplica strip_tags novamente (19) — entidades HTML (&amp;, &lt;) ficam gravadas literalmente no banco quando o usuário digita tais caracteres.
  7. **getAtividadesByLoteId/getInsumosByLoteId ignoram ad.lote_id**: existe a coluna atividades_diarias.lote_id (migration.sql:406), mas a classe liga atividade ao lote por produto -> lavoura (141, 164), misturando atividades de todos os lotes da mesma lavoura.
  8. **Estoque/status divergentes do runtime**: a coluna lotes_producao.status_lote referenciada em api/relatorios/visao-geral.php:30 **não existe** no banco real (confirmado por information_schema no banco sisagro) — consultas de relatório desse tipo falham; e o banco real tem lotes_producao.atualizado_em, ausente do baseline.
  9. Sem AuditLog em create/update/delete da classe (só no excluir.php do módulo, linha 31).

### classes/Agroindustria.php

- **Rota/acesso**: modulos/agroindustria/{minhas_listar:15, minhas_cadastrar:40, minhas_editar:21, setores_cadastrar:24, setores_editar:29, ordens_cadastrar:69}; rotas /agroindustria/minhas, /minha/nova, /minha/<id>/editar, /setores, /setor/... (index.php:920-937).
- **Propósito de negócio**: CRUD da agroindústria, gestão de membros (compartilhamento entre associados) e resolução de escopo (quais agroindústrias/associados o usuário enxerga). **Dashboard/relatórios: não encontrado no código nesta classe** — não há método de agregação, indicador, série temporal ou relatório; a classe expõe apenas getAll (17-33), getById (35-40), countByAssociado (42-47), getMembros (53-65), getAgroindustriasPorAssociado (71-88), getAssociadosDaMesmaAgroindustria (95-112), adicionarMembro (118-128), removerMembro (134-142), create (149-199), update (201-244), softDelete (246-250), marcarAssociadoComAgroindustria (252-263), soDigitos (265-268), formatarCnpj (270-278), validarCnpj (280-306). O dashboard do perfil usa User::temAgroindustria (modulos/dashboard/dashboard_agroindustria.php:10; classes/User.php:177-200), não esta classe; nenhum relatório em relatorios/ referencia a classe (grep).
- **Campos/formulário (contrato de entrada)**: create/update usam associado_id, razao_social, nome_fantasia, cnpj, inscricao_estadual, telefone, email, responsavel_tecnico, endereco, numero, bairro, cidade, estado, cep, status, observacoes, criado_por (171-189, 218-234). adicionarMembro/removerMembro usam (agroindustriaId, associadoId, criadoPor).
- **Regras/validações/cálculos**:
  - create (149-199): Sanitizer::sanitizeArray (151); CNPJ normalizado para dígitos (153) e **validado com validarCnpj** (154-156); **associado_id obrigatório > 0** (158-161); INSERT com 17 colunas (164-170); CNPJ formatado antes de gravar (175); status normalizado para 'Ativa'/'Inativa' (186); marca associados.tem_agroindustria = 1 (192, 252-263).
  - update (201-244): mesma validação de CNPJ (205-208); UPDATE de 15 colunas WHERE id = ? AND excluido = 0 (211-216); chama marcarAssociadoComAgroindustria((int)($dados['associado_id'] ?? 0)) na linha 237 — porém **associado_id não está no SET** (212-215), logo a chamada só funciona se o POST trouxer associado_id, sem alterar o dono da agroindústria (inconsistência).
  - getAssociadosDaMesmaAgroindustria (95-112): obtém os ids via getAgroindustriasPorAssociado (100), monta **IN ($in) com implode(',', array_map('intval', $agros))** (104-107) — interpolação direta mas neutralizada por intval; se não houver agroindústria retorna [(int)$associadoId] (101-103); sempre anexa o próprio associado e deduplica (110-111).
  - getMembros (53-65): JOIN associados com m.excluido = 0 AND a.status = 'ativo' (56-60).
  - adicionarMembro (118-128): **INSERT IGNORE** (121) e retorno **$stmt->rowCount() > 0 || true** (123) — sempre true, inclusive em erro de duplicidade; falha real só em PDOException (124-127).
  - removerMembro (134-142): UPDATE ... SET excluido = 1 com **AND associado_id <> (SELECT associado_id FROM agroindustrias WHERE id = ? AND excluido = 0)** (137-140) — protege o proprietário.
  - validarCnpj (280-306): exige 14 dígitos (283-285); rejeita repetição /^(\d)\1+$/ (286-288); pesos [5,4,3,2,9,8,7,6,5,4,3,2] para 12 e [6,5,4,3,2,9,8,7,6,5,4,3,2] para 13 (291-293); soma % 11, dígito = resto < 2 ? 0 : 11 - resto (294-299); confere cnpj[12] com calc(12) (302-304) e cnpj[13] com calc(13) (305).
  - formatarCnpj (270-278) e soDigitos (265-268) — normalização/padronização de CNPJ.
- **Tabelas SQL usadas**: **agroindustrias** (19-23, 37, 44, 78-84, 139, 164-170, 211-217, 248), **associados** (20, 56-58, 258), **agroindustria_membros** (56-59, 81-83, 106-107, 121, 137-140).
- **Ações/POST/redirects**: a classe não redireciona. O chamador minhas_cadastrar.php:41 usa o retorno (int = id ou string = erro) e minhas_editar.php:76/51/63 faz o mesmo para membros; minhas_listar.php:22-24 faz getById + softDelete.
- **Riscos/gaps**:
  1. **Validação de CNPJ não é aplicada aos fornecedores** (esta é a única validação de documento do sistema, e Fornecedor.php não a utiliza) — inconsistência de política de dados.
  2. **adicionarMembro sempre retorna true** (123) — o chamador (minhas_editar.php:51) não consegue distinguir sucesso de falha lógica.
  3. **Escopo de membros sem validação de posse**: adicionarMembro/removerMembro (118-142) não verificam se o solicitante administra a agroindústria; a checagem fica no chamador (minhas_editar.php:45-66).
  4. **Interpolação de IN** em 104-107 (mitigada por intval) e em pontos análogos de outros módulos — padrão de risco a manter sob controle.
  5. **update não altera associado_id** (212-215) mas chama marcarAssociadoComAgroindustria com o valor do POST (237): permite marcar outro associado como tem_agroindustria = 1 sem transferir a titularidade.
  6. **Soft delete sem verificação de dependências** (246-250): agroindústrias excluídas continuam referenciadas por fornecedor_unidades (nome cai para "Unidade #id" em Fornecedor.php:218 e o vínculo é perdido no próximo salvamento), por produtos (produtos.unidade_tipo/unidade_id, migration.sql:1365-1366) e por receitas/ordens (associado_id).
  7. **Sem AuditLog** na classe (nem em create/update/softDelete/membros).
  8. **Dashboard/relatórios: não encontrado no código** — o módulo de dashboard (dashboard_agroindustria.php) não consulta esta classe; não existem métodos de contagem/agregação além de countByAssociado (42-47).

## Síntese do grupo — classes

**Fluxos ponta-a-ponta**

1. **Fornecedor -> insumo -> ordem -> rastreabilidade**
   - Cadastro do fornecedor (quebrado, ver Grupo 1) grava em fornecedores; o vínculo com unidades vai para fornecedor_unidades (Fornecedor.php:248-250).
   - O insumo referencia o fornecedor por **insumos.fornecedor_id** (Insumo.php:119/126 e 173/188), escolhido na tela entre os fornecedores visíveis (modulos/caderno_campo/insumos/cadastrar.php:38-44; 183-190).
   - A ordem de produção da agroindústria (OrdemProducao::create, 101-153) grava o cabeçalho em ordens_producao e cada insumo utilizado em rastreabilidade_insumos_processo (insumo_id + numero_nf + quantidade_utilizada), calculando o status de auditoria em calcularBalanco (64-94, tolerância 10%).
   - A rastreabilidade do fornecedor aparece por join indireto: **insumos.fornecedor_id -> fornecedores** (OrdemProducao.php:52; modulos/agroindustria/rastreabilidade.php:34; classes/ReportConformidade.php:324/390; classes/Relatorio.php:153).
   - **Elo ausente**: ingredientes_receita não referencia insumo nem fornecedor (migration.sql:931-943) — a cadeia fornecedor -> insumo -> receita é textual, sem integridade referencial.
   - **Elo sem escopo**: nenhum dos joins de rastreabilidade filtra o fornecedor pelas unidades visíveis do associado; a visibilidade do fornecedor existe apenas nas telas de listagem/seleção.

2. **Higienização -> Ordem de produção (RF08)**
   - controle_higienizacao criado por higienizacao_cadastrar.php (validação do sanitizante contra insumos_organicos_aprovados categoria='Higienização' permitido=1, 29 registros reais).
   - A OP só é criada depois de passar pelo bloco de ordens_cadastrar.php:149-170, que usa haHigienizacaoValida com o literal 'Qualquer linha' (nunca encontrado, logo sempre false) e depois revalida a janela de 24h em PHP; o parâmetro $associadoId de haHigienizacaoValida é ignorado (ControleHigienizacao.php:38-50) — **higienização de outro associado satisfaz a trava** (comprovado em runtime).
   - O docblock de OrdemProducao::create (96-100) promete a trava dentro da classe, mas ela não existe lá.

3. **Receita -> selo orgânico -> OP**
   - ReceitaAgroindustria::create/update calculam percentual_organico_calculado e selo_autorizado (89-99: >=95% PRODUTO_ORGANICO_BRASIL, >=70% INGREDIENTES_ORGANICOS, senão SEM_SELO) a partir dos pesos convertidos por fatorConversao (47-59).
   - A OP copia esses dois valores da receita (ordens_cadastrar.php:175-176) e os grava em ordens_producao.percentual_organico/selo_autorizado — sem recálculo a partir dos insumos efetivamente usados.

4. **Lote -> produção/estoque**
   - lotes_producao é criado/atualizado por LoteProducao (create 17-50, update 183-200) com UUID aleatório (mt_rand), produto, data e quantidade; o consumo de insumos é derivado das atividades_diarias da lavoura do produto (136-175).
   - **Não há baixa/entrada de estoque**: as tabelas lote_insumo/lote_atividade e as colunas lotes_producao.atividades_id/insumos_id não são usadas pela classe; o estoque permanece em insumos.quantidade_estoque (migration.sql:955), mantido manualmente.

5. **Escopo/permissão (denominador comum)**
   - Níveis: NIVEL_ADMIN=1, NIVEL_GESTOR=2, NIVEL_USUARIO=3 (config.php:23-25); semântica nivel <= required, admin sempre true (User.php:114-141).
   - Fornecedor: leitura escopada por unidade (Fornecedor.php:152-202), escrita de cadastro liberada a qualquer logado (cadastrar.php:18) e de edição liberada com anti-IDOR (editar.php:37-49); exclusão só Gestor (excluir.php:17) — porém por GET sem CSRF.
   - Agroindústria/Receita/Higienização/Ordem/Lote: **nenhuma checagem de posse nas classes**; dependem integralmente de validação nos chamadores, que em vários casos também não existe (OrdemProducao associa associado_id do POST; Receita update/softDelete e Lote create/update/delete sem dono).
   - CSRF: o módulo fornecedor imprime token mas não valida (cadastrar.php:153) e editar.php/excluir.php não têm token; nos módulos agroindustria o padrão correto existe (ex.: minhas_cadastrar.php:24, higienizacao_* e ordens_* são a exceção).

**Tabelas consolidadas do grupo**

| Tabela | Colunas relevantes (uso no código) | Chaves/índices confirmados |
|---|---|---|
| fornecedores | id, nome, razao_social, representante, cnpj, email, telefone, produto_fornecido, endereco, bairro, cidade, estado, status, abrangencia, data_cadastro (**sem coluna excluido**) | PK id; UNIQUE cnpj, UNIQUE email (verificado no banco) |
| fornecedor_unidades | fornecedor_id, unidade_tipo, unidade_id, associado_id, excluido, criado_por, criado_em | UNIQUE (fornecedor_id, unidade_tipo, unidade_id); idx associado_id e (unidade_tipo, unidade_id) |
| insumos | id, nome, lote, tipo_insumo, fornecedor_id, quantidade_estoque, estoq_anterior, quant_recebida, certificacao_insumo, insumo_aprovado_id, excluido | UNIQUE uuid; idx aprovado_organico/tipo_insumo |
| insumos_organicos_aprovados | categoria (enum com 'Higienização'), permitido, nome, subcategoria, restricoes, normativa | 250 linhas; 29 com categoria='Higienização' e permitido=1 |
| receitas_agroindustria | nome_produto_final, descricao, percentual_organico_calculado, selo_autorizado, associado_id, status, excluido, criado_por | status default 'Rascunho' no banco |
| ingredientes_receita | receita_id, nome_ingrediente, eh_organico, quantidade, unidade_medida, proporcao_porcentagem (recebe peso convertido), observacoes | sem FK com insumos/fornecedores |
| ordens_producao | receita_id, higienizacao_previa_id, lote_processado_final, data_processamento, quantidade_final_obtida, unidade_medida, status_auditoria (APROVADO/ALERTA_FRAUDE/EM_ANALISE), percentual_organico, selo_autorizado, associado_id, excluido, criado_por | idx fk_op_higienizacao, idx_op_receita |
| rastreabilidade_insumos_processo | ordem_producao_id, lote_primario_id, produto_id, insumo_id, numero_nf, quantidade_utilizada, unidade_medida | idx_rast_op; sem FK declarada |
| controle_higienizacao | data_hora_inicio, data_hora_fim, equipamento_linha, sanitizante_utilizado, responsavel_nome, associado_id, observacoes, excluido, criado_por | só PK (sem índice em equipamento_linha/data_hora_fim) |
| lotes_producao | uuid, nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes, atividades_id, insumos_id, excluido (+atualizado_em no banco real) | UNIQUE uuid (idx_lotes_uuid) |
| agroindustrias | associado_id, razao_social, nome_fantasia, cnpj, inscricao_estadual, telefone, email, responsavel_tecnico, endereco, numero, bairro, cidade, estado, cep, status, observacoes, excluido | idx associado_id, idx status |
| agroindustria_membros | agroindustria_id, associado_id, excluido, criado_por | núcleo do compartilhamento |
| produtos / lavouras / propriedades | produtos.lavoura_id, produtos.associado_id, produtos.unidade_tipo/unidade_id; lavouras.propriedade_id; propriedades.associado_id (sem excluido) | usados na resolução de lote -> dono |
| atividades_diarias | lavoura_id, lote_id, insumo_utilisado (typo do schema), data_atividade, tipo_atividade, status, excluido | insumo_utilisado é o único vínculo "insumo" do caderno de campo |

**Bugs/riscos críticos consolidados (prioridade de correção)**

1. cadastrar.php:70 chama Fornecedor::vincularUnidades(), método inexistente (fatal error garantido no caminho de sucesso).
2. cadastrar.php:83/115 usa action /fornecedor/cadastrar, rota inexistente no roteador (index.php:739-751) -> 404.
3. Sem validação de CSRF no módulo fornecedor (cadastrar.php só imprime o token; editar.php e excluir.php não têm token).
4. excluir.php:32 faz DELETE físico por GET sem CSRF e sem limpar fornecedor_unidades; UI diz que a exclusão está desabilitada.
5. Fornecedor::substituirUnidades (244-250) soft-deleta vínculos de todos os associados e reescreve associado_id com o do editor.
6. Campo cidade ausente no cadastro (Fornecedor.php:39 x cadastrar.php:90-111) e select cidade disabled no editar (219) pode NULL-ar o valor.
7. Cidade/estado sem FK formal (fornecedores.cidade -> cidades.id, fornecedores.estado -> estados.id) no baseline — integridade só por convenção.
8. Nenhuma validação de CNPJ no fluxo de fornecedor (dados inválidos já gravados: '98765432000100', '45454654546541') e UNIQUE(cnpj) com string vazia gera mensagem enganosa no segundo cadastro sem documento.
9. ControleHigienizacao::haHigienizacaoValida ignora $associadoId (38-50) — cross-associado; 'Qualquer linha' em ordens_cadastrar.php:149 nunca casa.
10. OrdemProducao::create não valida posse de receita/associado e seu docblock promete um bloqueio RF08 que não implementa.
11. ReceitaAgroindustria::update/softDelete, LoteProducao::create/update/delete e ControleHigienizacao::getById/softDelete sem verificação de dono (IDOR de escrita).
12. LoteProducao não movimenta estoque algum; lote_insumo/lote_atividade e lotes_producao.atividades_id/insumos_id não são usados; api/relatorios/visao-geral.php:30 referencia coluna inexistente (status_lote).
13. XSS armazenado potencial em listar.php:117 (json_encode em <script> sem HEX_TAG) e em cadastrar.php:270 (nome de unidade concatenado em HTML via JS).
14. Agroindustria::adicionarMembro sempre retorna true (123); update não altera associado_id mas marca tem_agroindustria do associado do POST (237).
15. Sem trilha de auditoria em todo o módulo fornecedor e nas classes analisadas (contra o padrão AuditLog usado em agroindustria/ordens_cadastrar.php:179 e higienizacao_cadastrar.php:42).
16. Duplicação de SQL de fornecedores na tela (editar.php:104-117) sem método correspondente na classe — regra de negócio em dois lugares.


---


# PARTE 7 — PRODUÇÃO ANIMAL E MANEJO ANIMAL (telas + classes)

> Seção produzida por análise dedicada de `modulos/producao_animal/*` (17 arquivos), `modulos/manejo_animal/*` (4 arquivos) e `classes/{ProducaoAnimal,ManejoAnimal,LoteProducao}` — leitura integral.



---

# Engenharia Reversa — Produção Animal + Manejo Animal (SINPORG / PHP puro + MySQL)

## 0. Contexto transversal (roteamento, guard, CSRF, schema)

### Cadeia de acesso (verificada)
- `.htaccess:16-24` — qualquer URL que não seja arquivo/diretório existente cai em `index.php?url=$1` (RewriteBase /app/).
- `index.php:6-8` — o roteador só entra com `$rawUrl !== ''` e faz `$parts = explode('/', $rawUrl)`.
- Rotas de Produção Animal: `index.php:993-1044`. Rotas de Manejo Animal: `index.php:1046-1058`.
- Rotas de Lote de Produção (vegetal, classe LoteProducao): `index.php:366-383` e `index.php:385-401`.
- Menu lateral: `includes/menu.php:139-158` (bloco "Produção Animal"), exibido só quando `in_array('animal', $areaProducaoAtual)` (`includes/menu.php:16-19`).
- **Não há guard de módulo no roteador**: nenhuma linha de `index.php:993-1058` verifica nível ou área de produção antes do `require`. Cada tela repete apenas `isLoggedIn()`.

### Guard de sessão padrão (idêntico nas 21 telas internas)
```php
$user = new User();
if (!$user->isLoggedIn()) { header("Location: " . SITE_URL . "/login"); exit; }
```
- `classes/User.php:106-108` (isLoggedIn = isset($_SESSION['user_id'])).
- `classes/User.php:114-141` (hasPermission: rejeita nível não numérico/≤0; Admin=1 tudo; senão `$nivel <= $required_level`).
- `classes/User.php:147-149` (isGestorOuAdmin() = hasPermission(NIVEL_GESTOR)).
- `classes/User.php:259-272` (getAssociadoId() — pode retornar **null** quando não há associado_id).
- `classes/User.php:299-311` (getAssociadoIdResolvido() — cobre 4 formas de vínculo; **retorna 0** se nada).
- Constantes: `config/config.php:23-25` (NIVEL_ADMIN=1, NIVEL_GESTOR=2, NIVEL_USUARIO=3).
- Bootstrap: `config/config.php:102` (session_start()); `classes/Database.php:2` faz require config/config.php, logo SITE_URL existe mesmo nas telas que não requerem config diretamente (ex.: painel.php).

### CSRF
- Infra existe: `config/config.php:47` (CSRF_TOKEN_NAME), `classes/CSRFProtection.php`, `window.csrfHeaders()` em `includes/header.php:104`.
- **Nenhuma das 21 telas de produção/manejo animal usa CSRF**: grep `csrf` em `modulos/producao_animal` = 0 ocorrências; em `modulos/manejo_animal` = 0 ocorrências. Todos os POST e todas as exclusões por GET são forjáveis.

### Auditoria
- `classes/AuditLog.php:13-27` — registrar($acao, $tabela, $registro_id, $descricao) → INSERT INTO audit_log (user_id, acao, tabela, registro_id, descricao, ip).

---

## 1. modulos/producao_animal/

### modulos/producao_animal/painel.php
- **Rota/acesso**: `index.php:994-996` (/producao-animal → painel.php); menu em `includes/menu.php:145`. Guard: apenas isLoggedIn() (`painel.php:8-11`). Sem hasPermission para exibir; NIVEL_GESTOR só define o escopo dos números (`painel.php:13-18`).
- **Propósito de negócio**: dashboard do rebanho e checklist de conformidade com a Portaria MAPA 52/2021 (Título III).
- **Campos/formulário**: nenhum (tela somente leitura).
- **Regras/validações/cálculos**: `$isGestor ? null : $assoc_id` (`painel.php:15-18`) — se getAssociadoId() retornar null, estatisticas(null) soma **todos** os produtores. 8 cards montados em `painel.php:20-29`: ativos, total, lotes, cabeças em lotes, sanidade, reprodução, "Sendo orgânicos", convencionais. Cards de conformidade em `painel.php:60-62`: nutrição 15% MS ruminantes / 20% não ruminantes; ambiente arts. 38-44; sanidade carência dobrada ≥96h e máx. 2 tratamentos/ano.
- **Tabelas SQL usadas**: via ProducaoAnimal::estatisticas() (`classes/ProducaoAnimal.php:451-502`): animais (460-468), animal_lotes (470-472), controle_sanidade_animal (474-476), controle_reproducao_animal (478-480), manejo_animal_registros JOIN animais (482-487).
- **Ações/POST/redirects**: nenhuma; links para /manejo-animal, /producao-animal/lotes, /sanidade, /reproducao e botões rápidos `painel.php:71-76`.
- **Riscos/gaps**: (a) card **"Sendo orgânicos" é hardcoded '—'** (`painel.php:27`) enquanto o valor existe no banco — o indicador nunca é calculado; (b) `$where = " WHERE excluido = 0"` é reutilizado em 4 tabelas diferentes (`classes/ProducaoAnimal.php:453-480`) — funciona porque todas têm excluido/associado_id, mas acopla o filtro de associado ao esquema; (c) contagem de espécies por LIKE '%bovino%' em campo de texto livre (`462-464`) — animal gravado como "Bovinos" ou "BOI" não conta; (d) escopo por getAssociadoId() (null ⇒ global) é vazamento de indicadores entre produtores.

### modulos/producao_animal/lotes_cadastrar.php
- **Rota/acesso**: `index.php:1000-1002` (/producao-animal/lote/novo); link em `lotes_listar.php:36` e `painel.php:72`. Guard: isLoggedIn() `lotes_cadastrar.php:10-13`. Sem nível, sem CSRF.
- **Propósito de negócio**: criar lote de animais (animal_lotes), usado como agrupador de manejo e como unidade de rastreabilidade (etiqueta QR).
- **Campos/formulário**: nome_lote text **required** (`57`); especie select com ['BOVINOS','BUBALINOS','OVINOS','CAPRINOS','SUINOS','AVES','EQUINOS','APICULTURA'] (`61-66`); categoria text livre, placeholder "recria, terminação, matrizes" (`70`); quantidade number min=0 (`74`); descricao textarea (`78`).
- **Regras/validações/cálculos**: só `trim($dados['nome_lote']) === ''` (`22-24`). associado_id e criado_por são **forçados da sessão** (`26-27`).
- **Tabelas SQL usadas**: INSERT INTO animal_lotes (associado_id, nome_lote, especie, categoria, quantidade, descricao, criado_por) — `classes/ProducaoAnimal.php:41-55`.
- **Ações/POST/redirects**: POST → AuditLog::registrar('PA_LOTE_CADASTRADO','animal_lotes',$id,'Lote: '.$nome) (`29`) → redirect /producao-animal/lotes?cadastrado=1 (`30-31`).
- **Riscos/gaps**: sem CSRF; sem verificação de nível (qualquer usuário logado, inclusive nível 3 de outro produtor, cria lotes); para gestor/admin getAssociadoId() pode ser null ⇒ lote órfão (associado_id NULL, coluna nullable em `sinporg/prisma/migrations/0_baseline/migration.sql:216`); quantidade é digitada à mão e **não é sincronizada** com o número real de animais do lote (a listagem mostra a contagem via subquery, não a coluna).

### modulos/producao_animal/lotes_editar.php
- **Rota/acesso**: `index.php:1003-1005` (/producao-animal/lote/{id}/editar, $_GET['id'] injetado pelo roteador); link `lotes_listar.php:65`. Guard: isLoggedIn() `10-13`; id<=0 → volta (`15-19`); lote inexistente → volta (`22-26`).
- **Guard anti-IDOR (presente)**: `28-36` — isGestorOuAdmin() OU (int)$lote['associado_id'] === (int)$user->getAssociadoIdResolvido() (exige donoLote > 0).
- **Campos/formulário**: idênticos ao cadastro — nome_lote required (`75`), especie (`79-84`), categoria (`88`), quantidade (`92`), descricao (`96`).
- **Regras/validações/cálculos**: nome obrigatório (`42-44`).
- **Tabelas SQL usadas**: UPDATE animal_lotes SET nome_lote, especie, categoria, quantidade, descricao WHERE id — `classes/ProducaoAnimal.php:59-70`. **Não altera associado_id** (dono imutável) nem excluido.
- **Ações/POST/redirects**: POST → audit PA_LOTE_ATUALIZADO (`46`) → redirect ?atualizado=1 (`47`); em erro, `$lote = array_merge($lote, $_POST)` para reexibir (`53`).
- **Riscos/gaps**: sem CSRF; $_POST inteiro é repassado ao atualizarLote (campos extras ignorados pelo SQL, sem whitelist explícita); a checagem de posse usa getAssociadoIdResolvido() enquanto as listagens usam getAssociadoId() — critérios divergentes.

### modulos/producao_animal/lotes_listar.php
- **Rota/acesso**: `index.php:997-999` (/producao-animal/lotes); menu `includes/menu.php:148`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: listar lotes com contagem de animais, gerar QR de rastreabilidade e imprimir etiqueta.
- **Campos/formulário**: nenhum (tabela + botões).
- **Regras/validações/cálculos**: `$escopo = $user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null)` (`26`) — **se getAssociadoId() for null, o filtro vira null e a listagem retorna lotes de TODOS os produtores**. Código público do lote: 'AL-' . str_pad($id, 6, '0', STR_PAD_LEFT) (`57`, `121`); URL do QR = SITE_URL/producao-animal/rastreabilidade?lote={id} (`55`, `82`).
- **Tabelas SQL usadas**: SELECT l.*, (SELECT COUNT(*) FROM animais a WHERE a.lote_id = l.id AND a.excluido = 0) AS animais_total FROM animal_lotes l WHERE l.excluido = 0 [AND l.associado_id = ?] ORDER BY l.criado_em DESC — `classes/ProducaoAnimal.php:19-29`.
- **Ações/POST/redirects**: exclusão por **GET** `?excluir=id` (`17-24`) → UPDATE animal_lotes SET excluido = 1 WHERE id = ? (`classes/ProducaoAnimal.php:75`) → audit PA_LOTE_EXCLUIDO (`20`) → redirect (`22`). Impressão de etiqueta 60×90 mm via window.open + qrcodejs CDN (`77-150`).
- **Riscos/gaps**: **(1) IDOR de exclusão** — o `?excluir=` não verifica nível nem posse (nenhuma comparação com associado_id em `17-24`) ⇒ qualquer usuário logado apaga (soft delete) lotes de qualquer produtor; **(2) sem CSRF** — exclusão por GET é disparável por tag img; (3) QR aponta para ?lote=id sequencial (ver rastreabilidade.php); (4) a etiqueta imprime o selo fixo "ORGÂNICO" (`128`) independentemente de o lote conter animais CONVENCIONAL ou EM_CONVERSAO; (5) data-rastreio-url + innerText no JS (`92-98`) montam a etiqueta sem escapar, mas o conteúdo vem de htmlspecialchars na célula (risco residual baixo).

### modulos/producao_animal/ambientes_cadastrar.php
- **Rota/acesso**: `index.php:1036-1038` (/producao-animal/ambiente/novo); link `ambientes_listar.php:36`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: cadastrar instalação/ambiente de criação (ambiente_criacao_animal) conforme arts. 38-44 (acesso a área externa, sombreamento, densidade máxima).
- **Campos/formulário**: nome_instalacao text **required** (`62`); especie select ENUM 8 valores (`66-71`); sistema select ['EXTENSIVO','SEMI_EXTENSIVO','INTENSIVO','ROTACIONADO'] (`75-79`); tipo_instalacao text (`83`); area_total_m2 number step 0.01 min 0 (`87`); quantidade_animais number min 0 (`91`); densidade_referencia number step 0.01 **min 0** (`95`, texto auxiliar "m²/animal"); tem_sombreamento_abrigo checkbox value=1 (`111`); observacoes textarea (`117`).
- **Regras/validações/cálculos**: só nome_instalacao (`27-29`). **Nenhum cálculo de lotação/ocupação** (não há area_total_m2 / quantidade_animais nem comparação com densidade_referencia em nenhum ponto do arquivo). getDensidades() é chamado em `16-20` para montar a lista de referência normativa (`102-105`) — mas ver risco (a).
- **Tabelas SQL usadas**: INSERT INTO ambiente_criacao_animal (associado_id, nome_instalacao, especie, sistema, tipo_instalacao, area_total_m2, quantidade_animais, densidade_referencia, tem_sombreamento_abrigo, observacoes, criado_por) — `classes/ProducaoAnimal.php:384-404`; SELECT * FROM densidades_maximas_animais WHERE ativo = 1 [AND especie = ?] ORDER BY especie, fase — `classes/ProducaoAnimal.php:437-446`.
- **Ações/POST/redirects**: POST → audit PA_AMBIENTE_CADASTRADO (`34`) → redirect /producao-animal/ambientes?cadastrado=1 (`35`).
- **Riscos/gaps**: **(a) BUG de ordem de execução**: `16-20` usam $dados mas `$dados = $_POST;` só é atribuído em `24` ⇒ o filtro por espécie nunca aplica (toda a lista é carregada) e em PHP 8 gera warning de variável indefinida; (b) densidades_maximas_animais **não possui seed em nenhum SQL do repositório** (só o DDL em `sinporg/prisma/migrations/0_baseline/migration.sql:727-738`) ⇒ o bloco de referências renderiza lista vazia em produção; (c) sem CSRF; (d) sem cálculo de densidade real; (e) tem_sombreamento_abrigo é a única verificação de bem-estar, e é opcional.

### modulos/producao_animal/ambientes_editar.php
- **Rota/acesso**: `index.php:1039-1041` (/producao-animal/ambiente/{id}/editar); link `ambientes_listar.php:68`. Guard: isLoggedIn() `10-13`; id/existência `15-26`.
- **Guard anti-IDOR (presente)**: `28-36` — gestor/admin ou associado_id === getAssociadoIdResolvido() (exige dono > 0).
- **Campos/formulário**: nome_instalacao required (`77`), especie (`81-86`), sistema (`90-94`), tipo_instalacao (`98`), area_total_m2 (`102`), quantidade_animais (`106`), densidade_referencia (`110`), tem_sombreamento_abrigo checkbox (`116`), observacoes (`132`).
- **Regras/validações/cálculos**: nome obrigatório (`44-46`); densidades filtradas pela espécie do ambiente (`38`). Nenhum cálculo de ocupação.
- **Tabelas SQL usadas**: UPDATE ambiente_criacao_animal SET nome_instalacao, especie, sistema, tipo_instalacao, area_total_m2, quantidade_animais, densidade_referencia, tem_sombreamento_abrigo, observacoes WHERE id — `classes/ProducaoAnimal.php:409-424` (não altera associado_id).
- **Ações/POST/redirects**: audit PA_AMBIENTE_ATUALIZADO (`48`) → ?atualizado=1 (`49`).
- **Riscos/gaps**: sem CSRF; **checkbox desmarcado zera o campo**: `isset($dados['tem_sombreamento_abrigo']) ? (int)... : 0` (`classes/ProducaoAnimal.php:421`) — uma edição que não reenvie o switch desliga o sombreamento silenciosamente; especie pode ser trocada sem reavaliar as densidades já gravadas.

### modulos/producao_animal/ambientes_listar.php
- **Rota/acesso**: `index.php:1033-1035`; menu `includes/menu.php:156`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: listar instalações com área, nº de animais, densidade de referência e flag de sombra/abrigo.
- **Campos/formulário**: nenhum.
- **Regras/validações/cálculos**: $escopo em `26` (mesmo padrão de risco: getAssociadoId() null ⇒ lista global). Colunas em `52` e densidade_referencia impressa crua (`65`). Nenhum cálculo de ocupação em % ou m²/animal.
- **Tabelas SQL usadas**: SELECT * FROM ambiente_criacao_animal WHERE excluido = 0 [AND associado_id = ?] ORDER BY criado_em DESC — `classes/ProducaoAnimal.php:363-372`.
- **Ações/POST/redirects**: exclusão por GET `?excluir=id` (`17-24`) → UPDATE ambiente_criacao_animal SET excluido = 1 WHERE id = ? (`classes/ProducaoAnimal.php:429`) → audit PA_AMBIENTE_EXCLUIDO → redirect.
- **Riscos/gaps**: IDOR de exclusão (sem posse/nível, `17-24`); sem CSRF; tipo_instalacao exibido sem sanitização adicional (já vem de htmlspecialchars, `60`); sem alerta quando quantidade_animais excede a capacidade implícita (area_total_m2 / densidade_referencia) — a checagem não existe em lugar nenhum.

### modulos/producao_animal/alimentacao_cadastrar.php
- **Rota/acesso**: `index.php:1009-1011` (/producao-animal/alimentacao/novo); link `alimentacao_listar.php:36` e `painel.php:73`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: registro de alimentação (arts. 34-37) por animal ou por lote, com origem orgânica/conversão/exceção.
- **Campos/formulário**: data_alimentacao date **required** (`68`, default date('Y-m-d')); tipo_alimento select **required** ['FORRAGEM','CONCENTRADO','SUPLEMENTO_MINERAL','ADITIVO_TECNOLOGICO','LEITE_COLOSTRO','APICULTURA'] (`72-76`); origem select **required** ORGANICO|EM_CONVERSAO|NAO_ORGANICO_AUTORIZADO (`80-84`); animal_id select (`88-93`); lote_id select (`97-102`); produto_utilizado text (`106`); quantidade_kg number step 0.01 min 0 (`110`); percentual_materia_seca number step 0.01 min 0 **max 100** (`114`); observacoes textarea (`119`).
- **Regras/validações/cálculos**: data obrigatória (`26-28`); **se origem === 'NAO_ORGANICO_AUTORIZADO' e observacoes vazio ⇒ erro** "descreva a escassez/necessidade e a autorização no PMO" (`30-34`). **Nenhum cálculo** de kg/animal/dia, de % de MS não orgânica acumulada no período, de custo ou de consumo de estoque. Os limites 15%/20% MS aparecem apenas como form-text no HTML (`115`).
- **Tabelas SQL usadas**: INSERT INTO controle_alimentacao_animal (associado_id, animal_id, lote_id, data_alimentacao, tipo_alimento, produto_utilizado, quantidade_kg, percentual_materia_seca, origem, observacoes, criado_por) — `classes/ProducaoAnimal.php:138-156`. Listas: getAll (animais, `classes/ManejoAnimal.php:15-28`) e getLotes (`classes/ProducaoAnimal.php:19-29`).
- **Ações/POST/redirects**: audit PA_ALIMENTACAO_CADASTRADA (`39`) → ?cadastrado=1 (`40`).
- **Riscos/gaps**: sem CSRF; **sem validação de posse de animal_id/lote_id** — o select só lista itens do escopo, mas o POST aceita qualquer id (o INSERT não revalida: `classes/ProducaoAnimal.php:145-146`) ⇒ registro de alimentação pode ser vinculado a animal/lote de outro produtor; quantidade_kg é total, não normalizado por cabeça/dia (o foco "kg/animal/dia" **não existe no código**); não há vínculo com insumos/estoque (grep custo|estoque = 0 ocorrências em `modulos/producao_animal` e em `classes/ProducaoAnimal.php`).

### modulos/producao_animal/alimentacao_editar.php
- **Rota/acesso**: `index.php:1012-1014` (/producao-animal/alimentacao/{id}/editar); link `alimentacao_listar.php:75`. Guard: isLoggedIn() `10-13`; id/existência `15-26`.
- **Guard anti-IDOR**: **ausente**. getAlimentacaoById($id) não filtra associado_id (`classes/ProducaoAnimal.php:130-131`) e `28-30` apenas carregam listas de escopo, sem comparar dono.
- **Campos/formulário**: mesmos do cadastro — data_alimentacao (`72`), tipo_alimento (`76-80`), origem (`84-88`), animal_id (`92-97`), lote_id (`101-106`), produto_utilizado (`110`), quantidade_kg (`114`), percentual_materia_seca (`118`), observacoes (`123`).
- **Regras/validações/cálculos**: data obrigatória (`36-38`); exceção não orgânica exige observações (`39-41`).
- **Tabelas SQL usadas**: UPDATE controle_alimentacao_animal SET animal_id, lote_id, data_alimentacao, tipo_alimento, produto_utilizado, quantidade_kg, percentual_materia_seca, origem, observacoes WHERE id — `classes/ProducaoAnimal.php:162-177` (não altera associado_id).
- **Ações/POST/redirects**: audit PA_ALIMENTACAO_ATUALIZADA (`43`) → ?atualizado=1 (`44`).
- **Riscos/gaps**: **IDOR de leitura+escrita** (qualquer autenticado edita registro de qualquer produtor); pode-se trocar animal_id/lote_id para ids de terceiros; sem CSRF.

### modulos/producao_animal/alimentacao_listar.php
- **Rota/acesso**: `index.php:1006-1008`; menu `includes/menu.php:150`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: listar controles de alimentação com origem (badge) e %MS.
- **Campos/formulário**: nenhum.
- **Regras/validações/cálculos**: $escopo em `26` (null ⇒ global); exibição animal **ou** lote: `$r['nome_animal'] ?? 'Lote: '.$r['nome_lote']` (`62`) — se houver animal e lote, mostra o animal; badges de origem mapeados em `68-72`.
- **Tabelas SQL usadas**: SELECT c.*, a.nome_animal, a.especie AS especie_animal, l.nome_lote FROM controle_alimentacao_animal c LEFT JOIN animais a ON a.id = c.animal_id LEFT JOIN animal_lotes l ON l.id = c.lote_id WHERE c.excluido = 0 [AND c.associado_id = ?] ORDER BY c.data_alimentacao DESC — `classes/ProducaoAnimal.php:112-125`.
- **Ações/POST/redirects**: exclusão por GET `?excluir=` (`17-24`) → UPDATE controle_alimentacao_animal SET excluido = 1 WHERE id = ? (`classes/ProducaoAnimal.php:182`).
- **Riscos/gaps**: IDOR de exclusão; sem CSRF; sem custo total, sem somatório de kg/MS por período (não há agregação nenhuma na tela).

### modulos/producao_animal/reproducao_cadastrar.php
- **Rota/acesso**: `index.php:1027-1029` (/producao-animal/reproducao/novo); `reproducao_listar.php:36`, `painel.php:75`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: registrar evento reprodutivo (controle_reproducao_animal): cobertura, inseminação, parto, nascimento, desmame.
- **Campos/formulário**: tipo_evento select **required** ['COBERTURA','INSEMINACAO','PARTO','NASCIMENTO','DESMAME'] (`75-79`); data_evento date **required** (`83`); animal_id select "Animal (mãe/fêmea)" (`87-92`); macho_reprodutor_id **text** ("Identificação do Reprodutor") (`95-96`); previsao_parto date (`100`); grupo_reprodutivo text (`104`); nascidos_vivos number min 0 (`108`); natimortos number min 0 (`112`); periodo_aleitamento_dias number min 0 (`116`); especie select (só para validação, `121-126`); observacoes textarea (`130`).
- **Regras/validações/cálculos**: data obrigatória (`25-27`). **Regra de aleitamento mínimo** (`29-41`): se tipo_evento === 'DESMAME' e periodo_aleitamento_dias > 0: `$min = 90`; OVINOS|CAPRINOS ⇒ 28; SUINOS ⇒ 45; sem espécie ⇒ `$min = 0` (regra desativada); se aleitamento < $min ⇒ bloqueio com mensagem. **Não existe** validação cruzada de data (previsão de parto vs. data da cobertura), diagnóstico de gestação, intervalo entre partos ou DEL (grep diagnostico|gestacao|intervalo|lacta em `modulos/producao_animal` = 0 ocorrências; em `classes/ProducaoAnimal.php` = 0).
- **Tabelas SQL usadas**: INSERT INTO controle_reproducao_animal (associado_id, animal_id, grupo_reprodutivo, tipo_evento, data_evento, macho_reprodutor_id, previsao_parto, nascidos_vivos, natimortos, periodo_aleitamento_dias, observacoes, criado_por) — `classes/ProducaoAnimal.php:309-328`. **especie não é persistida** (não consta no INSERT nem no DDL).
- **Ações/POST/redirects**: audit PA_REPRODUCAO_CADASTRADA (`46`) → ?cadastrado=1 (`47`).
- **Riscos/gaps**: **(a) campo "Espécie" é usado só para a validação e descartado** — o dado não é gravado; **(b) incompatibilidade de tipo**: macho_reprodutor_id é INTEGER NULL no banco (`sinporg/prisma/migrations/0_baseline/migration.sql:666`) mas o formulário é texto livre e a classe grava trim($dados['macho_reprodutor_id']) como string (`classes/ProducaoAnimal.php:320`) ⇒ em STRICT_TRANS_TABLES o INSERT falha ("Incorrect integer value") quando o usuário digita um nome; (c) sem CSRF; (d) animal_id sem validação de posse; (e) **não há DIAGNOSTICO_GESTACAO** no ENUM (`migration.sql:664`) nem na UI; (f) o formulário não vincula o evento a lote_id (o DDL não tem a coluna) — ver bug em rastreabilidade.php.

### modulos/producao_animal/reproducao_editar.php
- **Rota/acesso**: `index.php:1030-1032` (/producao-animal/reproducao/{id}/editar); link `reproducao_listar.php:72`. Guard: isLoggedIn() `10-13`; id/existência `15-26`.
- **Guard anti-IDOR**: **ausente** — getReproducaoById não filtra associado (`classes/ProducaoAnimal.php:301-302`) e `28-29` só carregam a lista de animais.
- **Campos/formulário**: idênticos ao cadastro (`83-139`).
- **Regras/validações/cálculos**: mesma regra de desmame (`39-50`; mensagem sem os números das espécies, diferente do cadastro, `47`).
- **Tabelas SQL usadas**: UPDATE controle_reproducao_animal SET animal_id, grupo_reprodutivo, tipo_evento, data_evento, macho_reprodutor_id, previsao_parto, nascidos_vivos, natimortos, periodo_aleitamento_dias, observacoes WHERE id — `classes/ProducaoAnimal.php:334-350`.
- **Ações/POST/redirects**: audit PA_REPRODUCAO_ATUALIZADA (`53`) → ?atualizado=1 (`54`).
- **Riscos/gaps**: IDOR total; sem CSRF; mesmo problema de tipo de macho_reprodutor_id; edição permite transformar um PARTO sem periodo_aleitamento_dias sem qualquer consistência.

### modulos/producao_animal/reproducao_listar.php
- **Rota/acesso**: `index.php:1024-1026`; menu `includes/menu.php:154`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: listar eventos reprodutivos com vivos/natimortos, aleitamento e previsão de parto.
- **Campos/formulário**: nenhum.
- **Regras/validações/cálculos**: $escopo em `26`; coluna vivos/natimortos: mostra nascidos_vivos e, se natimortos > 0, também natimortos (`65-67`). Aviso normativo de aleitamento (90/28/45 dias) em `39-42`. **Não há** cálculo de taxa de concepção, intervalo entre partos (IEP), período de serviço ou dias em lactação — "não encontrado no código".
- **Tabelas SQL usadas**: SELECT c.*, a.nome_animal, a.especie AS especie_animal FROM controle_reproducao_animal c LEFT JOIN animais a ON a.id = c.animal_id WHERE c.excluido = 0 [AND c.associado_id = ?] ORDER BY c.data_evento DESC — `classes/ProducaoAnimal.php:284-296`.
- **Ações/POST/redirects**: exclusão por GET (`17-24`) → UPDATE controle_reproducao_animal SET excluido = 1 WHERE id = ? (`classes/ProducaoAnimal.php:355`).
- **Riscos/gaps**: IDOR de exclusão; sem CSRF; nenhuma métrica reprodutiva derivada (o foco "intervalo entre partos / dias em lactação" não está implementado); macho_reprodutor_id é exibido cru (`63`) o que mascara o problema de tipo.

### modulos/producao_animal/sanidade_cadastrar.php
- **Rota/acesso**: `index.php:1018-1020` (/producao-animal/sanidade/novo); `sanidade_listar.php:36`, `painel.php:74`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: registro sanitário (arts. 59-63): vacinação, terapêutico, controle parasitário, preventivo, exame; com tratamento excepcional e carência.
- **Campos/formulário**: data_tratamento date **required** (`71`); tipo_tratamento select **required** ['VACINACAO','TRATAMENTO_TERAPEUTICO','CONTROLE_PARASITARIO','PREVENTIVO','EXAME_LABORATORIAL'] (`75-79`); produto_normatizado **checkbox** value=1 "Produto consta do Anexo II" (`84`); produto_utilizado text (`90`); motivo text (`94`); animal_id select (`98-103`); lote_id select (`107-112`); dose_quantidade **text** (`116`); via_aplicacao text (`120`); responsavel text (`124`); periodo_carencia_horas number min 0 (`128`); data_liberacao_organica date (`133`); aprovado_ocs select vazio/1/0 (`137-141`); numero_tratamento number min 1 default 1 (`145`); observacoes textarea (`150`).
- **Regras/validações/cálculos**: `$excepcional = !isset($dados['produto_normatizado']) || (int)$dados['produto_normatizado'] !== 1` (`29`); `$carencia = (int)$dados['periodo_carencia_horas'] ?: 0` (`30`); se excepcional e carência ≤ 0 ⇒ erro (`32-34`); se excepcional e carência < 96 ⇒ erro (`35-37`). **Não há** validação de aprovado_ocs, **não há contagem** de tratamentos por animal/ano e **não há** cálculo de data_liberacao_organica (é digitada manualmente; nenhuma fórmula data_tratamento + carência no arquivo).
- **Tabelas SQL usadas**: INSERT INTO controle_sanidade_animal (associado_id, animal_id, lote_id, data_tratamento, tipo_tratamento, produto_utilizado, motivo, dose_quantidade, via_aplicacao, responsavel, produto_normatizado, periodo_carencia_horas, data_liberacao_organica, aprovado_ocs, numero_tratamento, observacoes, criado_por) — `classes/ProducaoAnimal.php:216-242` (17 colunas / 17 placeholders).
- **Ações/POST/redirects**: audit PA_SANIDADE_CADASTRADA (`42`) → ?cadastrado=1 (`43`).
- **Riscos/gaps**: (a) limite "**Nº do Tratamento** (máx. 2/ano)" é **apenas form-text** (`146`) — não há SELECT COUNT(*) de tratamentos do animal no ano em nenhum lugar; (b) aprovado_ocs não é exigido para tratamento excepcional (só a carência); (c) "carência dobrada" é mencionada na UI (`sanidade_listar.php:41-42`) mas não é calculada — só o piso de 96h é checado; (d) sem CSRF; (e) animal_id/lote_id sem revalidação de posse no INSERT (`classes/ProducaoAnimal.php:225-226`); (f) sem catálogo de vacinas/lote de vacina/validade/reforço — não encontrado no código; (g) carência em horas enquanto a norma é em dias (sem conversão).

### modulos/producao_animal/sanidade_editar.php
- **Rota/acesso**: `index.php:1021-1023` (/producao-animal/sanidade/{id}/editar); link `sanidade_listar.php:75`. Guard: isLoggedIn() `10-13`; id/existência `15-26`.
- **Guard anti-IDOR**: **ausente** — getSanidadeById não filtra associado (`classes/ProducaoAnimal.php:208-209`); `28-30` carregam apenas listas.
- **Campos/formulário**: iguais ao cadastro (`77-156`). Checkbox com default diferente: `(int)($registro['produto_normatizado'] ?? 1) === 1` (`90`) — pré-marcado quando NULL.
- **Regras/validações/cálculos**: data obrigatória (`36-38`); excepcional ⇒ carencia < 96 bloqueia (`40-45`). **Nota**: na edição, o teste é só < 96 (o cadastro também valida <= 0, `sanidade_cadastrar.php:32-37`) — assimetria de validação.
- **Tabelas SQL usadas**: UPDATE controle_sanidade_animal SET ... WHERE id — `classes/ProducaoAnimal.php:248-271`.
- **Ações/POST/redirects**: audit PA_SANIDADE_ATUALIZADA (`47`) → ?atualizado=1 (`48`).
- **Riscos/gaps**: IDOR; sem CSRF; desmarcar o switch em edição grava produto_normatizado = 0 (`classes/ProducaoAnimal.php:264`) e converte um registro conforme em "excepcional" sem exigir aprovado_ocs; numero_tratamento segue sem enforcement.

### modulos/producao_animal/sanidade_listar.php
- **Rota/acesso**: `index.php:1015-1017`; menu `includes/menu.php:152`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: listar tratamentos com marcação Anexo II × Excepcional, carência e data de liberação orgânica.
- **Campos/formulário**: nenhum.
- **Regras/validações/cálculos**: $escopo (`26`); badge produto_normatizado → "Anexo II" / "Excepcional" (`65-71`). Aviso normativo com "carência dobrada, mínimo 96 horas, máx. 2 tratamentos/ano" (`40-43`).
- **Tabelas SQL usadas**: SELECT c.*, a.nome_animal, a.especie AS especie_animal, l.nome_lote FROM controle_sanidade_animal c LEFT JOIN animais a ON a.id = c.animal_id LEFT JOIN animal_lotes l ON l.id = c.lote_id WHERE c.excluido = 0 [AND c.associado_id = ?] ORDER BY c.data_tratamento DESC — `classes/ProducaoAnimal.php:190-203`.
- **Ações/POST/redirects**: exclusão por GET (`17-24`) → UPDATE controle_sanidade_animal SET excluido = 1 WHERE id = ? (`classes/ProducaoAnimal.php:276`).
- **Riscos/gaps**: IDOR de exclusão; sem CSRF; **não exibe** dose_quantidade, via_aplicacao, responsavel, numero_tratamento nem aprovado_ocs (8 colunas no total, `53`) — dados de prescrição existem no banco mas não aparecem na lista; nenhum alerta de carência vigente (não há comparação data_liberacao_organica vs. hoje em nenhum arquivo).

### modulos/producao_animal/rastreabilidade.php
- **Rota/acesso**: `index.php:1042-1044` (/producao-animal/rastreabilidade). **Página pública**: `rastreabilidade.php:2` comenta "Não requer login" e o arquivo **não** instancia User nem checa sessão (só config e Database, `3-8`). Acessada pelo QR impresso (`lotes_listar.php:82`) e pelo formulário GET interno (`295-298`).
- **Propósito de negócio**: consulta pública de origem/rastreabilidade do lote animal: produtor (CNPO), composição, alimentação, sanidade e reprodução.
- **Campos/formulário**: GET lote (int, `6`); GET uuid (texto, `7`) — o "código de rastreabilidade" é comparado com CONCAT('AL-', LPAD(al.id, 6, '0')) (`19-24`), ou seja **não é um UUID**: é o ID numérico formatado e sequencial; formulário público name="uuid" com placeholder "Ex.: AL-000001" e required autofocus (`296`).
- **Regras/validações/cálculos**: se $uuid !== '' busca por código (`17-26`); senão, se $lote_id > 0, busca por id (`27-35`); rótulos tipoEntradaLabels (`78-83`), origemLabels (`84-88`), tipoAlimentoLabels (`89-96`); exibição "Quantidade de Animais" = coluna animal_lotes.quantidade digitada à mão (`144`), **não** a contagem real (COUNT(animais)); selo fixo "Orgânico"/"Em conformidade orgânica" (`123`, `148`) sem nenhuma verificação de origem_conversao dos animais; data/hora de emissão fixa no rodapé (`167`).
- **Tabelas SQL usadas**: animal_lotes LEFT JOIN associados (`19-23` e `28-32`, traz registro_cnpo); animais WHERE lote_id = ? AND (excluido IS NULL OR excluido = 0) (`41-42`); controle_alimentacao_animal WHERE lote_id = ? (`49-51`); controle_sanidade_animal WHERE lote_id = ? (`59-61`); controle_reproducao_animal WHERE lote_id = ? (`69-71`).
- **Ações/POST/redirects**: nenhuma (somente GET); modal com iframe do PDF /doc/Guia-do-Produtor-Organico-CI-Organicos-SNA-OrganicsNet.pdf (`321`, `324`); Bootstrap via CDN (`104`, `331`).
- **Riscos/gaps**: **(1) BUG confirmado**: controle_reproducao_animal **não possui coluna lote_id** (`sinporg/prisma/migrations/0_baseline/migration.sql:659-679`) ⇒ a query de `rastreabilidade.php:69-72` lança PDOException, engolida pelo catch (Exception $e) vazio (`75`) ⇒ **a seção "Reprodução" nunca é exibida** e o erro fica silencioso (o mesmo padrão de catch mudo ocorre em `45`, `55`, `65`, mascarando qualquer falha de esquema); **(2) exposição pública enumerável**: ?lote=1..N revela nome do produtor, registro_cnpo, espécie/categoria e histórico sanitário/nutricional sem autenticação, sem rate-limit e sem token — o "código" AL-00000N é derivado do ID; (3) **sem SISBOV/NIF**: grep sisbov e NIF em *.sql e `classes/*.php` = 0 ocorrências; o único identificador animal é animais.identificacao ("brinco/tag", `185`), sem unicidade no DDL (`migration.sql:193`); (4) **sem genealogia**: pai_id/mae_id/genealog* = 0 ocorrências; (5) **sem movimentações** de animais entre lotes (não há tabela/histórico de movimentação; animais.lote_id é apenas o vínculo atual); (6) não exibe produto_normatizado, numero_tratamento, dose_quantidade, via_aplicacao nem aprovado_ocs embora os selecione (`59-61`) — dados de prescrição/carência incompletos no comprovante público.

### Síntese do grupo (modulos/producao_animal)
**Fluxo ponta-a-ponta**
1. Login → `index.php:11-14` → $_SESSION['user_id']/['user_nivel'] (`classes/User.php:95-98`) → menu filtra por areaProducao() (`includes/menu.php:16-19`) mas o roteador não filtra: acesso direto por URL só depende de isLoggedIn().
2. Painel (`index.php:994-996`) soma KPIs via ProducaoAnimal::estatisticas() (`classes/ProducaoAnimal.php:451-502`) e entrega os atalhos para lote/animal/alimentação/sanidade/reprodução/ambiente (`painel.php:71-76`).
3. Animal (manejo-animal) → opcionalmente vinculado a um lote (animal_lotes, `classes/ManejoAnimal.php:76-79`) → lotes agregam a contagem real por subquery (`classes/ProducaoAnimal.php:19`).
4. Lote → QR AL-0000NN (`lotes_listar.php:57`) → URL pública /producao-animal/rastreabilidade?lote=id (`lotes_listar.php:55`) → rastreabilidade.php consolida animais + alimentação + sanidade do lote (`41-65`); reprodução **quebra** por falta de lote_id (`69-75`).
5. Alimentação/sanidade/reprodução gravam com associado_id da sessão (`alimentacao_cadastrar.php:36`, `sanidade_cadastrar.php:39`, `reproducao_cadastrar.php:43`) e criado_por de $_SESSION['user_id'].
6. Ambientes são cadastrados isoladamente (`ambientes_cadastrar.php`); **não existe vínculo ambiente↔lote/animal** em nenhuma tabela deste escopo (ambiente_criacao_membros existe no DDL mas **não é referenciada por nenhum arquivo PHP de produção animal**).
7. Exclusões são todas soft delete, mas via **GET** e sem posse (lotes, ambientes, alimentação, sanidade, reprodução).

**Tabelas de banco consolidadas**
| Tabela | Colunas-chave | DDL | Uso nas telas |
|---|---|---|---|
| animal_lotes | id, associado_id, nome_lote, especie, categoria, quantidade, descricao | migration.sql:214-228 | lotes_*, rastreabilidade, painel, selects de animal |
| animais | id, associado_id, nome_animal, especie, raca, identificacao, sexo, data_nascimento, status, origem_conversao, lote_id, peso_kg, tipo_entrada, fornecedor_origem, data_entrada, observacoes_origem | migration.sql:187-211 | manejo_animal/*, rastreabilidade, selects |
| ambiente_criacao_animal | id, associado_id, nome_instalacao, especie(ENUM), sistema(ENUM), tipo_instalacao, area_total_m2, quantidade_animais, densidade_referencia, tem_sombreamento_abrigo, observacoes | migration.sql:152-170 | ambientes_* |
| densidades_maximas_animais | especie, fase, densidade(varchar), referencia, ativo | migration.sql:727-738 | somente leitura em ambientes_* (**sem seed**) |
| controle_alimentacao_animal | associado_id, animal_id, lote_id, data_alimentacao, tipo_alimento(ENUM), produto_utilizado, quantidade_kg, percentual_materia_seca, origem(ENUM), observacoes | migration.sql:619-639 | alimentacao_* |
| controle_sanidade_animal | associado_id, animal_id, lote_id, data_tratamento, tipo_tratamento(ENUM), produto_utilizado, motivo, dose_quantidade, via_aplicacao, responsavel, produto_normatizado, periodo_carencia_horas, data_liberacao_organica, aprovado_ocs, numero_tratamento, observacoes | migration.sql:682-708 | sanidade_* |
| controle_reproducao_animal | associado_id, animal_id, grupo_reprodutivo, tipo_evento(ENUM), data_evento, macho_reprodutor_id(INT), previsao_parto, nascidos_vivos, natimortos, periodo_aleitamento_dias, observacoes — **sem lote_id, sem especie** | migration.sql:659-679 | reproducao_* (+ query inválida em rastreabilidade) |
| manejo_animal_registros | animal_id, tipo_registro(ENUM de 7), data_registro, descricao, produto_utilizado, dose_quantidade, periodo_carencia_horas, responsavel, observacoes | migration.sql:1063-1079 | animais_visualizar, painel |
| associados | id, nome, sobrenome, registro_cnpo | migration.sql:322+ | rastreabilidade, selects de associado |
| audit_log | user_id, acao, tabela, registro_id, descricao, ip | `classes/AuditLog.php:19-21` | todas as escritas |

---

## 2. modulos/manejo_animal/

### modulos/manejo_animal/animais_cadastrar.php
- **Rota/acesso**: `index.php:1050-1052` (/manejo-animal/novo); links `animais_listar.php:46`, `painel.php:71`. Guard: isLoggedIn() `12-15`. Sem nível, sem CSRF.
- **Propósito de negócio**: cadastrar animal do rebanho (animais) com origem/conversão orgânica, entrada e lote (RF05).
- **Campos/formulário**: nome_animal text **required** (`77`); especie **text required** (`81`, placeholder "Ex.: Bovino, Suíno..."); raca (`85`); identificacao text "brinco/tag" (`89`); sexo select M/F (`93-97`); data_nascimento date (`101`); associado_id select (`105-112`); origem_conversao select ORGANICO|EM_CONVERSAO|CONVENCIONAL (`116-120`); status select ATIVO|VENDIDO|ABATIDO|MORTO (`125-130`); lote_id select (`134-139`); peso_kg number step 0.01 min 0 (`143`); tipo_entrada select NASCIDO_PROPRIEDADE|COMPRADO_ORGANICO|COMPRA_CONVENCIONAL|TRANSFERIDO_OCS (`147-153`); fornecedor_origem (`158`); data_entrada date (`162`); observacoes_origem textarea (`166`).
- **Regras/validações/cálculos**: `empty($dados['nome_animal']) || empty($dados['especie'])` (`47-49`). associado_id = $_POST['associado_id'] ?? getAssociadoId() (`29`); criado_por = (int)$_SESSION['user_id'] (`44`). Nenhum cálculo de conversão (prazos por espécie) apesar do texto de ajuda (`154`).
- **Tabelas SQL usadas**: INSERT INTO animais (associado_id, nome_animal, especie, raca, identificacao, sexo, data_nascimento, status, origem_conversao, criado_por) — `classes/ManejoAnimal.php:48-62`; em seguida, se houver lote/peso/tipo_entrada, UPDATE animais SET lote_id, peso_kg, tipo_entrada, fornecedor_origem, data_entrada, observacoes_origem WHERE id — `classes/ManejoAnimal.php:65-67` + `76-79`.
- **Ações/POST/redirects**: AuditLog::registrar('ANIMAL_CADASTRADO','animais',$id,...) (`52`) → redirect /manejo-animal/{id} (`53`). O audit ocorre **sem verificar** se create() retornou id válido.
- **Riscos/gaps**: **(a) IDOR de escrita na criação**: associado_id vem do POST sem validação (`29`) — um nível 3 pode criar animal em nome de outro produtor (o select só lista o próprio, mas o POST não é revalidado); (b) **especie é texto livre** enquanto animal_lotes.especie/ambiente_criacao_animal.especie são ENUM maiúsculos (`migration.sql:218`, `156`) ⇒ KPIs de espécie por LIKE falham (`classes/ProducaoAnimal.php:462-464`); (c) identificacao sem UNIQUE no DDL (`migration.sql:193`) ⇒ brincos duplicados permitidos; (d) sem CSRF; (e) $_SESSION['user_id'] usado sem ?? (`44`).

### modulos/manejo_animal/animais_editar.php
- **Rota/acesso**: `index.php:1053-1055` (/manejo-animal/{id}/editar); link `animais_listar.php:89`, `animais_visualizar.php:91`. Guard: isLoggedIn() `12-15`; id/existência `17-28`.
- **Guard anti-IDOR**: **ausente** — não há qualquer comparação de associado_id neste arquivo; getById (`classes/ManejoAnimal.php:31-36`) também não filtra.
- **Campos/formulário**: mesmos do cadastro (`75-160`), com associado_id (`103-108`) e status pré-selecionados.
- **Regras/validações/cálculos**: `$dados = array_merge($animal, $_POST)` (`41`); valida nome/especie (`42-44`); ProducaoAnimal::atualizarAnimal (`45`).
- **Tabelas SQL usadas**: UPDATE animais SET nome_animal, especie, raca, identificacao, sexo, data_nascimento, status, origem_conversao, lote_id, peso_kg, tipo_entrada, fornecedor_origem, data_entrada, observacoes_origem WHERE id — `classes/ProducaoAnimal.php:84-105` (14 colunas; **não** atualiza associado_id).
- **Ações/POST/redirects**: audit ANIMAL_ATUALIZADO (`46`) → /manejo-animal/{id}?atualizado=1 (`47`).
- **Riscos/gaps**: **IDOR total** (qualquer usuário autenticado edita qualquer animal por id, inclusive de outros produtores, pois nem a leitura nem a escrita filtram o dono); sem CSRF; array_merge permite sobrescrever colunas do registro com chaves POST arbitrárias antes do UPDATE (as colunas são fixas no SQL, mitigando); sem validação de lote_id pertencente ao mesmo associado.

### modulos/manejo_animal/animais_listar.php
- **Rota/acesso**: `index.php:1047-1049` (/manejo-animal); menu `includes/menu.php:146`. Guard: isLoggedIn() `10-13`.
- **Propósito de negócio**: listagem do rebanho (RF05) com lote, origem, status e nº de registros de manejo.
- **Campos/formulário**: nenhum.
- **Regras/validações/cálculos**: `$animais = $animalClass->getAll($user->hasPermission(NIVEL_GESTOR) ? null : ($user->getAssociadoId() ?? null))` (`25`) — mesmo risco do escopo null. Badges origemBadge (`27-31`) e statusBadge (`32-37`); total_registros vem de subquery (`86`).
- **Tabelas SQL usadas**: SELECT a.*, l.nome_lote AS lote_nome, (SELECT COUNT(*) FROM manejo_animal_registros r WHERE r.animal_id = a.id) AS total_registros FROM animais a LEFT JOIN animal_lotes l ON a.lote_id = l.id WHERE a.excluido = 0 [AND a.associado_id = ?] ORDER BY a.criado_em DESC — `classes/ManejoAnimal.php:15-28`.
- **Ações/POST/redirects**: exclusão por **GET** `?excluir=id` (`17-23`) → UPDATE animais SET excluido = 1 WHERE id = ? (`classes/ManejoAnimal.php:93`) → audit ANIMAL_EXCLUIDO (`20`) → redirect (`21`). O resultado do soft delete não é verificado (`19`).
- **Riscos/gaps**: **IDOR de exclusão de animais** (sem posse nem nível, `17-23`); sem CSRF (exclusão por GET); especie e origem_conversao ecoadas em badge cru (`84-85`) — valor fora do mapa cai em bg-secondary, mas o texto é impresso sem htmlspecialchars; subquery de total_registros não filtra excluido/período.

### modulos/manejo_animal/animais_visualizar.php
- **Rota/acesso**: `index.php:1056-1058` (/manejo-animal/{id} numérico); links `animais_listar.php:88`, `animais_cadastrar.php:53`. Guard: isLoggedIn() `10-13`; animal inexistente → mensagem (`17-24`).
- **Guard anti-IDOR (presente, mas inconsistente)**: `27-33` — !hasPermission(NIVEL_GESTOR) && (int)$animal['associado_id'] !== (int)$user->getAssociadoId() ⇒ nega. Usa getAssociadoId() (que pode ser null), enquanto `lotes_editar.php:32` e `ambientes_editar.php:32` usam getAssociadoIdResolvido() ⇒ critérios diferentes para o mesmo tipo de decisão.
- **Propósito de negócio**: ficha do animal + histórico de manejo + lançamento de registros clínicos/vacinação/alimentação.
- **Campos/formulário**: painel-resumo lê especie, raca, identificacao, sexo, data_nascimento, status, lote_nome, peso_kg, tipo_entrada, fornecedor_origem (`99-108`). Formulário de registro: tipo_registro select CLINICO|VACINACAO|ALIMENTACAO **required** (`128-132`); data_registro date **required** default hoje (`136`); descricao (`140`); produto_utilizado (`144`); dose_quantidade (`148`); responsavel (`152`); observacoes (`156`).
- **Regras/validações/cálculos**: `empty($dados['tipo_registro']) || empty($dados['data_registro'])` (`60-62`); criado_por = (int)$_SESSION['user_id'] (`57`). Rótulos/badges de tipo em `73-77` com fallback ?? [$reg['tipo_registro'],'bg-secondary','bi-journal'] (`174`).
- **Tabelas SQL usadas**: INSERT INTO manejo_animal_registros (animal_id, tipo_registro, data_registro, descricao, produto_utilizado, dose_quantidade, responsavel, observacoes, criado_por) — `classes/ManejoAnimal.php:100-113`; SELECT * FROM manejo_animal_registros WHERE animal_id = ? ORDER BY data_registro DESC — `classes/ManejoAnimal.php:40-41`; DELETE FROM manejo_animal_registros WHERE id = ? — `classes/ManejoAnimal.php:119`.
- **Ações/POST/redirects**: (1) `?remover_registro=id` por **GET** (`39-44`) → removerRegistro (**hard delete**) → audit MANEJO_REGISTRO_EXCLUIDO (`41`) → redirect (`42`); (2) POST adicionar registro (`47-69`) → audit MANEJO_REGISTRO_ADICIONADO (`65`) → ?registrado=1 (`66`). Exibição do link de remoção em `180`.
- **Riscos/gaps**: **(1) IDOR de exclusão de registros**: `?remover_registro=N` só valida is_numeric (`39`) e o DELETE (`classes/ManejoAnimal.php:119-120`) não confirma que o registro pertence ao animal/associado verificado ⇒ apaga registros de qualquer produtor; **(2) hard delete** de histórico sanitário (sem soft delete, sem trilha do conteúdo removido); (3) sem CSRF (exclusão por GET); (4) o select de tipo oferece 3 valores, mas o ENUM do banco tem 7 (REPRODUCAO, PARASITARIO, BEM_ESTAR, OBITO, `migration.sql:1066`) — recursos existentes no esquema sem UI; (5) **periodo_carencia_horas existe na tabela (`migration.sql:1071`) mas não há campo no formulário** ⇒ carência não registrável neste fluxo (só em sanidade); (6) a checagem IDOR usa getAssociadoId() podendo negar acesso legítimo de usuários da tabela usuarios sem associado_id direto (`classes/User.php:316-324`).

### Síntese do grupo (modulos/manejo_animal)
**Fluxo ponta-a-ponta**
1. /manejo-animal (`index.php:1047`) → lista rebanho filtrado por getAssociadoId() (`animais_listar.php:25`) com lote e contagem de registros (`classes/ManejoAnimal.php:15-28`).
2. /manejo-animal/novo → cadastro com origem/conversão, lote, peso, tipo de entrada (`animais_cadastrar.php:28-45`) → INSERT animais + UPDATE animais de anexos (`classes/ManejoAnimal.php:45-69`) → ficha do animal.
3. /manejo-animal/{id} (ficha) → histórico + lançamento de registros clínicos/vacinação/alimentação (`animais_visualizar.php:47-69`) → manejo_animal_registros.
4. /manejo-animal/{id}/editar → UPDATE animais por ProducaoAnimal::atualizarAnimal (`animais_editar.php:45`, `classes/ProducaoAnimal.php:81-106`) — **sem guard de posse**.
5. Integração com produção animal: o animal alimenta (a) o lote_id usado em animal_lotes/rastreabilidade; (b) os SELECTs de animal em alimentação/sanidade/reprodução (`alimentacao_cadastrar.php:18`, `sanidade_cadastrar.php:17`, `reproducao_cadastrar.php:17`); (c) a contagem animais_total dos lotes e os KPIs do painel (`classes/ProducaoAnimal.php:19`, `460-468`); (d) manejo_registros no painel (`482-487`). **Não há** vínculo ambiente↔animal, nem histórico de movimentação de lote.

**Tabelas de banco consolidadas**: animais, animal_lotes, manejo_animal_registros, audit_log (mesmos DDL/linhas da tabela consolidada da seção 1). Todas as escritas passam por AuditLog::registrar (`classes/AuditLog.php:13-27`), exceto a falha silenciosa quando create() retorna 0 (`animais_cadastrar.php:51-52`).

---

## 3. classes/

### classes/ManejoAnimal.php
- **Rota/acesso**: classe base (não roteável); consumida por `animais_*.php` e herdada por ProducaoAnimal (`classes/ProducaoAnimal.php:13`).
- **Propósito de negócio**: CRUD de animais e do histórico manejo_animal_registros.
- **Campos/formulário**: n/a (mapeia os campos dos formulários de manejo).
- **Regras/validações/cálculos**: __construct obtém a conexão singleton (`8-11`); create (`45-69`) faz INSERT e, **condicionalmente** (lote_id || peso_kg || tipo_entrada, `65`), o UPDATE de anexos (`74-89`); nenhum cálculo/normalização de unidade.
- **Tabelas SQL usadas**: SELECT a.*, l.nome_lote ... (subquery manejo_animal_registros) FROM animais a LEFT JOIN animal_lotes l ... (`15-28`); SELECT a.*, l.nome_lote ... WHERE a.id = ? AND a.excluido = 0 (`33-34`); SELECT * FROM manejo_animal_registros WHERE animal_id = ? (`40-41`); INSERT INTO animais (...) (`48-62`); UPDATE animais SET lote_id, peso_kg, tipo_entrada, fornecedor_origem, data_entrada, observacoes_origem WHERE id = ? (`77-78`); UPDATE animais SET excluido = 1 WHERE id = ? (`93`); INSERT INTO manejo_animal_registros (...) (`100-113`); DELETE FROM manejo_animal_registros WHERE id = ? (`119`).
- **Ações/POST/redirects**: n/a (retorna ids/bools).
- **Riscos/gaps**: (a) **nenhum get* filtra por associado_id** (`31-43`) — a defesa contra IDOR depende exclusivamente de cada tela, e três telas não a implementam; (b) getRegistros não valida o dono do animal; (c) removerRegistro é **hard delete** sem escopo (`117-121`); (d) create não valida campos obrigatórios e faz trim($dados['nome_animal'])/especie (`53-54`) — em PHP 8.1+ trim(null) deprecado se ausentes; (e) atualizarAnexos sobrescreve com NULL qualquer campo não reenviado (`81-86`) — perda silenciosa de dados de origem em edição parcial.
- **SQL injection**: não há SQL concatenado nesta classe; todos os valores vão por placeholders (`27-28`, `35`, `41`, `62`, `80-88`, `94`, `114`, `120`).

### classes/ProducaoAnimal.php
- **Rota/acesso**: classe de domínio (não roteável); instanciada em 17 telas de `modulos/producao_animal` e em `modulos/manejo_animal/animais_cadastrar.php:18` / `animais_editar.php:30`.
- **Propósito de negócio**: controles de produção animal orgânica — lotes, alimentação (arts. 34-37), sanidade (arts. 59-63), reprodução, ambiente (arts. 38-44), densidades de referência e estatísticas (Portaria MAPA 52/2021, Título III — comentário `5-12`).
- **Campos/formulário**: define as colunas persistidas de cada formulário (ver seções 1/2).
- **Regras/validações/cálculos**: **a classe não contém regra de negócio normativa** — nenhuma validação de 15%/20% MS, nenhuma contagem de 2 tratamentos/ano, nenhum cálculo de carência/liberação, nenhum cálculo de densidade/ocupação, nenhuma métrica reprodutiva (IEP/DEL). Toda a validação existe apenas nas telas (e parcialmente). Normalizações: `!empty() ? (int)/(float)/trim() : null` em todos os INSERT/UPDATE; defaults 'ATIVO'/'ORGANICO' (`96-97`, `152`, `174`) e produto_normatizado default 1 (`234`, `264`), numero_tratamento default 1 (`238`, `268`).
- **Métodos**: getLotes `17-30`; getLoteById `32-37`; criarLote `39-55`; atualizarLote `57-71`; softDeleteLote `73-77`; atualizarAnimal `81-106`; getAlimentacoes `110-126`; getAlimentacaoById `128-133`; criarAlimentacao `135-157`; atualizarAlimentacao `159-178`; softDeleteAlimentacao `180-184`; getSanidades `188-204`; getSanidadeById `206-211`; criarSanidade `213-243`; atualizarSanidade `245-272`; softDeleteSanidade `274-278`; getReproducoes `282-297`; getReproducaoById `299-304`; criarReproducao `306-329`; atualizarReproducao `331-351`; softDeleteReproducao `353-357`; getAmbientes `361-373`; getAmbienteById `375-380`; criarAmbiente `382-404`; atualizarAmbiente `406-425`; softDeleteAmbiente `427-431`; getDensidades `435-447`; estatisticas `451-502`.
- **Tabelas SQL usadas (consolidado da classe)**: animal_lotes (`19-20`, `34`, `42-43`, `60-61`, `75`, `470`); animais (`19`, `84-87`, `460-466`); controle_alimentacao_animal (`112-116`, `130`, `138-141`, `162-164`, `182`); controle_sanidade_animal (`190-194`, `208`, `216-221`, `248-252`, `276`); controle_reproducao_animal (`284-287`, `301`, `309-312`, `334-337`, `355`); ambiente_criacao_animal (`363`, `377`, `385-388`, `409-411`, `429`); densidades_maximas_animais (`437`); manejo_animal_registros + animais (`482-484`).
- **Ações/POST/redirects**: n/a.
- **Riscos/gaps**: (a) **todos os get*ById ignoram associado_id** (`34`, `130`, `208`, `301`, `377`) — é a raiz dos IDOR de alimentação/sanidade/reprodução; (b) **macho_reprodutor_id gravado como string via trim** (`320`, `343`) contra coluna INTEGER (`migration.sql:666`); (c) **especie não persistida** em reprodução (`309-312`); (d) atualizarLote/atualizarAmbiente/atualizarAnimal/atualizarAlimentacao/atualizarSanidade/atualizarReproducao **nunca alteram associado_id** — positivo para integridade, mas implica que lotes criados por gestor com associado_id NULL (`46`) ficam permanentemente sem dono e inacessíveis ao produtor pela listagem escopada, enquanto o anti-IDOR de `lotes_editar.php:31-32` exige donoLote > 0; (e) estatisticas reutiliza o mesmo $where em quatro tabelas (`453-480`) — acoplamento frágil; (f) softDelete* não recebe/valida usuário nem dono (`75`, `182`, `276`, `355`, `429`); (g) sem SQL concatenado com entrada de usuário ⇒ **sem SQL injection** nesta classe (todos os prepare/execute com placeholders).
- **Inconsistência de campos**: animais.especie texto livre (`migration.sql:191`) × animal_lotes.especie VARCHAR(60) (`218`) × ambiente_criacao_animal.especie ENUM (`156`) — três representações para o mesmo conceito.

### classes/LoteProducao.php
- **Rota/acesso**: **não pertence ao módulo Produção Animal**. É usada por `modulos/caderno_campo/lotes_producao/{cadastrar,editar,listar,rastrear}.php` (`cadastrar.php:5,29`; `editar.php:5,25`; `listar.php:5,15`; `rastrear.php:5,19`) e instanciada no roteador para resolver UUID: `index.php:367-383` (/lote/{id|uuid}) e `index.php:385-401` (/caderno-campo/lote/{id|uuid}).
- **Propósito de negócio**: CRUD do lote de **produção vegetal** (lotes_producao: produto_id, quantidade, unidade_medida) com UUID público de rastreio e recuperação das atividades/insumos da lavoura do produto.
- **Campos/formulário**: uuid (gerado se ausente, `22-30`), nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes (`32-43`, `186-193`).
- **Regras/validações/cálculos**: sanitização integral de entrada por Sanitizer::sanitizeArray (`19`, `185`; implementação em `classes/Sanitizer.php:119-131` → sanitizeString remove tags/trim/remove caracteres de controle, `15-21`); geração de UUID v4 com mt_rand (`23-29`); associado_id convertido para int ou NULL (`39`); nenhum cálculo de quantidade/unidade ou de rendimento.
- **Tabelas SQL usadas**: INSERT INTO lotes_producao (uuid, nome_lote, produto_id, associado_id, data_producao, quantidade, unidade_medida, observacoes, criado_em) (`32-33`); SELECT ... FROM lotes_producao lp LEFT JOIN produtos p ON lp.produto_id = p.id WHERE lp.excluido = 0 (`58-62` — getAll **sem filtro de associado**); getByAssociadoId com LEFT JOIN produtos → lavouras → propriedades e regra `lp.associado_id = :id OR (lp.associado_id IS NULL AND p.associado_id = :id)` (`79-86`); getByUuid (`104`); getById (`121`); getAtividadesByLoteId JOIN atividades_diarias (`138-143`); getInsumosByLoteId (ad.insumo_utilisado, `161-166`); UPDATE lotes_producao SET nome_lote, produto_id, data_producao, quantidade, unidade_medida, observacoes WHERE id (`186`); UPDATE lotes_producao SET excluido = 1 WHERE id (`209`).
- **Ações/POST/redirects**: n/a na classe (retorna bool/array); catch (PDOException) em todos os métodos faz error_log e devolve false/[] (`46-49`, `66-69`, `91-94`, `148-151`, `171-174`, `196-199`, `212-215`).
- **Riscos/gaps**: (a) **nenhum método valida a posse pelo usuário logado** — getById/getByUuid/update/delete aceitam qualquer id/uuid; o filtro por associado existe apenas em getByAssociadoId (`77-95`) e não em getAll (`56-70`); (b) UUID gerado com mt_rand (não CSPRNG) (`23-29`) e usado como identificador público de rastreio (getByUuid, `102-112`; rotas `index.php:367-372`, `385-391`) ⇒ previsibilidade teórica/enumerabilidade; (c) create acessa chaves do array sem verificar existência (ex.: $dados_sanitizados['nome_lote'], `37`) ⇒ warning/erro se o formulário não enviar o campo; (d) falhas são silenciosas para o chamador (false) sem distinguir validação de erro de banco; (e) o comentário final confirma que **não existem** métodos para adicionar/remover atividades e insumos a um lote (`218`) — lotes_producao.atividades_id/insumos_id (`migration.sql:1054-1055`) não são manipulados por esta classe; (f) getInsumosByLoteId depende da coluna grafada insumo_utilisado (`161`) — nome com erro de digitação que precisa existir em atividades_diarias; (g) sem SQL concatenado ⇒ sem SQL injection; (h) **escopo**: esta classe não é referenciada por nenhuma tela de producao_animal — "lote" aqui é lote de produto vegetal, não lote de animais.

### Síntese do grupo (classes/)
**Fluxos ponta-a-ponta**
1. ManejoAnimal (base) → CRUD de animais + manejo_animal_registros; não impõe escopo de associado.
2. ProducaoAnimal extends ManejoAnimal (`classes/ProducaoAnimal.php:13`) → herda getAll/getById/getRegistros/create/atualizarAnexos/softDelete/adicionarRegistro/removerRegistro e adiciona lotes, alimentação, sanidade, reprodução, ambientes, densidades e estatísticas. **A herança é o único ponto de integração entre os dois módulos** (as telas de alimentação/sanidade/reprodução chamam getAll() herdado para popular os selects de animal, `alimentacao_cadastrar.php:18`, `sanidade_cadastrar.php:17`, `reproducao_cadastrar.php:17`).
3. LoteProducao é uma classe **independente e não relacionada** (sem herança, sem require de ManejoAnimal/ProducaoAnimal) — pertence ao caderno de campo (produção vegetal) e compartilha apenas o singleton Database e o Sanitizer.
4. Todas as três usam exclusivamente PDO com prepare/execute; LoteProducao é a única que sanitiza entrada na camada de classe (Sanitizer::sanitizeArray).

**Tabelas de banco consolidadas (classes)**
| Tabela | Classe que escreve | Classe que lê |
|---|---|---|
| animais | ManejoAnimal (`48-62`, `77-78`, `93`), ProducaoAnimal (`84-87`) | ManejoAnimal (`15-28`, `33`), ProducaoAnimal (`19`, `460-466`, `482-484`) |
| manejo_animal_registros | ManejoAnimal (`100-113`, `119`) | ManejoAnimal (`16`, `40-41`), ProducaoAnimal (`482-484`) |
| animal_lotes | ProducaoAnimal (`42-43`, `60-61`, `75`) | ProducaoAnimal (`19-20`, `34`, `470`) |
| controle_alimentacao_animal | ProducaoAnimal (`138-141`, `162-164`, `182`) | ProducaoAnimal (`112-116`, `130`) |
| controle_sanidade_animal | ProducaoAnimal (`216-221`, `248-252`, `276`) | ProducaoAnimal (`190-194`, `208`, `474`) |
| controle_reproducao_animal | ProducaoAnimal (`309-312`, `334-337`, `355`) | ProducaoAnimal (`284-287`, `301`, `478`) |
| ambiente_criacao_animal | ProducaoAnimal (`385-388`, `409-411`, `429`) | ProducaoAnimal (`363`, `377`) |
| densidades_maximas_animais | — | ProducaoAnimal (`437`) |
| lotes_producao | LoteProducao (`32-33`, `186`, `209`) | LoteProducao (`58-62`, `79-86`, `104`, `121`) |
| produtos, lavouras, propriedades, atividades_diarias | — | LoteProducao (`60`, `81-83`, `140-141`, `163-164`) |
| audit_log | — | escritas por AuditLog (`classes/AuditLog.php:19-21`) a partir das telas |

---

## 4. Achados consolidados (bugs, segurança, inconsistências)

### Segurança
1. **Ausência total de CSRF** nas 21 telas de produção/manejo animal (grep csrf = 0 em `modulos/producao_animal` e `modulos/manejo_animal`), apesar de CSRFProtection existir (`classes/CSRFProtection.php`) e ser usado em outros módulos (ex.: `modulos/associados/excluir.php:19`, `api/certificadoras.php:25-26`).
2. **IDOR de exclusão por GET** em: `lotes_listar.php:17-24`, `ambientes_listar.php:17-24`, `alimentacao_listar.php:17-24`, `sanidade_listar.php:17-24`, `reproducao_listar.php:17-24`, `animais_listar.php:17-23`. Nenhuma verifica associado_id/nível antes do soft delete.
3. **IDOR de edição** em: `alimentacao_editar.php` (sem guard em 15-56), `sanidade_editar.php` (15-56), `reproducao_editar.php` (15-62), `animais_editar.php` (17-54).
4. **IDOR de exclusão de registros de manejo**: `animais_visualizar.php:39-44` + `classes/ManejoAnimal.php:117-121` (DELETE físico sem escopo).
5. **Exposição pública enumerável**: `rastreabilidade.php` (sem login, `2-8`) com id sequencial ?lote=N e "código" AL-%06d derivado do ID (`19-24`), expondo nome do produtor e registro_cnpo (`132-134`).
6. **Escopo por getAssociadoId() que pode ser null** → consultas sem filtro de associado e leitura global do rebanho/lotes/ambientes/registros: `lotes_listar.php:26`, `ambientes_listar.php:26`, `alimentacao_listar.php:26`, `sanidade_listar.php:26`, `reproducao_listar.php:26`, `animais_listar.php:25`, `painel.php:18`, `classes/LoteProducao.php:56-70` (getAll sem filtro).
7. **Vínculo cruzado de animal/lote no POST**: `classes/ProducaoAnimal.php:145-146`, `225-226`, `316`, `animais_cadastrar.php:29` — ids aceitos sem revalidação de posse.
8. **UUID de rastreio gerado com mt_rand**: `classes/LoteProducao.php:23-29`.
9. **Sem SQL injection identificado**: todas as queries das três classes e das telas usam prepare/execute com placeholders (verificado em `classes/ProducaoAnimal.php`, `classes/ManejoAnimal.php`, `classes/LoteProducao.php`, `rastreabilidade.php:19-71`). O único ponto de interpolação dinâmica é o $where de `classes/ProducaoAnimal.php:453-458` (composto por literais + placeholder, sem entrada de usuário).

### Bugs / defeitos funcionais confirmados
1. `ambientes_cadastrar.php:16-20` usa $dados antes de `$dados = $_POST;` (`24`) → variável indefinida; filtro de densidade por espécie nunca aplica.
2. `rastreabilidade.php:69-72` consulta controle_reproducao_animal.lote_id, **coluna inexistente** (`sinporg/prisma/migrations/0_baseline/migration.sql:659-679`) → seção "Reprodução" nunca renderiza; exceção engolida por `catch (Exception $e) {}` em `75` (padrão repetido em `45`, `55`, `65`).
3. `classes/ProducaoAnimal.php:320` e `343` gravam macho_reprodutor_id como string em coluna INTEGER (`migration.sql:666`) enquanto o formulário é texto livre (`reproducao_cadastrar.php:95-96`) → falha em MySQL estrito.
4. `reproducao_cadastrar.php:121-126` coleta especie que **não é persistida** (`classes/ProducaoAnimal.php:309-328`; coluna inexistente no DDL).
5. `painel.php:27` exibe "Sendo orgânicos" fixo como "—" — indicador não implementado.
6. densidades_maximas_animais **sem seed** no repositório (só DDL `migration.sql:727-738`) → bloco de referências vazio em `ambientes_cadastrar.php:102-105` e `ambientes_editar.php:124-127`.
7. Checkbox desmarcado em edição zera tem_sombreamento_abrigo (`classes/ProducaoAnimal.php:421`) e produto_normatizado (`264`).
8. `animais_cadastrar.php:51-52` registra auditoria de criação mesmo se create() falhar/retornar 0.
9. Assimetria de validação de carência: cadastro exige > 0 e >= 96 (`sanidade_cadastrar.php:32-37`); edição exige apenas >= 96 (`sanidade_editar.php:43`) — permite gravar carência 0 quando excepcional já marcado no banco.
10. manejo_animal_registros.tipo_registro tem 7 valores no ENUM (`migration.sql:1066`) mas a UI oferece 3 (`animais_visualizar.php:128-132`); periodo_carencia_horas existe na tabela (`1071`) e não é coletado.

### Gaps de escopo normativo (não encontrado no código)
- **kg/animal/dia** e composição de dieta: só quantidade_kg total + produto_utilizado texto livre (controle_alimentacao_animal, `migration.sql:619-639`); nenhum cálculo.
- **Custo de alimentação e baixa de estoque**: 0 ocorrências de custo/estoque em `classes/ProducaoAnimal.php` e `modulos/producao_animal`; a tabela insumos (`migration.sql:946-969`) e sql/seed_insumos_aprovados_animal.sql não são referenciadas pelo módulo.
- **Limites 15%/20% MS**: apenas form-text (`alimentacao_cadastrar.php:115`); nenhum somatório de MS não orgânica por período/espécie.
- **Capacidade/ocupação de ambientes**: nenhum cálculo área ÷ densidade nem alerta de superlotação; ambiente_criacao_membros (`migration.sql:173-180`) não é usada por nenhum PHP.
- **Vacinas**: sem catálogo, lote de vacina, validade ou dose de reforço — controle_sanidade_animal guarda texto livre.
- **Prescrição**: dose_quantidade TEXT/VARCHAR(60) e via_aplicacao VARCHAR(40) (`migration.sql:691-692`) — sem estrutura de prescrição veterinária nem responsável técnico com CRMV.
- **Carência**: sem conversão para dias, sem bloqueio de venda/abate, sem verificação de data_liberacao_organica contra a data corrente.
- **2 tratamentos/ano**: nenhuma contagem em nenhum arquivo (somente texto de ajuda, `sanidade_cadastrar.php:146`, `sanidade_listar.php:42`).
- **Diagnóstico de gestação**: ausente do ENUM e da UI (`migration.sql:664`, `reproducao_cadastrar.php:76`).
- **Intervalo entre partos / dias em lactação / produção de leite**: 0 ocorrências (intervalo, lacta, producao_leite, dias_em_lactacao) em *.sql e em `classes/*.php`.
- **SISBOV/NIF**: 0 ocorrências de sisbov/NIF em *.sql e `classes/*.php`; único identificador é animais.identificacao (brinco/tag, sem UNIQUE — `migration.sql:193`).
- **Genealogia**: 0 ocorrências de pai_id/mae_id/genealog* — não há árvore genealógica.
- **Movimentações de animais**: não existe tabela de histórico de movimentação; apenas animais.lote_id (estado atual) — sem GTA/trânsito/documento sanitário.


---


# PARTE 8 — SÍNTESE CONSOLIDADA, RECONCILIAÇÃO E RISCOS GLOBAIS

## 8.1 Verificações cruzadas feitas pelo autor deste relatório

Os relatórios parciais produzidos para os grupos de produção foram **reconciliados** com leitura própria dos arquivos. Confirmações e correções:

| Afirmação de terceiro/parcial | Status | Evidência própria |
|---|---|---|
| `Mensalidade::gerarPix()`/`processarWebhookPix()` usam `mensalidades.txid` | **CONFIRMADO** | `classes/Mensalidade.php:698,711`; `migration.sql:1082-1099` (sem `txid`); `migration.sql:1235,1240` (`txid` só em `pagamentos`) |
| `PIX_PROVIDER` não definido | **CONFIRMADO** | busca global: só `classes/PixProvider.php:7,18` e `modulos/mensalidades/baixa.php:92,100` |
| `Fornecedor::vincularUnidades()` inexistente, mas invocado | **CONFIRMADO** | única ocorrência no repositório é a chamada `modulos/fornecedor/cadastrar.php:70` |
| `modulos/fornecedor/cadastrar.php` faz POST para rota inexistente | **CONFIRMADO** | `cadastrar.php:83,115` (`/fornecedor/cadastrar`); roteador só tem `/fornecedor`, `/fornecedor/novo`, `/fornecedor/{id}/editar` (`index.php:739-751`) |
| Módulo `fornecedor` não valida CSRF (só imprime o token) | **CONFIRMADO** | `cadastrar.php:11,153` (única referência a `CSRFProtection` no módulo); `editar.php`/`excluir.php` sem token |
| `haHigienizacaoValida()` ignora `$associadoId` | **CONFIRMADO** | `classes/ControleHigienizacao.php:38-48` (4 binds para 4 placeholders; `$associadoId` ausente do SQL) |
| Trava RF08 usa literal `'Qualquer linha'` | **CONFIRMADO** | `modulos/agroindustria/ordens_cadastrar.php:149` vs. texto livre em `higienizacao_cadastrar.php:75` |
| Docblock de `OrdemProducao::create()` promete RF08 que não existe na classe | **CONFIRMADO** | docblock `classes/OrdemProducao.php:96-100`; `create()` `:101-153` não referencia `ControleHigienizacao` |
| Balanço de massas: `|entrada−final|/entrada×100`, tolerância 10% | **CONFIRMADO** | `classes/OrdemProducao.php:82-83` |
| Percentual orgânico = soma de peso orgânico / soma de peso × 100 | **CONFIRMADO** | `classes/ReceitaAgroindustria.php:65-81` |
| Selo ≥95% / ≥70% / <70% | **CONFIRMADO** | `classes/ReceitaAgroindustria.php:89-99` |
| `proporcao_porcentagem` guarda peso (g/mL), não percentual | **CONFIRMADO** | `classes/ReceitaAgroindustria.php:188` grava `$peso`; `modulos/agroindustria/rastreabilidade.php:131` imprime "`g`" |
| `agroindustria/rastreabilidade.php` e `producao_animal/rastreabilidade.php` são públicas | **CONFIRMADO** | comentários `:2`; nenhuma checagem de sessão; roteador sem guarda (`index.php:1-8`) |
| `producao_animal/rastreabilidade.php` consulta `controle_reproducao_animal.lote_id` inexistente | **CONFIRMADO** | query `:69-72`; schema `migration.sql:659-679`; `catch` mudo `:75` |
| Rótulos de `tipo_entrada`/`origem` divergentes dos ENUMs | **CONFIRMADO** | `producao_animal/rastreabilidade.php:78-88` vs. `migration.sql:197,200,629` |
| Escopo por `getAssociadoId()` pode virar `null` ⇒ consulta global | **CONFIRMADO** | `classes/User.php:259-272` (retorna null no caso 4) vs. `classes/User.php:299-329` (versão robusta) |
| Sem CSRF nos módulos `producao_animal`/`manejo_animal` | **CONFIRMADO** | busca de `CSRFProtection` nesses diretórios: 0 ocorrências |
| Sem CSRF em `agroindustria` exceto `minhas_*` | **CONFIRMADO** | `CSRFProtection` aparece só em `minhas_cadastrar.php` e `minhas_editar.php` (busca no diretório) |
| `controle_reproducao_animal` não tem `lote_id` | **CONFIRMADO** | `migration.sql:659-679` |
| `atividades_diarias.insumo_utilisado` é typo legado real | **CONFIRMADO** (evita falso positivo) | `migration.sql:401`; usado corretamente em `classes/LoteProducao.php:161` e `rastreabilidade.php:43`; a tabela distinta `agro_atividades_diarias.insumo_utilizado` (`migration.sql:15`) é usada por `classes/AgroAtividadeDiaria.php:55-100` |
| Sem cálculo de curva de lactação / leite / DEL | **CONFIRMADO** | busca `lacta|litro|leite|curva|producao_leite` em `modulos/producao_animal`: 0 ocorrências; em `classes/ProducaoAnimal.php`: 0 |
| Sem SQL injection nos módulos de produção | **CONFIRMADO por amostragem** | todas as queries lidas usam `prepare()`/`execute()` com placeholders; única interpolação é `LIMIT `+inteiro (`classes/Mensalidade.php:95,532`) e `$where` de literais (`classes/ProducaoAnimal.php:453-458`) |

## 8.2 Tabela única de risco (financeiro + produção), priorizada

| # | Sev. | Grupo | Achado | Evidência |
|---|---|---|---|---|
| 1 | Crítico | Financeiro | `mensalidades.txid` inexistente quebra `gerarPix()` e todos os webhooks PIX (exceção não capturada) | `classes/Mensalidade.php:698,711`; `migration.sql:1082-1099` |
| 2 | Crítico | Financeiro | `PIX_PROVIDER`/`ASAAS_*`/`GN_*`/`MP_*` inexistentes ⇒ fluxo PIX inalcançável | `modulos/mensalidades/baixa.php:92`; `classes/PixProvider.php:18` |
| 3 | Crítico | Fornecedor | Cadastro quebra: método inexistente + POST para rota inexistente | `modulos/fornecedor/cadastrar.php:70,83,115`; `index.php:739-751` |
| 4 | Alto | Financeiro | Webhooks sem assinatura/IP, CORS `*`, erro ecoa txid (sondagem) | `api/pix/webhook/*.php`; `api/.htaccess:14`; `classes/Mensalidade.php:716` |
| 5 | Alto | Financeiro | Baixa duplicável sob concorrência (sem lock/UNIQUE) | `classes/Mensalidade.php:719-721,237-283` |
| 6 | Alto | Financeiro | Estorno e exclusão por DELETE físico, sem auditoria | `classes/Mensalidade.php:318-326` |
| 7 | Alto | Produção | IDOR de exclusão por GET em 6 listagens (lotes, ambientes, alimentação, sanidade, reprodução, animais) | `producao_animal/*_listar.php:17-24`; `manejo_animal/animais_listar.php:17-23` |
| 8 | Alto | Produção | IDOR de edição (sem posse) em alimentação/sanidade/reprodução/animais | `*_editar.php`; `classes/ProducaoAnimal.php:130,208,301`; `classes/ManejoAnimal.php:31-36` |
| 9 | Alto | Produção | Ausência total de CSRF em produção/manejo animal e em quase toda a agroindústria | buscas por `CSRFProtection` nos diretórios |
| 10 | Alto | Transversal | Escopo `getAssociadoId()` nulo ⇒ consultas sem filtro (dados de todos os produtores) | `classes/User.php:259-272`; `*_listar.php:25-26`; `painel.php:18` |
| 11 | Alto | Produção | IDOR de escrita: `associado_id`/`animal_id`/`lote_id`/`receita_id` de POST sem revalidação | `ManejoAnimal.php:29`; `ProducaoAnimal.php:145-146,225-226,316`; `OrdemProducao.php:124` |
| 12 | Alto | Produção | RF08 contornável: higienização de outro produtor valida a OP | `classes/ControleHigienizacao.php:38-48` |
| 13 | Médio-Alto | Financeiro | `contas` sem CSRF/sanitização e sem vínculo contábil com `caixa` | `modulos/contas/cadastrar.php:13-25`; `editar.php:36-52`; `migration.sql:608-616` |
| 14 | Médio-Alto | Financeiro | `gerarLote()` não atômico; ignora valor/vencimento do plano | `classes/Mensalidade.php:158,165-200`; `gerar.php:40,103` |
| 15 | Médio | Produção | Exposição pública enumerável (ID sequencial) com dados pessoais e de fornecedores | `producao_animal/rastreabilidade.php:19-24,132-134`; `agroindustria/rastreabilidade.php:14-23` |
| 16 | Médio | Produção | `densidades_maximas_animais` sem seed ⇒ referência normativa vazia | `migration.sql:727-738`; `ambientes_cadastrar.php:102-105` |
| 17 | Médio | Produção | `macho_reprodutor_id` texto→INTEGER; `especie` coletada e não persistida | `classes/ProducaoAnimal.php:320,343`; `reproducao_cadastrar.php:95-96,121-126` |
| 18 | Médio | Produção | Hard delete de registros de manejo sem posse | `manejo_animal/animais_visualizar.php:39-44`; `classes/ManejoAnimal.php:117-121` |
| 19 | Médio | Fornecedor | Exclusão por GET sem CSRF e sem checagem de dependências | `modulos/fornecedor/excluir.php:23-40` |
| 20 | Médio | Financeiro | XSS refletido no aviso de comunicação + HTML injection no e-mail | `modulos/comunicacao/index.php:89-91,119,188` |
| 21 | Médio | Agro | `ReceitaAgroindustria::update()` faz DELETE+INSERT dos ingredientes (perde ids/histórico) | `classes/ReceitaAgroindustria.php:156-158` |
| 22 | Médio | Agro | Métrica de percentual orgânico mistura massa (g) e volume (mL) | `classes/ReceitaAgroindustria.php:47-59,71` |
| 23 | Médio | Agro | Balanço de massas soma unidades heterogêneas (KG/L/UN) sem conversão | `classes/OrdemProducao.php:66-69` |
| 24 | Baixo-Médio | Financeiro | `Caixa::getSaldo()` é acumulado de todo o histórico (sem competência) | `classes/Caixa.php:121-144` |
| 25 | Baixo-Médio | Financeiro | `getPrevisaoReceita()` agrupa por referência, não por vencimento | `classes/Mensalidade.php:606-624` |
| 26 | Baixo | Produção | `LoteProducao` gera UUID com `mt_rand` (não CSPRNG) para identificador público | `classes/LoteProducao.php:23-29` |
| 27 | Baixo | Produção | `manejo_animal_registros.tipo_registro` tem 7 valores e a UI oferece 3; `periodo_carencia_horas` não coletado | `migration.sql:1066,1071`; `animais_visualizar.php:128-132` |
| 28 | Baixo | Agro | Sem `AuditLog` no caminho financeiro (mensalidade/caixa) | `classes/Mensalidade.php`, `classes/Caixa.php` |

## 8.3 Verificações cruzadas adicionais — AGROINDÚSTRIA (extraídas e conferidas pelo autor)

Extração independente sobre `modulos/agroindustria` para validar os achados da PARTE 5:

| Verificação | Resultado objetivo |
|---|---|
| Guard de perfil `temAgroindustria()` | presente em **12** arquivos: `atividades_{cadastrar,editar,listar}`, `categorias_{cadastrar,editar,listar}`, `setores_{cadastrar,editar,listar}`, `ocorrencias_{cadastrar,editar,listar}`; **ausente** em `receitas_*`, `ordens_*`, `higienizacao_*` e `minhas_*` (que usam apenas `isLoggedIn()` + `hasPermission`) |
| CSRF | só `minhas_cadastrar.php:24` e `minhas_editar.php:40` (`CSRFProtection::validatePostRequest()`); **nenhum** outro arquivo do módulo valida CSRF |
| Exclusão por GET | confirmada em **7** telas de listagem: `setores_listar`, `categorias_listar`, `atividades_listar`, `ocorrencias_listar`, `receitas_listar`, `ordens_listar`, `higienizacao_listar` — todas com `?excluir=<id>` e `softDelete($id)` sem verificação de dono |
| Anti-IDOR presente | apenas em `atividades_editar.php:39`, `setores_editar.php:39`, `ocorrencias_editar.php:38` (compara `associado_id`), `categorias_editar.php:37-40` e `ordens_visualizar.php:26-28` (usam `getAssociadoIdResolvido()`), e `minhas_editar.php:28` |
| Anti-IDOR **ausente** | `receitas_listar.php`, `receitas_editar.php`, `ordens_listar.php`, `higienizacao_listar.php`, `ordens_cadastrar.php`, `higienizacao_cadastrar.php`, `categorias_cadastrar.php`, `minhas_listar.php` (só nas rotas de exclusão) |
| Escrita cross-tenant | `associado_id` vem do POST em `setores_cadastrar.php:41`, `atividades_cadastrar.php:42`, `ocorrencias_cadastrar.php:39`, `receitas_cadastrar.php:57`, `receitas_editar.php:78`, `ordens_cadastrar.php:108`, `higienizacao_cadastrar.php:30` (com fallback para `getAssociadoId()`) |
| `AuditLog::registrar()` com `registro_id = null` | confirmado em `setores_cadastrar.php:43`, `categorias_cadastrar.php:35`, `atividades_cadastrar.php:45`, `ocorrencias_cadastrar.php:44` |
| Ordem de includes em `ordens_visualizar.php` | `header.php`/`menu.php` nas linhas `:3-4` e o `if (!isLoggedIn())` só em `:9-12` ⇒ a checagem local é código morto (o guard real é `includes/header.php:16-19`) |

Confirmações qualitativas da PARTE 5 (reconciliadas com a leitura própria das PARTES 4 e 6):
- **RF08**: além do literal `'Qualquer linha'` (`ordens_cadastrar.php:149`) e da ausência de filtro por produtor (`classes/ControleHigienizacao.php:38-50`), verifica-se que **se** existisse um registro com `equipamento_linha = 'Qualquer linha'` dentro de 24 h, a checagem específica da `higienizacao_previa_id` escolhida (`:154-169`) **não seria executada** — a trava desaparece nesse ramo.
- **Rastreabilidade frágil**: `ordens_producao.lote_processado_final VARCHAR(50)` não tem índice único nem FK (`migration.sql:1203`), e `modulos/agroindustria/rastreabilidade.php:14-23` usa `fetch()` (primeira linha) — dois lotes com a mesma string tornam a consulta pública ambígua (falha de RF12).
- **Balanço de massas**: a fórmula `abs(entrada − final)/entrada` (`classes/OrdemProducao.php:82`) não distingue ganho de perda e as mensagens fixam a unidade em "kg" (`:91-92`), enquanto a UI exibe "kg" independentemente de `unidade_medida` (`ordens_visualizar.php:89,93`).
- **Custo/rendimento/perdas**: **não encontrado** — não há coluna de valor/custo nas tabelas de receita/ordem (dicionário em `migration.sql:1199-1219,1436-1449`) nem qualquer cálculo de rendimento ou perda no módulo.
- **FKs**: `agro_setores`, `agro_categorias_atividades`, `agro_atividades_diarias`, `agro_ocorrencias`, `receitas_agroindustria` e `controle_higienizacao` não possuem FK declarada no baseline (busca de `FOREIGN KEY` em `migration.sql` cobre apenas `ingredientes_receita`, `ordens_producao`, `rastreabilidade_insumos_processo` e `ocorrencias_manejo`).

## 8.4 Recursos previstos no schema e NÃO implementados (mapa de lacunas)

Financeiro:
- `mensalidades.status_mensalidade` aceita `'isenta'` e `'cancelada'` — nenhum código escreve esses valores (`migration.sql:1093`).
- `pagamentos.txid` (UNIQUE) nunca é preenchido por `baixar()` (`classes/Mensalidade.php:239-258`).
- `caixa.comprovante_url` existe e é gravado na baixa, mas não há upload em nenhuma tela financeira.
- `planos_mensalidades.dia_vencimento`/`valor` não alimentam `gerarLote()`.

Produção animal (lacunas normativas — Portaria MAPA 52/2021):
- kg/animal/dia, composição de dieta, custo e baixa de estoque de alimentação; limite de 15%/20% de MS não orgânica (só texto de ajuda, `alimentacao_cadastrar.php:115`); capacidade/ocupação de ambiente (área ÷ densidade) — nenhum cálculo em `classes/ProducaoAnimal.php`; contagem de 2 tratamentos/ano; cálculo de carência/liberação orgânica; diagnóstico de gestação (ausente do ENUM); intervalo entre partos; dias em lactação; curva de lactação/produção de leite; SISBOV/NIF; genealogia; histórico de movimentação de animais; `ambiente_criacao_membros` não é referenciada por nenhum PHP.

Agroindústria:
- `ingredientes_receita` não referencia `insumo_id`/`fornecedor_id` ⇒ cadeia fornecedor→insumo→receita é textual (`migration.sql:931-943`).
- `lotes_producao.atividades_id`/`insumos_id` e as tabelas `lote_insumo`/`lote_atividade` não são usadas por `LoteProducao` (`migration.sql:1030,1020,1054-1055`).
- Rastreabilidade de lote primário sempre nula porque a UI fixa `lote_primario_id = null` (`ordens_cadastrar.php:128`).

---


---

