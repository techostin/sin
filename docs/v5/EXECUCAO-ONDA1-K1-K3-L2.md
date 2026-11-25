# Execução Onda 1 — K1, K3 e L2

> **Data:** 01/11/2026 · **Status:** fundação da Onda 1 entregue.
> **Commit:** `089f246` · **Tag:** `onda1-k1-k3-l2`
> **Nenhum dado pessoal ou segredo é exibido neste relatório.**

---

## 1. Entregas

| Módulo | Entrega | Status |
|---|---|---|
| **K1** | papéis, permissões, escopos, contexto de sessão e capabilities | ✅ |
| **K3** | auditoria em banco e numeração sequencial de documentos | ✅ (arquivos/notificações depois) |
| **L2** | manifesto de navegação por capacidade e superfícies web/PWA | ✅ (contrato da UI) |

### Números

| Item | Valor |
|---|---:|
| Papéis | **8** |
| Permissões | **19** |
| Vínculos usuário × papel iniciais | **11** |
| Áreas de navegação no manifesto | **9** |
| Testes | **42** |
| Asserções | **115** |
| PHPStan / Deptrac / PHPCS | **0 / 0 / 0** |

---

## 2. K1 — Identidade & Acesso

### Schema (aditivo, em `sql/k1_identidade_k3_platform.sql`)

- `papeis`: admin, gestor_associacao, gestor_fiscalizacao, fiscal_campo, certificador, produtor, diretoria, agroindustria.
- `permissoes`: 19 capacidades (`associados.*`, `fiscalizacao.*`, `caderno.*`, `financeiro.*`, `governanca.*`, `relatorios.read`, `config.*`, `portal.read`, `agroindustria.*`).
- `papel_permissao`: matriz papel × permissão.
- `usuario_papel`: vínculo com escopo (`global`, `certificadora`, `associacao`, `municipio`).
- Migração inicial: `nivel=1` → admin; `nivel=2` → gestor_associacao; `nivel=3` → produtor, apenas se o usuário ainda não tiver papel.

### API

| Endpoint | Comportamento |
|---|---|
| `GET /api/v1/me` | dados do usuário autenticado, papéis e escopos |
| `GET /api/v1/me/capabilities` | capabilities + navegação + superfícies |

- Sem sessão: **401**.
- O provider inicia a sessão legada apenas nesses endpoints; a API continua stateless nos demais.
- `RolePolicy` agora usa as capabilities quando existem e mantém o fallback por papel legado.

### Exemplo de manifesto do admin ID 7

```text
roles: admin
capabilities: 19
navigation: 9 áreas
surfaces: web
```

### UI por persona

O manifesto já distingue, por exemplo:

- **fiscal_campo** → fiscalização, caderno, perfil; superfície **PWA**;
- **gestor_associacao** → associação, financeiro, governança, relatórios; **web**;
- **produtor** → associação (leitura), caderno, fiscalização (leitura); **PWA**;
- **admin** → todas as 9 áreas; **web**.

> **Limite consciente:** o menu legado ainda não consome o manifesto. A Onda 1 entrega o
> **contrato** (`/me/capabilities`); a composição visual entra junto de cada módulo (D1, D3, D2…),
> para não reescrever o menu inteiro antes de as telas existirem.

---

## 3. L2 — Manifesto de navegação

`NavigationCatalog` define as áreas:

`inicio`, `associados`, `fiscalizacao`, `caderno`, `financeiro`, `governanca`,
`relatorios`, `agroindustria`, `configuracoes`.

Cada item tem `key`, `label`, `module`, `permission` e `surface` (`web`/`pwa`). A UI filtra pelo
retorno de `/me/capabilities`; o servidor continua validando toda ação.

---

## 4. K3 — Plataforma

| Componente | Arquivo | Cobertura |
|---|---|---|
| Auditoria em banco | `src/Kernel/Audit/DatabaseAuditLogger.php` | insert em `audit_log` com usuário, ação, tabela, registro, descrição e IP |
| Numeração | `src/Kernel/Documents/DocumentNumberGenerator.php` | sequência por tipo/ano com transação e `FOR UPDATE` no MySQL |
| Tabela | `document_sequences` | criada no schema |
| Testes | `AuditLoggerTest`, `DocumentNumberGeneratorTest` | SQLite em memória |

Arquivos, notificações e templates de documento ficam para a evolução da K3/Onda 2, quando D3
começar a emitir documentos.

---

## 5. Staging e ambientes

- Staging sincronizado no commit `089f246`, sem repetir o incidente do vendor: a junção foi removida
  com `rmdir` antes de remover o worktree.
- `app-staging` em `http://localhost/app-staging` com `sisagro_staging`.
- Testes: login **200**, health **200**, K2 estados **200**, `/me` **401** (sem sessão), main health **200**.

---

## 6. Próxima onda

**Onda 2 — D1 + T1 + D2 + D7:**

1. Expor D1 com `AuthMiddleware`/capabilities e concluir escrita/aprovação.
2. Criar o núcleo **T1** de lotes/eventos/custódia.
3. Primeira fatia vegetal do **D2** com lote colhido, insumo e QR.
4. Vincular lote de insumo do **D7**.

Gate da Onda 2: associado com escrita/aprovação, lote vegetal rastreável e QR público.
