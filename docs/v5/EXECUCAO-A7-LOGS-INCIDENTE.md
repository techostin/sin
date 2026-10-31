# Execução do item 7 — Preservação e triagem de logs (P0)

> **Data:** 2026-10-27 · **Escopo:** logs do Apache/PHP e da aplicação no ambiente local XAMPP
> **Status:** **preservação concluída** e **triagem inicial concluída**. Logs de produção ainda
> precisam ser obtidos do provedor.
> **Nenhum dado pessoal é reproduzido neste relatório** (IPs, URLs com IDs e user-agents completos
> ficam apenas nos arquivos preservados, com acesso restrito).

---

## 1. Preservação (cadeia de custódia)

Cópia estática dos logs foi feita **antes de qualquer alteração de WAF**, para
`C:\xampp\incident-2026-10-27\logs\`, com SHA-256 em `hashes.txt` e `custodia.txt`.

| Arquivo | Origem | Tamanho |
|---|---|---:|
| `access.log` | `C:\xampp\apache\logs\access.log` | 19.997.230 bytes |
| `error.log` | `C:\xampp\apache\logs\error.log` | 9.360.578 bytes |
| `php_error_log` | `C:\xampp\php\logs\php_error_log` | 77 bytes |
| `api_debug.log` | `C:\xampp\htdocs\app\logs\api_debug.log` | 6.380 bytes |
| `cron_externos.log` | `C:\xampp\htdocs\app\logs\cron_externos.log` | 722 bytes |
| `ssl_request.log` | `C:\xampp\apache\logs\ssl_request.log` | 12.847 bytes |
| `security-waf-test.log` | evidência do WAF de aplicação (item 5) | 153 bytes |

Os originais permanecem nos locais de origem. O diretório de incidente está **fora do webroot** e
deve ter acesso restrito.

---

## 2. Cobertura e volume

| Fonte | Período | Volume |
|---|---|---|
| `access.log` | **07/04/2025 17:29** a **27/10/2026 20:59** | **75.343 linhas** |
| `error.log` | **07/04/2025 17:26** a **27/10/2026 20:58** | **47.361 linhas** |
| `php_error_log` | 27/10/2026 | 1 linha (teste do hardening) |
| `api_debug.log` / `cron_externos.log` | 2026 | preservados para análise |

> O `access.log` **não é rotacionado por aplicação**; cobre 18 meses e inclui outros projetos
> locais (`/phpmyadmin`, `/sinporg`, `/sicon`, `/organic`, `/sisagro`). A análise abaixo isola o
> prefixo `/app`.

---

## 3. Achados principais

### 3.1 Origem do tráfego

| Categoria | Requisições |
|---|---:|
| Localhost (`::1`/`127.0.0.1`) | **74.780** |
| Rede privada/LAN (RFC1918) | **26** |
| IP público | **0** |
| Clientes distintos | **6** |

As 26 requisições privadas não foram para `/app`: são, na maioria, `/WDP/SCAN` e `/dashboard/`
(provavelmente dispositivo da rede) e `/cgi-bin/luci/...` (roteador). **Nenhum IP público**
aparece nos logs locais.

### 3.2 Tráfego da aplicação (`/app`)

| Métrica | Valor |
|---|---:|
| Requisições `/app` | **40.624** |
| `200` | 26.683 |
| `302` | 5.775 |
| `304` | 7.351 |
| `401` | 141 |
| `403` | 172 |
| `404` | 355 |
| `500` | **28** |
| Erros `500` entre 20 e 27/10/2026 | **1** — teste interno de configuração às 21:10:49 (diretiva `<Limit>`; confirmado no `error.log`) |
| Requisições `/app` entre 20 e 27/10/2026 | 174 |

### 3.3 Indicadores da CVE (PHP2Shell)

| Indicador | Ocorrências |
|---|---:|
| `PHP_SESSION_UPLOAD_PROGRESS` / `session.upload_progress` | **0** |
| `Allowed memory size` / `Out of memory` | **0** |
| `Maximum execution time` | **0** |
| `PHPSESSID` anômalo no `error.log` | **0** |
| `session-bridge` | **0** |
| Erros PHP entre 20 e 27/10/2026 | **0** |

### 3.4 Eventos suspeitos encontrados (e resultado)

| Data/hora | Evento | Resultado |
|---|---|---|
| 01/09/2026 01:40–01:42 | 6 tentativas de travessia em `index.php?url=...` (relatórios/pix) | **404** (todas) |
| 14/09/2026 10:15 | `GET /app/sinporg/.env` | **403** |
| 21/10/2026 18:06 | `GET /app/sinporg` e `/app/sinporg/` | **403** (pasta já removida) |
| 27/10/2026 20:58 | `GET /app/config/secrets.php` | **403** (teste interno) |

As tentativas de 01/09/2026 foram feitas de **localhost**, com user-agent de PowerShell, e todas
retornaram 404 — compatíveis com testes de segurança internos, sem sucesso de inclusão de arquivo.

### 3.5 Erros PHP no período total

| Tipo | Ocorrências |
|---|---:|
| `PHP Fatal error` | 870 |
| `PHP Warning` | 4.613 |
| `PHP Notice` | 156 |

Os exemplos encontrados são **bugs de desenvolvimento** já conhecidos (ex.: `hash()` recebendo
array em `PasswordReset.php`, caminho de `bootstrap.php`, classe `AuditLog` ausente), não padrões
da CVE. Não há erros de memória, upload progress ou sessão anômala.

---

## 4. Limitações da análise

1. **Somente logs locais.** O ambiente de produção (`sinporg.com.br`) não forneceu logs nesta
   execução; a exposição real depende do PHP de produção, do período sem patch e do tráfego de
   borda.
2. **Memory disclosure pode ser silenciosa.** O CVE-2026-109619 pode expor memória sem gerar erro
   ou 500; a ausência de indicadores locais **não prova** ausência de exploração.
3. **Log local não rotacionado** e com outros projetos; a análise isolou `/app`, mas não substitui
   um SIEM.
4. **Relógio/fuso:** Apache registra `-0300`; o PHP CLI registrou `Europe/Berlin` no teste de log.
   Para correlação fina, normalizar para UTC.

---

## 5. Ações recomendadas

| # | Ação | Prazo |
|---|---|---|
| 1 | Solicitar ao provedor os logs de acesso/erro/WAF do período **20/10/2026 a 27/10/2026** (e anteriores) | imediato |
| 2 | Confirmar a versão do PHP em produção e a data exata da atualização para 8.4.27+ | imediato |
| 3 | Monitorar `logs/security.log` do WAF de aplicação e os logs de borda | contínuo |
| 4 | Rotacionar a chave Resend (item 6) e revisar acessos administrativos | 24–48h |
| 5 | Definir retenção: logs operacionais ≥ 6 meses; registro de incidente **5 anos** (LGPD) | 30 dias |
| 6 | Ativar WAF de borda e rate limit (item 5) | 7 dias |
| 7 | Preservar o diretório de incidente com acesso restrito; expirar após a avaliação | 30 dias |

---

## 6. Conclusão da triagem

**Não há evidência, nos logs locais, de exploração das CVE-2026-109618/109619.** As tentativas
encontradas de travessia e acesso a `.env` foram bloqueadas (404/403) e não atingiram dados.

**Isso não encerra o caso:** a avaliação de incidente LGPD (item 8) depende dos logs de produção e
da confirmação da versão/período de exposição do PHP no servidor. Até essa confirmação, o caso é
tratado como **incidente potencial em avaliação**, não como "sem incidente".

---

## Anexo — Hashes SHA-256 (cópia preservada)

```
7E2E09E527F01A48531192D4A6C457B72512FAC27C714ED2FE137FC13F10636F  access.log
656D807BD71DB1A1DD117AC19AA83AF94590E0A593DCF2A76CC0C8C88E1845CE  error.log
E8CD907F4B6218E1C905A53F3559FE6F5D4E72650C90F96249F8BC6DAD40136B  php_error_log
3CD1B27DE7C59F5CF6D3A4DB7D45ABBFB0167C3B3C47A5D37010826374A38204  api_debug.log
1A4CFBC8234379530203A70464D6688B105212BE26323874CF5972FE569E7E7F  cron_externos.log
044C78E993EC10638426773EA36D24433899B9A790B1E45F9FF1C40CBC81255E  ssl_request.log
1159BC30061BE5D5B93198DB4F0F2BF11B07B6C1A1AF57D5C503F437C07FEBE5  security-waf-test.log
```
