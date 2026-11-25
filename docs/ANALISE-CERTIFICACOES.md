# Análise da tela atual de Certificações (`/certificacoes`) → novo módulo social

Fonte analisada: `modulos/certificacao/listar.php`, `api/certificacao/controller.php`,
`cadastrar.php`, `editar.php`, `renovar.php`, `historico.php` e `certificado.php?format=html`.

## 1. Filtros da listagem atual

| Filtro | Valores no sistema atual | Implementado no novo módulo |
|---|---|---|
| Tipo | `SPG`, `OCS`, `Rede Ecovida`, `Outro` | `?tipo=` (select) |
| Status | `Ativa`, `Em Conversão`, `Vencida`, `Suspensa`, `Cancelada` | `?status=` (select) |
| Validade | `proximas` (vence em 30 dias), `vencidas` | `?validade=` (select) |
| Associado | `associado_id` (usado no histórico) | `?associado_id=` |
| Busca | (não existia na tela; a API não tinha `q`) | `?q=` por nome/sobrenome/nº do certificado |

Chips de resumo: total, ativas, vencendo em 30 dias e vencidas, calculados com os mesmos
filtros aplicados.

## 2. Botões de navegação da tela

| Botão atual | Rota atual | Nova rota |
|---|---|---|
| Nova Certificação | `/certificacao/novo` | `/social/certificacoes.php?acao=nova` |
| Voltar ao início | `/` | `← Painel` (novo) / link da lista |
| Aplicar Filtros | JS → API | submit GET (filtros no servidor) |

## 3. Coluna Ações (todos os links)

| Ação | Rota atual | Nova rota | Regra |
|---|---|---|---|
| Editar | `/certificacao/{id}/editar` | `?acao=editar&id=` | `fiscalizacao.write` ou `certificate` |
| Renovar | `/certificacao/{id}/renovar` | `?acao=renovar&id=` | idem |
| Ver histórico | `/certificacao/historico/{associado_id}` | `?acao=historico&associado_id=` | `fiscalizacao.read` |
| Ver detalhes | modal `GET api/certificacao/controller.php?id=` | `?acao=detalhes&id=` | `fiscalizacao.read` |
| Visualizar certificado | `/certificacao/{id}/certificado?format=html` | `?acao=certificado&id=` (imprimir/PDF do navegador) | `fiscalizacao.read` |
| Baixar certificado assinado | `documento_url` | link direto quando existir | `fiscalizacao.read` |
| Excluir | `DELETE api/certificacao/controller.php?id=` | `POST acao=excluir` (CSRF, confirmação) | `write`/`certificate` |

## 4. Estrutura implementada

- `src/Platform/Certificacoes.php` — regras: filtros, resumo, consultas, validação,
  criação, edição, renovação, exclusão lógica, histórico e propriedades.
- `src/Platform/FluxoCertificacoes.php` — rotas internas (`acao=nova/editar/renovar/detalhes/
  historico/certificado/excluir/salvar`), CSRF, auditoria e flash.
- `src/Platform/UiCertificacoes.php` — telas (lista com filtros, formulário, detalhes,
  histórico e certificado imprimível).
- `social/certificacoes.php` — entrada do módulo; o item **Certificações (SPG/OCS)** do menu
  lateral agora aponta para esta tela.

Registros de auditoria: `CERTIFICACAO_CRIADA`, `CERTIFICACAO_ATUALIZADA`,
`CERTIFICACAO_RENOVADA`, `CERTIFICACAO_EXCLUIDA` no mesmo `audit_log`.

## 5. Diferenças conscientes em relação ao sistema atual

1. **Exclusão**: o controller atual faz `DELETE` físico; o novo módulo marca `excluido = 1`
   (preserva histórico e permite auditoria). A listagem filtra `excluido = 0`; o histórico
   mostra o registro com a marca “excluída”.
2. **Renovação**: o fluxo atual também permite subir o PDF assinado (`arquivo_assinatura`);
   nesta fatia a renovação atualiza número/emissão/validade/status/data de renovação e
   observações. O upload do PDF assinado é a próxima fatia (reaproveitando
   `api/certificacao/controller.php` e a pasta `uploads/certificados`).
3. **Certificado**: a tela atual gera HTML via `CertificadoOrganico` e PDF via Dompdf; o novo
   módulo entrega HTML imprimível (`Ctrl+P` → salvar como PDF), com os mesmos dados
   (associado, CPF, propriedades, número, organismo, emissão, validade, grupo e status).

## 6. Verificação

`dev-utils/smoke-primeiro-acesso.php` cobre, com um usuário fiscal real: abertura do módulo,
filtros `tipo`/`validade`, formulário de nova certificação, criação, detalhes, edição,
renovação, histórico, certificado imprimível e exclusão lógica — com limpeza dos registros de
teste ao final.
