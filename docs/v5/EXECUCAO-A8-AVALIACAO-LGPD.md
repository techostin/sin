# Execução do item 8 — Avaliação de incidente LGPD (P0)

> **Data:** 2026-10-27 · **Escopo:** avaliação preliminar do incidente potencial PHP2Shell
> (CVE-2026-109618/109619) para a LGPD e a Resolução CD/ANPD nº 15/2024.
> **Status:** **incidente potencial em avaliação — não confirmado**. A decisão final depende dos
> logs de produção e da confirmação da versão/período de exposição do PHP no servidor.
> **Este documento não é parecer jurídico**; deve ser validado pelo encarregado (DPO) e pelo jurídico.
> **Nenhum dado pessoal é reproduzido neste relatório.**

---

## 1. Resumo executivo

| Pergunta | Resposta atual |
|---|---|
| Houve incidente confirmado? | **Não confirmado** nos logs locais; possível em produção |
| Há risco relevante? | **Sim, se explorado** — a falha permite leitura de memória com credenciais, tokens e dados financeiros |
| É preciso comunicar à ANPD agora? | **Ainda não**; prazo de 3 dias úteis (6 para pequeno porte) começa do **conhecimento** do incidente com dados pessoais |
| O que falta para decidir? | Logs de produção (20–27/10/2026), versão/data de patch do PHP em produção, logs de WAF/IDS e de autenticação |
| Medidas já tomadas | PHP 8.4.27, hardening, WAF local, rotação de banco/ID/sessões, tokens truncados |
| Pendências | Chave Resend, `TraceEnable Off` (restart), logs de produção, decisão formal do controlador |

**Decisão preliminar:** tratar como **incidente de segurança potencial** e manter o registro
(art. 10 da Res. 15/2024) até conclusão da apuração. Não comunicar neste momento **não** significa
encerrar; significa aguardar evidência de produção dentro do prazo legal.

---

## 2. Fatos e linha do tempo

| Data | Fato |
|---|---|
| 20/10/2026 | PHP comunica vulnerabilidades críticas e agenda correção |
| 21/10/2026 | Divulgação pública; correções 8.2.35 / 8.3.36 / 8.4.27 / 8.5.12; exploração ativa; CISA KEV em 26/10 |
| 21–27/10/2026 | Período de exposição potencial (versão local era 8.2.12) |
| 27/10/2026 | Ambiente local atualizado para 8.4.27; hardening, WAF e rotação de segredos |
| 27/10/2026 | Triagem de logs locais: sem indicadores de exploração; produção pendente |
| 30/10/2026 | Marco de 3 dias úteis a partir de 27/10 (se houver dever de comunicação) |
| 04/11/2026 | Marco de 6 dias úteis (agente de pequeno porte), se aplicável |

> A data de **conhecimento** do controlador precisa ser formalizada. Se o responsável tomou
> ciência da exposição em 21/10, os prazos contam dessa data. O registro deve explicar a data
> adotada.

---

## 3. Dados pessoais potencialmente afetados

| Categoria | Exemplos no sistema | Critério de risco (Res. 15/2024, art. 5º) |
|---|---|---|
| Identificação | nome, CPF, RG, data de nascimento | dado pessoal geral |
| Contato/endereço | e-mail, telefone, endereço, CEP | dado pessoal geral |
| **Autenticação** | hashes de senha, sessões, tokens de reset/refresh | **inciso IV** |
| **Financeiro** | mensalidades, pagamentos/PIX, caixa, inadimplência | **inciso III** |
| Certificação/produção | CNPO, certificações, propriedades, lotes | dado pessoal geral (contexto regulatório) |
| Equipe interna | usuários, papéis, e-mail, certificadora | dado pessoal geral |

Titulares: associados, agricultores, equipe interna e pré-cadastros. No banco local: **31
associados** e **12 usuários**; o volume de produção deve ser confirmado.

---

## 4. Avaliação de risco

### 4.1 Cenários

| Cenário | Probabilidade | Impacto | Risco |
|---|---|---|---|
| Leitura de memória com **tokens de sessão** → sequestro de conta | Média (exploração ativa; logs sem evidência) | Alto | **Alto** |
| Leitura de **credenciais/chaves** (DB, e-mail, API) → acesso lateral | Média | Alto | **Alto** |
| Exposição de **CPF/endereço/telefone** → phishing/fraude | Média | Médio/Alto | **Médio-Alto** |
| Exposição de **dados financeiros** → fraude | Média | Alto | **Alto** |
| Alteração/integridade de dados (RCE) | Baixa/Média | Alto | **Alto** |

### 4.2 Fatores atenuantes já aplicados

- PHP atualizado para **8.4.27** (correção das duas CVE críticas).
- `display_errors` desligado, `upload_progress` desabilitado, `post_max_size` reduzido.
- WAF local (Apache + aplicação), cabeçalhos de segurança, bloqueio de diretórios internos.
- Credencial do banco rotacionada para usuário dedicado de menor privilégio.
- Sessões invalidadas (43 arquivos) e tokens/resets truncados.
- `ID_ENCODE_KEY` rotacionada.
- Logs preservados com hash para apuração.

### 4.3 Fatores agravantes / incertezas

- **Logs de produção indisponíveis** nesta avaliação.
- CVE com **exploração ativa** e possibilidade de *memory disclosure* sem erro visível.
- Chave **Resend** ainda não rotacionada (já comprometida historicamente).
- `root` do MySQL local permanece com acesso administrativo (não usado pela aplicação).
- WAF de borda (Cloudflare/AWS) não configurado.
- `TraceEnable Off` pendente de restart.

### 4.4 Conclusão de risco

**Se houver evidência de exploração em produção**, o incidente é **relevante** (dados de
autenticação e financeiros, art. 5º, III e IV) e a comunicação é obrigatória. **Sem evidência**, o
controlador deve registrar a avaliação e a decisão de não comunicar, com justificativa, mantendo o
monitoramento. A ausência de logs locais **não basta** para concluir ausência de incidente.

---

## 5. Obrigações legais aplicáveis

| Norma | Exigência | Aplicação ao caso |
|---|---|---|
| LGPD art. 46 | medidas técnicas e administrativas de segurança | patch, WAF, rotação, controle de acesso, auditoria |
| LGPD art. 48 | comunicar à ANPD e ao titular incidente com risco/dano relevante | avaliar e, se confirmado, comunicar |
| LGPD art. 49 | sistemas estruturados para segurança e boas práticas | backlog do plano v5 (RBAC, auditoria, retenção) |
| LGPD art. 50 | programa de governança em privacidade | DPO, RIPD, treinamento, fornecedores |
| Res. CD/ANPD nº 15/2024, art. 5º | critérios de risco relevante (autenticação, financeiro, sensível, larga escala) | **dados de autenticação e financeiros presentes** |
| Res. 15/2024, art. 6º | comunicação à ANPD em **3 dias úteis** (em dobro para pequeno porte) | prazo condicionado ao conhecimento |
| Res. 15/2024, art. 9º | comunicação ao titular em 3 dias úteis, linguagem simples | se confirmado |
| Res. 15/2024, art. 10 | registro do incidente por **5 anos** | registrar mesmo sem comunicação |

Fontes: [LGPD](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm),
[Resolução CD/ANPD nº 15/2024](https://www.in.gov.br/en/web/dou/-/resolucao-cd/anpd-n-15-de-24-de-abril-de-2024-556243024).

---

## 6. Decisão e próximos passos

### 6.1 Decisão preliminar registrada

> **Não comunicar neste momento**, por ausência de evidência de exploração nos logs disponíveis e
> por ausência de confirmação de exposição em produção. Manter o caso aberto como **incidente
> potencial**, com prazo de apuração e registro por 5 anos. Reavaliar imediatamente se qualquer
> evidência de produção surgir.

**Justificativa:** os logs locais não mostram indicadores; as medidas de contenção foram aplicadas;
faltam logs de produção. A decisão deve ser assinada pelo controlador e validada pelo DPO/jurídico.

### 6.2 Evidências que faltam (prioridade)

| # | Evidência | Onde obter |
|---|---|---|
| 1 | Versão do PHP em produção e data do patch | painel/host/`php -v` |
| 2 | Logs de acesso e erro do Apache/PHP-FPM em 20–27/10/2026 | hospedagem |
| 3 | Logs de WAF/CDN (Cloudflare/AWS/Fastly) | painel do provedor |
| 4 | Logs de autenticação e de sessão | hospedagem/aplicação |
| 5 | Logs de acesso ao banco / consultas anômalas | hospedagem/DBA |
| 6 | Reinícios de workers, erros 500 e memory limit | hospedagem |
| 7 | Alertas de IDS/EDR e tráfego de saída incomum | infraestrutura |

### 6.3 Prazos e responsáveis (proposta)

| Prazo | Ação | Responsável |
|---|---|---|
| Imediato | Confirmar versão/PHP e data do patch em produção | Infra |
| 24h | Obter logs de produção e concluir a triagem | Infra + Segurança |
| 48h | Rotacionar chave Resend e revisar acessos | Infra |
| 3 dias úteis (se aplicável) | Comunicar ANPD e titulares | Controlador/DPO |
| 30 dias | Concluir RIPD e plano de ação | DPO + Produto |
| 5 anos | Manter registro do incidente | DPO |

### 6.4 Se a comunicação for necessária

**ANPD (art. 6º, §2º):** natureza/categoria dos dados; número de titulares; medidas antes/depois;
riscos e impactos; motivos de eventual demora; medidas de mitigação; data do incidente e do
conhecimento; dados do encarregado; identificação do controlador/operador; descrição e causa
provável; total de titulares nas atividades afetadas.

**Titulares (art. 9º):** linguagem simples, natureza/categoria dos dados, medidas, riscos,
mitigações, data do conhecimento, contato do encarregado; comunicação individualizada quando
possível, ou divulgação ampla por até 3 meses.

### 6.5 Registro do incidente (art. 10 — guardar 5 anos)

Campos mínimos: data do conhecimento; descrição das circunstâncias; natureza/categoria dos dados;
número de titulares; avaliação de risco/danos; medidas de correção/mitigação; forma e conteúdo da
comunicação (se houver); motivos da ausência de comunicação.

---

## 7. Recomendações estruturais (além do incidente)

1. **Designar formalmente o encarregado (DPO)** e publicar canal de contato.
2. **RIPD/DPIA** para tratamentos com dados financeiros/autenticação.
3. **Inventário de dados (RoPA)** e base legal por finalidade.
4. **Política de retenção e descarte** (3 anos para diário de campo, conforme regulação; 5 anos
   para registro de incidente).
5. **Revisão de contratos** com hospedagem, Resend, PIX e certificadoras (operadores).
6. **MFA para equipe** e revisão de acessos administrativos.
7. **Testes de segurança periódicos** e exercício de resposta a incidente.
8. **Monitoramento contínuo** de logs e alertas de 5xx/memória/sessão.

---

## 8. Conclusão

O caso **não está encerrado**. A avaliação local não encontrou exploração, mas a decisão final
depende de evidências de produção. O controlador deve:

1. registrar a avaliação e a decisão preliminar;
2. obter os logs/versão de produção;
3. reavaliar o risco em até 48h;
4. comunicar ANPD/titulares se houver evidência de comprometimento com risco relevante;
5. manter o registro por 5 anos e concluir o RIPD em 30 dias.

---

## Anexo — Referências

- [LGPD — Lei 13.709/2018](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm)
- [Resolução CD/ANPD nº 15/2024](https://www.in.gov.br/en/web/dou/-/resolucao-cd/anpd-n-15-de-24-de-abril-de-2024-556243024)
- [Guia de Agente de Tratamento e Encarregado — ANPD](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/anonimizado___guia_de_agente_de_tratamento_e_encarregado_da_anpd_novo.pdf)
- [PHP — aviso de segurança 21/10/2026](https://www.php.net/archive/2026.php#2026-10-21-5)
- [CISA KEV — CVE-2026-109618/109619](https://www.cisa.gov/known-exploited-vulnerabilities-catalog?field_cve=CVE-2026-109618)
- [Triagem de logs](EXECUCAO-A7-LOGS-INCIDENTE.md)
- [WAF e compensações](EXECUCAO-A5-WAF.md)
