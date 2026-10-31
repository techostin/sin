# 04 — Contratos de API (alvo v2)

> **Objetivo:** definir o contrato HTTP que substitui as ~194 rotas do roteador legado e os
> ~40 endpoints de `api/`. É a especificação de origem do `packages/contracts` e a base
> do gate **G4** (OpenAPI sem rota não documentada).
> **Gerado a partir de:** extração do roteador `index.php`, inventário de `api/` e do
> catálogo de módulos (`02-CATALOGO-DE-MODULOS-E-TELAS.md`).

---

## 1. Convenções gerais

| Convenção | Definição |
|---|---|
| Prefixo | `/api/v2` (o NestJS usa `setGlobalPrefix('api/v2')`) |
| Documentação | `/api/docs` (OpenAPI 3.1) |
| Formato | JSON `application/json; charset=utf-8` (upload: `multipart/form-data`) |
| Autenticação | `Authorization: Bearer <access_token>` |
| Datas | ISO-8601 com fuso (`2026-09-11T14:30:00-03:00`) |
| IDs | inteiro (interno) **e** `uuid` (público/exposto em URL) — **nunca** ID ofuscado com chave simétrica |
| Dinheiro | inteiro em centavos (**nunca** ponto flutuante) |
| Paginação | `?page=1&perPage=25` → envelope com `meta` |
| Ordenação | `?sort=-createdAt,nome` |
| Filtros | `?filtro[campo]=valor` e `?q=` para busca textual |
| Idempotência | `Idempotency-Key: <uuid>` obrigatório em POST de sincronização e webhooks |
| Correlação | `X-Request-Id` ecoado em `meta.requestId` e presente nos logs |
| Erros | formato único (seção 1.2) |

### 1.1 Envelope de sucesso

```json
{
  "data": { },
  "meta": { "requestId": "01J...", "timestamp": "2026-09-11T14:30:00-03:00" }
}
```

Para listas:

```json
{
  "data": [ ],
  "meta": {
    "page": 1, "perPage": 25, "total": 412, "totalPages": 17,
    "requestId": "01J..."
  }
}
```

### 1.2 Envelope de erro (obrigatório em 100% das falhas)

```json
{
  "error": {
    "code": "VALIDACAO",
    "message": "Mensagem legível para o usuário final",
    "details": [
      { "campo": "cpf", "problema": "CPF inválido" }
    ],
    "requestId": "01J..."
  }
}
```

| Código HTTP | `error.code` | Quando |
|---|---|---|
| 400 | `VALIDACAO` | Zod rejeitou a entrada |
| 401 | `NAO_AUTENTICADO` | Token ausente, inválido ou expirado |
| 403 | `SEM_PERMISSAO`, `ESCOPO_INVALIDO` | Papel insuficiente ou recurso de outro tenant |
| 404 | `NAO_ENCONTRADO` | Recurso inexistente **ou** fora do escopo do usuário (não revela existência) |
| 409 | `CONFLITO` | Violação de unicidade, transição de estado inválida |
| 422 | `REGRA_NEGOCIO` | Regra de domínio violada (insumo proibido, rótulo bloqueado) |
| 429 | `LIMITE_EXCEDIDO` | Rate limit |
| 500 | `ERRO_INTERNO` | Nunca expõe stack trace, SQL ou caminho absoluto |

> **Regra de ouro:** recursos de outro tenant ou de outro associado retornam **404**, não
> 403 — evita enumeração. (O legado devolvia a existência do recurso.)

### 1.3 Paginação, filtros e busca

Todos os endpoints de coleção aceitam:
- `page` (padrão 1), `perPage` (padrão 25, máximo 100)
- `sort` (ex.: `-data`, `nome`)
- `q` (busca textual — implementada com índice de texto, **nunca** com `REPLACE` encadeado como no legado)
- filtros específicos documentados por recurso

---

## 2. Kernel — autenticação, identidade e acesso

| Método | Rota | Descrição | Auth |
|---|---|---|---|
| POST | `/auth/login` | Login unificado (staff em `usuarios` e associado em `associados`) | pública + rate limit |
| POST | `/auth/refresh` | Rotaciona o refresh token | cookie/refresh |
| POST | `/auth/logout` | Revoga a família de tokens | JWT |
| GET | `/auth/me` | Retorna `ContextoUsuario` (id, origem, papéis, escopo, permissões) | JWT |
| POST | `/auth/esqueci-senha` | Inicia recuperação (resposta sempre genérica) | pública + rate limit |
| POST | `/auth/redefinir-senha` | Consome o token de reset | pública |
| POST | `/auth/alterar-senha` | Troca de senha autenticada | JWT |
| POST | `/auth/completar-perfil` | Complemento obrigatório de perfil | JWT |
| GET | `/kernel/associados/:id` | `AssociadoRef` (contrato do dado comum) | JWT (serviço) |
| GET | `/kernel/unidades-produtivas` | Lista `UnidadeProdutivaRef` (propriedade, agroindústria, ambiente) | JWT |
| GET | `/health` | Healthcheck | pública |

### 2.1 Payload de `POST /auth/login` (substitui o login em 2 etapas do gestor)

```json
// requisição
{ "identificador": "email-ou-cpf", "senha": "***" }

// resposta 200 — etapa 1 concluída
{
  "data": {
    "accessToken": "...",
    "expiresIn": 900,
    "contexto": {
      "usuarioId": 58, "origem": "staff", "associadoId": 28,
      "papeis": ["gestor"], "certificadoraId": 3,
      "areasProducao": ["vegetal","agroindustria"],
      "associacoesVisiveis": [1,2]
    },
    "requerSelecaoContexto": true,
    "contextosDisponiveis": [
      { "orgao": "SINPORG", "especialidade": "Fiscalização", "setor": "RS-01" }
    ]
  }
}
```

> **Decisão:** a "etapa 2" do legado (órgão, especialidade e setor) passa a ser um
> **contexto de sessão** dentro do JWT, e não um segundo formulário de autenticação.
> Mantém a paridade funcional sem duplicar o fluxo de credenciais.

---

## 3. M1 — Associados & Quadro Associativo

| Método | Rota | Substitui | Permissão |
|---|---|---|---|
| GET | `/associados` | `modulos/associados/listar.php` | gestor+ |
| GET | `/associados/:uuid` | `editar.php` (leitura) | gestor+ ou o próprio |
| POST | `/associados` | `cadastrar.php` | gestor+ |
| PATCH | `/associados/:uuid` | `editar.php` | gestor+ ou o próprio (campos restritos) |
| DELETE | `/associados/:uuid` | `excluir.php` | admin (**soft delete** + anonimização) |
| GET | `/associados/pendentes` | `pendentes.php` | gestor+ |
| GET | `/associados/cadastros-pendentes` | `cadastros_pendentes.php` | gestor+ |
| POST | `/associados/:uuid/aprovar` | fluxo de aprovação | gestor+ |
| POST | `/associados/:uuid/reprovar` | fluxo de reprovação | gestor+ |
| GET | `/associados/:uuid/ficha-adesao` | `ficha_adesao_visualizar.php` | gestor+ ou o próprio |
| POST | `/associados/:uuid/ficha-adesao` | ficha + assinatura | o próprio |
| POST | `/associados/ficha-adesao/publica` | `api/ficha_adesao.php` (público) | pública + rate limit + captcha |
| POST | `/pre-cadastro` | `api/pre_cadastro.php` | pública + rate limit |
| GET | `/associados/:uuid/carteirinha` | `carteirinha_gerar.php` | gestor+ ou o próprio |
| GET | `/associados/:uuid/carteirinha.pdf` | `carteirinha_pdf.php` | gestor+ ou o próprio |
| POST | `/associados/:uuid/carteirinha/enviar` | `carteirinha_enviar.php` | gestor+ |
| POST | `/carteirinhas/enviar-em-massa` | `carteirinhas_enviar_massa.php` | gestor+ (job assíncrono) |
| GET | `/publico/carteirinha/:token` | `carteirinha_publica.php` | **pública** (validação) |
| GET | `/associacoes` | `associacoes/listar.php` | gestor+ |
| POST | `/associacoes` | `associacoes/cadastrar.php` | admin |
| PATCH | `/associacoes/:uuid` | `associacoes/editar.php` | admin |
| DELETE | `/associacoes/:uuid` | `associacoes/excluir.php` | admin (**soft delete**) |
| GET | `/associacoes/:uuid/associados` | `associacoes/associados.php` | gestor+ |

**Regras de negócio a preservar:**
- Máquina de estados: `pendente`, `ativo`, `inativo` (e `status_associado` de vínculo).
- CPF com validação de dígito verificador e unicidade.
- Aprovação registra autor, data e observação em `audit_log`.
- Carteirinha tem token público opaco (não sequencial) e é invalidável.

---

## 4. M2 — Caderno de Campo

| Método | Rota | Substitui |
|---|---|---|
| GET/POST | `/propriedades` | `propriedades/listar.php`, `cadastrar.php` |
| GET/PATCH/DELETE | `/propriedades/:uuid` | `editar.php` |
| GET | `/propriedades/:uuid/imagens` | `visualizar_imagens.php` |
| GET | `/propriedades/:uuid/historico-conversao` | `historico_conversao.php` |
| GET/POST | `/lavouras` | `lavouras/*.php` |
| GET/PATCH/DELETE | `/lavouras/:uuid` | idem |
| GET/POST | `/atividades-diarias` | `atividade_diaria/*.php` |
| GET/PATCH/DELETE | `/atividades-diarias/:uuid` | idem (**soft delete**) |
| GET | `/atividades-diarias/:uuid/imagens` | `visualizar_imagens.php` |
| GET/POST | `/categorias-atividades` | `categorias_atividades/*.php` |
| GET/POST | `/insumos` | `caderno_campo/insumos/*.php` |
| GET/PATCH/DELETE | `/insumos/:uuid` | idem |
| POST | `/insumos/validar` | `insumos/validacao.php` mais `validar_insumo.php` |
| GET/POST | `/produtos` | `produtos/*.php` |
| GET/PATCH/DELETE | `/produtos/:uuid` | idem |
| GET/POST | `/lotes` | `lotes_producao/*.php` |
| GET/PATCH/DELETE | `/lotes/:uuid` | idem |
| GET | `/publico/lotes/:codigo/rastreabilidade` | `lotes_producao/rastrear.php` (público, QR) |
| GET/POST | `/ocorrencias` | `ocorrencias/*.php` |
| GET/PATCH/DELETE | `/ocorrencias/:uuid` | idem |
| POST | `/sync/push` | `db_sync.js` (offline) |
| GET | `/sync/pull` | `sincronizacao.php` (deltas) |
| GET | `/sync/status` | contagem de pendências |

### 4.1 Contrato de sincronização offline (crítico)

```json
// POST /sync/push
{
  "operacoes": [
    {
      "idempotencyKey": "uuid-v4-gerado-no-dispositivo",
      "entidade": "atividade_diaria",
      "operacao": "criar",
      "uuid": "uuid-do-registro",
      "dados": { },
      "criadoEm": "2026-09-11T10:00:00-03:00"
    }
  ]
}

// 200
{
  "data": {
    "resultados": [
      { "idempotencyKey": "uuid...", "status": "criado", "uuid": "...", "servidorAtualizadoEm": "..." },
      { "idempotencyKey": "uuid...", "status": "duplicado-ignorado", "uuid": "..." },
      { "idempotencyKey": "uuid...", "status": "conflito", "resolucao": "servidor-venceu", "dadosServidor": { } }
    ]
  },
  "meta": { "processadas": 3, "criadas": 1, "ignoradas": 1, "conflitos": 1 }
}
```

**Regras não-negociáveis:**
1. `idempotencyKey` é gerada **no dispositivo** e o servidor garante processamento único.
2. O servidor é **autoritativo** em conflito; a resolução é registrada em `sync_conflicts`.
3. `GET /sync/pull?desde=<timestamp>` devolve deltas; requer `updated_at` confiável em
   todas as tabelas do caderno de campo.
4. Nenhuma operação é descartada silenciosamente — o cliente recebe o motivo.

---

## 5. M7 — Suprimentos & Insumos

| Método | Rota | Substitui |
|---|---|---|
| GET/POST | `/fornecedores` | `fornecedor/*.php` |
| GET/PATCH/DELETE | `/fornecedores/:uuid` | idem |
| GET | `/fornecedores/:uuid/unidades` | `Fornecedor::getUnidades` (via registry do kernel) |
| GET | `/insumos-aprovados` | `api/insumos_aprovados.php` |
| GET | `/insumos-aprovados/validar` | validação por nome/composição |

---

## 6. M3 — Fiscalização & Conformidade

| Método | Rota | Substitui |
|---|---|---|
| GET/POST | `/roteiros-inspecao` | `templates/listar.php`, `cadastrar.php` |
| GET/PATCH/DELETE | `/roteiros-inspecao/:uuid` | (**inclui a tela de editar que faltava**) |
| GET | `/roteiros-inspecao/:uuid/ficha.pdf` | rota que faltava (`/ficha/{id}` → 404 no legado) |
| GET/POST | `/visitas-tecnicas` | `visitas/listar.php`, `agendar.php` |
| GET | `/visitas-tecnicas/:uuid` | `relatorio.php` |
| POST | `/visitas-tecnicas/:uuid/iniciar` | transição Agendada → Em Andamento |
| PUT | `/visitas-tecnicas/:uuid/resultado` | `realizar.php` (checklist + evidências + assinatura) |
| POST | `/visitas-tecnicas/:uuid/assinar` | assinatura do produtor (**armazenar como blob/arquivo, nunca VARCHAR(255)**) |
| POST | `/visitas-tecnicas/:uuid/ciencia` | ciência ou recusa do produtor |
| GET | `/visitas-tecnicas/:uuid/relatorio.pdf` | `relatorio.php` mais `ficha_pdf.php` |
| GET/POST | `/nao-conformidades` | `nao_conformidades/listar.php` |
| GET/PATCH | `/nao-conformidades/:uuid` | `detalhe.php`, `acompanhar.php` |
| POST | `/nao-conformidades/:uuid/evidencia` | upload de evidência da correção |
| GET/POST | `/certificacoes` | `certificacao/*.php` |
| GET/PATCH/DELETE | `/certificacoes/:uuid` | idem (**com as colunas reais do schema**) |
| POST | `/certificacoes/:uuid/renovar` | `renovar.php` (**cria novo registro; preserva histórico**) |
| GET | `/certificacoes/:uuid/certificado.pdf` | `certificado.php` mais `CertificadoOrganico` |
| GET | `/certificacoes/:uuid/historico` | `historico.php` |
| GET/POST | `/certificadoras` | `certificadoras_*.php`, `api/certificadoras.php` |
| GET/PATCH/DELETE | `/certificadoras/:uuid` | idem (**uma única API, com CSRF**) |
| GET/POST | `/planos-manejo` | `plano_manejo/*.php`, `api/plano_manejo.php` |
| GET/PATCH/DELETE | `/planos-manejo/:uuid` | idem (**com checagem de dono — corrige o IDOR**) |
| POST | `/planos-manejo/:uuid/aprovar` | `aprovar.php` |
| POST | `/planos-manejo/:uuid/croqui` | `upload_croqui.php` (**URL relativa, não absoluta**) |
| GET | `/conformidade/relatorios` | `modulos/conformidade/relatorios.php` |
| POST | `/conformidade/relatorios/gerar` | `conformidade/relatorios/gerar.php` (job assíncrono) |

**Regras de negócio a preservar (e corrigir):**
- Pontuação da visita: **aplicar o `peso` do item** (o legado ignora o peso).
- Item crítico não conforme **reprova automaticamente**, independentemente da pontuação.
- Gravidade de NC gerada automaticamente deve usar a escala completa (Baixa, Média, Alta,
  Crítica) — o legado só gera Crítica e Alta.
- Transições de status de NC validadas (Aberta → Em Correção → Resolvida), com
  `verificado_por` referenciando **usuário** e não nome.
- Renovação de certificação **cria novo registro**; o anterior é marcado como renovado.
- Job diário promove certificações vencidas para status "Vencida".
- **Certificado** com QR de verificação pública e, se exigido, assinatura digital
  (ICP-Brasil ou PAdES) — o legado não tem nenhuma das duas.

---

## 7. M4 — Financeiro

| Método | Rota | Substitui |
|---|---|---|
| GET/POST | `/planos-mensalidade` | `mensalidades/planos.php`, `plano_cadastrar.php` |
| GET/PATCH/DELETE | `/planos-mensalidade/:uuid` | `plano_editar.php`, `plano_excluir.php` |
| GET/POST | `/mensalidades` | `mensalidades/listar.php`, `cadastrar.php` |
| GET | `/mensalidades/:uuid` | detalhe |
| POST | `/mensalidades/gerar` | `gerar.php` (**idempotente por competência**) |
| POST | `/mensalidades/:uuid/baixa` | `baixa.php` |
| POST | `/mensalidades/:uuid/estornar` | `estornar.php` (**transação única reversa**) |
| DELETE | `/mensalidades/:uuid` | `excluir.php` (**soft delete**) |
| GET/POST | `/caixa` | `caixa/listar.php`, `cadastrar.php` |
| DELETE | `/caixa/:uuid` | `excluir.php` |
| GET | `/caixa/relatorio` | `caixa/relatorio.php` |
| POST | `/caixa/cobranca` | `caixa/cobranca.php` (**gera cobrança PIX**) |
| GET | `/caixa/cobranca/:uuid/status` | consulta de status |
| GET/POST | `/contas` | `contas/*.php` |
| GET/PATCH/DELETE | `/contas/:uuid` | idem |
| POST | `/webhooks/pix/:provedor` | `api/pix/webhook/*.php` |

### 7.1 Webhook PIX — contrato seguro

```
POST /api/v2/webhooks/pix/{asaas|gerencianet|mercadopago}
Headers: assinatura conforme o provedor; X-Request-Id; corpo bruto preservado para validação
```

**Requisitos obrigatórios (todos ausentes no legado):**
1. **Validar a assinatura** do provedor sobre o **corpo bruto** antes de qualquer parsing.
2. **Idempotência** por `event_id` do provedor — reentrega não pode gerar baixa dupla.
3. Processar em **fila** (BullMQ) e responder `200` rápido; nunca fazer trabalho pesado na requisição.
4. Registrar o evento bruto em `webhook_eventos` (auditoria e reprocessamento).
5. Tolerar **eventos fora de ordem** (o estado final deve ser consistente).
6. Nunca confiar em valores do payload para decidir montante — conferir contra a
   `mensalidade` local.

---

## 8. M5 — Produção Animal

| Método | Rota | Substitui |
|---|---|---|
| GET | `/producao-animal/painel` | `producao_animal/painel.php` |
| GET/POST | `/producao-animal/lotes` | `lotes_*.php` |
| GET/PATCH/DELETE | `/producao-animal/lotes/:uuid` | idem |
| GET/POST | `/producao-animal/ambientes` | `ambientes_*.php` |
| GET/PATCH/DELETE | `/producao-animal/ambientes/:uuid` | idem |
| GET/POST | `/producao-animal/alimentacao` | `alimentacao_*.php` |
| GET/PATCH/DELETE | `/producao-animal/alimentacao/:uuid` | idem |
| GET/POST | `/producao-animal/sanidade` | `sanidade_*.php` |
| GET/PATCH/DELETE | `/producao-animal/sanidade/:uuid` | idem |
| GET/POST | `/producao-animal/reproducao` | `reproducao_*.php` |
| GET/PATCH/DELETE | `/producao-animal/reproducao/:uuid` | idem |
| GET/POST | `/animais` | `manejo_animal/animais_*.php` |
| GET/PATCH | `/animais/:uuid` | `animais_editar.php`, `visualizar.php` |
| GET | `/producao-animal/rastreabilidade` | `rastreabilidade.php` |

---

## 9. M6 — Agroindústria

| Método | Rota | Substitui |
|---|---|---|
| GET/POST | `/agroindustrias` | `minhas_*.php` |
| GET/PATCH/DELETE | `/agroindustrias/:uuid` | idem |
| GET/POST | `/agroindustrias/:uuid/setores` | `setores_*.php` |
| GET/POST | `/agroindustrias/:uuid/categorias` | `categorias_*.php` |
| GET/POST | `/agroindustrias/:uuid/atividades` | `atividades_*.php` |
| GET/POST | `/agroindustrias/:uuid/ocorrencias` | `ocorrencias_*.php` |
| GET/POST | `/receitas` | `receitas_*.php` |
| GET/PATCH/DELETE | `/receitas/:uuid` | idem |
| POST | `/receitas/calcular-rotulo` | cálculo do percentual orgânico e selo |
| GET/POST | `/ordens-producao` | `ordens_*.php` |
| GET/PATCH | `/ordens-producao/:uuid` | `ordens_visualizar.php` |
| GET | `/ordens-producao/:uuid/balanco-massas` | balanço entrada e saída |
| GET/POST | `/higienizacao` | `higienizacao_*.php` |
| GET | `/agroindustria/rastreabilidade` | `rastreabilidade.php` |

### 9.1 Serviço de rotulagem (regra pura, cobertura 100%)

```json
// POST /receitas/calcular-rotulo
{ "ingredientes": [ { "produtoId": 12, "quantidade": 700, "unidade": "g", "organico": true } ] }

// 200
{
  "data": {
    "percentualOrganico": 87.5,
    "selo": "INGREDIENTES_ORGANICOS",
    "frasePermitida": "Produto com ingredientes orgânicos",
    "bloqueiaMencaoOrganico": false
  }
}
```

**Testes obrigatórios de fronteira:** 69,99 por cento → `NENHUM`; 70,00 → `INGREDIENTES_ORGANICOS`;
94,99 → `INGREDIENTES_ORGANICOS`; 95,00 → `ORGANICO_BRASIL`.

---

## 10. M8 — Governança, Eventos & Comunicação

| Método | Rota | Substitui |
|---|---|---|
| GET/POST | `/assembleias` | `assembleia/listar.php`, `cadastrar.php` |
| GET/PATCH/DELETE | `/assembleias/:uuid` | `editar.php` |
| GET/POST | `/assembleias/:uuid/pautas` | `pautas.php` |
| POST | `/assembleias/:uuid/publicar` | `publicar.php` (dispara notificações) |
| GET | `/assembleias/:uuid` | `visualizar.php` |
| POST | `/assembleias/:uuid/votos` | votação (1 voto por associado) |
| POST | `/assembleias/:uuid/encerrar` | `encerrar.php` |
| GET | `/assembleias/:uuid/resultado` | `resultado.php` (imutável após encerrar) |
| GET/POST | `/eventos` | `eventos/listar.php`, `cadastrar.php` |
| GET/PATCH/DELETE | `/eventos/:uuid` | `visualizar.php`, `editar.php` |
| POST | `/eventos/:uuid/presenca` | `toggle_presenca.php` (idempotente) |
| GET | `/eventos/:uuid/presencas` | `presencas.php`, `get_presenca_evento.php` |
| GET | `/eventos/:uuid/relatorio.pdf` | `relatorio_presenca.php` |
| GET | `/eventos/pendentes` | `get_eventos_pendentes.php` |
| GET/PUT | `/notificacoes/preferencias` | `salvar_notificacoes.php` |
| GET | `/notificacoes` | `notificacoes` |
| POST | `/notificacoes/:id/lida` | marcar como lida |
| GET | `/comunicacao/links-whatsapp` | `api/comunicacao/whatsapp_links.php` |

---

## 11. M9 — Territorialidade & CNPO

| Método | Rota | Substitui |
|---|---|---|
| GET | `/localidades/estados` | `api/get_estado_cidade.php` |
| GET | `/localidades/cidades` | `api/get_cidades.php` |
| GET | `/localidades/microrregioes` | `api/regionalizacao/microrregioes.php` |
| GET | `/regionalizacao/dados` | `api/regionalizacao/dados.php` |
| GET | `/regionalizacao/mapa` | `modulos/regionalizacao/mapa.php` |
| GET | `/cnpo` | `consulta_cnpo/index.php` |
| GET | `/cnpo/consulta` | `api/cnpo_consulta.php` (**com índice e busca normalizada persistida**) |
| POST | `/admin/cnpo/importar` | `dev-utils/atualizar_cnpo.php` (job, **não** endpoint web) |

> **Correção obrigatória:** a consulta CNPO do legado faz `REPLACE` encadeado sobre 5
> colunas → *full scan* de 23.232 linhas por consulta. Na versão nova, criar coluna
> normalizada (sem acento, minúscula) **indexada** e buscar nela.

---

## 12. M10 — Relatórios & Analytics

| Método | Rota | Substitui |
|---|---|---|
| GET | `/relatorios/catalogo` | `config/relatorios_config.php` |
| POST | `/relatorios/:tipo/gerar` | geração (**enfileira** e devolve `jobId`) |
| GET | `/relatorios/jobs/:jobId` | status do job (progresso, pronto, erro) |
| GET | `/relatorios/jobs/:jobId/download` | URL assinada do arquivo gerado |
| GET | `/relatorios/visao-geral` | `api/relatorios/visao-geral.php` (**agora autenticado**) |
| GET | `/relatorios/produtividade` | `api/relatorios/produtividade.php` |
| GET | `/relatorios/inadimplencia` | `api/relatorios/inadimplencia.php` |
| GET | `/relatorios/fluxo-caixa` | `api/relatorios/fluxo-caixa*.php` |
| GET | `/relatorios/contas-pagar` | `api/relatorios/contas-pagar.php` |
| GET | `/relatorios/contas-receber` | `api/relatorios/contas-receber.php` |
| GET | `/relatorios/estoque-atual` | `api/relatorios/estoque-atual.php` |
| GET | `/relatorios/movimentacoes` | `api/relatorios/movimentacoes.php` |
| GET | `/relatorios/pagamentos-por-forma` | `api/relatorios/pagamentos-por-forma.php` |
| GET | `/relatorios/previsao-receita` | `api/relatorios/previsao-receita.php` |
| GET | `/relatorios/status-associados` | `api/relatorios/status-associados.php` |
| GET | `/relatorios/lista-associados` | `api/relatorios/lista-associados.php` |
| GET | `/relatorios/atividades-diarias` | `api/relatorios/atividades-diarias.php` |
| GET | `/relatorios/admin/export` | `api/relatorios/admin_export.php` |
| GET | `/dashboards/:perfil` | `modulos/dashboard/dashboard_*.php` |

**Decisões:**
- **Relatórios pesados são assíncronos** (ADR-007): `POST /gerar` enfileira, devolve
  `jobId`; o usuário é notificado; o download usa **URL assinada com expiração**.
- **PDF por Puppeteer** sobre os templates HTML existentes (ADR-006) — preserva o layout.
- Nenhum endpoint de relatório sem autenticação (corrige o risco A-01).
- Todo relatório é **escopado** pelo `ContextoUsuario` (associado vê só o seu; gestor de
  certificadora vê só a sua).

---

## 13. M11 — Portal, Dashboards e Administração

| Método | Rota | Substitui |
|---|---|---|
| GET | `/portal/resumo` | `modulos/portal/area.php` (dados do BFF) |
| GET | `/portal/acessos` | `classes/PortalAccess.php` |
| GET | `/dashboards/agricultor` | `dashboard_agricultor.php` |
| GET | `/dashboards/agroindustria` | (**novo** — não existe no legado) |
| GET | `/dashboards/gestor` | `dashboard_gestor.php` |
| GET | `/dashboards/admin` | `dashboard_admin.php` |
| GET | `/dashboards/diretoria` | `dashboard_diretoria.php` |
| GET/POST | `/usuarios` | `usuarios/listar.php`, `cadastrar.php` |
| GET/PATCH/DELETE | `/usuarios/:uuid` | `editar.php`, `excluir.php` |
| GET/PATCH | `/usuarios/meu-perfil` | `usuarios/perfil.php`, `perfil.php` |
| GET | `/auditoria` | `audit_log` (**novo endpoint — o legado não expõe**) |
| GET | `/admin/feature-flags` | (**novo**) |

---

## 14. Matriz de paridade de rotas (legado → alvo)

| Prefixo legado | Rotas | Módulo alvo | Observação |
|---|---:|---|---|
| `associados, associado, associacoes, associacao, cadastros-pendentes, cadastro-ficha, fichas-completas, carteirinha, meu-perfil` | ~22 | M1 | — |
| `caderno-campo, atividades-diarias, atividade-diaria, propriedade(s), lavoura(s), insumo(s), produto(s), lote(s), ocorrencia(s), categoria(s)-atividade(s)` | ~40 | M2 | inclui `sync` |
| `roteiros-inspecao, roteiro-inspecao, visitas-tecnicas, visita-tecnica, nao-conformidade(s), certificacao, certificacoes, insumos-aprovados, plano(s)-manejo` | ~20 | M3 | **+2 rotas que faltavam** |
| `mensalidades, mensalidade, planos, plano, caixa, contas, conta` | ~17 | M4 | + webhooks |
| `producao-animal, manejo-animal` | ~17 | M5 | — |
| `agroindustria` | ~24 | M6 | — |
| `fornecedor` | 4 | M7 | — |
| `eventos, evento, assembleia, comunicacao` | ~22 | M8 | — |
| `regionalizacao, consulta-cnpo` | ~4 | M9 | — |
| `relatorios, relatorios-admin, relatorios-conformidade, dashboard` | ~20 | M10 | — |
| `portal, api/...` | ~30 | M11 | — |
| `usuarios, usuario, login, logout, recuperar-senha, redefinir-senha, completar-perfil, admin` | ~20 | M12 | — |
| `teste, teste-db, teste-ajax` | 3 | — | **remover** |
| `api/**` (raiz) | ~30 | por módulo | **todos autenticados** |

---

## 15. Checklist de contrato (gate G4)

- [ ] OpenAPI 3.1 gerado e acessível em `/api/docs`
- [ ] **Nenhuma** rota sem documentação
- [ ] **Nenhuma** rota sem guarda (exceto as explicitamente públicas, marcadas)
- [ ] Todo `*Ref` consumido vem do kernel — nenhum módulo lê tabela alheia
- [ ] Todos os schemas definidos em `packages/contracts` e reusados por Web e Mobile
- [ ] Nenhum `any` nos contratos
- [ ] Erros no formato único (seção 1.2) — verificado por teste de contrato
- [ ] Paginação e filtros consistentes em todas as coleções
- [ ] `Idempotency-Key` exigido em `/sync/push` e nos webhooks
- [ ] IDs expostos em URL são `uuid`, nunca ID numérico sequencial nem ID cifrado
