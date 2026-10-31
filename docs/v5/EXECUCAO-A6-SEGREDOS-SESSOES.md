# Execução do item 6 — Rotação de segredos e sessões (P0)

> **Data:** 2026-10-27 · **Escopo:** ambiente local XAMPP (`C:\xampp`) e aplicação em `C:\xampp\htdocs\app`
> **Status:** **executado** para banco de dados, `ID_ENCODE_KEY`, sessões e tokens.
> **Pendente:** `RESEND_API_KEY` (rotação manual no painel Resend) e restart do Apache2.4 (item 4).
> **Nenhum valor de segredo é exibido neste relatório.**

---

## 1. Resumo

| Ação | Resultado |
|---|---|
| Credencial do banco | **rotacionada**; app passou a usar usuário dedicado `sinporg_app` (menor privilégio) |
| `root` na configuração | **removido** de `config/config.php` e de `api/relatorios/_config.php` |
| `ID_ENCODE_KEY` | **rotacionada** (44 caracteres); links antigos com ID codificado deixam de funcionar |
| Sessões PHP | **43 arquivos removidos**; todos os usuários foram deslogados |
| `auth_refresh_tokens` | **597 registros** exportados e truncados |
| `password_resets` | **1 registro** exportado e truncado (reset pendente invalidado) |
| `RESEND_API_KEY` | **pendente** — rotação exige o painel da Resend |
| Validação | conexão, counts, encode/decode, login web e API **OK** |

---

## 2. Segredos e credenciais

### 2.1 Banco de dados

- Criado/rotacionado o usuário **`sinporg_app`** nos hosts `localhost`, `127.0.0.1` e `::1`.
- Senha aleatória de **64 caracteres hex** (não exibida), gravada em `config/secrets.php` (não versionado).
- Privilégios concedidos: **SELECT, INSERT, UPDATE, DELETE** em `sisagro.*` — sem DDL, sem `GRANT`, sem
  privilégios globais. `root` continua existindo apenas para administração local.
- `config/config.php` não define mais `root` nem senha vazia: passou a carregar `config/secrets.php`
  no início e a aceitar variáveis de ambiente (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) com
  precedência sobre o arquivo.
- `api/relatorios/_config.php` (segunda fonte de credenciais `root`, aparentemente morta) foi
  substituído por um *shim* que apenas inclui `config/config.php`.

### 2.2 Chave de codificação de IDs

- `ID_ENCODE_KEY` foi **rotacionada** (32 bytes aleatórios em base64, 44 caracteres; não exibida).
- **Impacto esperado:** links e formulários antigos que carregam IDs codificados com a chave anterior
  deixam de decodificar. Novos links são gerados corretamente (roundtrip validado).

### 2.3 Resend (pendente — ação manual)

A chave atual está marcada no próprio `secrets.php` como **comprometida** (já esteve no Git). A rotação
não pode ser feita sem o painel da Resend:

1. Acessar o painel da Resend → **API Keys** → criar nova chave com a mesma permissão de envio.
2. Atualizar `RESEND_API_KEY` em `config/secrets.php` **ou** definir a variável de ambiente
   `RESEND_API_KEY` no servidor (recomendado).
3. Testar recuperação de senha e envio de carteirinha.
4. Revogar a chave antiga no painel.

---

## 3. Sessões e tokens

| Item | Antes | Depois |
|---|---:|---:|
| Arquivos `sess_*` em `C:\xampp\tmp` | 43 | **0** (novas sessões são criadas no próximo login) |
| `auth_refresh_tokens` | 597 | **0** |
| `password_resets` | 1 | **0** |

- Sessões eliminadas no servidor; cookies antigos (`PHPSESSID`) deixam de corresponder a sessão válida.
  O login seguinte cria uma sessão nova (validado: cookie novo com `HttpOnly; SameSite=Lax`).
- `auth_refresh_tokens` era resíduo do monorepo removido (0 tokens ativos); foi exportado e truncado.
- `password_resets` tinha 1 token pendente; foi exportado e invalidado — o usuário precisará solicitar
  novo link de redefinição.
- Backup das tabelas antes do truncamento: `tokens-resets.sql` (fora do webroot).

---

## 4. Arquivos alterados

| Arquivo | Alteração |
|---|---|
| `config/config.php` | carrega `secrets.php` antes das credenciais; suporta ambiente; fallback `sinporg_app`; sem `root` |
| `config/secrets.php` | `DB_USER`/`DB_PASS` adicionados; `ID_ENCODE_KEY` rotacionada (**não versionado**) |
| `config/secrets.example.php` | placeholders de `DB_USER`/`DB_PASS` e nota sobre variáveis de ambiente |
| `api/relatorios/_config.php` | shim para `config/config.php`; removida a credencial `root` duplicada |

> `config/secrets.php` está no `.gitignore` e responde **403** quando acessado pela web
> (`config/.htaccess`). O backup dos segredos fica **fora do webroot**.

---

## 5. Evidências de validação

| Teste | Resultado |
|---|---|
| `php -l config/config.php` | sem erros |
| `php -l config/secrets.php` | sem erros |
| `php -l api/relatorios/_config.php` | sem erros |
| Constantes carregadas | `DB_USER=sinporg_app`, `DB_PASS` definida (64), `ID_ENCODE_KEY` definida (44) |
| Conexão PDO | `conexao=ok` |
| Counts consultados | `associados=31`, `usuarios=12`, `atividades_diarias=11`, `mensalidades=22` |
| `encodeId`/`decodeId` | roundtrip **ok** |
| `GET /app/login.php` | **200**; cookie novo `HttpOnly; SameSite=Lax` |
| `GET /app/api/get_cidades.php` | **200** |
| `GET /app/config/secrets.php` | **403** |
| `root` no código de configuração | **ausente** |
| Privilégios do usuário dedicado | `SELECT, INSERT, UPDATE, DELETE` em `sisagro.*` (3 hosts) |

---

## 6. Observação de segurança sobre a execução

Na primeira validação, uma falha de máscara exibiu temporariamente os valores recém-gerados no
terminal. Por isso, **a senha do banco e a `ID_ENCODE_KEY` foram rotacionadas uma segunda vez**, e os
valores expostos foram invalidados. Nenhum valor atual aparece neste relatório. A chave da Resend
continua pendente de rotação manual (e já era considerada comprometida antes).

---

## 7. Impactos e rollback

**Impactos esperados**
- Todos os usuários foram deslogados (sessões invalidadas).
- Links antigos com ID codificado (`encodeId`) deixam de funcionar.
- Reset de senha pendente (1) foi invalidado.
- Tokens do monorepo removido foram descartados.
- Nenhum dado de negócio foi alterado; banco permanece com 35,6 MB.

**Rollback**
1. Restaurar `config/config.php` e `config/secrets.php` de
   `C:\xampp\secrets-backup-20261027-205529\` (contém os valores anteriores; **não recomendado**,
   pois a chave Resend antiga está comprometida).
2. Opcionalmente remover o usuário `sinporg_app` (o `root` local permanece disponível para administração).
3. Sessões e tokens não são restauráveis por design; o dump `tokens-resets.sql` serve apenas para auditoria.

**Backup (fora do webroot)**

```
C:\xampp\secrets-backup-20261027-205529\
├── config.php
├── relatorios_config.php
├── secrets.php              (versão anterior à rotação)
├── tokens-resets.sql        (dados anteriores ao truncamento)
└── rotacao-meta.txt         (datas/tamanhos; sem valores)
```

> Esse diretório contém segredos antigos e dados de tokens. Restringir acesso e apagar após o período
> de validação (sugestão: 30 dias).

---

## 8. Pendências e próximos itens

| Item | Status | Observação |
|---|---|---|
| 4. Hardening do PHP | ✅ (restart Apache pendente) | ver [`EXECUCAO-A4-HARDENING-PHP.md`](EXECUCAO-A4-HARDENING-PHP.md) |
| **6. Segredos e sessões** | ✅ **executado** | Resend pendente de ação manual |
| 5. WAF / regras virtuais | ⏳ | Cloudflare/AWS/Fastly |
| 7. Preservar/revisar logs | ⏳ | procurar multipart anômalo, memory limit, 500, `PHPSESSID` malformado |
| 8. Avaliação LGPD | ⏳ | Res. CD/ANPD 15/2024: 3 dias úteis (6 para pequeno porte) |

**Produção (quando aplicável):** repetir os mesmos passos no servidor; preferir variáveis de ambiente
a `secrets.php`; garantir que o arquivo não seja servido pelo Apache; revisar o `deploy.sh` (hoje o
`rsync` copia `config/secrets.php` para o servidor); desabilitar acesso remoto do `root`.

---

## Anexo — Rastreabilidade

| Artefato | Caminho |
|---|---|
| Configuração central | `config/config.php` |
| Segredos (não versionado) | `config/secrets.php` |
| Modelo de segredos | `config/secrets.example.php` |
| Shim de relatórios | `api/relatorios/_config.php` |
| Backup da rotação | `C:\xampp\secrets-backup-20261027-205529\` |
| Relatório do item 4 | [`EXECUCAO-A4-HARDENING-PHP.md`](EXECUCAO-A4-HARDENING-PHP.md) |
| Plano | [`PLANO-DE-MODULOS-E-EXECUCAO.md`](PLANO-DE-MODULOS-E-EXECUCAO.md) |
