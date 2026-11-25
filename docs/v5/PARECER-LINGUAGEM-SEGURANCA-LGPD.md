# Parecer Técnico — Linguagem, Segurança e LGPD

> **Objeto:** avaliar o plano [`docs/v5/PLANO-DE-MODULOS-E-EXECUCAO.md`](PLANO-DE-MODULOS-E-EXECUCAO.md)
> à luz das vulnerabilidades críticas do PHP divulgadas em outubro de 2026 e identificar para qual
> linguagem de programação o projeto deve migrar, considerando segurança e LGPD.
>
> **Data:** 2026-10-27 · **Status:** parecer para decisão · **Nada foi executado.**
> **Escopo:** plano v5, código PHP do projeto, configuração local do PHP e banco `sisagro`.

---

## 0. Veredito em cinco pontos

1. **Não migre de linguagem por causa da CVE.** As vulnerabilidades divulgadas (PHP2Shell) estão no
   **runtime do PHP**, não no código deste projeto. A correção é **atualizar o PHP** — não reescrever
   a aplicação em outra linguagem.
2. **Ação P0 imediata:** o ambiente local roda **PHP 8.2.12**, afetado pelas CVE-2026-109618 e
   CVE-2026-109619, e a linha 8.2 **morre em 31/12/2026**. Atualizar para **8.4.27+** (segurança até
   2028) ou **8.5.12+** (até 2029), com hardening e rotação de segredos.
3. **LGPD:** a falha permite leitura de memória do processo com **senhas, tokens e chaves** de outras
   requisições. Isso pode ser **incidente de segurança** nos termos do art. 48 da LGPD e da
   Resolução CD/ANPD nº 15/2024 — avaliar, registrar e, se houver risco relevante, comunicar em até
   **3 dias úteis** (6 para agente de pequeno porte).
4. **Se e quando migrar:** alvo recomendado **TypeScript/Node.js (NestJS)**; alternativa de maior
   *assurance* **Java 21/25 LTS (Spring Boot)**. A migração é programa separado, por módulo, nunca
   big-bang.
5. **O plano v5 continua válido** como caminho de transição (PHP modular + API + PWA), com uma
   **emenda P0** de atualização de segurança e um **gate de linguagem** após o MVP.

---

## 1. Fatos recentes (com fontes)

### 1.1 PHP2Shell — as vulnerabilidades de outubro de 2026

| Item | Fato |
|---|---|
| CVEs | **CVE-2026-109618** (RCE via corrupção de OOM + *use-after-free* no upload-progress + *memory disclosure*) e **CVE-2026-109619** (RCE via estado global obsoleto + *fatal-error backtrace UAF* + *OPcache type confusion*) |
| Severidade | **CVSS 4.0 = 9,2 (crítica)** nas duas |
| Impacto | **RCE não autenticada** e **leitura de memória do processo** (senhas, tokens, chaves de API, certificados de outras requisições) |
| Abrangência | Praticamente toda versão dos últimos 10 anos — inclusive **PHP 5.4+**; falha **independente da aplicação** (um "Hello World" vulnerável basta) |
| Corrigidas em | **8.2.35, 8.3.36, 8.4.27, 8.5.12** (21/10/2026) |
| Exploração | **Ativa**: CISA adicionou ao catálogo KEV em 26/10/2026 (prazo 29/10/2026); Shadowserver registrou 202 conexões de honeypot em 23/10; CERT-FR emitiu alerta |
| SAPI de maior risco | **mod_php** — o aviso do PHP diz que o risco é maior nessa configuração; o projeto usa Apache + PHP (XAMPP), tipicamente mod_php |
| Mitigação | O próprio PHP afirma: **"não há mitigação completa; atualizar é a única escolha segura"**. Medidas provisórias só reduzem caminhos específicos |
| CVE adicional | **CVE-2026-115560** (RCE; correção no próximo ciclo; condições improváveis — exige troca de `session.serialize_handler` em runtime) |

Fontes: [aviso do PHP de 21/10/2026](https://www.php.net/archive/2026.php#2026-10-21-5),
[página de versões suportadas](https://static.php.net/supported-versions.php),
[Wiz Research](https://www.wiz.io/blog/php2shell-php-rce),
[CERT-FR CERTFR-2026-ALE-014](https://vulnerability.circl.lu/vuln/CERTFR-2026-ALE-014),
[CVE-2026-109618](https://vulnerability.circl.lu/vuln/cve-2026-109618),
[CVE-2026-109619](https://vulnerability.circl.lu/vuln/cve-2026-109619).

> **Leitura crítica da imprensa:** a manchete "70% da internet afetada" é do fornecedor que
> descobriu a falha e se refere a sites que usam PHP. Para este projeto, o que importa é: **está
> desatualizado? está exposto à internet? usa mod_php? tem WAF?** — não o número da manchete.

### 1.2 Ciclo de vida do PHP (situação em 27/10/2026)

| Linha | Suporte ativo até | Suporte de segurança até | Situação |
|---|---|---|---|
| 8.1 | encerrado | **31/12/2025** (EOL) | sem correções |
| **8.2** | encerrado | **31/12/2026** | **em 2 meses EOL** |
| 8.3 | 31/12/2025 | 31/12/2027 | ainda recebe segurança |
| 8.4 | 31/12/2026 | **31/12/2028** | opção recomendada de transição |
| 8.5 | 31/12/2027 | **31/12/2029** | mais longa; validar bibliotecas |

### 1.3 Situação deste projeto (verificada hoje)

| Evidência | Valor | Consequência |
|---|---|---|
| Versão do PHP local | **8.2.12** | **afetada** por CVE-2026-109618/109619 (correção a partir de 8.2.35) |
| Linha 8.2 | EOL em 31/12/2026 | sem correções futuras em 2 meses |
| `display_errors` | **On** | vaza caminhos/stack; piora investigação e exposição |
| `expose_php` | **On** | revela versão do PHP |
| `session.upload_progress.enabled` | não definido (default **On**) | superfície do CVE-2026-109619 |
| `post_max_size` / `memory_limit` | 40M / 512M | vetor de OOM controlável (CVE-2026-109618) |
| `disable_functions` | vazio | funções perigosas disponíveis |
| Banco | `root` **sem senha** em `config/config.php` | se a memória vazar credenciais, o impacto é total |
| Internet | aplicação publicada em domínio próprio | exposição real |
| WAF | não identificado | sem mitigação compensatória |

**Conclusão factual:** o projeto está exatamente no perfil de risco descrito pelo aviso do PHP
(PHP afetado, configuração permissiva, Apache/mod_php, internet). A prioridade número um **não é
linguagem**; é **patch + hardening + rotação de segredos + avaliação de incidente**.

---

## 2. O que a CVE significa — e o que ela não significa

### 2.1 O que ela significa

- O **runtime** pode ser comprometido antes de qualquer código da aplicação rodar. Não importa quão
  bem escrito está o PHP: a falha está embaixo.
- A **leitura de memória** é tão grave quanto o RCE para fins de LGPD: pode expor credenciais,
  tokens de sessão, dados pessoais de outras requisições e chaves de API — sem deixar rastro óbvio.
- **Atualizar é obrigatório**; WAF e configuração são apenas paliativos.

### 2.2 O que ela não significa

| Alegação comum | Realidade |
|---|---|
| "PHP é inseguro, temos que trocar de linguagem" | A falha é do runtime; **todos** os runtimes são escritos em C/C++/Rust e têm CVEs (Log4Shell no Java, por exemplo). Trocar de linguagem não elimina a classe de risco; muda o ecossistema e a cadência de correção |
| "Migrar resolve o problema" | **Não resolve o CVE atual** (o runtime novo tem seus próprios CVEs) e **não resolve os riscos reais deste projeto**: LFI ativo (`index.php:49-51`), 31 de 56 endpoints sem guarda, CSRF incompleto, banco `root` sem senha, ausência de escopo por certificadora |
| "A imprensa disse que PHP morreu" | PHP 8.4/8.5 têm suporte de segurança até 2028/2029 e o projeto tem 81 mil linhas de regra nele. O problema é **verso desatualizada + código legado**, não a existência da linguagem |
| "Basta trocar para linguagem memory-safe" | Java, Node e .NET são gerenciados, mas seus runtimes também são C/C++; a diferença é de **ecossistema, defaults e processo de patch**, não de imunidade |

**Consequência para a decisão:** migrar de linguagem por causa desta CVE seria uma reação
desproporcional e arriscada. O que a CVE justifica é **disciplina de atualização** e **arquitetura
segura** — exatamente o que o plano v5 já prevê, com uma emenda urgente.

---

## 3. LGPD — o que a lei exige (e por que a linguagem não decide)

### 3.1 Base normativa relevante

| Norma | Exigência |
|---|---|
| LGPD, art. 46 | Adotar **medidas de segurança técnicas e administrativas** aptas a proteger dados pessoais de acessos não autorizados e de destruição/perda/alteração/comunicação ilícita |
| LGPD, art. 48 | Comunicar à ANPD e ao titular **incidente de segurança** que possa acarretar risco ou dano relevante |
| LGPD, art. 49 | Sistemas devem ser estruturados para atender **segurança, padrões de boas práticas e princípios** |
| LGPD, art. 50 | Programa de governança em privacidade (regras, treinamento, monitoramento) |
| **Resolução CD/ANPD nº 15/2024** | Regulamento de Comunicação de Incidente: **3 dias úteis** para comunicar à ANPD e ao titular (contados do conhecimento); **em dobro para agente de pequeno porte**; registro do incidente por **5 anos**; critérios de risco relevante incluem **dados de autenticação**, **dados financeiros**, dados sensíveis, dados de crianças/adolescentes/idosos e larga escala |
| ANPD — guias e nota técnica | Medidas técnicas/administrativas, avaliação de risco, governança e prestação de contas |

Fontes: [Resolução CD/ANPD nº 15/2024 (DOU)](https://www.in.gov.br/en/web/dou/-/resolucao-cd/anpd-n-15-de-24-de-abril-de-2024-556243024),
[Guia de Agente de Tratamento e Encarregado (ANPD)](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/anonimizado___guia_de_agente_de_tratamento_e_encarregado_da_anpd_novo.pdf),
[LGPD — Lei 13.709/2018](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm).

### 3.2 Aplicação ao caso

- Dados tratados pelo projeto incluem **CPF, nome, endereço, telefone, e-mail, dados financeiros
  (mensalidades/PIX), dados de certificação e credenciais** — vários deles expressamente listados
  nos critérios de risco relevante da Resolução 15/2024.
- A CVE permite **leitura de memória com dados de autenticação e possivelmente dados financeiros de
  outras requisições**. Se explorada, é **incidente de segurança** por definição (art. 3º, XII da
  Resolução).
- **Avaliação obrigatória:** houve exploração? há evidência em logs? quais titulares/dados foram
  afetados? qual o risco? Registrar a avaliação mesmo que não se comunique (art. 10).
- **Prazos:** 3 dias úteis (6 para pequeno porte) a partir do conhecimento — não do patch.
- **Medidas imediatas:** conter (patch), erradicar (hardening), recuperar (rotação de credenciais e
  sessões), notificar (se aplicável), documentar.

### 3.3 Por que linguagem não resolve LGPD

A LGPD é **tecnologicamente neutra**: ela exige **resultado e processo** (medidas, governança,
resposta a incidente), não uma linguagem. Um sistema em Java/TypeScript sem controle de acesso,
sem auditoria e sem criptografia continua não conforme; um sistema em PHP atualizado, com RBAC,
auditoria, criptografia e segregação de tenant pode ser conforme.

O que a escolha de linguagem/framework **pode** influenciar:
- defaults seguros (ORM parametrizado, template com escape automático, CSRF nativo);
- tipagem estática (menos classes de erro);
- ecossistema de autenticação/autorização/auditoria;
- maturidade de patches e ferramentas de análise (SAST/SCA).

Isso entra na decisão como **peso**, não como substituto dos controles.

### 3.4 Controles mínimos de LGPD independentes de linguagem

| Controle | Situação no projeto | Ação |
|---|---|---|
| Inventário de dados e finalidade | parcial (dicionário de dados existe) | formalizar RoPA |
| Controle de acesso (RBAC/ABAC + tenant) | **ausente** na prática | implementar no Kernel |
| MFA para equipe | não existe | implantar |
| Auditoria de acesso/ação | `audit_log` existe, uso parcial | generalizar |
| Criptografia em trânsito | TLS no host (verificar) | forçar HTTPS |
| Criptografia/segredo em repouso | senhas com hash; resto não | avaliar campos sensíveis |
| Minimização/mascaramento | CPF exposto em views/relatórios | mascarar por padrão |
| Retenção e descarte | não formalizado | política + jobs |
| Resposta a incidente | não documentada | runbook + registro 5 anos |
| DPIA/RIPD | não existe | fazer para dados financeiros/autenticação |
| Encarregado (DPO) | não formalizado | designar |
| Fornecedores (Resend, PIX, hospedagem) | não avaliados | due diligence/contratos |
| PWA/IndexedDB | armazena dados offline | política de cache e limpeza no logout |

---

## 4. Critérios para escolher a linguagem

Pesos propostos (a decisão é sensível a eles — ver 4.2):

| Critério | Peso | O que mede |
|---|---:|---|
| Segurança técnica | 25% | defaults seguros, tipagem, maturidade de auth/authz, cadência de patches |
| LGPD/privacidade | 15% | ecossistema para RBAC, auditoria, criptografia, mascaramento, retenção |
| Suporte/LTS | 15% | vida útil da versão, previsibilidade de atualização |
| Adequação à equipe | 15% | curva de aprendizado, disponibilidade de profissionais |
| Custo/tempo de migração | 15% | reescrita de 81 mil linhas + paridade + risco |
| Web + mobile/PWA | 10% | compartilhamento de contratos, ecossistema front/mobile |
| Operação/performance | 5% | infraestrutura, custo, estabilidade |

### 4.1 Matriz comparativa (0–5, avaliação qualitativa)

| Linguagem/stack | Segurança | LGPD | LTS | Equipe | Custo migração | Web/Mobile | Operação | **Ponderado** |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| **PHP 8.4/8.5 corrigido** (curto prazo) | 3 | 3 | 4 | 5 | 5 | 4 | 4 | **3,90** |
| **TypeScript / Node 24/26 LTS (NestJS)** | 4 | 4 | 4 | 4 | 2 | 5 | 4 | **3,80** |
| **Java 21/25 LTS (Spring Boot)** | 5 | 5 | 5 | 2 | 1 | 3 | 3 | **3,65** |
| **C# / .NET 10 LTS (ASP.NET Core)** | 5 | 4 | 4 | 1 | 1 | 3 | 4 | **3,25** |
| **Python 3.14 (Django/FastAPI)** | 3 | 3 | 4 | 2 | 2 | 4 | 3 | **2,95** |
| **Go** | 4 | 3 | 3 | 1 | 1 | 3 | 5 | **2,75** |
| **Rust** | 5 | 2 | 3 | 1 | 1 | 2 | 4 | **2,70** |

**Leitura:**
- **PHP corrigido lidera no curto prazo** — porque o custo de migração é zero e a equipe já domina.
  Isso não significa "PHP é a melhor linguagem para sempre"; significa **não trocar agora**.
- **Entre alvos de migração, TypeScript/Node é o melhor equilíbrio** para este projeto: runtime
  gerenciado, LTS (Node 24 até 04/2028; Node 26 vira LTS em 28/10/2026 e vai até 04/2029),
  compartilha linguagem com a PWA, ecossistema forte (NestJS, Zod, Prisma, Passport, CASL, Helmet),
  mercado amplo e custo moderado.
- **Java é a alternativa de maior assurance** (Spring Security, Keycloak, auditoria, criptografia,
  LTS até 2029/2031). Só vence se a organização tiver equipe Java e exigência de compliance
  corporativo — o que eleva o peso de segurança/LGPD e derruba o de equipe.
- **C#/.NET** é tecnicamente forte, mas sem equipe/ecossistema local compensa menos.
- **Python/Go/Rust**: boas linguagens, mas inadequadas ao contexto (menor encaixe em auth/auditoria,
  custo de reescrita, disponibilidade de profissionais).

### 4.2 Sensibilidade da decisão

| Cenário | Vencedor |
|---|---|
| Equipe atual, orçamento limitado, PWA-first (recomendado) | **TypeScript/Node** |
| Exigência de assurance máxima + equipe Java | **Java 21/25 LTS** |
| Decisão de não migrar | **PHP 8.4/8.5 corrigido + hardening** |
| Necessidade de app nativo iOS com offline pesado | TypeScript + Expo/Capacitor (não muda o backend) |

---

## 5. Recomendação

### 5.1 Curto prazo — P0, nas próximas 72 horas

| # | Ação | Evidência de conclusão |
|---|---|---|
| 1 | Fazer backup do banco e do código | dump verificado + teste de restauração |
| 2 | Verificar a versão do PHP **em produção** (`php -v`, SAPI, `phpinfo` via CLI/arquivo temporário) | registro com data/hora |
| 3 | Atualizar para **PHP 8.4.27+** (recomendado) ou **8.5.12+**; se 8.2 for inevitável, **8.2.35+** e planejar saída até 31/12/2026 | `php -v`; `php -i \| grep` das extensões; app sobe |
| 4 | Validar as bibliotecas do `vendor/` na versão nova (mPDF, PhpSpreadsheet, TCPDF, dompdf, Resend) | smoke test dos relatórios/PDF/e-mail |
| 5 | Aplicar hardening do `php.ini` (ver Anexo B) e reiniciar **todos** os workers | config efetiva conferida |
| 6 | Ativar WAF/regras virtuais do provedor (Cloudflare/AWS/Fastly publicaram mitigação) enquanto o patch propaga | regra publicada e testada |
| 7 | **Rotacionar todos os segredos**: senha do banco (criar usuário dedicado, mínimo privilégio), `RESEND_API_KEY`, `ID_ENCODE_KEY`, sal de sessão; remover `root` sem senha | credenciais novas funcionando; antigas invalidadas |
| 8 | **Invalidar sessões** ativas e revisar `config/secrets.php` | usuários reautenticados |
| 9 | Preservar e revisar logs: multipart anômalo, erros de memory limit, rajadas de 500, cookies `PHPSESSID` malformados, churn de workers | relatório de triagem |
| 10 | **Avaliação LGPD** do incidente (art. 5º da Res. 15/2024); se risco relevante, comunicar ANPD e titulares em até 3 dias úteis (6 para pequeno porte); registrar por 5 anos | decisão documentada + protocolo, se aplicável |
| 11 | Definir **SLA de patch crítico ≤ 48h** e assinar os avisos de segurança do PHP | processo publicado |

### 5.2 Médio prazo — 15 a 90 dias

1. **Corrigir os riscos reais do projeto** (independentes de linguagem): LFI (`index.php:49-51`),
   guarda de 100% dos endpoints, CSRF, escopo por certificadora, uploads, banco com mínimo
   privilégio.
2. **Controles de LGPD** da seção 3.4 (RBAC, auditoria, mascaramento, retenção, runbook, DPO, DPIA).
3. **Testes de segurança**: matriz de autorização, IDOR, tenant cruzado, upload, webhooks.
4. **Continuar o plano v5** (Trilha A + F1–F3), que constrói a arquitetura onde a segurança passa a
   ser estrutural (Kernel, API, policies, auditoria).
5. **Auditar dependências** (`composer audit`), remover `vendor` desatualizado e travar versões.

### 5.3 Longo prazo — se a organização decidir migrar

**Alvo recomendado: TypeScript/Node.js (NestJS).**

- Runtime gerenciado (V8), LTS longo (Node 24/26), tipagem estática, NestJS com guards/interceptors,
  Zod para validação, Prisma parametrizado, Passport/CASL para authz, Helmet, rate limit, pino para
  auditoria.
- Compartilha linguagem com a PWA — um único conjunto de contratos e tipos.
- Migração **por módulo**, atrás da API definida no v5; sem big-bang; PWA permanece.
- Custo estimado: **18–24 meses**, 4–6 pessoas, com paridade e gates — apenas após o MVP em PHP.

**Alternativa de maior assurance: Java 21/25 LTS (Spring Boot).**

- Spring Security + Keycloak (OIDC/MFA), Spring Data Envers (auditoria), JPA parametrizado,
  Bean Validation, LTS até 2029/2031.
- Melhor para exigência corporativa/auditoria e equipes Java; custo de migração e operação maior.
- Custo estimado: **24–30 meses**.

**Recomendação final:** **não migrar agora**; **corrigir o PHP imediatamente**; decidir a linguagem
em um **gate após o MVP (6–9 meses)**, com os critérios da seção 4.2. Se a decisão for migrar, o
alvo é **TypeScript/Node**; se a organização priorizar assurance máxima e tiver equipe Java, **Java**.

### 5.4 O que não fazer

1. Migrar de linguagem como reação à manchete, sem corrigir o PHP antes.
2. Reescrita total (big-bang) — já houve duas tentativas (Laravel e monorepo TS) e ambas deixaram
   rastro.
3. Confiar apenas em WAF: o próprio PHP diz que não há mitigação completa.
4. Manter PHP 8.2/8.1 após o EOL.
5. Migrar sem resolver LFI, authz, CSRF, credenciais e tenant — os problemas viajariam junto.
6. Escolher linguagem por benchmark ou moda, ignorando equipe, custo e suporte.

---

## 6. Impacto no plano v5 (emenda)

| Seção do v5 | Emenda |
|---|---|
| §2.1 Diagnóstico | registrar PHP **8.2.12** (afetado) e EOL em 31/12/2026 |
| §3.4 ADRs | **ADR-31**: linguagem — permanecer em PHP corrigido no curto prazo; alvo TS/Node condicionado a gate; **ADR-32**: SLA de patch crítico ≤ 48h |
| §7.2 Trilha A | **A12**: atualizar PHP + hardening + rotação + WAF; **A13**: avaliação LGPD do incidente; **A14**: SLA de patch e assinatura de avisos |
| §7.5 Gates | **G0.1**: versão do PHP corrigida e configurada; nenhum módulo inicia antes |
| §8 Riscos | **R17**: CVE/EOL do runtime; **R18**: migração de linguagem duplicar trabalho |
| §11 Decisões | **D-11**: confirmar P0 de patch; **D-12**: decidir alvo de linguagem somente no gate pós-MVP |
| Anexo B | substituir "reconstruir TS" por "migração TS por módulo, condicionada ao gate"; incluir Java como alternativa |

**O que permanece válido no v5:** a decomposição em 13 módulos, o Kernel de contratos, a API única,
a PWA-first, a Trilha A de estabilização e o caminho de modularização em PHP. A CVE **não invalida**
o plano; ela **antecipa** a necessidade de governança de patches e de uma decisão de linguagem
consciente — não impulsiva.

---

## 7. Plano de ação consolidado

| Horizonte | Ações | Critério de aceite |
|---|---|---|
| **0–72h** | Patch PHP, hardening, rotação de segredos, invalidar sessões, WAF, logs preservados | `php -v` corrigido; app funciona; segredos novos; triagem registrada |
| **3–15 dias** | Avaliação LGPD e eventual comunicação ANPD/titulares; auditoria da versão de produção; SLA de patch | decisão documentada; protocolo (se aplicável); SLA publicado |
| **15–90 dias** | Correção de LFI/authz/CSRF/tenant/credenciais; testes de autorização; `composer audit`; início do F1 do v5 | gates G0.1, G1 e G2 verdes |
| **3–9 meses** | MVP do v5 (Kernel + D1 + D2 + PWA offline); decisão de linguagem no gate | MVP em produção com paridade; decisão registrada em ADR |
| **9–24 meses** | Se migrar: implementação TS/Node por módulo atrás da API; senão: continuar PHP modular | 0 regressão; módulos migrados com paridade |

---

## 8. Decisões que dependem da organização

| # | Decisão | Recomendação |
|---|---|---|
| D-11 | Aprovar o P0 de patch/hardening imediatamente (sem esperar decisão de linguagem) | **sim, incondicional** |
| D-12 | Manter PHP 8.4/8.5 corrigido como stack de transição | **sim** |
| D-13 | Definir alvo de linguagem **apenas** no gate pós-MVP | **sim**; alvo recomendado TS/Node |
| D-14 | Se migrar, autorizar programa por módulo (não big-bang) com orçamento 18–24 meses | **sim** |
| D-15 | Formalizar controles de LGPD (DPO, RoPA, DPIA, runbook, retenção) | **sim, independente da linguagem** |
| D-16 | Migrar hospedagem para ambiente com patch gerenciado (se o host atual não permitir 8.4/8.5) | **sim** |

---

## Anexo A — Versões corrigidas (referência rápida)

| Linha | Versão mínima corrigida | Suporte de segurança até |
|---|---|---|
| 8.2 | **8.2.35** | 31/12/2026 (EOL) |
| 8.3 | **8.3.36** | 31/12/2027 |
| 8.4 | **8.4.27** | 31/12/2028 |
| 8.5 | **8.5.12** | 31/12/2029 |
| 8.1 e anteriores | sem correção oficial (EOL) | encerrado |

## Anexo B — Checklist de hardening do PHP

```
[ ] Atualizar para 8.4.27+ (ou 8.5.12+; 8.2.35+ só como ponte até 31/12/2026)
[ ] Reiniciar Apache/PHP-FPM e confirmar que todos os workers reiniciaram
[ ] session.upload_progress.enabled = 0
[ ] (PHP 8.5) fatal_error_backtraces = 0
[ ] post_max_size = 8M (sem upload) ou 32M (com upload); upload_max_filesize < post_max_size
[ ] memory_limit coerente com a aplicação (evitar limite controlável pelo atacante)
[ ] expose_php = Off
[ ] display_errors = Off; log_errors = On; error_log fora do webroot
[ ] allow_url_include = Off; allow_url_fopen = Off (se não usado)
[ ] disable_functions revisado (exec, shell_exec, system, passthru, proc_open, popen...)
[ ] session.cookie_httponly = On; session.cookie_secure = On; session.cookie_samesite = Lax/Strict
[ ] session.use_strict_mode = 1; session.use_only_cookies = 1
[ ] TLS obrigatório; HSTS
[ ] WAF com regras para PHP2Shell enquanto o patch propaga
[ ] Usuário de banco dedicado, mínimo privilégio (sem root)
[ ] Backup verificado antes e depois
```

## Anexo C — Checklist de avaliação de incidente LGPD

```
[ ] Data/hora do conhecimento do incidente
[ ] Descrição das circunstâncias e causa provável
[ ] Dados e categorias afetadas (autenticação? financeiro? sensível?)
[ ] Número de titulares afetados
[ ] Avaliação de risco e possíveis danos
[ ] Evidências de exploração (logs, WAF, EDR, honeypot)
[ ] Medidas de contenção, erradicação e recuperação
[ ] Necessidade de comunicação à ANPD (3 dias úteis) e aos titulares
[ ] Registro do incidente por 5 anos
[ ] Lições aprendidas e ações preventivas
```

## Anexo D — Fontes

- [PHP — Security advisory de 21/10/2026](https://www.php.net/archive/2026.php#2026-10-21-5)
- [PHP — Supported Versions](https://static.php.net/supported-versions.php) · [PHP — Unsupported Branches](https://www.php.net/eol.php)
- [Wiz — PHP2Shell](https://www.wiz.io/blog/php2shell-php-rce)
- [CERT-FR — CERTFR-2026-ALE-014](https://vulnerability.circl.lu/vuln/CERTFR-2026-ALE-014)
- [CVE-2026-109618](https://vulnerability.circl.lu/vuln/cve-2026-109618) · [CVE-2026-109619](https://vulnerability.circl.lu/vuln/cve-2026-109619)
- [CISA KEV — CVE-2026-109619](https://www.cisa.gov/known-exploited-vulnerabilities-catalog?field_cve=CVE-2026-109619)
- [ANPD — Resolução CD/ANPD nº 15/2024](https://www.in.gov.br/en/web/dou/-/resolucao-cd/anpd-n-15-de-24-de-abril-de-2024-556243024)
- [ANPD — Guia de Agente de Tratamento e Encarregado](https://www.gov.br/anpd/pt-br/centrais-de-conteudo/materiais-educativos-e-publicacoes/anonimizado___guia_de_agente_de_tratamento_e_encarregado_da_anpd_novo.pdf)
- [LGPD — Lei 13.709/2018](https://www.planalto.gov.br/ccivil_03/_ato2015-2018/2018/lei/l13709.htm)
- [Node.js — ciclo de vida (endoflife.date)](https://endoflife.date/nodejs)
- [Eclipse Temurin/Java — ciclo de vida (endoflife.date)](https://endoflife.date/eclipse-temurin)
- [.NET — ciclo de vida (endoflife.date)](https://endoflife.date/dotnet)
- [Python — ciclo de vida (endoflife.date)](https://endoflife.date/python)

---

### Encerramento

**Resposta direta à pergunta:** se a organização decidir migrar, migre para **TypeScript/Node.js
(NestJS)**; se o critério dominante for assurance máxima e houver equipe Java, **Java 21/25 LTS
(Spring Boot)**. **Mas não migre agora por causa da CVE** — a falha é do runtime e a correção é
**atualizar o PHP para 8.4.27+ (ou 8.5.12+) nas próximas 72 horas**, com hardening, rotação de
segredos e avaliação de incidente LGPD. O plano v5 permanece válido como caminho de transição, com
a emenda P0 e o gate de linguagem após o MVP.

**Nada foi executado.** As ações acima dependem de decisão e de janela operacional.
