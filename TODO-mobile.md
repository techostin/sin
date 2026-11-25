# Plano de Implementação Mobile + Web Responsivo - SINPORG

## Filosofia: Um App, Duas Experiências

O SINPORG deve ser **totalmente funcional tanto no desktop (gestores/admin) quanto no mobile (agricultores/campo)**.  
A abordagem é **Mobile-First com Progressive Enhancement**: o layout base funciona perfeitamente em telas pequenas e é progressivamente melhorado para telas grandes.

---

## Diagnóstico Atual

**Stack:** Bootstrap 5.3.3 + PHP (sem framework) + MySQL

### ✅ Já existe (bom ponto de partida)
- ✅ PWA: `manifest.json` + `sw.js` (service worker com cache offline)
- ✅ `viewport` meta tag no `header.php`
- ✅ FAB (Floating Action Button) no `dashboard_agricultor.php`
- ✅ DataTables com plugin `responsive` em algumas páginas
- ✅ CSS `agricultura-familiar.css` com `--touch-target: 48px`
- ✅ CSS `table-responsive-stack` para tabelas em mobile
- ✅ Media queries parciais em `style.css` e `agricultura-familiar.css`
- ✅ Motor de sincronização offline (`assets/js/db_sync.js`)
- ✅ Indicador de conexão no sidebar
- ✅ `composer.json` para dependências

### ❌ Problemas identificados
- ❌ **Sidebar fixa de 280px** → impraticável em mobile (precisa de bottom nav)
- ❌ **Navbar não tem breadcrumb/indicador de página atual** → desorientação em mobile
- ❌ **DataTables inconsistentes**: algumas páginas usam `responsive`, outras não
- ❌ **Apenas `dashboard_agricultor.php` tem FAB** → CRUDs sem atalho rápido
- ❌ **Login não usa tema do sistema** → standalone com CSS inline
- ❌ **Formulários sem otimização mobile** → inputs agrupados, labels longos
- ❌ **Modal de eventos não adaptado para telas pequenas**
- ❌ **`completar_perfil.php` é standalone** → sem menu/header/footer
- ❌ **Sem bottom navigation** → navegação mobile difícil
- ❌ **CSS global sem breakpoints consistentes** → mistura de abordagens

---

## Estratégia de Layout: Dual-Mode

```
DESKTOP (≥992px):                     MOBILE (<992px):
┌──────────┬──────────────────┐      ┌──────────────────────┐
│          │   Top Navbar      │      │   Top Bar (simples)  │
│  Sidebar │   (breadcrumb)    │      │   + Page Title       │
│  (280px) │                   │      ├──────────────────────┤
│          │   Main Content    │      │                      │
│  Menu    │   (cards, tables, │      │   Main Content       │
│  (dark)  │    forms)         │      │   (full width)       │
│          │                   │      │                      │
│          │                   │      ├──────────────────────┤
└──────────┴──────────────────┘      │   Bottom Nav (5 itens)│
                                     └──────────────────────┘
```

---

## Fase 1: CSS Global Mobile-First (agricultura-familiar.css + style.css)

### 1.1 - Variáveis CSS e Reset Mobile
**Arquivo: `assets/css/agricultura-familiar.css`**
- [ ] Adicionar variáveis para breakpoints, spacing mobile, safe-area
- [ ] `html { -webkit-text-size-adjust: 100%; }` para evitar zoom em inputs
- [ ] Safe area insets para iOS (notch, home indicator):
  ```css
  :root {
    --sat: env(safe-area-inset-top);
    --sar: env(safe-area-inset-right);
    --sab: env(safe-area-inset-bottom);
    --sal: env(safe-area-inset-left);
    --bottom-nav-height: 64px;
    --top-bar-height: 56px;
    --sidebar-width: 280px;
  }
  ```

### 1.2 - Bottom Navigation (Mobile)
**Arquivo: `assets/css/agricultura-familiar.css`**
- [ ] `.bottom-nav` bar fixa inferior com 5 ícones
- [ ] Suporte a `padding-bottom: calc(var(--bottom-nav-height) + var(--sab))`
- [ ] Esconder sidebar em mobile, mostrar bottom nav
- [ ] Transições suaves entre abas

### 1.3 - Melhorias no Sidebar (Desktop)
**Arquivo: `assets/css/style.css`**
- [ ] Backdrop overlay para sidebar em mobile (<992px)
- [ ] Sidebar com posição `fixed` e `z-index` apropriado
- [ ] Transição melhorada com `cubic-bezier`
- [ ] Scroll suave com `overscroll-behavior: contain`

### 1.4 - Table Responsive Stack Global
**Arquivo: `assets/css/style.css`**
- [ ] Fazer `.table-responsive-stack` ser o padrão em todas as tabelas em <768px
- [ ] Adicionar variante `table-responsive-stack-sm` para telas muito pequenas
- [ ] Animações de expansão de linha (accordion-style para mobile)

### 1.5 - Global FAB Pattern
**Arquivo: `assets/css/agricultura-familiar.css`**
- [ ] `.fab-global` - FAB fixo para ações rápidas
- [ ] Esconder FAB em desktop (onde o menu já tem atalhos)
- [ ] Mostrar FAB em mobile em páginas de listagem
- [ ] Menu contextual expansível (como no dashboard_agricultor)

### 1.6 - Form Mobile Optimization
**Arquivo: `assets/css/agricultura-familiar.css`**
- [ ] Labels flutuantes ou acima do campo (nunca ao lado)
- [ ] Input groups verticais
- [ ] Botões full-width em mobile
- [ ] Espaçamento vertical maior entre campos (`mb-3` → `mb-4`)

### 1.7 - Desktop Enhancement
**Arquivo: `assets/css/style.css`**
- [ ] Max-width para conteúdo (`max-width: 1600px`) em telas muito largas
- [ ] Grid de cards adaptável (2 colunas → 3 → 4 conforme largura)
- [ ] Melhor uso de `col-lg-*` e `col-xl-*` para gestores com monitores grandes

---

## Fase 2: Core Layout - Header / Footer / Menu

### 2.1 - `includes/menu.php` (Sidebar + Bottom Nav)
- [ ] Adicionar **Bottom Navigation Bar** fixa (visível apenas em mobile <992px):
  - Ícone 1: Início (dashboard)
  - Ícone 2: Caderno de Campo (com badge de pendências)
  - Ícone 3: Certificações
  - Ícone 4: Eventos  
  - Ícone 5: Mais (abre menu expandido / sidebar)
- [ ] No desktop (≥992px): sidebar normal com os submenus collapse atuais
- [ ] **Botão "Voltar"** no topo em páginas internas (mobile)
- [ ] **Indicador de página ativa** no bottom nav
- [ ] Botão "Voltar ao topo" flutuante
- [ ] Badge de sincronização offline no bottom nav
- [ ] Swipe gesture: `touchstart`/`touchend` para abrir sidebar (mobile)
- [ ] Adicionar backdrop overlay escuro quando sidebar estiver aberta (mobile)

### 2.2 - `includes/header.php`
- [ ] Adicionar detecção de dispositivo via JS (`window.innerWidth`)
- [ ] Adicionar CSS condicional para bottom nav
- [ ] Adicionar meta tag `theme-color` para PWA (usar cor orgânica)
- [ ] Adicionar `<link rel="apple-touch-icon" href="...">` para iOS
- [ ] Adicionar splash screen config para PWA
- [ ] **Offline indicator** no topo da página (não apenas no sidebar)
- [ ] Exportar `SITE_URL` e `USER_NIVEL` como variáveis JS globais

### 2.3 - `includes/footer.php`
- [ ] Inicializar Bottom Nav via JS (itens dinâmicos baseados no user role)
- [ ] Inicializar swipe listener para sidebar
- [ ] Adicionar debounce no resize para trocar entre layouts desktop/mobile
- [ ] Inicializar FAB global (quando aplicável)
- [ ] Adicionar "Toast Container" para notificações offline

---

## Fase 3: Páginas Específicas - Mobile-First

### 3.1 - Login (`login.php`)
- [ ] **Redesign completo** para usar o tema visual do sistema:
  - Fundo com gradiente orgânico (verde escuro → verde claro)
  - Logo do sistema
  - Card com sombra e bordas arredondadas
  - Versão/ano do sistema no rodapé
- [ ] Formulário otimizado para mobile (um campo por linha, botão full-width)
- [ ] Etapa 2 (gestor) com steps horizontais adaptados
- [ ] Suporte a "login biométrico" via WebAuthn (futuro)
- [ ] Lembrar de mim com checkbox grande (fácil toque)

### 3.2 - Index / Dashboard (`index.php`)
- [ ] Cards responsivos: `col-12` em mobile, `col-md-4` em desktop
- [ ] Grid adaptável com `row-cols-1 row-cols-md-2 row-cols-lg-3`
- [ ] Tabela de eventos com `table-responsive-stack`
- [ ] Botões de ação com alvos de toque ≥48px
- [ ] Esconder cards administrativos para usuário "agricultor"

### 3.3 - Dashboard Agricultor (`modulos/caderno_campo/dashboard_agricultor.php`)
- [ ] **Já está bem otimizado** (FAB, cards responsivos, gradientes)
- [ ] Melhorias: garantir que `table-responsive` seja aplicado na tabela de atividades
- [ ] Adicionar botão "Sincronizar agora" visível em mobile
- [ ] Melhorar contraste dos cards de ação rápida em telas muito pequenas

### 3.4 - Listagens com DataTables (TODAS as páginas)
- [ ] Garantir que **todas** usem `responsive: true` no DataTables
- [ ] Adicionar classe `table-responsive-stack` consistentemente
- [ ] Ocultar colunas desnecessárias em mobile com `columnDefs.responsivePriority`
- [ ] Botões de ação em formato de dropdown ou ícones maiores em mobile
- [ ] Filtros colapsáveis em mobile (acordeão)
- [ ] **Páginas a revisar**:
  - [ ] `modulos/associados/listar.php`
  - [ ] `modulos/certificacao/listar.php`
  - [ ] `modulos/caderno_campo/ocorrencias/listar.php`
  - [ ] `modulos/caderno_campo/propriedades/listar.php`
  - [ ] `modulos/caderno_campo/insumos/listar.php`
  - [ ] `modulos/caderno_campo/lavouras/listar.php`
  - [ ] `modulos/caderno_campo/atividade_diaria/listar.php`
  - [ ] `modulos/caderno_campo/produtos/listar.php`
  - [ ] `modulos/caderno_campo/lotes_producao/listar.php`
  - [ ] `modulos/eventos/listar.php`
  - [ ] `modulos/fornecedor/listar.php`
  - [ ] `modulos/roteiros_inspecao/visitas/listar.php`
  - [ ] `modulos/roteiros_inspecao/templates/listar.php`

### 3.5 - Formulários (CRUD)
- [ ] Layout de coluna única em mobile (`col-12`)
- [ ] Labels grandes e visíveis
- [ ] Botões de submit/voltar full-width em mobile
- [ ] Validação inline amigável
- [ ] **Páginas a revisar**:
  - [ ] `modulos/certificacao/cadastrar.php`
  - [ ] `modulos/caderno_campo/ocorrencias/cadastrar.php`
  - [ ] `modulos/caderno_campo/propriedades/cadastrar.php`
  - [ ] `modulos/caderno_campo/lavouras/cadastrar.php`
  - [ ] `modulos/caderno_campo/insumos/cadastrar.php`
  - [ ] `modulos/caderno_campo/produtos/cadastrar.php`
  - [ ] `modulos/caderno_campo/atividade_diaria/cadastrar.php`
  - [ ] `modulos/associados/cadastrar.php`
  - [ ] `modulos/eventos/cadastrar.php`
  - [ ] `modulos/plano_manejo/cadastrar.php`

### 3.6 - Páginas Especiais
- [ ] `completar_perfil.php`: Integrar ao tema do sistema (não standalone)
- [ ] `modulos/usuarios/configuracoes.php`: Layout responsivo com abas
- [ ] `modulos/roteiros_inspecao/visitas/realizar.php`: Interface de checklist touch-friendly
- [ ] `modulos/roteiros_inspecao/visitas/relatorio.php`: PDF preview responsivo

### 3.7 - Modais
- [ ] Modal de eventos: full-screen em mobile (<576px)
- [ ] Modal de detalhes de ocorrência: scroll suave
- [ ] Modal de correção: textarea grande
- [ ] Swipe-to-close em modais (mobile)

---

## Fase 4: PWA & Offline

### 4.1 - Service Worker (`sw.js`)
- [ ] Estratégia de cache melhorada:
  - **Stale-while-revalidate** para páginas dinâmicas
  - **Cache-first** para assets estáticos (já implementado)
  - **Network-only** para POST/PUT/DELETE
- [ ] Background Sync para envio de formulários offline:
  ```js
  self.addEventListener('sync', event => {
    if (event.tag === 'sync-forms') {
      event.waitUntil(syncForms());
    }
  });
  ```
- [ ] Push notifications para lembretes de atividades (futuro)

### 4.2 - Manifest (`manifest.json`)
- [ ] Adicionar screenshots para instalação:
  ```json
  "screenshots": [
    { "src": "screenshots/desktop.png", "sizes": "1280x800", "form_factor": "wide" },
    { "src": "screenshots/mobile.png", "sizes": "750x1334", "form_factor": "narrow" }
  ]
  ```
- [ ] Categorias: `"agriculture", "productivity", "farming"`
- [ ] Ícones em mais tamanhos: 48, 72, 96, 128, 192, 512
- [ ] Shortcuts para ações rápidas:
  ```json
  "shortcuts": [
    { "name": "Nova Atividade", "url": "/atividade-diaria/novo", "icons": [...] },
    { "name": "Nova Ocorrência", "url": "/caderno-campo/ocorrencia/novo", "icons": [...] }
  ]
  ```

### 4.3 - Sincronização (`assets/js/db_sync.js`)
- [ ] Indicador visual de sincronização em tempo real (já existe, mas melhorar)
- [ ] Toast notifications para status de sync (com progresso)
- [ ] Fila de sincronização persistente (IndexedDB)
- [ ] Botão "Forçar Sincronização" em todas as páginas
- [ ] Badge no bottom nav com contagem de pendências

---

## Fase 5: Touch & UX Mobile

### 5.1 - Gestos
- [ ] Swipe right para abrir sidebar (mobile)
- [ ] Pull-to-refresh em listagens (usar `overscroll-behavior` + JS)
- [ ] Long-press para ações rápidas em cards (context menu)
- [ ] Double-tap para voltar ao topo

### 5.2 - Botões Flutuantes (FAB)
- [ ] **Implementar FAB global** em todas as páginas de listagem
- [ ] FAB contextual: mostra ações relevantes para a página atual
- [ ] FAB com menu radial (como no dashboard_agricultor)
- [ ] Esconder FAB ao scrollar para baixo, mostrar ao scrollar para cima

### 5.3 - Teclado e Input
- [ ] `inputmode` correto para números, telefone, data
- [ ] `autocomplete` preenchido corretamente
- [ ] Evitar zoom em inputs (font-size ≥16px)
- [ ] Scroll automático para o campo ativo (evitar teclado cobrindo campo)

### 5.4 - Navegação
- [ ] Breadcrumb visível no topo (mobile e desktop)
- [ ] Botão "Voltar" (←) na top bar (mobile)
- [ ] Indicador de página atual no título da página

---

## Fase 6: Performance & Acessibilidade

### 6.1 - Performance
- [ ] Lazy loading para imagens (`loading="lazy"`)
- [ ] Minificar CSS/JS em produção
- [ ] Defer para scripts não críticos
- [ ] Otimizar fontes (apenas pesos necessários)
- [ ] Usar `will-change` para animações de transição

### 6.2 - Acessibilidade (a11y)
- [ ] `aria-label` em todos os botões de ícone
- [ ] `role="navigation"` no bottom nav e sidebar
- [ ] Foco visível em todos os elementos interativos
- [ ] Contraste de cor WCAG AA (4.5:1 para texto normal)
- [ ] Suporte a `prefers-reduced-motion`

---

## Prioridades de Implementação (Roadmap)

### Sprint 1: Fundação CSS + Layout Core
1. **Fase 1.1 → 1.5**: CSS global (variáveis, bottom nav, sidebar melhorado, FAB)
2. **Fase 2.1 → 2.3**: menu.php + header.php + footer.php com dual-mode
3. **Fase 6.1**: Performance básica

### Sprint 2: Páginas Prioritárias (Mobile)
4. **Fase 3.1**: Login rediseñado
5. **Fase 3.2 → 3.3**: Dashboard + Dashboard Agricultor
6. **Fase 3.4**: Listagens (Ocorrências, Propriedades, Atividades)
7. **Fase 3.5**: Formulários (Ocorrências, Atividades, Insumos)

### Sprint 3: PWA + Offline
8. **Fase 4.1 → 4.3**: SW, Manifest, Sincronização
9. **Fase 5.1 → 5.3**: Gestos, FAB global, Input optimization
10. **Fase 5.4**: Breadcrumb e navegação

### Sprint 4: Polish + Acessibilidade
11. **Fase 3.6 → 3.7**: Páginas especiais, modais
12. **Fase 6.2**: Acessibilidade
13. Testes em dispositivos reais (Android + iOS)
14. Testes em monitores grandes (gestores)

---

## Como Detectar Desktop vs Mobile

No PHP, usar CSS classes condicionais:

```php
<?php
$isMobile = preg_match('/(android|iphone|ipad|ipod|mobile|blackberry|opera mini)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
// Mas o ideal é fazer isso no CSS/JS, não no PHP
?>
```

No CSS (recomendado):
```css
/* Mobile first: bottom nav visível, sidebar escondido */
.bottom-nav { display: flex; }
#sidebar-wrapper { display: none; }

/* Desktop: sidebar visível, bottom nav escondido */
@media (min-width: 992px) {
  .bottom-nav { display: none; }
  #sidebar-wrapper { display: flex; }
}
```

No JS:
```javascript
function isMobile() {
  return window.innerWidth < 992;
}
window.addEventListener('resize', () => {
  document.body.classList.toggle('is-mobile', isMobile());
  document.body.classList.toggle('is-desktop', !isMobile());
});
```

---

## Critérios de Aceitação

**Mobile (<768px)**:
- [ ] Bottom navigation com 5 ícones funciona
- [ ] Sidebar acessível via swipe ou botão hamburger
- [ ] FAB global presente em páginas de listagem
- [ ] DataTables convertidas para cards (table-responsive-stack)
- [ ] Formulários em coluna única, inputs grandes
- [ ] Login com tema do sistema
- [ ] Offline indicator visível
- [ ] Sincronização com um toque

**Desktop (≥992px)**:
- [ ] Sidebar completa com todos os submenus
- [ ] Bottom navigation escondida
- [ ] DataTables com visualização normal (tabelas)
- [ ] Formulários com layout de múltiplas colunas
- [ ] Max-width para conteúdo em telas ultra-wide
- [ ] Breadcrumb sempre visível
- [ ] Botões de ação inline (não em dropdown)

**Ambos**:
- [ ] PWA instalável com manifest completo
- [ ] Offline funcional para leitura de dados cacheados
- [ ] Sincronização automática quando online
- [ ] Tempo de carregamento <3s em 3G
- [ ] Todos os botões com alvo de toque ≥48px
- [ ] Contraste WCAG AA
- [ ] Rolagem suave em iOS e Android

---

## Checklist de Progresso

- [ ] **Fase 1** - CSS Global Mobile-First
  - [ ] 1.1 Variáveis + Safe Area
  - [ ] 1.2 Bottom Navigation CSS
  - [ ] 1.3 Sidebar Desktop melhorado
  - [ ] 1.4 Table Responsive Stack global
  - [ ] 1.5 FAB Pattern global
  - [ ] 1.6 Form optimization
  - [ ] 1.7 Desktop enhancement

- [ ] **Fase 2** - Core Layout
  - [ ] 2.1 menu.php (sidebar + bottom nav)
  - [ ] 2.2 header.php
  - [ ] 2.3 footer.php

- [ ] **Fase 3** - Páginas Mobile-First
  - [ ] 3.1 Login
  - [ ] 3.2 Index/Dashboard
  - [ ] 3.3 Dashboard Agricultor
  - [ ] 3.4 Listagens (13 páginas)
  - [ ] 3.5 Formulários (10 páginas)
  - [ ] 3.6 Páginas especiais
  - [ ] 3.7 Modais

- [ ] **Fase 4** - PWA & Offline
  - [ ] 4.1 Service Worker
  - [ ] 4.2 Manifest
  - [ ] 4.3 Sincronização

- [ ] **Fase 5** - Touch & UX
  - [ ] 5.1 Gestos
  - [ ] 5.2 FAB global
  - [ ] 5.3 Input optimization
  - [ ] 5.4 Navegação

- [ ] **Fase 6** - Performance & A11y
  - [ ] 6.1 Performance
  - [ ] 6.2 Acessibilidade

