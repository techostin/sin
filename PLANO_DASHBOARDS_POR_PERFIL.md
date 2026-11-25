# Plano: Dashboards por Perfil de Usuário

> Proposta: o sistema passará a entregar **um dashboard específico para cada perfil**, cada um com front-end, atalhos e relatórios restritos ao escopo de interação do usuário com o sistema.

---

## 1. Perfis e Níveis

| Perfil | Nível | `tipo_usuario` | Tabela | Posto atual |
|--------|-------|----------------|--------|-------------|
| **Admin** | `NIVEL_ADMIN` (1) | — | `usuarios` | Dashboard genérico em `index.php` |
| **Gestor / Técnico** | `NIVEL_GESTOR` (2) | — | `usuarios` | Dashboard genérico em `index.php` |
| **Associado — Agricultor** | `NIVEL_USUARIO` (3) | `agricultor` | `associados` | `dashboard_agricultor.php` ✅ |
| **Associado — Produtor de Agroindústria** | `NIVEL_USUARIO` (3) | `produtor_agroindustria` | `associados` | **Inexistente** ⚠️ |

**Decisão:** rotas separadas + um **dispatch central** em `index.php` que redireciona cada usuário ao dashboard do seu perfil ao logar/acessar a raiz.

---

## 2. Arquitetura do Dispatch

Criar 4 arquivos sob `modulos/`:

```
modulos/
  dashboard/
    dispatch.php        ← lê o perfil e redireciona (raiz `/` e pós-login)
    dashboard_admin.php         ← Nível 1
    dashboard_gestor.php        # Nível 2 (técnico/fiscal)
    dashboard_agricultor.php    # Nível 3 + tipo_usuario = agricultor
    dashboard_agroindustria.php # Nível 3 + tipo_usuario = produtor_agroindustria
```

**Regras de roteamento** (função em `classes/User.php` ou helper `includes/perfil.php`):

```php
function perfil_dashboard(): string {
    $nivel   = (int)($_SESSION['user_nivel'] ?? 3);
    $tipo    = $_SESSION['user_tipo_usuario'] ?? $_SESSION['user_tipo'] ?? '';
    if ($nivel === NIVEL_ADMIN)  return 'admin';
    if ($nivel === NIVEL_GESTOR) return 'gestor';
    // associado
    return strtolower($tipo) === 'produtor_agroindustria' ? 'agroindustria' : 'agricultor';
}
```

**Integrações no dispatch**:
- `index.php:893` — hoje redireciona **todo** usuário `NIVEL_USUARIO` para `dashboard-agricultor`. Passa a usar `perfil():ivel()` e redireciona agroindústria para o novo dashboard.
- `includes/menu.php:12` — o link "Início" usa o mesmo `perfil_dashboard()`.
- `login.php` — após login, `header()` de destino usa o dispatch (raiz `/`).

---

## 3. Escopo de cada dashboard (front-end + relatórios)

### 3.1 — Dashboard Admin (Nível 1)
De código central: **gestão total + fiscalização**.
- Widgets: pendentes de ativação, associados ativos, associações, usuários, contas, certificações pendentes, plano de manejo em análise.
- Acesso total a **todos** os módulos e menus (Fiscalização, Usuários, Contas, Associados, Relatórios Admin).
- Relatórios: **todos** — administrativos + conformidade + auditoria + caderno de campo (visão completa).

### 3.2 — Dashboard Gestor / Técnico (Nível 2)
Foco: **fiscalização, inspeção e acompanhamento técnico**.
- Indicadores: certificações/ativas, planos em análise, visitas agendadas, não conformidades abertas, validação de insumos, produtividade por técnico (já existe `api/relatorios/produtividade.php`).
- Relatórios: administrativos + fiscalização/conformidade + auditoria. Relatórios do caderno de campo de **todos** os produtores.

### 3.3 — Dashboard Agricultor (Nível 3 + `agricultor`)
Reutilizar **`dashboard_agricultor.php`** (já existe e está sólido).
- Indicadores já implementados: atividades/insumos/ocorrências, status e validade da certificação, próxima visita, NCs abertas, conformidade-score, insumos em falta.
- Relatórios: apenas **vinculados à própria propriedade/atividades** (escopo pessoal) — já implementado no modal "Acessos a Relatórios".

### 3.4 — Dashboard Produtor de Agroindústria (Nível 3 + `produtor_agroindustria`) ⚠️ **Novo**
Foco em **transformação/processamento e rastreabilidade**.
- Indicadores a incluir:
  - Ordens de produção (ativas, concluídas, em andamento);
  - Balanço de massas (entrada × saída por ordem);
  - Receitas cadastradas;
  - Higienização (livro de registros);
  - Rastreabilidade do lote final até a origem;
  - Certificações/validade do que foi processado.
- Relatórios de conformidade pertinentes à agroindústria (balanço de massas, rastreabilidade, livro de higienização, manejo animal se houver).
- No futuro, refinar em sub-perfis: **produtor de agroindústria** vs **manejo animal**.

---

## 3. Ajuste no menu lateral (`includes/menu.php`)
- O menu deve mostrar **apenas** os vínculos do perfil:
  - Agroindústria: submenu "Agroindústria" + relatórios de agroindústria.
  - Agricultor: submenu "Produção Orgânica" + caderno de campo (registro individual).
  - Gestor/Admin: bloco "Fiscalização & Conformidade" (será mantido).
- Reformar o modal **"Acessos a Relatórios"** para usar o perfil (hoje só diferencia `gestor` vs `não-gestor`).

---

## 4. Faseamento

### Fase 1 — Infraestrutura de dispatch
- [ ] Adicionar `tipo_usuario`/perfil na sessão ao login (`User::login`) e em `getCurrentUser`.
- [ ] Criar helper `perfil_dashboard()` em `classes/User.php`.
- [ ] Criar pasta `modulos/dashboard/` + `dispatch` e os 4 arquivos de dashboard.
- [ ] Atualizar `index.php` (antigo redirect), `includes/menu.php` e `login.php`.
- [ ] Nenhum erro em `php -l` nos arquivos alterados.

### Fase 2 — Dashboard Admin e Gestor
- [ ] Extrair dashboards existentes de `index.php` + `dashboard.php` para `dashboard_admin.php` e `dashboard_gestor.php`.
- [ ] Adicionar cards/indicadores de fiscalização para Gestor.

### Fase 3 — Agroindústria (nova)
- [ ] `dashboard_agroindustria.php`: KPIs de ordens, massas, receitas, higienização, rastreabilidade.
- [ ] Menus/relatórios de agroindústria restritos ao perfil.
- [ ] Revisar `ReportManager` para escopo por perfil (agroindústria).

### Fase 4 — Testes e finalização
- [ ] Teste por perfil (Admin, Gestor, Agricultor, Produtor Agroindústria) para acesso a dashboard e relatórios.
- [ ] Atualizar `REPORT.md`/documentação.

---

## 5. Arquivos impactados
- `classes/User.php` (helper de perfil + login)
- `index.php` (dispatch e redirect)
- `includes/menu.php` (menu por perfil)
- `login.php` (redirect pós-login)
- **Novos:** `modulos/dashboard/dashboard_admin.php`, `dashboard_gestor.php`, `dashboard_agroindustria.php`
- **Reutilizado:** `dashboard_agricultor.php`
- Relatórios/API: conforme fase 3 (agroindústria)

---

## Status
- [x] Modelo de perfis mapeado
- [ ] Fase 1 — dispatch
- [ ] Fase 2 — Admin/Gestor
- [ ] Fase 3 — Agroindústria
- [ ] Fase 4 — Testes/documentação