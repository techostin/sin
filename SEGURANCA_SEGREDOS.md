# Segredos expostos: situação e correção

Auditoria em 12/09/2026.

## O que estava exposto

`config/config.php` continha, em texto puro e **versionado**:

| Constante | Risco |
|---|---|
| `RESEND_API_KEY` = `re_hWUFR...8fZD` | Envio de e-mail em nome do SINPORG |
| `ID_ENCODE_KEY` = `sinporg_id_encode_2024_...` | Permite forjar IDs de URLs (acesso a registros de terceiros) |

**Alcance:** a chave aparece em **2 commits** e o repositório tem remoto em
`https://github.com/techostin/app.git` — ou seja, **foi publicada**.

## O que já foi corrigido

1. `config/config.php` **não contém mais** os valores. Passou a carregar de:
   - `config/secrets.php` (não versionado), ou
   - variáveis de ambiente `RESEND_API_KEY` / `ID_ENCODE_KEY`
2. `config/secrets.php` criado com os valores atuais e **adicionado ao .gitignore**
3. `config/secrets.example.php` criado como modelo
4. Teste de fumaça do app PHP: **OK** após a mudança

## O que FALTA — e só o SINPORG pode fazer

### 1. Rotacionar a chave na Resend (obrigatório)

A chave atual deve ser considerada **comprometida**. Enquanto não for trocada, ela
continua válida para quem a leu no GitHub.

```
1. Acesse https://resend.com/api-keys
2. Crie uma nova chave com permissão apenas de envio (send-only)
3. Revogue a chave antiga (re_hWUFR...8fZD)
4. Atualize config/secrets.php (ou a variavel de ambiente RESEND_API_KEY)
5. Atualize tambem sinporg/.env (RESEND_API_KEY)
```

### 2. Rotacionar a ID_ENCODE_KEY (avaliar)

Trocar esta chave **invalida todos os links já emitidos** que usam IDs codificados
(ex.: carteirinhas, links de visita). Antes de trocar:

- verificar quanto tempo os links precisam sobreviver;
- planejar uma janela em que o decodificador aceite as duas chaves.

Enquanto isso, o risco é alguém forjar um ID e acessar outro registro. Avaliar se
os endpoints que usam `decodeId()` também validam permissão de acesso — se sim, o
impacto é baixo.

### 3. Limpar o histórico do Git (opcional, depois da rotação)

Rotacionar já neutraliza o vazamento. A limpeza do histórico é higiene adicional.

**Atenção:** reescrever o histórico altera todos os hashes de commit e exige
`push --force`. Quem tiver clone precisa re-clonar. Só faça com o time avisado.

```bash
# Opção A: git-filter-repo (recomendado)
pip install git-filter-repo
git filter-repo --replace-text <(echo "re_REDACTED==>REDACTED")
git push --force --all

# Opção B: BFG (requer Java)
java -jar bfg.jar --replace-text secrets.txt
git reflog expire --expire=now --all && git gc --prune=now --aggressive
git push --force --all
```

## Regra para o futuro

Nenhuma chave real em arquivo versionado. O padrão adotado:

- `config/secrets.php` (ignorado) para o app PHP legado
- `.env` (ignorado) para o monorepo TypeScript
- `*.example` versionado apenas com placeholders

## Verificação

```bash
# Nada de chave em arquivo versionado:
git grep -n "re_hWUFRfsw" -- .       # deve retornar vazio
git check-ignore config/secrets.php  # deve listar o arquivo
```
