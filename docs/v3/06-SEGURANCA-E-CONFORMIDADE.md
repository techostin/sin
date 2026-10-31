# 06 — Segurança, Privacidade e Conformidade Regulatória

> **Fontes:** `AUDITORIA_SEGURANCA.md`, `SEGURANCA_SEGREDOS.md`,
> `RELATORIO_MODULARIZACAO.md` §4.5, `recicla/PRD.md` §3, `modulos/TODO_Agroind.md`
> §2–5. Este documento define o que a versão nova **deve** cumprir, e o que o
> `fiscal-seguranca` (F2) e o `fiscal-regulatorio` (F4) verificam.

---

## 1. Estado de segurança do legado (linha de base auditada)

### 1.1 Vulnerabilidades já corrigidas no legado (não podem regredir na versão nova)

| ID | Vulnerabilidade | Correção aplicada | Como a versão nova garante |
|---|---|---|---|
| C-01 | **RCE por upload arbitrário** (comprovado: `.php` enviado e executado) | ```ImageUploader``` (MIME + `getimagesize` + re-encode GD) | Upload em storage fora do webroot, tipo derivado do **conteúdo**, nome aleatório, sem execução |
| C-02 | **Path Injection / LFI** na rota de relatórios e no webhook PIX | `includes/rota_segura.php` (formato + whitelist + `realpath`) | Não existe include dinâmico; rotas são **declaradas**, não montadas de string |
| C-03 | **SQL Injection** via `$_SESSION['user_table']` interpolado | Whitelist `User::TABELAS_USUARIO` | Prisma parametrizado; nenhuma interpolação de identificador |
| C-04 | **Stored XSS** via logo SVG | SVG removido da whitelist + validação de conteúdo | Sanitização na renderização + CSP; SVG servido como download |
| C-05 | **IDOR** em cadastro/edição de atividade (`associado_id` vindo do POST) | Força `associado_id` do usuário logado para não-gestores | `policies/` + escopo no repositório; **teste de IDOR obrigatório por rota** |
| C-06 | **CSRF** ausente em cadastros/edições | `CSRFProtection::validatePostRequest()` | Cookie `SameSite` + token ***double-submit***; mutações só por método não-idempotente |
| C-07 | ***Debug* ativo em produção** (~25 arquivos) | Flag única `APP_DEBUG` (padrão off) | NODE_ENV/LOG_LEVEL; erro nunca serializa stack para o cliente |
| C-08 | **Segredos versionados** (chave de e-mail + chave de cifra de ID) | Movidos para `config/secrets.php` (ignorado) / variáveis de ambiente | Apenas variáveis de ambiente; `.env.example` com placeholders; `gitleaks` no CI |

### 1.2 Riscos ainda abertos no legado (a versão nova **deve** resolver por construção)

| ID | Risco | Evidência no legado | Requisito na versão nova |
|---|---|---|---|
| A-01 | **~30 endpoints de API sem autenticação** | `api/relatorios/{visao-geral,estoque-atual,inadimplencia,lista-associados,...}.php` | Guarda **global**; rota pública é exceção **explícita** e decorada, nunca padrão |
| A-02 | **~56 arquivos sem guarda** de login | páginas que não incluem `header.php` | Autorização por ***middleware*** — impossível esquecer |
| A-03 | **Autorização por nível numérico** (1/2/3), sem escopo por recurso | `classes/User.php` | Papéis + `pode(recurso, ação)` + `CertificadoraScopeGuard` |
| A-04 | **UI usada como autorização** (menu esconde, URL direta entra) | `includes/menu.php` × roteador sem middleware | Menu é derivado da política; **a API é a fonte de verdade** |
| A-05 | **Escrita cruzada entre domínios** (6 UPDATEs) | `Agroindustria.php:258`, `admin/clusters.php:106`, `portal/area.php:102`, `usuarios/salvar_notificacoes.php:29` | Lei L2: nenhum UPDATE em tabela alheia; evento + projeção |
| A-06 | **God table de identidade** (senha/nível em `associados`) | `RELATORIO_MODULARIZACAO.md` §4.3 | `kernel_usuarios` separado |
| A-07 | **Duas/três implementações de login e de reset de senha** | `login.php`, `classes/User.php`, `portal/login.php`; `password_resets` com 2 esquemas | Um único serviço de autenticação |
| A-08 | **Scripts destrutivos no webroot** (~36 na raiz, ~33 em `dev-utils/`) | `run_sql.php`, `fix_codes.php`, `debug_seed.php` | Fora do webroot; execução apenas por CLI/CI |
| A-09 | **Credenciais de banco `root` sem senha** | `config/config.php` | Usuário dedicado, privilégio mínimo, segredo em `.env` |
| A-10 | **Sem rate limit / lockout no login** | `login.php` | Rate limit por IP + por conta; *lockout* progressivo |
| A-11 | **Sem `session_regenerate_id` no legado antigo** | auditoria | JWT com refresh rotativo; revogação por família de token |
| A-12 | **Webhooks PIX sem validação de assinatura/idempotência** | `api/pix/webhook/*.php` | Assinatura obrigatória + `event_id` único + processamento idempotente |
| A-13 | **Chave de sessão inexistente** (`$_SESSION['nivel']`) | `includes/header.php` | Tipagem elimina a classe do erro |
| A-14 | **Sem trilha de auditoria confiável** | `audit_log` existe com 56 chamadas | `audit_log` obrigatório em toda ação sensível, *append-only* |
| A-15 | **Sem testes automatizados** | nenhuma suíte | Vitest + Playwright + Testcontainers no CI |

---

## 2. Requisitos de segurança da versão nova

### 2.1 OWASP Top 10 (2021) → controle obrigatório

| Risco | Controle |
|---|---|
| A01 Broken Access Control | Guarda global; `pode()` explícito; teste de IDOR e tenant por rota; **negar por padrão** |
| A02 Cryptographic Failures | TLS obrigatório; senhas em bcrypt→argon2id; segredos fora do código; sem ID previsível (UUID) |
| A03 Injection | Prisma parametrizado; Zod em 100% da entrada; sem SQL concatenado; sem `eval` |
| A04 Insecure Design | Kernel com contrato explícito; 5 leis de modularização; *threat model* por módulo |
| A05 Security Misconfiguration | Imagem mínima, usuário não-root, `helmet`, CORS por lista, CSP, sem diretório listável, sem *debug* em prod |
| A06 Vulnerable Components | `pnpm audit` bloqueante no CI; Renovate/Dependabot; versões fixadas por *lockfile* |
| A07 Auth Failures | JWT + refresh rotativo com hash; rate limit; MFA opcional para admin; *lockout* |
| A08 Integrity Failures | Imagem assinada por SHA; `pnpm --frozen-lockfile`; verificação de assinatura de webhook |
| A09 Logging Failures | Logs JSON estruturados com `traceId`, sem PII; alerta em 5xx; `audit_log` |
| A10 SSRF | Nenhuma URL fornecida pelo usuário é buscada pelo servidor; allowlist de domínios externos |

### 2.2 Multi-tenant (certificadoras) — controle crítico

O legado tem `usuarios.certificadora_id` e `associados.certificadora_id`, mas a
autorização é só numérica. A versão nova exige:

1. `CertificadoraScopeGuard` **obrigatório** em toda rota do domínio de certificação.
2. O escopo entra no repositório como filtro **não removível** (`where certificadoraId = ctx.certificadoraId`).
3. **Teste automatizado de acesso cruzado**: usuário da certificadora A tentando ler/alterar recurso da B → `403`. **Bloqueia o merge.**
4. Auditoria registra `certificadora_id` em cada leitura sensível.

### 2.3 Dados pessoais (LGPD)

| Requisito | Implementação |
|---|---|
| Base legal e finalidade | Cadastro de associado = execução de contrato + obrigação legal (certificação) |
| Minimização | Coletar só o necessário; CPF/RG criptografados em repouso quando possível |
| Direito de acesso e correção | Tela de perfil + exportação de dados do próprio titular |
| Direito de eliminação | **Conflito com retenção regulatória de 3 anos** → eliminação é *anonimização*, não exclusão; documentar em ADR |
| Segurança | TLS, controle de acesso, auditoria, backup cifrado |
| Retenção | 3 anos para diário de campo, insumos e balanço de massas (regulatória, sobrepõe LGPD) |
| Encarregado (DPO) | Registrar contato no rodapé/política de privacidade |
| Consentimento de imagem | Fotos de campo: consentimento registrado no cadastro |

### 2.4 Segredos — gestão obrigatória

| Segredo | Onde vive | Rotação |
|---|---|---|
| `DATABASE_URL` | variável de ambiente / `docker secret` | trimestral |
| `JWT_ACCESS_SECRET` | idem | trimestral |
| `JWT_REFRESH_SECRET` | idem | trimestral |
| `RESEND_API_KEY` | idem | **rotacionar imediatamente** (a chave do legado foi publicada no GitHub) |
| Webhooks PIX (segredo de assinatura) | idem | semestral |
| Chave de cifra de IDs em URL | ❌ **eliminada** — substituída por UUID + autorização real | — |
| Credenciais de administrador | cofre do provedor | semestral |

**Regra:** nenhuma chave real em arquivo versionado. `config/secrets.php` e `.env`
ignorados; apenas `*.example` no repositório. Verificação por `gitleaks` no CI.

### 2.5 Uploads de arquivo (regra única e reutilizável)

```
1. Limite de tamanho por tipo (imagem 5 MB, PDF 20 MB) — validado no servidor
2. Tipo real pelo CONTEUDO (magic bytes), nunca pela extensao nem pelo Content-Type
3. Whitelist: image/jpeg, image/png, image/webp, application/pdf
4. Imagens: re-encode via sharp (remove payload embutido e metadados perigosos)
5. Nome aleatorio (UUID v4) + extensao derivada do tipo validado
6. Storage FORA do webroot; entrega por URL assinada com expiracao
7. SVG: nunca renderizado inline; entrega como download
8. Todo upload gera registro em audit_log
```

---

## 3. Conformidade regulatória (domínio orgânico)

### 3.1 Base legal

| Norma | Escopo | Impacto no sistema |
|---|---|---|
| **Lei nº 10.831/2003** | Agricultura orgânica | Retenção de registros (3 anos), rastreabilidade |
| **Decreto nº 6.323/2007** | Mecanismos de controle e comercialização | Segregação espaço-temporal na agroindústria |
| **Portaria MAPA nº 52/2021** | Regras técnicas e lista de substâncias permitidas | Bloqueio de insumo não permitido; tipo de semente |
| **IN nº 50/2009** | Rotulagem | Cálculo do percentual orgânico e selo |
| **Portarias 404/2022, 811/2023, 685/2024, 1.201/2024** | Atualizações | Manter a lista de insumos versionada e atualizável |

### 3.2 Matriz de rastreabilidade regulatória (verificada por F4)

| Requisito funcional | Base legal | Controle no sistema | Verificação |
|---|---|---|---|
| RF02 — Zonas de amortecimento e barreiras físicas | Portaria 52/2021 | Campo obrigatório na propriedade/parcela | Não permite salvar sem descrever barreira |
| RF03 — Histórico da área e conversão | Portaria 52/2021 | `historico_conversao` + cálculo do período restante | Cálculo testado unitariamente |
| RF04 — Sementes (origem orgânica ou excepcional) | Portaria 52/2021 | `plano_manejo_cultivos.tipo_semente` + justificativa | Exige justificativa quando não-orgânica |
| RF06 — Insumos permitidos | Portaria 52/2021 | Validação contra `insumos_organicos_aprovados` | **Alerta impeditivo** ao lançar proibido |
| RF08 — Segregação e higienização | Decreto 6.323/2007 | `controle_higienizacao` antes do processamento | Exige registro de "cleaning" para lote orgânico |
| RF09 — Cálculo de percentual orgânico | IN 50/2009 | Cálculo por receita e ingredientes | ≥95%: selo; 70–95%: frase permitida; <70%: **bloqueia** menção |
| RF11 — Balanço de massas | Lei 10.831/2003 + Portaria 52 | Entrada × saída por ordem de produção | Saída > entrada dispara alerta |
| RF12 — QR Code de rastreabilidade | Lei 10.831/2003 | Lote → produto → lavoura → atividades → insumos | Consulta pública devolve a cadeia completa |
| NFR01 — Retenção de 3 anos | Lei 10.831/2003 | *Soft delete* em diário, insumos e balanço | Nenhum DELETE físico; auditoria confirma |
| NFR03 — Trilha de auditoria | Portaria 52/2021 | `audit_log` *append-only* (quem/quando/o quê) | Consultável por OAC/SPG |

### 3.3 Regras de rotulagem (implementar como serviço, não como tela)

```ts
// packages/contracts/src/rotulagem.ts
export type Selo = 'ORGANICO_BRASIL' | 'INGREDIENTES_ORGANICOS' | 'NENHUM';

export function calcularSelo(percentualOrganico: number): Selo {
  if (percentualOrganico >= 95) return 'ORGANICO_BRASIL';
  if (percentualOrganico >= 70) return 'INGREDIENTES_ORGANICOS';
  return 'NENHUM';
}
```

> **Teste obrigatório:** 94,99% → ```INGREDIENTES_ORGANICOS```; 95,00% → ```ORGANICO_BRASIL```;
> 69,99% → ```NENHUM```. Cobertura 100% (gate QUA-2).

### 3.4 Retenção e *soft delete*

| Tabela / domínio | Retenção mínima | Estratégia |
|---|---|---|
| Diário de campo (`atividades_diarias`, `agro_atividades_diarias`) | 3 anos | *Soft delete* + histórico imutável |
| Aplicação de insumos (`insumos`, `lote_insumo`) | 3 anos | *Soft delete*; nota fiscal anexada |
| Balanço de produção (`lotes_producao`, `ordens_producao`) | 3 anos | *Soft delete* |
| Certificação (`certificacao_participativa`, `visitas_tecnicas`) | permanente | Nunca excluir; versionar |
| Auditoria (`audit_log`) | permanente | *Append-only*, sem UPDATE/DELETE |
| Dados pessoais (LGPD) | enquanto o vínculo existir | Anonimização, não exclusão |

**Implementação:** coluna `deleted_at` + Prisma *middleware* que converte `delete` em
`update { deletedAt: now() }`, e um filtro global que exclui registros deletados. Um job
de retenção **só** purga o que já passou de 3 anos **e** não tem obrigação legal vigente.

---

## 4. Testes de segurança obrigatórios (o que F2 executa, não o que ele lê)

| # | Teste | Resultado esperado |
|---|---|---|
| S-01 | Anônimo acessa cada rota do módulo | `401` |
| S-02 | Agricultor acessa rota de gestor | `403` |
| S-03 | Agricultor A acessa recurso do agricultor B (IDOR) | `403`/`404` |
| S-04 | Gestor da certificadora A acessa dado da B | `403` |
| S-05 | Token com assinatura inválida / `papel` adulterado | `401` |
| S-06 | Refresh revogado ou reutilizado | `401` + revogação da família |
| S-07 | Upload de `.php` / `.svg` / arquivo com MIME forjado | Rejeitado |
| S-08 | Entrada com SQLi, XSS, JSON malformado, campo extra | Rejeitada por Zod; nada persistido |
| S-09 | Webhook com assinatura inválida | `401`; nada persistido |
| S-10 | Webhook duplicado (mesmo `event_id`) | Processado **uma** vez |
| S-11 | Rate limit: 20 tentativas de login em 1 min | Bloqueio progressivo |
| S-12 | Enumeração de usuário no login/reset | Mensagem genérica, tempo constante |
| S-13 | `.env` faltando variável obrigatória | Aplicação **falha ao subir** (não sobe meio configurada) |
| S-14 | `gitleaks detect` no repositório | Zero achados |
| S-15 | `pnpm audit --audit-level=high` | Zero vulnerabilidades altas/críticas |
| S-16 | Headers de segurança (CSP, HSTS, X-Frame-Options, nosniff) | Presentes |
| S-17 | Log de erro não expõe SQL, caminho absoluto nem PII | Verificado por amostragem |
| S-18 | Migração Prisma testada sobre cópia do dump real | Aplicada e revertida com sucesso |

---

## 5. Resposta a incidentes (mínimo viável)

| Passo | Ação |
|---|---|
| 1. Detectar | Alerta de 5xx, de latência ou de tentativa de acesso cruzado no `audit_log` |
| 2. Conter | *Feature flag* desliga o módulo afetado sem deploy; rotação do segredo suspeito |
| 3. Erradicar | Correção + teste de regressão que reproduz o incidente |
| 4. Recuperar | Rollback por tag de imagem ou restauração de backup (runbook em `docs/runbooks/`) |
| 5. Aprender | Post-mortem sem culpa, ADR se houver decisão estrutural, novo teste no CI |

**Contatos e escalação:** registrar no próprio runbook (não neste documento, que é
versionado e não deve conter dados pessoais).
