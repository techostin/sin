# Execução Onda 4 — D5 Animal + D6 Agroindústria (fatia inicial)

> **Data:** 01/11/2026 · **Status:** fatia inicial do T1 para animal e transformação.
> **Commit:** `20d6abb` · **Tag:** `onda4-d5-d6`
> **Nenhum dado pessoal ou segredo é exibido neste relatório.**

---

## 1. Entregas

| Módulo | Endpoint | Resultado |
|---|---|---|
| **D5** | `POST /api/v1/animal/lotes` | cria `animal_lotes` + registro T1 `animal` + evento `entrada` |
| **D6** | `POST /api/v1/agroindustria/transformacoes` | cria elo T1 `transformacao` entre dois lotes |
| **T1** | `createAnimalLot` | lote animal no mesmo registro canônico do T1 |

### Evidências

| Item | Resultado |
|---|---:|
| Testes | **67 / 157 asserções** |
| PHPStan / Deptrac / PHPCS | **0 / 0 / 0** |
| Rotas sem sessão | **401** |
| Health main/staging | **200** |

---

## 2. Prova real

Executado no main com a conta admin:

```text
animal_uuid=295f9e01-8d20-4def-b782-14562747c1fb
animal_codigo=LOTE-067C2B89FB8D
vegetal_uuid=9c1e2b3c-f977-44b9-96c0-6035fbd553a5
transformacao=295f9e01... -> 9c1e2b3c...
```

Os registros de teste foram removidos em seguida.

---

## 3. O que ainda falta para D5/D6 completos

| Item | Situação |
|---|---|
| Animais individuais, sanidade, reprodução, movimentação | próxima fatia D5 |
| Ordem de produção, receita, higienização, balanço de massa | próxima fatia D6 |
| Vínculo automático insumo → transformação → produto | parcial; o elo T1 existe |
| UI animal/agroindústria | não iniciada |
| Relatórios de cadeia animal/agro | via L1, depois |

---

## 4. Próxima etapa

1. Completar D5 (animais individuais e eventos sanitários).
2. Completar D6 (ordem, receita, massa e rótulo).
3. D7 (insumos legados), D8 (governança) e D4 (financeiro).
4. Fechar UI, E2E e relatórios por persona.

---

## 5. Staging

- Staging sincronizado em `20d6abb`.
- Health/K2/login: **200**.
- D5/D6 sem sessão: **401** (GET nas rotas POST retorna 405).

---

## 6. Git

- Branch: `feat/f1-kernel`
- Commit: `20d6abb`
- Tag: `onda4-d5-d6`
- Push: não realizado
