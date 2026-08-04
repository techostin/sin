1. Estrutura do Projeto
plain
Copy
app/
├── .agent/                  # Diretório de agentes IA (Cursor/prompts/skills) — NÃO é parte da aplicação
├── .git/                    # Repositório Git ativo
├── api/                     # Endpoints REST (certificacao, cidades, ocorrencias, plano-manejo, relatorios)
├── assets/
│   ├── css/
│   │   ├── style.css                    # Estilos globais, sidebar, responsividade mobile
│   │   └── agricultura-familiar.css     # Estilos mobile-first, touch targets, temática orgânica
│   ├── js/
│   │   ├── db_sync.js                   # Motor PWA de sincronização offline (IndexedDB)
│   │   ├── eventos.js                   # Scripts de eventos
│   │   ├── eventos_modal_debug.js
│   │   └── masks.js
│   └── img/                 # Ícones PWA (icon-192.png, icon-512.png)
├── classes/                 # Modelos PHP (User.php, Database.php, Evento.php, etc.)
├── config/
├── includes/
│   ├── header.php           # Template <head>, carrega Bootstrap, DataTables, PWA, db_sync.js
│   ├── menu.php             # Sidebar completa com permissões por nível
│   ├── footer.php           # Fecha wrapper, scripts JS globais
│   ├── lembretes.php
│   └── widget_eventos.php
├── modulos/                 # Páginas do sistema organizadas por domínio (veja lista abaixo)
├── index.php                # Entry point + Roteador amigável completo
├── login.php                # Autenticação em 2 etapas (credenciais → órgão/especialidade para gestores)
├── logout.php
├── manifest.json            # PWA: standalone, tema verde #198754
├── sw.js                    # Service Worker com cache versionado, offline fallback
├── 404.php
└── teste_*.php              # Scripts de teste diversos
2. Stack Tecnológica
Table
Camada	Tecnologia
Backend	PHP 8.x (PDO, sessões nativas)
Banco	MySQL (classe Database.php singleton)
Frontend	Bootstrap 5.3.3, Bootstrap Icons, jQuery 3.7, DataTables 1.13.6
PWA	manifest.json + sw.js (cache versionado sinporg-v3)
Offline	IndexedDB via db_sync.js
Roteamento	Roteador amigável custom em index.php (parse manual de $_GET['url'])
Segurança	CSRFProtection class, password_hash/verify
3. Módulos Identificados (modulos/)
O sistema possui 12 domínios funcionais com CRUD completo:
Table
Domínio	Arquivos	Destaque
associados/	listar, cadastrar, editar, excluir, form_relatorio	Gestão de agricultores
associacoes/	listar, cadastrar, editar, excluir	Organizações
caderno_campo/	30+ arquivos	Core do sistema — propriedades, lavouras, insumos, produtos, lotes, atividades diárias, ocorrências, categorias, dashboards
certificacao/	listar, cadastrar, editar, renovar, historico, insumos	SPG/OCS
contas/	listar, cadastrar, editar, excluir	Financeiro
eventos/	listar, cadastrar, editar, excluir, presencas, acompanhar_presenca, relatorio_presenca, visualizar	Eventos com presença
fornecedor/	listar, cadastrar, editar, excluir	Fornecedores
plano_manejo/	listar, cadastrar, editar, visualizar, aprovar, upload_croqui	Planos de manejo orgânico
relatorios/	relatorio_associados	
caderno_campo/relatorios/	10+ relatórios com exportação PDF/Excel	atividades, insumos, produção, lotes, propriedades, produtores, ocorrências
roteiros_inspecao/	visitas (agendar, realizar, relatorio), templates, nao_conformidades	Fiscalização de campo
usuarios/	listar, cadastrar, editar, excluir, perfil, configuracoes, salvar_notificacoes	Gestão de usuários
4. Estado dos Arquivos Principais
includes/header.php
Inclui config + classe User
Proteção de página (isLoggedIn())
Carrega Bootstrap 5, DataTables, Bootstrap Icons, 2 CSS custom
Inclui manifest.json, registra sw.js
Define window.SITE_URL global
Inclui db_sync.js e masks.js
Tem script para modal de eventos pendentes (mostrar_modal_eventos)
includes/menu.php
Sidebar responsiva com Bootstrap list-group
Role-based menu: NIVEL_GESTOR vê bloco "Fiscalização & Conformidade", usuário comum vê "Minha Conformidade"
Badge dinâmico de não-conformidades pendentes
Indicador de conexão (online/offline) injetado via JS no bottom da sidebar
Ações rápidas: "Nova Atividade", "Reportar Ocorrência"
Badge de sincronização (sync-badge-count)
includes/footer.php
Fecha wrappers HTML
Scripts globais: Bootstrap bundle, jQuery, DataTables
Toggle do menu lateral (#menu-toggle)
login.php
Autenticação em 2 etapas para gestores: credenciais → órgão/especialidade/setor
Busca em associados ou usuarios (unificou tabelas)
password_verify para senhas
Redirecionamento por perfil: usuario → dashboard-agricultor, admin/gestor → /
index.php
Roteador amigável monolítico com ~700 linhas de if/elseif parseando $_GET['url']
Suporte a UUIDs (36 chars) para lavouras, insumos, produtos, lotes, atividades
Suporte a rotas legadas e novas
Inclui dashboard com cards de navegação, DataTables de eventos e presenças
assets/css/style.css (151 linhas)
Layout com sidebar off-canvas (#sidebar-wrapper 280px)
Animações suaves em 0.3s cubic-bezier
Scrollbar customizada
Responsividade mobile: tabelas viram cards em < 768px (.table-responsive-stack)
Scroll horizontal para relatórios técnicos
assets/css/agricultura-familiar.css (109 linhas)
Paleta orgânica (verde #198754, terra #6d4c41)
Touch targets mínimos de 48px para botões
Inputs com min-height: 44px
Modais com glassmorphism
Em telas < 576px: botões ficam width: 100%, cards reduzem padding
manifest.json
PWA configurado: standalone, tema verde, ícones 192/512
sw.js (170 linhas)
Cache versionado: sinporg-v3-static + sinporg-v3-dynamic
Estratégia Cache First para assets, Network First para páginas
API calls nunca cacheadas (/api/, validações, CSRF)
Fallback offline embutido com página HTML estilizada ("Modo Offline Ativo")
assets/js/db_sync.js ⚠️ COM BUG
Motor de sincronização offline com IndexedDB
Intercepta submit de forms (#form-atividade, #form-insumo, #form-ocorrencia)
Salva na fila sync_queue quando offline
Sincroniza automaticamente quando volta online
🚨 BUG CRÍTICO: O método checkPendingCount() (linha 261) está fora da classe OfflineSyncEngine (a classe fecha na linha 259). Isso causará TypeError quando window.syncEngine.checkPendingCount() for chamado no header.php. O método precisa ser movido para dentro da classe.
5. Observações para Implementação Mobile
Table
Aspecto	Estado	Recomendação
Viewport	✅ Configurado (width=device-width)	—
Touch targets	✅ 48px mínimo em botões	—
Sidebar mobile	✅ Off-canvas com toggle	Pode precisar de bottom nav para acesso rápido
Tabelas mobile	✅ Converte para cards	Algumas páginas ainda usam DataTables puro
PWA	✅ Manifest + SW registrado	Ícones precisam existir (assets/img/icon-*.png)
Offline sync	⚠️ Funciona parcialmente (bug no checkPendingCount)	Corrigir bug antes de expandir
QR Code / Rastreabilidade	✅ Existe (lotes_producao/teste_qr.php, debug_qr.php)	Pode ser usado para scanner mobile
Login mobile	⚠️ Formulário centralizado básico	Melhorar UX com autofill e teclado otimizado
Notificações push	❌ Não implementado	Considerar para eventos e visitas técnicas
6. Próximos Passos Sugeridos
Corrigir o bug em db_sync.js movendo checkPendingCount para dentro da classe OfflineSyncEngine é a prioridade #1 para o offline funcionar.
Quer que eu corrija o db_sync.js e implemente as melhorias mobile imediatamente