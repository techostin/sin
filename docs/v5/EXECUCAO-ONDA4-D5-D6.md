# Execução Onda 4 — D5 Animal + D6 Agroindústria (fatia inicial)

> **Data:** 01/11/2026 · **Status:** fatia inicial do T1 para animal e transformação.
> **Commit:** `20d6abb`, `bd7b4c6`, `7699553` · **Tag:** `onda4-d5-d6`, `onda4-animais`, `onda4-d6-completo`
> **Nenhum dado pessoal ou segredo é exibido neste relatório.**

---

## 1. Entregas

| Módulo | Endpoint | Resultado |
|---|---|---|
| **D5** | `POST /api/v1/animal/lotes` | cria `animal_lotes` + registro T1 `animal` + evento `entrada` |
| **D5** | `POST /animal/lotes/{uuid}/animais` | animal individual vinculado ao lote e evento T1 |
| **D5** | `POST /animal/lotes/{uuid}/animais/{id}/sanidade` | evento sanitário + evento T1 |
| **D6** | `POST /api/v1/agroindustria/transformacoes` | cria elo T1 `transformacao` entre dois lotes |
| **D6** | `POST /agroindustria/receitas` | receita + ingredientes |
| **D6** | `POST /agroindustria/ordens` | ordem de produção + lote T1 agroindústria + vínculo dos lotes de entrada |
| **T1** | `createRecipe` / `createAgroOrder` | receita/ordem e elos no mesmo registro canônico |

### Evidências

| Item | Resultado |
|---|---:|
| Testes | **69 / 162 asserções** |
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

### Animais individuais e sanidade

```text
lote_animal=24233955-...
animal_id=5 (Mimosa, Bovino)
sanidade_id=8 (VACINACAO)
```

Ambos os dados foram limpos, incluindo `animais`, `controle_sanidade_animal`, `animal_lotes` e o
registro T1.

### D6 — receita e ordem

```text
receita_id=9 (E2E Molho)
ordem_uuid=aca539b2-...
codigo=LOTE-4ACA3EC8957D
entrada_total=18 kg
elos=1 (lote de entrada -> ordem)
```

Receita, ingredientes, ordem, lotes e vínculos foram removidos após o teste.

---

## 3. O que ainda falta para D5/D6 completos

| Item | Situação |
|---|---|
| Animais individuais, sanidade, reprodução, movimentação | ✅ indivíduo + sanidade; reprodução/movimentação depois |
| Ordem de produção, receita, higienização, balanço de massa | ✅ receita/ordem/vínculo; higienização e perdas finas depois |
| Vínculo automático insumo → transformação → produto | parcial; o elo T1 existe |
| UI animal/agroindústria | não iniciada |
| Relatórios de cadeia animal/agro | via L1, depois |

---

## 4. Próxima etapa

1. Completar D5 (reprodução e movimentação).
2. Completar D6 (higienização, perdas finas e rótulo).
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
