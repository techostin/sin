# Fase 0 — Item 4: P0 Residual (Resend, Logs de Produção, Decisão LGPD)

> **Data:** 30/10/2026 · **Status:** **NÃO CONCLUÍDO — bloqueado por dependências externas/humanas.**
> **Nenhum segredo é exibido neste documento.**

---

## 1. Resumo

| Frente | Situação | Bloqueio |
|---|---|---|
| Chave Resend | **antiga ainda válida** (HTTP 422); API de gerenciamento retorna **401** | rotação exige painel da Resend; sem credencial de administração |
| Logs de produção | **não obtidos** | acesso ao provedor/hospedagem |
| Decisão LGPD | parecer A8 emitido; **decisão formal/signatária pendente** | controlador/DPO/jurídico |
| P0 técnico local | ✅ concluído | — |

**Consequência:** o item 4 **não pode ser marcado como concluído** neste ambiente. A F1 não deve
começar sob o gate estrito até que estes itens tenham responsável e prazo.

---

## 2. Resend

### Evidência coletada em 30/10/2026

| Teste | Resultado |
|---|---|
| `POST https://api.resend.com/emails` com payload vazio | **HTTP 422** → a chave **autentica** (válida) |
| `GET https://api.resend.com/api-keys` | **HTTP 401** → a chave não pode gerenciar outras chaves |

A chave é **sending-only** (ou sem permissão de API keys) e continua **comprometida** (histórico).
O script [atualizar-chave-resend.ps1](../../scripts/atualizar-chave-resend.ps1) está atualizado para
validar e gravar a nova chave em `config/secrets.php`, mas **depende da chave nova**.

### Ação necessária (somente o responsável)

1. Painel Resend → **API Keys** → criar chave nova com permissão de envio.
2. Executar:
   ```powershell
   .\scripts\atualizar-chave-resend.ps1 -NovaChave "re_..."
   ```
3. Testar recuperação de senha e envio de carteirinha.
4. **Revogar a chave antiga** no painel.
5. Registrar data/evidência no `STATUS.md`.

**Prazo recomendado:** 24h (chave ativa e comprometida).

---

## 3. Logs de produção

### O que já foi feito

- Logs locais preservados com SHA-256 em `C:\xampp\incident-2026-10-27\` (ver
  [`EXECUCAO-A7-LOGS-INCIDENTE.md`](EXECUCAO-A7-LOGS-INCIDENTE.md)).
- Triagem local: **sem indicadores** de exploração; sem IP público; sem `upload_progress`/memória.

### O que falta obter do provedor

- Logs de acesso e erro do Apache/PHP-FPM entre **20/10/2026 e 27/10/2026** (e anteriores);
- Logs de WAF/CDN (Cloudflare/AWS/Fastly), se existirem;
- Versão do PHP em produção e **data exata** da atualização para 8.4.27+;
- Reinícios de workers, erros 5xx e memory limit;
- Logs de autenticação/sessão e de acesso ao banco.

**Solicitação pronta:** [`COMUNICADO-P0-RESIDUAL.txt`](COMUNICADO-P0-RESIDUAL.txt).

**Prazo:** imediato (a avaliação LGPD depende disso).

---

## 4. Decisão LGPD

O parecer [`EXECUCAO-A8-AVALIACAO-LGPD.md`](EXECUCAO-A8-AVALIACAO-LGPD.md) concluiu:

- incidente **potencial em avaliação**, não confirmado;
- dados de autenticação e financeiros atendem a critérios de risco relevante (Res. 15/2024);
- decisão preliminar: **não comunicar ainda**, mantendo registro e prazo condicionado;
- **3 dias úteis** (30/10/2026) ou **6 dias úteis** (04/11/2026) se aplicável.

### O que falta

- Assinatura do **controlador** e validação do **DPO/jurídico**;
- Definição da data de **conhecimento** do incidente;
- Confirmação de agente de pequeno porte;
- Registro formal no livro de incidentes (5 anos).

---

## 5. Responsáveis e prazos propostos

| # | Ação | Responsável | Prazo |
|---|---|---|---|
| 1 | Criar/revogar chave Resend | responsável pelo painel | 24h |
| 2 | Solicitar logs de produção | infraestrutura/hospedagem | imediato |
| 3 | Confirmar PHP de produção e data do patch | infraestrutura | 24h |
| 4 | Assinar decisão LGPD | controlador + DPO | 48h |
| 5 | Atualizar `STATUS.md` e este documento | Tech Lead | após cada item |

---

## 6. Conclusão

O P0 técnico local está concluído; o item 4 permanece **aberto** por três dependências que este
agente não pode executar (painel Resend, logs do provedor e assinatura humana). **Não marcar como
concluído** e **não iniciar a F1 sob o gate estrito** até o fechamento.
