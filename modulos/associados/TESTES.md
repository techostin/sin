# ✅ CHECKLIST DE TESTE FINAL (EXECUTE NA ORDEM)

> Este checklist foi atualizado para refletir a arquitetura **real** do sistema (SINPORG).
> O cadastro usa o fluxo **"Primeiro Acesso"** com ativação pelo administrador.
> Rotas amigáveis: `http://localhost/app/...` (rewrite via `index.php`).

---

## 🧪 Teste 1: Cadastro (Primeiro Acesso)

1. Acesse `http://localhost/app/login`
2. Clique na aba **"Primeiro Acesso"** e preencha:
   - Nome: `Maria`
   - Sobrenome: `Agricultora`
   - E-mail: `teste.agricultor@sinporg.org`
   - Senha: `SenhaForte123!`
   - Confirmar senha: `SenhaForte123!`
   - Tipo de Usuário: `Usuário / Agricultor`
3. ✅ Esperado: mensagem inline **"Cadastro enviado!"** + "Aguarde a ativação por um administrador".
4. ✅ Verificar BD:
   ```sql
   SELECT id, nome, sobrenome, email, senha, nivel, tipo, status, data_cadastro
   FROM associados
   WHERE email = 'teste.agricultor@sinporg.org';
   ```
   - Deve retornar **UM** registro com:
     - `status = 'pendente'`
     - `senha` no formato `$2y$...` (bcrypt)
     - `nivel = 3` (NIVEL_USUARIO) e `tipo = 'Usuario'`

---

## 🧪 Teste 1b: Login com cadastro pendente

1. Com o cadastro acima ainda `pendente`, tente `http://localhost/app/login`:
   - E-mail: `teste.agricultor@sinporg.org`
   - Senha: `SenhaForte123!`
2. ✅ Esperado: **"E-mail ou senha inválidos!"** (o login só funciona com `status = 'ativo'`).

---

## 🧪 Teste 1c: Ativação pelo administrador

1. Acesse `http://localhost/app/associados/pendentes` logado como **admin/gestor**.
2. Clique em **"Ativar"** no registro de `Maria Agricultora`.
3. ✅ Esperado: mensagem "Usuário ativado com sucesso!" e `status` passa a `'ativo'`.

---

## 🧪 Teste 2: Login válido (perfil incompleto)

1. Acesse `http://localhost/app/login`
2. E-mail: `teste.agricultor@sinporg.org` — Senha: `SenhaForte123!`
3. ✅ Esperado (primeiro acesso, dados de perfil vazios): redirecionamento para
   `http://localhost/app/meu-perfil` (para completar telefone/CPF/nicho).
4. ✅ Verificar sessão (ex.: `debug_sessao.php` com `<?php session_start(); var_dump($_SESSION); ?>`):
   ```php
   array(6) {
     ["user_id"]=> int(N)
     ["user_nivel"]=> int(3)
     ["user_nome"]=> string(5) "Maria"
     ["user_tipo"]=> string(7) "Usuario"
     ["user_table"]=> string(10) "associados"
     ["mostrar_modal_eventos"]=> bool(true)
   }
   ```
5. Preencha o perfil em `/meu-perfil` (telefone, CPF e nicho) — necessário para liberar o dashboard.

---

## 🧪 Teste 3: Login válido → Dashboard do Agricultor

1. Faça login novamente com `teste.agricultor@sinporg.org` / `SenhaForte123!` (perfil já completo).
2. ✅ Esperado: redirecionamento para `http://localhost/app/caderno-campo/dashboard-agricultor`.
3. ✅ Verificar: página mostra **"Olá, Maria!"** + ações rápidas (Nova Atividade, Registrar Ocorrência, etc.).

---

## 🧪 Teste 4: Proteção de rota (gestor tentando acessar área do agricultor)

1. Cadastre/ative um usuário com `nivel = 2` (`tipo = 'Gestor'`) ou use um existente.
2. Faça login com esse gestor.
3. Acesse `http://localhost/app/caderno-campo/dashboard-agricultor`
4. ✅ Esperado: mensagem **"Acesso negado. Você não tem permissão para acessar este recurso."**
5. ✅ Verificar: nenhum conteúdo do painel do agricultor é carregado.
6. Obs.: o menu "Painel do Agricultor" também fica oculto para gestores/admins
   (`includes/menu.php`), já que o painel é exclusivo de `NIVEL_USUARIO`.

---

## 🧪 Teste 5: Atomicidade (simular falha no cadastro)

1. TEMPORARIAMENTE quebre a query de `INSERT INTO associados` em `api/pre_cadastro.php`
   (ex.: mude para `INSERT INTO associados_xyz`).
2. Tente cadastrar um usuário pela aba "Primeiro Acesso".
3. ✅ Esperado:
   - NENHUM registro inserido na tabela `associados` (verifique com
     `SELECT * FROM associados WHERE email = 'teste';`)
   - Retorno JSON com erro amigável: `{"success":false,"error":"Erro interno..."}`
4. ⚠️ IMPORTANTE: Restaure o código correto em `api/pre_cadastro.php` **IMEDIATAMENTE** após o teste.
5. Obs.: o cadastro é um único `INSERT` (transação implícita), portanto é atômico por natureza.

---

## 🧪 Teste 6: Logout seguro

1. Após login, acesse `http://localhost/app/logout`
2. ✅ Esperado: redirecionamento para `http://localhost/app/login`
3. ✅ Verificar:
   - Tentando acessar `http://localhost/app/caderno-campo/dashboard-agricultor`
     (ou qualquer página protegida) → redirecionado para `/login` (sem 404).
   - `$_SESSION` está vazia (verifique com `debug_sessao.php`).

---

## ⚠️ NOTAS CRÍTICAS PARA O AMBIENTE XAMPP/WINDOWS

1. **Caminhos nos includes**: usam `__DIR__ . '/caminho/relativo.php'` (funciona no Windows com PHP).
2. **Credenciais do DB** (`config/config.php`):
   - Atualmente `root`/senha vazia (padrão XAMPP), banco `sisagro`.
   - EM PRODUÇÃO: crie usuário dedicado com privilégios mínimos e senha forte.
3. **PHP 8.x Compatível** (testado em PHP 8.2.12):
   - `match`, `?->`/nullsafe e tipos de retorno explícitos (`: bool`, `: string|null`).
4. **Sessão e permissões**:
   - Chaves da sessão: `user_id`, `user_nivel`, `user_nome`, `user_tipo`, `user_table`.
   - Níveis: `NIVEL_ADMIN=1`, `NIVEL_GESTOR=2`, `NIVEL_USUARIO=3` (`config/config.php`).
   - Controle de acesso via `User::hasPermission($nivel)` (`classes/User.php`) e
     `(int)$_SESSION['user_nivel']`.
   - Para restringir uma rota: verifique o nível antes de renderizar (ex.: `dashboard_agricultor.php`).
5. **Logout**: `classes/User::logout()` executa `session_destroy()`; `logout.php` redireciona via `SITE_URL`.

---

## 🚀 PRÓXIMOS PASSOS RECOMENDADOS

1. **Logs de auditoria** (recomendado para PGS):
   - Criar tabela `logs_acesso` (`user_id`, `acao`, `ip`, `timestamp`).
   - Registrar login/logout, tentativas falhas e alterações críticas.
2. **Recuperação de senha**: token temporário + redefinição segura.
3. **HTTPS em produção** (mesmo em localhost para testes, usar `mkcert`).
4. **CSRF em formulários admin**: `pendentes.php` ainda não usa token CSRF nos forms de ativar/rejeitar.

---

# ✅ UPGRADE DE CONFORMIDADE ORGÂNICA — TESTES (Fases 1–3)

> Implementado sobre o MySQL `sisagro`. Migração: `php dev-utils/migrate_conformidade.php` (idempotente).

## Fase 1 — RF06 (Insumos), NFR03 (Auditoria), NFR01 (Soft Delete)

1. **Bloqueio RF06 (insumos/cadastrar.php)**: tentar cadastrar insumo fora da Portaria 52 (ex.: `Glifosato`).
   - ✅ Esperado: mensagem de bloqueio + **nenhum** registro em `insumos`.
2. **Bloqueio RF06 (atividade_diaria/cadastrar.php)**: lançar atividade com `Glifosato`.
   - ✅ Esperado: bloqueio + nenhum registro em `atividades_diarias`.
3. **Insumo permitido**: `Esterco animal curtido` → `validar_insumo.php` retorna `{"status":"success","permitido":true,...}` e a atividade é criada.
4. **NFR03**: após login/logout e criações, conferir:
   ```sql
   SELECT user_id, acao, tabela, registro_id, ip, created_at FROM audit_log ORDER BY id DESC;
   ```
   - ✅ Esperado: `LOGIN`, `LOGIN_FALHOU`, `LOGOUT`, `INSUMO_CADASTRADO`, `ATIVIDADE_CADASTRADA`, `OCORRENCIA_EXCLUIDA`, etc.
5. **NFR01 (soft delete)**: excluir atividade/insumo/lote/ocorrência.
   - ✅ Esperado: registro continua no BD com `excluido = 1` e some das listagens.

## Fase 2 — Agroindústria (RF07–RF12)

1. **RF07/RF09 — Receitas**: `/agroindustria/receitas/nova` com ingredientes 80% org + 15% org + 5% convencional.
   - ✅ Esperado: `percentual_organico_calculado = 95.00` e `selo_autorizado = PRODUTO_ORGANICO_BRASIL`.
   - Faixas: ≥95% → `PRODUTO_ORGANICO_BRASIL`; 70–94% → `INGREDIENTES_ORGANICOS`; <70% → `SEM_SELO`.
2. **RF08 — Trava 24h**: criar higienização (`/agroindustria/higienizacao/nova`) e depois uma OP com processamento **2 dias depois**.
   - ✅ Esperado: OP bloqueada com mensagem RF08 e nenhum registro em `ordens_producao`.
3. **RF10/RF11 — OP + Balanço**: OP válida com higienização nas últimas 24h, entrada 110 kg (lote do campo) → saída 100 kg.
   - ✅ Esperado: `status_auditoria = APROVADO` (divergência 9,09% ≤ 10%).
   - Divergência > 10% (ex.: saída 50 kg) → `status_auditoria = ALERTA_FRAUDE`.
4. **RF12 — QR**: `/agroindustria/rastreabilidade?lote=LOTE-X` (pública) exibe produto, receita, ingredientes e origem no campo.

## Fase 3 — UPO e Manejo Animal (RF01/RF02/RF03/RF05)

1. **RF01**: `/associado/13/editar` → campo "Tipo de Certificação" = `OCS/SPG/AUDITORIA` (requer token CSRF).
2. **RF02**: `propriedades/editar.php` → campo "Zona de Amortecimento" persiste em `propriedades.zona_amortecimento`.
3. **RF03**: `/propriedade/{id}/historico-conversao` → registrar "Pomar — goiaba" iniciado há 3 meses.
   - ✅ Esperado: cálculo automático (~21 meses restantes, perenes = 24 meses; anuais = 12 meses).
4. **RF05**: `/manejo-animal/novo` → criar animal e adicionar registros de vacinação e alimentação.
   - ✅ Esperado: registros visíveis no histórico do animal e ações em `audit_log` (`ANIMAL_CADASTRADO`, `MANEJO_REGISTRO_ADICIONADO`).
