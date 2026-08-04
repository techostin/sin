# Plano: SINPORG — App Mobile + Web (PWA Dual-Mode)

## Visão Geral

O SINPORG já possui uma base sólida de PWA: `manifest.json` com `display: standalone`,
`sw.js` com estratégias de cache, `db_sync.js` com fila de sincronização IndexedDB,
CSS de bottom nav em `style.css`, sidebar overlay e FAB no dashboard do agricultor.

**O que falta** é ligar todas essas peças: o bottom nav existe no CSS mas não está
renderizado no HTML do `menu.php`; o FAB existe apenas no `dashboard_agricultor.php`;
o `manifest.json` está incompleto (faltam shortcuts, screenshots, mais tamanhos de ícone);
os formulários e listagens não estão otimizados para mobile; e o login é standalone.

**Abordagem:** Mobile-First com CSS breakpoints. Em telas < 992px o app exibe
bottom nav + FAB contextual (sem sidebar). Em telas >= 992px exibe sidebar completa
(comportamento atual de desktop). Nenhuma dependência nova é adicionada — tudo usa
Bootstrap 5, Bootstrap Icons e os assets já presentes.

---

## Sub-Tarefa 1 — CSS Global: Variáveis, Safe Area e Fundação Mobile

**Intent:** Garantir que os dois arquivos CSS tenham as variáveis e resets corretos
como base para todas as outras mudanças.

**Expected Outcomes:**
- `agricultura-familiar.css` possui variáveis `--sat/--sar/--sab/--sal`, `--bottom-nav-height`,
  `--top-bar-height`, `--sidebar-width`.
- Reset `html { -webkit-text-size-adjust: 100% }` presente.
- Botões com `font-size: 1rem` mínimo para evitar zoom no iOS.
- Formulários com inputs em coluna única em mobile (label + campo empilhados).
- Botões de submit full-width em mobile (exceto em tabelas e grupos de botão de ícone).

**Todo:**
1. Em `assets/css/agricultura-familiar.css`: adicionar variáveis de safe-area e dimensões
   de layout no bloco `:root` existente.
2. Adicionar `html { -webkit-text-size-adjust: 100%; }`.
3. Revisar regra de botões full-width para garantir que exceções estejam corretas.
4. Adicionar regra de form vertical em mobile.

**Contexto Relevante:**
- `assets/css/agricultura-familiar.css` linhas 7-14 (variáveis atuais)
- `assets/css/style.css` linhas 4-17 (body com safe-area e padding-bottom)

**Status:** [x] concluído

---

## Sub-Tarefa 2 — HTML do Bottom Nav em `menu.php`

**Intent:** O CSS do bottom nav já existe em `style.css` (linhas 243-364), mas o elemento
`#bottom-nav` não está no HTML do `menu.php`. Renderizar o bottom nav com 5 itens corretos
para o perfil do usuário logado e o badge de sincronização.

**Expected Outcomes:**
- `<nav id="bottom-nav">` inserido no `menu.php`, visível apenas em mobile.
- 5 itens: Início, Caderno de Campo, FAB central, Eventos, Mais (hamburger).
- Item "Mais" abre/fecha a sidebar overlay existente.
- Badge de sincronização com id `bnav-sync-badge`.
- JS existente de detecção de página ativa continua funcionando.

**Todo:**
1. Adicionar `<nav id="bottom-nav">` em `includes/menu.php` antes do bloco `<script>` final.
2. 5 itens com classes `.bottom-nav-item` e `data-page`.
3. Item central com `.bottom-nav-item.fab` + `.fab-circle`.
4. Condicionar por perfil PHP (agricultor vs gestor).
5. Badge `<span id="bnav-sync-badge" class="bottom-nav-badge">`.

**Contexto Relevante:**
- `includes/menu.php` linhas 201-234 (JS do bottom nav já pronto)
- `assets/css/style.css` linhas 243-364 (CSS do bottom nav já pronto)

**Status:** [ ] pendente

---

## Sub-Tarefa 3 — FAB Global em Páginas de Listagem

**Intent:** Criar um FAB global leve incluído pelo `footer.php`, exibido em páginas de
listagem no mobile com ação contextual por página via variável PHP `$fab_action`.

**Expected Outcomes:**
- FAB aparece em mobile nas páginas que definirem `$fab_action`.
- Se não definir `$fab_action`, FAB não é renderizado.
- Em desktop FAB está sempre oculto.
- Estilos do FAB movidos para `agricultura-familiar.css`.

**Todo:**
1. Mover estilos do FAB de `dashboard_agricultor.php` para `agricultura-familiar.css`.
2. Em `footer.php`: bloco condicional PHP que renderiza FAB se `isset($fab_action)`.
3. Declarar `$fab_action` e `$fab_label` nas listagens prioritárias:
   - `modulos/caderno_campo/ocorrencias/listar.php`
   - `modulos/eventos/listar.php`
   - `modulos/associados/listar.php`
   - `modulos/roteiros_inspecao/visitas/listar.php`

**Contexto Relevante:**
- `modulos/caderno_campo/dashboard_agricultor.php` linhas 485-603 (FAB de referência)
- `includes/footer.php`
- `assets/css/agricultura-familiar.css`

**Status:** [x] concluído

---

## Sub-Tarefa 4 — Header PWA: meta tags, theme-color e variáveis JS

**Intent:** Completar o `header.php` com meta tags para instalação iOS e exportar
variáveis JS úteis para o mobile.

**Expected Outcomes:**
- `<meta name="theme-color" content="#198754">` no `<head>`.
- `<link rel="apple-touch-icon">` apontando para o ícone existente.
- `window.USER_NIVEL` exportado como variável JS global.

**Todo:**
1. Adicionar `theme-color` e `apple-touch-icon` após `<link rel="manifest">`.
2. Exportar `window.USER_NIVEL` no bloco de variáveis globais (linha 64-67).
3. Verificar existência de `assets/img/icon-192.png`.

**Contexto Relevante:**
- `includes/header.php` linhas 24 e 64-67

**Status:** [ ] pendente

---

## Sub-Tarefa 5 — manifest.json: Completar para Instalação Plena

**Intent:** Expandir o manifest com shortcuts, categorias e orientação.

**Expected Outcomes:**
- `shortcuts` para Nova Atividade e Nova Ocorrência.
- `categories: ["agriculture", "productivity"]`.
- `orientation: "portrait"`.
- `lang: "pt-BR"`.
- Ícone 512px com `purpose: "maskable"`.

**Todo:**
1. Editar `manifest.json` adicionando os campos acima.
2. Confirmar rotas corretas no `index.php` para os shortcuts.

**Contexto Relevante:**
- `manifest.json` (arquivo atual — 21 linhas)
- `index.php` (rotas)

**Status:** [x] concluído

---

## Sub-Tarefa 6 — Listagens: DataTables Responsive e table-responsive-stack

**Intent:** Padronizar as listagens mais usadas com DataTables responsive e table-responsive-stack.

**Expected Outcomes:**
- Páginas com `responsive: true` no DataTables e classe `table-responsive-stack`.
- Colunas de ação com `responsivePriority: 1`.
- Atributos `data-label` nas células `<td>`.

**Páginas prioritárias:**
- `modulos/caderno_campo/ocorrencias/listar.php`
- `modulos/associados/listar.php`
- `modulos/eventos/listar.php`
- `modulos/roteiros_inspecao/visitas/listar.php`

**Todo:**
1. Adicionar `responsive: true` no DataTables de cada página.
2. Adicionar classe `table-responsive-stack` na `<table>`.
3. Adicionar `data-label` nas células `<td>`.

**Contexto Relevante:**
- `assets/css/style.css` linhas 136-178 (CSS table-responsive-stack já pronto)

**Status:** [x] concluído

---

## Sub-Tarefa 7 — Login: Redesign com Tema do Sistema

**Intent:** Redesenhar o login para usar o tema visual do sistema com UI mobile-first,
sem alterar nenhuma lógica PHP.

**Expected Outcomes:**
- Fundo com gradiente orgânico.
- Card centralizado com campos e botão full-width.
- Responsivo em 320px.
- Lógica PHP intacta.

**Todo:**
1. Ler `login.php` completo.
2. Substituir `<style>` inline por classes Bootstrap + variáveis CSS do sistema.
3. Incluir `style.css` e `agricultura-familiar.css`.
4. Card com `border-radius: 16px` + nome/logo do sistema.
5. Manter lógica de etapa 1 e etapa 2.

**Contexto Relevante:**
- `login.php`
- `assets/css/agricultura-familiar.css`

**Status:** [x] concluído

---

## Sub-Tarefa 8 — SW e Sincronização: Toast e Background Sync

**Intent:** Melhorar notificações de sync com toast Bootstrap e adicionar Background Sync API.

**Expected Outcomes:**
- Toast Bootstrap substitui alert div no sync.
- Background Sync registra tag `'sync-forms'`.
- `sw.js` responde ao evento `sync`.
- Fallback se API não disponível.

**Todo:**
1. Substituir `showNotification()` por toast Bootstrap 5 em `db_sync.js`.
2. Registrar `sync.register('sync-forms')` após salvar offline.
3. Adicionar `self.addEventListener('sync', ...)` em `sw.js`.
4. Verificação de compatibilidade `if ('SyncManager' in window)`.

**Contexto Relevante:**
- `assets/js/db_sync.js` linhas 107-147 e 246-258
- `sw.js` linhas 1-42

**Status:** [x] concluído

---

## Ordem de Implementação

```
Sprint 1 — Fundação (sem risco de quebra):
  Sub-Tarefa 1 (CSS global — variáveis e safe area)
  Sub-Tarefa 4 (Header — meta tags PWA)
  Sub-Tarefa 5 (manifest.json — completar)

Sprint 2 — Navegação Mobile (alto impacto visual):
  Sub-Tarefa 2 (Bottom Nav — HTML no menu.php)
  Sub-Tarefa 3 (FAB global — footer.php + listagens)
  Sub-Tarefa 7 (Login — redesign mobile-first)

Sprint 3 — Listagens e PWA (refinamento):
  Sub-Tarefa 6 (DataTables responsive)
  Sub-Tarefa 8 (SW + Sync toast + Background Sync)
```

---

## Dependências de Assets

- `assets/img/icon-192.png` e `assets/img/icon-512.png` — necessários para PWA instalável.
- Logo do sistema — necessário para redesign do login.

---

## Fora de Escopo

- Gestos de swipe — complexidade alta, baixo impacto imediato.
- Acessibilidade completa WCAG — trabalho independente.
- Push notifications — marcado como futuro no TODO-mobile.
- Todos os 13 formulários de CRUD — apenas listagens prioritárias tratadas aqui.
