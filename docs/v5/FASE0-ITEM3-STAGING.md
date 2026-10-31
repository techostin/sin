# Fase 0 — Item 3: Staging Anonimizado e Feature Flags

> **Data:** 30/10/2026 · **Status:** **concluído** (ambiente local) · **Nada foi alterado no banco de produção.**
> **Nenhum dado pessoal original é exibido neste relatório.**

---

## 1. Resultado

| Item | Valor |
|---|---|
| URL de staging | **`http://localhost/app-staging/login.php`** (HTTP 200) |
| Código | `C:\xampp\htdocs\app-staging` — *git worktree* detached em `0f6cc81` |
| Banco | **`sisagro_staging`** (cópia anonimizada de `sisagro`) |
| Vínculo de dependências | `vendor` por *junction* para `C:\xampp\htdocs\app\vendor` |
| Feature flags | tabela `feature_flags` criada em `sisagro` e `sisagro_staging` (4 chaves, inativas) |
| Usuário de banco | `sinporg_app` com `SELECT, INSERT, UPDATE, DELETE` em `sisagro_staging.*` |
| Aplicação principal | intacta; `http://localhost/app/login.php` 200 |

---

## 2. Anonimização

Regras aplicadas em `sisagro_staging`:

| Tabela | Tratamento |
|---|---|
| `associados` | nome/sobrenome fictícios, CPF sintético único, e-mail `associado<id>@example.test`, telefone/endereço/CEP genéricos, `foto_url=NULL` |
| `usuarios` | nome/e-mail fictícios, CPF sintético, contato/endereço genéricos |
| `ficha_adesao` | nome/CPF/e-mail/telefone/endereço/propriedade/banco/PIX/titular anonimizados; geolocalização e documentos zerados |
| `certificadoras`, `fornecedores`, `entidades` | nomes de contato, e-mail, telefone e endereço anonimizados |
| `eventos`, `assembleias` | descrições substituídas por `[anonimizado]` |
| `audit_log`, `notificacoes`, `password_resets`, `auth_refresh_tokens` | **truncadas** (não são necessárias no staging) |

### Validação

| Teste | Resultado |
|---|---|
| Associados anonimizados | **31/31** |
| Usuários anonimizados | **12/12** |
| Fichas anonimizadas | **7/7** |
| `audit_log` / `notificacoes` / resets / tokens | **0** |
| E-mails originais remanescentes | **0** |
| CPFs originais remanescentes | **0** |
| Contagens de negócio preservadas | 31 / 12 / 7 (mesmas do banco original) |

---

## 3. Configuração do staging

Alterações **apenas na cópia** `app-staging`:

| Arquivo | Alteração |
|---|---|
| `config/config.php` | `DB_NAME=sisagro_staging`; `SITE_URL=http://localhost/app-staging`; cookie de sessão em `/app-staging/` |
| `.htaccess` | `RewriteBase /app-staging/`; regra de relatórios para `/app-staging/relatorios/` |
| `config/secrets.php` | copiado do app principal (credenciais do usuário `sinporg_app`) |
| `uploads/` | diretórios vazios + `.htaccess` de proteção (sem fotos/evidências reais) |
| `vendor/` | junction para o vendor do app principal (não duplica 176 MB) |

A aplicação de staging foi validada por um verificador temporário (removido em seguida):

```
DB_NAME=sisagro_staging
associados=31
email_anon=associado1...
feature_flags=4
```

---

## 4. Feature flags

Tabela criada em ambos os bancos:

```sql
feature_flags(id, chave UNIQUE, descricao, ativo, escopo, atualizado_em)
```

Chaves semeadas (todas `ativo=0`):

| Chave | Uso |
|---|---|
| `api.v1` | API v1 da fundação |
| `modulos.territorio.v2` | K2 Território |
| `modulos.associados.v2` | D1 Associados |
| `relatorios.v2` | L1 Relatórios |

> A leitura das flags pela aplicação será implementada na F1 (`FeatureFlags` no Kernel). Nesta fase
> a estrutura está pronta para ativação gradual.

---

## 5. Características e limitações

| Item | Observação |
|---|---|
| Serviço | não há vhost/instância Apache separada; usa o mesmo Apache em `/app-staging` |
| Banco | `sisagro_staging` separado; produção intocada |
| Atualização | cópia estática; para atualizar, repetir o processo (script documentado abaixo) |
| Sessões | cookie restrito a `/app-staging/`; não colide com a sessão do app principal |
| Credenciais de teste | **não** foi criado usuário de teste; login usa e-mails anonimizados (sem senha conhecida) |
| Arquivos | uploads vazios; relatórios que dependem de fotos não terão imagem |
| Exposição | staging acessível apenas em `localhost`; **não publicar** |
| Worktree | `app-staging` é worktree Git; atualizações na branch F1 exigem recriar/sincronizar a cópia |

### Reprodução resumida

```powershell
# 1) banco
mysqldump -u root --routines --triggers --single-transaction sisagro > dump.sql
mysql -u root -e "CREATE DATABASE sisagro_staging ..."
mysql -u root sisagro_staging < dump.sql
# 2) anonimização (mesmas regras deste documento)
# 3) código
git worktree add --detach C:\xampp\htdocs\app-staging feat/f1-kernel
mklink /J C:\xampp\htdocs\app-staging\vendor C:\xampp\htdocs\app\vendor
# copiar secrets.php e ajustar config/htaccess
```

---

## 6. Pendências relacionadas

- Criar um **usuário de teste** no staging (e-mail/senha conhecidos) para E2E — pode ser feito na F1.
- Rotina de **atualização periódica** do staging a partir de um dump anonimizado.
- Staging não substitui um ambiente de homologação em nuvem; é o estágio local aprovado nesta fase.
- Proteger o staging contra exposição pública (hoje apenas localhost).

---

## 7. Conclusão

O item 3 está **concluído para o ambiente local**: staging funcional, banco anonimizado e validado,
feature flags criadas e isolamento da produção confirmado.
