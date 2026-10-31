# Fase 0 — Item 6: Designação de Fiscais e Especialista de Domínio

> **Data:** 30/10/2026 · **Status:** **PROPOSTA — pendente de nomes e aceite formal.**
> A designação só é válida com **nome + aceite + data**. Sem isso, o item 6 **não está concluído**.

---

## 1. Por que este item bloqueia a F1

O método do projeto exige **construtor e fiscal independentes** e um **especialista de domínio** para
validar paridade. Sem essas pessoas designadas:
- o gate G3 (módulo pronto) não tem quem aprove;
- a matriz de paridade fica sem árbitro de negócio;
- a F1 corre risco de "aprovar por cansaço".

---

## 2. Papéis e responsabilidades

| Papel | Responsabilidade | Dedicação | Independência |
|---|---|---|---|
| **Tech Lead** | fronteiras, ADRs, revisão final, gate técnico | 1 FTE | não pode ser fiscal do próprio código |
| **Product Owner (SINPORG)** | prioridade, aceite de negócio, comunicação com associados | 0,5 FTE | valida o "isto está igual ao legado" |
| **Especialista de Domínio** | regras de certificação, adesão, financeiro, territorialidade | 0,5 FTE | não pode ser o construtor do módulo que valida |
| **Fiscal de Paridade** | compara tela/campo/ação com o legado, linha a linha | 0,5 FTE | não participou da implementação |
| **Fiscal de Segurança** | authz, IDOR, tenant, LGPD, uploads, CSRF | 0,25 FTE | independente do backend |
| **Fiscal de Arquitetura** | fronteiras, deptrac, contratos, dados | 0,25 FTE | independente do Tech Lead |
| **Fiscal de Qualidade/QA** | testes, E2E, acessibilidade, offline | 0,5 FTE | independente do dev |

**Regras:**
- Um fiscal nunca audita módulo que construiu.
- Após 3 aprovações sem achado, o fiscal é rotacionado.
- Evidência obrigatória; parecer sem `arquivo:linha`/teste é inválido.
- Conflito de interesse declarado por escrito.

---

## 3. Designação proposta (a preencher)

| Papel | Nome | Aceite (data) | Observação |
|---|---|---|---|
| Tech Lead | ______________________ | ____/____/2026 | |
| Product Owner SINPORG | ______________________ | ____/____/2026 | |
| Especialista de Domínio | ______________________ | ____/____/2026 | |
| Fiscal de Paridade — K2 | ______________________ | ____/____/2026 | |
| Fiscal de Paridade — D1 | ______________________ | ____/____/2026 | |
| Fiscal de Segurança | ______________________ | ____/____/2026 | |
| Fiscal de Arquitetura | ______________________ | ____/____/2026 | |
| Fiscal de Qualidade/QA | ______________________ | ____/____/2026 | |

> Enquanto os campos estiverem vazios, **o item 6 permanece pendente** e a F1 não deve avançar para
> módulos de negócio.

---

## 4. Termo de aceite (modelo)

> Eu, ______________________, aceito a função de **______________________** no programa de
> modularização do SINPORG, a partir de ____/____/2026, ciente de que:
> (a) minha atuação é independente do construtor do módulo;
> (b) devo produzir evidência reproduzível (arquivo:linha, teste, captura);
> (c) não aprovam módulos sem paridade e segurança verificadas;
> (d) conflitos de interesse devem ser declarados.
>
> Assinatura: ______________________

---

## 5. Alternativa provisória (não substitui a designação)

Se o SINPORG ainda não puder nomear todos:
- o **Tech Lead** assume interinamente a coordenação técnica;
- o **Product Owner** indica um ponto focal de domínio;
- a F1 pode avançar **somente em infraestrutura** (sem módulo de negócio), com o item 6 marcado como
  pendência formal.

---

## 6. Prazo e próximo passo

| Ação | Prazo |
|---|---|
| Indicar nomes e colher aceite | **antes do gate G1** |
| Publicar designação no `STATUS.md` | junto com o aceite |
| Revisar independência dos fiscais | no início de cada módulo |

**Conclusão:** o item 6 está **documentado e pronto para preenchimento**, mas **não concluído**.
