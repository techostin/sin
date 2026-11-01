# Execução Onda 3 — Escopo Row-Level + D3 Fiscalização

> **Data:** 01/11/2026 · **Status:** fatia core + documentos + evidências entregues.
> **Commits:** `0c67a84`, `b357201`, `e49c7f9` · **Tags:** `onda3-d3`, `onda3-documentos`,
> `onda3-evidencias`
> **Nenhum dado pessoal ou segredo é exibido neste relatório.**

---

## 1. Fechamento da pendência de segurança

### `AccessScope`

Novo serviço em `src/Kernel/Security/AccessScope.php`:

- visão **global** quando não há restrição;
- **produtor**: só o próprio `associado_id`;
- **associação**: só associações visíveis no vínculo;
- certificadora ainda depende do mapeamento de associações (limite registrado).

### Aplicação

| Módulo | Mudança |
|---|---|
| **D1** | lista/pendentes filtram por `associado_id` ou `associacao_id`; detalhe retorna 404 fora do escopo |
| **T1** | lote inclui `associado_associacao_id`; leitura/evento/vínculo validam o escopo |
| **D3** | visitas usam o mesmo `AccessScope` |

### Testes de IDOR adicionados

- produtor não lê associado de outro;
- produtor não lê lote de outro;
- associação A não lê associado da associação B;
- associação correta e dono leem normalmente.

Resultado: **62 testes / 149 asserções**, PHPStan 0, Deptrac 0, PHPCS 0.

---

## 2. D3 — Fiscalização & Conformidade

### Schema (`sql/d3_fiscalizacao.sql`)

| Tabela | Papel |
|---|---|
| `fiscalizacao_visitas` | visita, fiscal, associado, propriedade e status |
| `fiscalizacao_nao_conformidades` | NC com gravidade/prazo/status |
| `documentos_emitidos` | certificado/documento, número único, snapshot, emissor |

### Fluxo

```text
agendada → em_andamento → concluida → aprovada | recusada
```

- `POST /fiscalizacao/visitas` — fiscal cria visita.
- `POST /fiscalizacao/visitas/{uuid}/status` — fiscal inicia/conclui.
- `POST /fiscalizacao/visitas/{uuid}/nao-conformidades` — fiscal registra NC.
- `POST /fiscalizacao/visitas/{uuid}/revisar` — gestor aprova/recusa.
- `POST /fiscalizacao/visitas/{uuid}/certificado` — certificador emite.
- `GET /documentos/verificar/{numero}` — verificação pública.

### Regras de emissão

O certificado só é emitido quando:

1. a visita está **aprovada**;
2. o lote T1 existe e está **ativo**;
3. o lote tem **pelo menos um evento** de cadeia;
4. o lote pertence ao associado da visita (quando informado).

O número vem de `DocumentNumberGenerator` (`CERT-AAAA-000000`); a emissão gera auditoria.

### Prova real

Fluxo executado no MySQL e limpo em seguida:

```text
visita=da7b5b22-...
lote=130158e4-...
numero=CERT-2026-000001
status=emitido
verify=true
```

> A sequência consumiu o número `000001`; o próximo certificado será `000002`.

### PDF, hash e assinatura

- `MpdfCertificateGenerator` gera o PDF a partir de
  `resources/certificados/certificado.php`.
- Saída em `.certificados/` (ignorada no Git e bloqueada pelo `.htaccess`).
- Temporários do mPDF em `.pdf-tmp/` (também ignorado/bloqueado).
- `CertificateSigner` grava HMAC-SHA256 sobre o payload + hash do PDF.
- `GET /documentos/{numero}/pdf` serve o PDF público (somente `emitido`).

Teste real:

```text
numero=CERT-2026-000008
arquivo=.certificados/CERT-2026-000008.pdf
hash=2548835e...
assinatura=51373cdb...
download=200 application/pdf (17 KB)
```

### Evidências da visita

- `POST /fiscalizacao/visitas/{uuid}/evidencias` — upload multipart `arquivo`.
- `GET /fiscalizacao/visitas/{uuid}/evidencias` — lista metadados.
- `GET /fiscalizacao/evidencias/{uuid}` — download autenticado.
- Validação: 5 MB, JPEG/PNG/PDF via `UploadValidator`.
- Armazenamento em `.evidencias/` (ignorado no Git e bloqueado pelo `.htaccess`).
- Registro em `fiscalizacao_evidencias` com MIME, tamanho e hash SHA-256.

> O upload não foi testado fim-a-fim porque as rotas exigem sessão; as rotas sem sessão
> retornam **401** e a validação de arquivo tem teste de ausência. O fluxo HTTP autenticado
> deve ser coberto no E2E com o login do admin.

### Escopo certificadora × associação

- Tabela `certificadora_associacoes`.
- `DatabaseIdentityRepository` carrega os IDs de associação do certificador.
- `UsuarioContexto` mescla esses IDs em `visibleAssociations`; `AccessScope` passa a usá-los.
- Ainda é necessário **cadastrar os vínculos reais** no banco.

---

## 3. Separação de funções

| Papel | Pode |
|---|---|
| fiscal_campo | criar visita, status, NC |
| gestor_fiscalizacao | ler, revisar, aprovar/recusar |
| certificador | revisar e emitir documento |
| produtor | ler apenas suas visitas |
| admin | tudo, com auditoria |

A capability é aplicada no servidor; esconder botão na UI não é suficiente.

---

## 4. Limites desta fatia

| Item | Situação |
|---|---|
| Cadeia completa | valida existência + evento; balanço de massa completo fica para D2/D5/D6 |
| Templates/PDF/assinatura | ✅ PDF + hash + HMAC; assinatura ICP-Brasil não |
| UI | endpoints prontos; telas ainda legadas |
| Evidências/fotos da visita | ✅ upload/lista/download; E2E autenticado pendente |
| Escopo de certificadora | ✅ tabela + carregamento no contexto; mapeamentos reais a cadastrar |
| Validade jurídica | depende do especialista/DPR; software não substitui certificadora |

---

## 5. Staging

- Staging sincronizado em `0c67a84`.
- Login **200**, health **200**, K2 **200**.
- D1/T1/D3 sem sessão: **401**.
- Documento inexistente: **404**.

---

## 6. Próxima etapa

1. E2E autenticado de D3/evidências e assinatura ICP-Brasil, se exigida.
2. Cadastrar os vínculos reais certificadora × associação.
3. **Onda 4:** D5 (animal) e D6 (agroindústria) usando o mesmo T1.
