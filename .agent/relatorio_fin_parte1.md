
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
