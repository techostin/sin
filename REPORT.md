# RELATÓRIO DE ANÁLISE — SINPORG (app)

> Documento de referência do sistema. Atualizado em 2026-08-04.

## 1. Estrutura do Projeto

```
app/
├── .agent/                  # Diretório de agentes IA (Cursor/prompts/skills) — NÃO é parte da aplicação
├── .git/                    # Repositório Git ativo
├── api/                     # Endpoints REST (certificadoras, certificacao, cidades, ocorrencias, plano-manejo, relatorios, pre-cadastro)
├── assets/
│   ├── css/
│   │   ├── style.css                    # Estilos globais, sidebar, responsividade mobile
│   │   └── agricultura-familiar.css     # Estilos mobile-first, touch targets, temática orgânica
│   ├── js/
│   │   ├── db_sync.js                   # Motor PWA de sincronização offline (IndexedDB) — OK
│   │   ├── eventos.js                   # Scripts de eventos
│   │   ├── eventos_modal_debug.js
│   │   └── masks.js
│   └── img/                 # Ícones PWA (icon-192.png, icon-512.png) — EXISTEM
├── classes/                 # Modelos PHP (User, Database, Certificadora, Evento, Ocorrencia, etc.)
├── config/                  # config.php, relatorios_config.php
├── dev-utils/               # Scripts de diagnóstico/migração (check_*/migrate_*) — movidos da raiz
├── includes/                # header, menu, footer, lembretes, widget_eventos
├── modulos/                 # Páginas por domínio (associados, certificacao, caderno_campo, etc.)
├── uploads/                 # Conteúdo enviado por usuários (logos, fotos, certificados, croquis) — NÃO versionado
├── index.php                # Entry point + Roteador amigável
├── login.php                # Autenticação em 2 etapas (gestor)
├── logout.php
├── certificadoras_*.php      # CRUD de certificadoras (listar/cadastrar/editar)
├── manifest.json            # PWA: standalone, tema verde #198754
├── sw.js                    # Service Worker com cache versionado, offline fallback
├── 404.php
└── sql/                     # Schemas e seeds (ex: create_certificadoras.sql)
```

> Nota: `.gitignore` ignora `vendor/`, `node_modules/` e `uploads/`. Dependências
> (Composer/npm) e arquivos de usuário ficam no disco, mas não no repositório.

## 2. Stack Tecnológica

| Camada    | Tecnologia |
|-----------|------------|
| Backend   | PHP 8.x (PDO, sessões nativas) |
| Banco     | MySQL (classe Database.php singleton) — DB `sisagro` |
| Frontend  | Bootstrap 5.3.3, Bootstrap Icons, jQuery 3.7, DataTables 1.13.6 |
| PWA       | manifest.json + sw.js (cache versionado sinporg-v3) |
| Offline   | IndexedDB via db_sync.js |
| Roteamento| Roteador amigável custom em index.php (parse manual de $_GET['url']) |
| Segurança | CSRFProtection class, password_hash/verify |

## 3. Módulos Identificados (modulos/)

O sistema possui 12+ domínios funcionais:

| Domínio            | Destaque |
|--------------------|----------|
| associados/        | Gestão de agricultores (listar, cadastrar, editar, meu_perfil, pendentes) |
| associacoes/       | Organizações |
| caderno_campo/     | Core — propriedades, lavouras, insumos, produtos, lotes, atividades, ocorrências, relatórios |
| certificacao/      | SPG/OCS — cadastrar, listar, editar, renovar, histórico, insumos, certificado (PDF) |
| certificadoras/    | CRUD de certificadoras (ver raiz: certificadoras_*.php + api/certificadoras.php) |
| contas/            | Financeiro |
| eventos/           | Eventos com presença |
| fornecedor/        | Fornecedores |
| plano_manejo/      | Planos de manejo orgânico (upload de croqui) |
| relatorios/        | Relatórios (PDF/Excel) |
| roteiros_inspecao/ | Fiscalização de campo (visitas, templates, não-conformidades) |
| usuarios/          | Gestão de usuários (perfil, configurações) |

### Módulo Certificadoras (novo / foco recente)
- **Front-end:** `certificadoras_list.php` (DataTable com export Excel/PDF/Print), `certificadoras_cadastrar.php`, `certificadoras_editar.php`
- **API REST:** `api/certificadoras.php` — GET (lista/detalhe), POST (criar), PUT (atualizar, via túnel `_method=PUT` em POST multipart), DELETE
- **Upload de logo:** o formulário aceita upload direto de arquivo (`<input type=file name=logo>`) processado por `classes/ImageUploader.php` (redimensiona p/ 400x400, máx 2MB), salvando em `uploads/certificadoras/`. Também aceita `logo_url` externa.
- **Modelo:** `classes/Certificadora.php`; **Schema:** `sql/create_certificadoras.sql`

## 4. Estado dos Arquivos Principais

**includes/header.php**
- Inclui config + classe User; proteção de página (`isLoggedIn()`)
- Carrega Bootstrap 5, DataTables, Bootstrap Icons, 2 CSS custom
- Inclui manifest.json, registra sw.js, define `window.SITE_URL` e `window.USER_NIVEL`
- Inclui db_sync.js e masks.js; script chama `window.syncEngine.checkPendingCount(...)`

**includes/menu.php**
- Sidebar responsiva, role-based (Gestor vê "Fiscalização & Conformidade", usuário comum "Minha Conformidade")
- Badge de não-conformidades pendentes, indicador online/offline, badge de sincronização

**includes/footer.php**
- Fecha wrappers; scripts globais (Bootstrap, jQuery, DataTables); toggle do menu (#menu-toggle)
- Container de toasts `#toast-container` usado por db_sync.js

**login.php**
- Autenticação em 2 etapas para gestores (credenciais → órgão/especialidade/setor)
- Busca unificada em `associados` ou `usuarios`; `password_verify`
- Redirecionamento por perfil (usuário → dashboard, admin/gestor → /)

**index.php**
- Roteador amigável (~1100 linhas) parseando `$_GET['url']`
- Suporta UUIDs e rotas legadas/novas; dashboard com cards e DataTables

**assets/js/db_sync.js** ✅ CORRIGIDO
- Motor de sincronização offline com IndexedDB
- Intercepta submit de forms (#form-atividade, #form-insumo, #form-ocorrencia)
- Salva na fila `sync_queue` quando offline; sincroniza ao voltar online
- `checkPendingCount(callback)` está DENTRO da classe `OfflineSyncEngine` (linha ~297, classe fecha em ~316) — o bug citado em versões anteriores já foi resolvido; o `window.syncEngine.checkPendingCount()` do header.php funciona.

**sw.js**
- Cache versionado `sinporg-v3-static` + `sinporg-v3-dynamic`
- Cache First para assets, Network First para páginas; `/api/` nunca cacheado
- Fallback offline ("Modo Offline Ativo")

## 5. Observações para Implementação Mobile

| Aspecto                | Estado | Recomendação |
|------------------------|--------|--------------|
| Viewport              | ✅ width=device-width | — |
| Touch targets         | ✅ 48px mínimo | — |
| Sidebar mobile        | ✅ Off-canvas | Considerar bottom nav |
| Tabelas mobile        | ✅ Viram cards | Algumas páginas ainda usam DataTables puro |
| PWA                   | ✅ Manifest + SW + ícones (assets/img/icon-*) | — |
| Offline sync          | ✅ Funcional (bug corrigido) | Expandir cobertura de forms |
| QR Code / Rastreabilidade | ✅ Existe | Scanner mobile |
| Login mobile          | ⚠️ Básico | Autofill / teclado otimizado |
| Notificações push     | ❌ Não implementado | Considerar para eventos/visitas |

## 6. Próximos Passos Sugeridos
1. Auditoria de segurança: validar CSRF nos forms via JS/fetch e sanear entradas nas APIs.
2. Expandir cobertura do motor offline (db_sync.js) para mais formulários.
3. Melhorar UX de login mobile (autofill, teclado).
4. Implementar notificações push para eventos e visitas técnicas.
5. Considerar bottom navigation para acesso rápido em telas pequenas.
