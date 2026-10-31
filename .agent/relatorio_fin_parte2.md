
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
