# Execução Onda 5 — L1 Relatórios + L2 Portal/UI por persona

> **Data:** 01/11/2026 · **Status:** fatia core entregue.
> **Commit:** `349383c` · **Tag:** `onda5-l1-l2`

---

## 1. Entregas

| Módulo | Endpoint/Entrega | Resultado |
|---|---|---|
| **L1** | `GET /api/v1/relatorios/resumo` | contagens de associados, lotes, visitas, financeiro e documentos |
| **L2** | `GET /api/v1/portal/dashboard` | cards filtrados por capability + manifesto de navegação |
| **L2/UI** | filtro de menu legado | esconde links sem capability usando `/me/capabilities` |
| **K1** | base de capabilities | 19 permissões e 9 áreas |

### E2E autenticado

```text
resumo=200
associados={"ativo":30,"pendente":1}
portal=200
cards=associados,rastreabilidade,fiscalizacao,financeiro,documentos
```

O menu legado agora consulta o manifesto e oculta áreas não autorizadas (admin continua vendo tudo).

---

## 2. Qualidade

| Item | Resultado |
|---|---:|
| Testes | **74 / 174 asserções** |
| PHPStan | **0 erros** |
| Deptrac | **0 violações** |
| PHPCS | **0 erros** |
| Main/staging health | **200** |
| Rotas sem sessão | **401** |
| Staging | `349383c` |

---

## 3. Limites

| Item | Situação |
|---|---|
| Exportação PDF/Excel dos relatórios | próxima fatia |
| Dashboard visual completo | cards e dados via API; front-end legado |
| Filtro de menu | oculta links; não redesenha as telas |
| D7/D8/D4 detalhados | listagens, votos/presença, planos/caixa ainda parciais |

---

## 4. Git

- Branch: `feat/f1-kernel`
- Commit: `349383c`
- Tag: `onda5-l1-l2`
- Push: não realizado
