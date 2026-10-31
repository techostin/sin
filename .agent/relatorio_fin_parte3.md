
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
