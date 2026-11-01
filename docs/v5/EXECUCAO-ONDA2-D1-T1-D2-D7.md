# Execução Onda 2 — D1, T1, D2 e D7

> **Data:** 01/11/2026 · **Status:** fatia vertical entregue.
> **Commit:** `4097523` · **Tag:** `onda2-d1-t1-d2`
> **Nenhum dado pessoal ou segredo é exibido neste relatório.**

---

## 1. Entregas

| Módulo | Entrega | Status |
|---|---|---|
| **D1** | leitura exposta com auth, criação e aprovação de associado | ✅ fatia core |
| **T1** | schema canônico, eventos, elos, QR público | ✅ vegetal + insumo |
| **D2** | lote vegetal no `lotes_producao` + registro/evento no T1 | ✅ primeira fatia |
| **D7** | lote de insumo no T1 | ✅ primeira fatia |
| **Auth** | rotas protegidas por capability, 401 sem sessão | ✅ |

### Qualidade

| Item | Resultado |
|---|---:|
| Testes | **49** |
| Asserções | **129** |
| PHPStan / Deptrac / PHPCS | **0 / 0 / 0** |
| Health main/staging | **200** |
| Login main/staging | **200** |

---

## 2. T1 — Rastreabilidade

### Schema (`sql/t1_rastreabilidade.sql`)

| Tabela | Papel |
|---|---|
| `rastreabilidade_lotes` | registro canônico: tipo, referência legada, código público, associação/propriedade, status |
| `rastreabilidade_eventos` | colheita, recebimento, manejo, transformação etc. |
| `rastreabilidade_elos` | insumo → produção, transformação, movimentação |

- O T1 **não duplica** o lote de produção: referencia `lotes_producao` e, no futuro, `animal_lotes`/`ordens_producao`.
- `codigo_publico` é usado no QR; a verificação pública não expõe PII.

### Endpoints

| Método | Rota | Permissão |
|---|---|---|
| GET | `/api/v1/rastreabilidade/verificar/{codigo}` | pública |
| GET | `/api/v1/rastreabilidade/lotes/{uuid}` | `caderno.read` |
| POST | `/api/v1/rastreabilidade/lotes/{uuid}/eventos` | `caderno.write` |
| POST | `/api/v1/rastreabilidade/lotes/{uuid}/vinculos` | `caderno.write` |

---

## 3. D2 — primeiro lote vegetal

`POST /api/v1/caderno/lotes`:

- grava o lote legado em `lotes_producao` (produto, data, quantidade, unidade);
- cria o registro T1 tipo `vegetal`;
- deriva a propriedade via `produtos.lavoura_id → lavouras.propriedade_id`;
- registra o evento `colheita`;
- devolve `uuid` e `codigo_publico` para o QR.

Teste real executado e limpo em seguida:

```text
uuid=81e1df1f-eaf2-44e2-8c28-fa658321931e
codigo=LOTE-509F659D195D
eventos=1
elos=0
```

---

## 4. D7 — primeiro lote de insumo

`POST /api/v1/insumos/lotes`:

- cria lote T1 tipo `insumo`;
- registra evento `recebimento`;
- aceita nome, fornecedor, lote e validade no payload.

> Nesta fatia o lote de insumo vive no T1; a integração com a tabela legada `insumos`
> e o vínculo insumo → lote vegetal via `/vinculos` entram na evolução do D7.

---

## 5. D1 — leitura + escrita core

### Rotas

| Método | Rota | Permissão |
|---|---|---|
| GET | `/api/v1/associados` | `associados.read` |
| GET | `/api/v1/associados/pendentes` | `associados.read` |
| GET | `/api/v1/associados/{uuid}` | `associados.read` |
| POST | `/api/v1/associados` | `associados.write` |
| POST | `/api/v1/associados/{uuid}/aprovar` | `associados.approve` |

- Criação grava nome, sobrenome, e-mail, CPF, associação, microrregião e CNPO; status inicial `pendente`.
- Aprovação muda `pendente → ativo`.
- CPF/e-mail continuam mascarados na resposta.
- Teste real: criado, aprovado e removido em seguida.

### Limites desta fatia

- Não cobre as 52 colunas da ficha completa; os campos restantes entram na evolução de D1.
- O escopo por certificadora/associação ainda **não filtra linhas**; a capability controla acesso, mas o row-level/tenant precisa ser fechado antes de produção.
- A UI legada ainda não consome essas rotas; a integração visual vem com as telas.

---

## 6. Segurança

- Todas as rotas de D1/T1/D2/D7 usam `SessionContextProvider` + capability.
- Sem sessão: **401**.
- Sem capability: **403**.
- Verificação pública por código não retorna nome, e-mail, CPF ou endereço.
- Upload/CSRF continuam no legado; o T1 ainda não recebe arquivos.

> **Pendência de segurança para a Onda 3:** aplicar `TenantScope` nas consultas de D1/T1
> (certificadora/associação) e testes de IDOR antes de expor D3.

---

## 7. Staging

- Staging sincronizado no commit `4097523` com o procedimento seguro (junção do vendor removida
  antes do worktree).
- `http://localhost/app-staging`: login **200**, health **200**, K2 **200**, D1/T1 sem sessão **401**.

---

## 8. Próxima onda

**Onda 3 — D3 Fiscalização & Conformidade**, precedida de:

1. `TenantScope` efetivo em D1/T1 (row-level).
2. Fluxo de visita/NC e revisão.
3. Emissão de documento somente com cadeia T1 completa.
4. `DocumentNumberGenerator` integrado ao certificado.

Gate: fiscal executa, gestor revisa, certificador emite; certificado bloqueado sem cadeia.
