# PRD - Product Requirements Document

## SINPORG - Sistema Integrado de Produção Orgânica

**Versão:** 2.0  
**Data:** 20/07/2026  
**Autor:** Análise Técnica  
**Status:** Proposta  

---

## 1. Resumo Executivo

O SINPORG é um sistema SaaS para gestão de produção orgânica, certificação participativa (SPG/OCS), caderno de campo digital, rastreabilidade e visitas técnicas. Atualmente em versão monolítica PHP, o sistema possui ~100 arquivos, 23 classes, 11 módulos e atende agricultores familiares, associações e certificadoras.

Esta análise identificou **9 vulnerabilidades de segurança**, **15 problemas arquiteturais**, **8 bugs de tipagem/banco** e **12 lacunas funcionais** que precisam ser endereçadas.

---

## 2. Estado Atual do Sistema

### 2.1 Stack Tecnológica

| Componente | Tecnologia |
|---|---|
| Backend | PHP 7.4+ (vanilla, sem framework) |
| Database | MySQL 5.7+ (InnoDB, utf8mb4) |
| Frontend | Bootstrap 5.3, jQuery, DataTables |
| Templates | PHP embutido em HTML |
| PDF | mPDF 8.x |
| Planilhas | PhpSpreadsheet 5.x |
| PWA | Service Worker + Manifest |

### 2.2 Arquitetura Atual (Problemas)

```
index.php (roteador manual de 500+ linhas)
├── config/config.php
├── classes/ (23 classes - Database, CRUDs, Utilitários)
├── modulos/ (11 módulos - cada um com listar/cadastrar/editar)
├── api/ (endpoints REST-like)
├── includes/ (header, footer, menu, widgets)
└── relatorios.php (dashboard + exportação)
```

**Principais problemas arquiteturais:**
- Sem framework: roteador manual, sem autoloading, sem ORM
- Lógica de negócio misturada com apresentação (PHP inline no HTML)
- Código CRUD duplicado em 10+ classes (Associado, Insumo, Produto, etc.)
- Sem camada Service, Repository ou DTO
- SQL Injection potencial em `User::getCurrentUser()` (concatenação de tabela)
- Layout e CSS inline/duplicado em múltiplos arquivos

---

## 3. Bugs Identificados

### 3.1 Críticos

| ID | Descrição | Arquivo | Risco |
|---|---|---|---|
| B-01 | SQL Injection: `User::getCurrentUser()` concatena `$_SESSION['user_table']` diretamente na query | `classes/User.php:83` | ALTO - Modificação de session pode executar SQL arbitrário |
| B-02 | Path Injection: `api/relatorios/$tipo.php` usa `$parts[2]` sem sanitização para incluir arquivo | `index.php:29` | ALTO - LFI permite execução remota |
| B-03 | Debug ativo em produção: `ini_set('display_errors', 1)` no relatorios.php | `relatorios.php:3-5` | MÉDIO - Vazamento de informação |
| B-04 | UUID não criptográfico: `mt_rand()` não é seguro para UUID v4 | `classes/*.php` (10 classes) | MÉDIO - UUIDs previsíveis |

### 3.2 Funcionais

| ID | Descrição | Arquivo |
|---|---|---|
| B-05 | Typo coluna `insumo_utilisado` (falta 'z') no INSERT | `classes/AtividadeDiaria.php:101` |
| B-06 | Typo coluna `categotia_id` mencionado em comentários/schema | `sql/*.sql` |
| B-07 | Route `/associacao/editar` lê `$_POST['id']` em GET route | `index.php:591` |
| B-08 | `select_justificativa` dinâmico sem usar prepared statement para o nome da coluna | `classes/Evento.php:160-162` |
| B-09 | Route `/api/relatorios` não autentica antes de servir o arquivo | `index.php:27-38` |
| B-10 | `Propriedade::update()` não verifica se registro existe antes de atualizar | `classes/Propriedade.php:128` |
| B-11 | Missing `exit` após `header()` em `header.php:9` | `includes/header.php:9` |
| B-12 | Session sem HttpOnly/Secure/SameSite configurados | Não configurado em `config.php` |

---

## 4. Melhorias Propostas

### 4.1 Sprint 1 - Segurança e Estabilidade (Prioridade Máxima)

#### 4.1.1 Correção de SQL Injection (B-01)
- Substituir concatenação direta por whitelist de tabelas permitidas
- Validar `user_table` contra valores conhecidos

#### 4.1.2 Correção de Path Injection (B-02)
- Whitelist de arquivos permitidos para inclusão
- Validar contra regex de segurança antes de incluir

#### 4.1.3 CSRF Generalizado
- Implementar `CSRFProtection::validatePostRequest()` em TODOS os formulários POST
- Adicionar token automático via middleware em todas as páginas administrativas
- Cobertura: login, cadastro, edição, exclusão, upload

#### 4.1.4 Configuração de Sessão Segura
```php
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,    // apenas HTTPS
    'httponly' => true,  // inacessível via JS
    'samesite' => 'Strict'
]);
```

#### 4.1.5 Desativar Debug em Produção
- Tornar `display_errors` configurável via ambiente
- Criar arquivo `.env` ou usar `config.php` com flag `APP_DEBUG`

#### 4.1.6 Remoção de UUIDs Inseguros
- Substituir `mt_rand()` por `random_bytes()` + UUID v4 validator
- Ou usar UUID v4 nativo do MySQL 8 (`UUID()`)

### 4.2 Sprint 2 - Qualidade de Código e Refatoração

#### 4.2.1 Autoloading PSR-4
- Implementar `spl_autoload_register()` ou Composer autoload
- Namespace: `SINPORG\Classes`, `SINPORG\Controllers`
- Remover `require_once` manuais

#### 4.2.2 Repositório Base (DRY)
Criar `BaseRepository` com métodos genéricos:

```php
abstract class BaseRepository {
    protected $db;
    protected $table;
    public function getAll(): array;
    public function getById(int $id): ?array;
    public function create(array $data): int|false;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
```

Herança:
- `AssociadoRepository extends BaseRepository`
- `InsumoRepository extends BaseRepository`
- `ProdutoRepository extends BaseRepository`
- etc.

Isso elimina ~70% de código duplicado (10 classes CRUD viram ~30 linhas cada).

#### 4.2.3 Service Layer
Separar regras de negócio dos repositórios:

- `AssociadoService` - regras de validação de CPF, email único, status
- `CertificacaoService` - cálculo de prazos, regras de renovação
- `VisitaService` - cálculo de pontuação, geração de NCs
- `RelatorioService` - orquestração de dados para exportação

#### 4.2.4 Refatoração do Roteador
Substituir o roteador de 500+ linhas (`index.php`) por:

```php
$router->get('/associados', 'AssociadoController@index');
$router->get('/associado/{id}/editar', 'AssociadoController@edit');
$router->post('/associado/salvar', 'AssociadoController@save');
```

Ou uma solução customizada leve em `Router.php`.

#### 4.2.5 Correção de Typos no Schema
- `insumo_utilisado` → `insumo_utilizado` (com migration)
- `categotia_id` → `categoria_id` (se existir)
- Criar script de migração para renomear colunas

### 4.3 Sprint 3 - Experiência do Usuário e Funcionalidades

#### 4.3.1 Dashboard Unificado
- Substituir cards duplicados no dashboard por layout responsivo
- Widgets configuráveis por papel (admin/gestor/agricultor)
- Gráficos reais (Chart.js) nos relatórios

#### 4.3.2 Sistema de Notificações
- Notificações in-app (bell icon)
- Templates de email para: novo cadastro, evento próximo, NC aberta
- Web Push Notifications (Service Worker existente)

#### 4.3.3 Busca Avançada e Paginação
- Search bar global no header
- Paginação server-side (DataTables já configurado, mas sem server-side)
- Filtros combinados por período, status, associado

#### 4.3.4 Upload Seguro de Imagens
- Validar MIME real do arquivo (não confiar em `$_FILES['type']`)
- Limitar dimensões máximas (já existe, mas não usa `getimagesize` corretamente)
- Scan antivírus opcional
- Nome de arquivo hash-based (remover caracteres especiais)

#### 4.3.5 Modal de Confirmação para Exclusões
- Substituir `confirm()` por modal Bootstrap confirmatório
- CSRF token incluso no modal dinâmico

### 4.4 Sprint 4 - Relatórios e Exportação

#### 4.4.1 Central de Relatórios Melhorada
- Relatórios financeiros com dados reais (não hardcoded como hoje)
- Gráficos de pizza/barras por categoria
- Comparativo entre safras

#### 4.4.2 Exportação Avançada
- PDF com layout profissional (logo, cabeçalho, rodapé, numeração)
- Excel com formatação condicional
- CSV para importação em outros sistemas

#### 4.4.3 Relatório de Rastreabilidade
- Histórico completo de: lote → produto → lavoura → atividades → insumos
- QR Code para consulta pública de lote
- Timeline visual da produção

### 4.5 Sprint 5 - DevOps e Infraestrutura

#### 4.5.1 Containerização
- Dockerfile + docker-compose (PHP, MySQL, Nginx)
- Variáveis de ambiente para configuração
- Health checks

#### 4.5.2 Logging Estruturado
- Substituir `error_log()` por Monolog ou similar
- Logs separados por canal: app, sql, auth, api
- Níveis configuráveis (debug, info, error)

#### 4.5.3 Testes Automatizados
- PHPUnit para classes de serviço e repositórios
- Testes de integração para API endpoints
- Testes de segurança (SQLi, XSS, CSRF)

#### 4.5.4 CI/CD
- GitHub Actions para lint + test + build
- Deploy automático para staging
- Migration scripts versionados

---

## 5. Roadmap

```
Sprint 1 (2 semanas)    ████████░░░░░░░░░░░░  Segurança e Estabilidade
Sprint 2 (3 semanas)    ███████████░░░░░░░░░  Refatoração e Qualidade
Sprint 3 (3 semanas)    ██████████████░░░░░░  UX e Funcionalidades
Sprint 4 (2 semanas)    ██████████████████░░  Relatórios e Exportação
Sprint 5 (2 semanas)    ████████████████████  DevOps e Infraestrutura
```

**Total estimado: 12 semanas (3 meses)**

---

## 6. Métricas de Sucesso

| Métrica | Alvo | Medição |
|---|---|---|
| Tempo de resposta (páginas CRUD) | < 500ms | Lighthouse / Chrome DevTools |
| Vulnerabilidades conhecidas | 0 | OWASP ZAP / Semgrep |
| Cobertura de testes | > 60% | PHPUnit Coverage |
| Duplicação de código | < 10% | PHPCPD / SonarQube |
| CSRF coverage | 100% das rotas POST | Auditoria manual |
| Uptime | 99.9% | Monitoração |

---

## 7. Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Refatoração quebrar funcionalidades existentes | Média | Alto | Testes E2E antes da refatoração |
| Migração de colunas com typo perder dados | Baixa | Alto | Backup + script de migração testado |
| Resistência da equipe a novas práticas | Média | Médio | Treinamento e code review gradual |
| Aumento de complexidade com camadas | Média | Baixo | Manter simplicidade, evitar over-engineering |
| Dados sensíveis em logs por engano | Baixa | Alto | Revisão de logging + Sanitizer de dados |

---

## 8. Apêndice

### 8.1 Arquivos Impactados por Sprint

**Sprint 1:** `User.php`, `index.php`, `config.php`, `CSRFProtection.php`, `login.php`, `relatorios.php`, todas as views com forms

**Sprint 2:** Todas as classes em `classes/`, `index.php`, `composer.json`, novas pastas `src/Repository/`, `src/Service/`, `src/Router/`

**Sprint 3:** `modulos/caderno_campo/`, `assets/js/`, `assets/css/`, novos controllers

**Sprint 4:** `Relatorio.php`, `ReportManager.php`, `relatorios.php`, `relatorios/`

**Sprint 5:** `Dockerfile`, `docker-compose.yml`, `.github/workflows/`, `phpunit.xml`

### 8.2 Diagrama de Arquitetura Alvo

```
src/
├── Controller/
│   ├── AssociadoController.php
│   ├── EventoController.php
│   ├── CadernoCampoController.php
│   └── ApiController.php
├── Service/
│   ├── CertificacaoService.php
│   ├── VisitaService.php
│   └── RelatorioService.php
├── Repository/
│   ├── BaseRepository.php
│   ├── AssociadoRepository.php
│   └── ...
├── Model/
│   ├── Associado.php (DTO)
│   ├── Evento.php (DTO)
│   └── ...
├── Security/
│   ├── CSRFProtection.php
│   ├── SessionManager.php
│   └── AccessControl.php
├── Router/
│   └── Router.php
├── Middleware/
│   ├── AuthMiddleware.php
│   └── CsrfMiddleware.php
└── Utils/
    ├── Sanitizer.php
    ├── ImageUploader.php
    └── UUIDGenerator.php
```

### 8.3 Schema de Segurança Recomendado

```sql
-- Nova tabela de audit logging
CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(50) NOT NULL,       -- login, create, update, delete, export
  entity_type VARCHAR(50) NOT NULL,   -- associado, evento, insumo
  entity_id INT,
  ip_address VARCHAR(45),
  user_agent TEXT,
  old_values JSON,
  new_values JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

*Documento gerado com base em análise de código-fonte completa do sistema SINPORG v2.0.*
