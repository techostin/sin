# Execução da Fase 0 — Item 2 (Git: commit, tag e branch)

> **Data:** 30/10/2026 · **Status:** **concluído** · **Fonte:** [STATUS.md](../../STATUS.md) / [KICKOFF](KICKOFF-IMPLEMENTACAO-MODULAR.md)
> **Nenhum segredo é exibido neste relatório.**

---

## 1. Resultado

| Item | Valor |
|---|---|
| Commit de baseline | **`796d11d38e764375cb96a51232dd5a48cb87545a`** (`796d11d`) |
| Mensagem | `chore(baseline): congela estado pre-modularizacao` |
| Tag anotada | **`pre-modularizacao`** (objeto `732f5a8` → commit `796d11d`) |
| Branch de trabalho | **`feat/f1-kernel`** (criada a partir do baseline) |
| Working tree | **limpo** (`git status --short` = 0) |
| Remoto | **não enviado** (sem `git push`; requer credenciais/decisão) |

---

## 2. Escopo do commit

- **356 arquivos** alterados no índice (inclui 47 modificados, 1 exclusão e 204 não rastreados
  anteriormente), **+62.648 / −292** linhas.
- Inclui: código do legado pendente, correções de segurança anteriores, documentos `docs/v3`,
  `docs/v4`, `docs/v5`, relatórios de auditoria, `STATUS.md`, congelamento e comunicado.
- Exclusão: `tmp/check_db.php` (arquivo temporário rastreado removido).
- Arquivos grandes incluídos: `doc/Guia-do-Produtor-Organico-CI-Organicos-SNA-OrganicsNet.pdf`
  (~20 MB) e logos em `img/`. O baseline fica mais pesado; se o time preferir, pode ser movido para
  LFS em um segundo momento.

### 2.1 O que **não** entrou (ignorado ou excluído)

| Caminho | Motivo |
|---|---|
| `config/secrets.php` | segredos locais; já ignorado pelo `.gitignore` |
| `api/uploads/` | evidências/fotos de runtime (PII); regra `/api/uploads/` adicionada ao `.gitignore` |
| `storage/externos/*.xlsx` | cache de planilhas CNPO/PEAF; `storage/externos/.gitignore` já bloqueia o conteúdo |
| `uploads/` (raiz) | uploads de usuários; já ignorado |
| `tmp/`, `vendor/`, `node_modules/`, `sql/_backup/`, `recicla/` | já ignorados |
| `logs/*.log` | já ignorados (`*.log`) |

> `storage/.htaccess` e `storage/externos/.gitignore` **foram** versionados; o conteúdo das planilhas
> não.

### 2.2 Redação de segredo antes do commit

A chave **Resend antiga** (já comprometida) aparecia em:

- `SEGURANCA_SEGREDOS.md` → substituída por `re_REDACTED`;
- `scripts/atualizar-chave-resend.ps1` → removida; script atualizado para operar apenas em
  `config/secrets.php` e receber a chave antiga por variável de ambiente
  (`RESEND_CHAVE_ANTIGA`), sem valor hardcoded.

A chave continua existindo **apenas** em `config/secrets.php` (não versionado) e segue pendente de
rotação manual no painel da Resend (item P0-6).

---

## 3. Verificações

| Verificação | Resultado |
|---|---|
| `git status` antes do commit | 253 entradas (47 modificadas, 205 não rastreadas, 1 exclusão) |
| `git diff --cached --name-only` | 356 arquivos |
| Segredo antigo no conteúdo staged | **nenhuma ocorrência** |
| `git commit` | sucesso (`796d11d`) |
| `git tag -a pre-modularizacao` | sucesso (tag anotada) |
| `git switch -c feat/f1-kernel` | sucesso |
| `git status` depois | **0** |
| Aplicação após commit | `GET /app/login.php` 200; WAF ativo; `TRACE=405` |

O `.git/index.lock` inicialmente foi bloqueado pelo sandbox de arquivos; a operação foi repetida com
permissão ampliada e o lock órfão removido. Nenhum arquivo do app foi alterado por esse processo.

---

## 4. Observações e pendências

1. **Não houve `git push`.** O commit/tag/branch existem apenas no repositório local. Enviar ao
   remoto (`app`) exige credenciais e decisão do responsável.
2. **Chave Resend antiga comprometida** continua em `config/secrets.php` local; rotacionar e revogar
   no painel (P0-6).
3. **20 MB de PDF** no commit; avaliar Git LFS se o repositório for publicado.
4. O baseline ainda contém **scripts de diagnóstico na raiz** (`_tmp_*`, `check_*`, `run_*`) e
   `api/legacy/session-bridge.php`; estão bloqueados pelo WAF/`.htaccess`, mas devem ser removidos na
   limpeza (A4/A6).
5. `.agent/` (relatórios de análise) foi versionado; se não for desejado no repositório principal,
   remover em commit posterior.
6. A branch `feat/f1-kernel` está pronta para receber a F1; a tag marca o estado **antes** da F1.

---

## 5. Próximos itens da Fase 0

| # | Item | Status |
|---|---|---|
| 1 | Congelar features e comunicar | ✅ |
| 2 | Commit/tag/branch | ✅ **este relatório** |
| 3 | Staging + feature flags | ⏳ |
| 4 | P0 residual (Resend/logs/LGPD) | ⚠️ |
| 5 | Paridade K2/D1 | ⏳ |
| 6 | Fiscais e especialista | ⏳ |

---

## Anexo — Comandos executados

```bash
git add -A
git commit -m "chore(baseline): congela estado pre-modularizacao" \
           -m "P0 de seguranca, congelamento de features, documentos v5 e correcoes pendentes do legado."
git tag -a pre-modularizacao -m "Baseline pre-modularizacao (Fase 0) - 2026-10-30"
git switch -c feat/f1-kernel
git status --short
```
