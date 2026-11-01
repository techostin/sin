# Plano de Ondas Aprovado

> **Aprovado em:** 01/11/2026 · **Substitui a ordem linear anterior.**
> A evolução é por **capacidades**, não por uma lista solta de módulos.
>
> **Status:** Onda 0 ✅ · Onda 1 ✅ (`089f246`) · Onda 2 ✅ fatia vertical (`4097523`) ·
> Onda 3 ✅ core + documentos/evidências/E2E (`0c67a84`, `b357201`, `e49c7f9`, `91a8a0e`) ·
> Onda 4 ✅ fatia inicial D5/D6 (`20d6abb`) · próximas fatias: D5/D6 completos, D7/D8/D4.

## 1. Unidades lógicas (14)

| Unidade | Nome | Onda |
|---|---|---:|
| K1 | Identidade & Acesso | 1 |
| K2 | Território & Referências | 0 (piloto) |
| K3 | Plataforma & Auditoria | 1 |
| L2 | Portal, Dashboards & Composição | 1 e 5 |
| D1 | Quadro Associativo & Adesão | 2 |
| T1 | **Rastreabilidade & Cadeia de Custódia** | 2 (transversal) |
| D2 | Caderno de Campo | 2 (vegetal) |
| D7 | Suprimentos & Insumos | 2 (lote de insumo) |
| D3 | Fiscalização & Conformidade | 3 |
| D5 | Produção Animal | 4 |
| D6 | Agroindústria | 4 |
| D8 | Governança & Comunicação | 5 |
| D4 | Financeiro Associativo | 5 |
| L1 | Relatórios & Analytics | 5 (incremental desde a 2) |

## 2. Ondas e gates

| Onda | Escopo | Gate de saída |
|---|---|---|
| 0 | F1, K2, D1 leitura | fundação, health, território, leitura mascarada |
| 1 | K1, K3, L2 | login com papéis/escopos, `/me/capabilities`, auditoria, numeração, manifesto |
| 2 | D1, T1, D2 vegetal, D7 insumo | escrita/aprovação de associado; lote vegetal rastreável; QR; insumo vinculado |
| 3 | D3 | fiscal executa; gestor revisa; certificador emite; certificado bloqueado sem cadeia |
| 4 | D5, D6 | lote animal e transformação agroindustrial no mesmo T1; balanço de massa |
| 5 | D8, D4, L1, L2 público | governança, financeiro, relatórios e verificação pública |

## 3. Regras

- T1 é **transversal**: D2/D5/D6/D7 publicam lotes/eventos nele; D6 não é dono da rastreabilidade.
- D3 só emite documento com cadeia completa e evidência.
- L1 entrega relatórios por módulo e consolida na Onda 5.
- Cada persona recebe uma **experiência** (web/PWA), não um sistema separado.
- Servidor é a autoridade; a UI apenas esconde ações sem capability.
