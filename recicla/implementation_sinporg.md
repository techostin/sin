# Plano de Melhorias SINPORG v2.0 para Agricultura Familiar Orgânica

## Visão Geral

Transformar o SINPORG v2.0 em uma ferramenta completa para pequenos agricultores familiares que produzem alimentos orgânicos, garantindo conformidade com certificação participativa e facilitando a gestão diária da produção.

---

## 🔍 Análise do Projeto Atual

### Pontos Fortes Identificados
- ✅ Sistema de **Caderno de Campo** já implementado com:
  - Propriedades, lavouras, insumos, produtos
  - Atividades diárias com registro de horas
  - Lotes de produção com rastreabilidade básica
  - Relatórios em PDF/Excel
- ✅ Gestão de **Associações e Associados** estruturada
- ✅ Sistema de **Eventos** para registrar reuniões
- ✅ Roteamento amigável e dashboard funcional

### Gaps Identificados para Produção Orgânica
- ❌ **Não há módulo de Certificação Participativa** (SPG/OCS/Rede Ecovida)
- ❌ **Falta Plano de Manejo Orgânico** estruturado
- ❌ **Ausência de Roteiros de Inspeção** para visitas técnicas
- ❌ **UX não otimizada para mobile** (agricultores usam mais celular)
- ❌ **Sem validação de insumos orgânicos** aprovados
- ❌ **Falta histórico de conformidade** e não-conformidades
- ❌ **Dashboard não orientado para pequenos agricultores**

---

## 📋 Requisitos para Produção Orgânica

### 1. Certificação Participativa
- **SPG (Sistema Participativo de Garantia)**
  - Registro de membros do grupo
  - Conselho de ética
  - Declaração de compromisso
  - Histórico de avaliações
- **OCS (Organização de Controle Social)**
  - Cadastro de consumidores
  - Controle de vendas diretas

### 2. Caderno de Campo (Já existe, precisa melhorias)
- Registro diário de atividades ✅
- Insumos utilizados ✅
- **MELHORAR:** Validar se insumos são permitidos pela IN 46/2011
- **ADICIONAR:** Registros de ocorrências (pragas, doenças)

### 3. Plano de Manejo Orgânico
- Descrição da propriedade
- Croqui/mapa da área
- Planejamento de cultivos
- Manejo de solo
- Controle de pragas e doenças
- Plano de conversão (se aplicável)

### 4. Roteiros de Inspeção
- Checklist de verificação
- Registro de visitas
- Evidências (fotos, assinaturas)
- Não-conformidades
- Plano de ação corretiva

---

## > [!IMPORTANT]
## Pontos Críticos que Requerem Decisão do Usuário

### 1. Sistema de Certificação
**Decisão do Usuário:** ✅ CONFIRMADA

- ✅ **SPG (Sistema Participativo de Garantia)** - PRIORIDADE
- ✅ **OCS (Organização de Controle Social)** - Suporte futuro
- ⚪ Rede Ecovida - Será tratada como um tipo de SPG

**Implementação:**
- Schema do banco de dados suportará SPG, OCS e tipos customizados
- Interface inicial focada em SPG
- OCS será ativado via configuração (feature flag)
- Campos adicionais específicos de cada sistema serão JSON (flexível)

### 2. Modo Offline
**Decisão do Usuário:** ✅ CONFIRMADA

- ✅ **SIM** - Implementar PWA (Progressive Web App)

**Implementação:**
- Service Worker para cache offline
- IndexedDB para armazenamento local
- Sincronização automática em background
- Indicador visual de status (online/offline/sincronizando)
- Notificações push para lembretes

**Impacto no Cronograma:** +2 semanas (Sprint dedicada ao PWA)

### 3. Nível de Simplificação
**Decisão do Usuário:** ✅ CONFIRMADA

- 🟡 **INTERMEDIÁRIO** - Interface mobile-friendly com tooltips

**Implementação:**
- Formulários com validação em tempo real
- Tooltips contextuais nos campos (ícone "?")
- Mensagens de sucesso/erro claras
- Ícones SVG + texto (não apenas ícones)
- Botões grandes para touch (mínimo 44x44px)
- Linguagem clara, evitar jargões técnicos
- Tutorial opcional no primeiro acesso

**NÃO será necessário:**
- Modo guiado passo-a-passo
- Vídeos tutoriais embutidos
- Interface ultra-simplificada

---

## 🏗️ Mudanças Propostas

### FASE 1: Fundamentos (Database & Security)

#### 1.1 Database Schema - Novos Módulos

##### Tabela: `certificacao_participativa`
```sql
CREATE TABLE certificacao_participativa (
  id INT PRIMARY KEY AUTO_INCREMENT,
  associado_id INT NOT NULL,
  tipo_certificacao ENUM('SPG', 'OCS', 'Rede Ecovida') NOT NULL,
  numero_certificado VARCHAR(100),
  data_emissao DATE,
  data_validade DATE,
  status ENUM('Ativa', 'Vencida', 'Suspensa', 'Em Conversão') DEFAULT 'Em Conversão',
  organismo_certificador VARCHAR(200),
  documento_url VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE
);
```

##### Tabela: `plano_manejo`
```sql
CREATE TABLE plano_manejo (
  id INT PRIMARY KEY AUTO_INCREMENT,
  propriedade_id INT NOT NULL,
  associado_id INT NOT NULL,
  ano_safra VARCHAR(9) NOT NULL, -- Ex: 2025/2026
  area_total_ha DECIMAL(10,2),
  area_organica_ha DECIMAL(10,2),
  area_conversao_ha DECIMAL(10,2),
  descricao_solo TEXT,
  fonte_agua TEXT,
  praticas_conservacao TEXT,
  manejo_pragas TEXT,
  manejo_doencas TEXT,
  adubacao_verde TEXT,
  rotacao_culturas TEXT,
  croqui_propriedade_url VARCHAR(255),
  status ENUM('Rascunho', 'Em Análise', 'Aprovado', 'Reprovado') DEFAULT 'Rascunho',
  aprovado_por INT, -- user_id do técnico
  data_aprovacao DATE,
  observacoes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE
);
```

##### Tabela: `roteiros_inspecao`
```sql
CREATE TABLE roteiros_inspecao (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(200) NOT NULL,
  tipo ENUM('Visita Inicial', 'Visita Regular', 'Visita de Renovação', 'Visita Surpresa') NOT NULL,
  descricao TEXT,
  checklist JSON, -- Array de itens a verificar
  pontuacao_minima INT DEFAULT 70,
  ativo BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

##### Tabela: `visitas_tecnicas`
```sql
CREATE TABLE visitas_tecnicas (
  id INT PRIMARY KEY AUTO_INCREMENT,
  associado_id INT NOT NULL,
  propriedade_id INT NOT NULL,
  roteiro_id INT NOT NULL,
  data_visita DATE NOT NULL,
  hora_inicio TIME,
  hora_fim TIME,
  tecnico_responsavel_id INT, -- associado_id do técnico
  acompanhantes TEXT, -- Nomes separados por vírgula
  observacoes TEXT,
  resultado JSON, -- Respostas do checklist
  pontuacao INT,
  conformidade ENUM('Conforme', 'Não Conforme', 'Parcialmente Conforme') DEFAULT 'Conforme',
  evidencias_fotos TEXT, -- URLs separadas por vírgula
  assinatura_produtor_url VARCHAR(255),
  assinatura_tecnico_url VARCHAR(255),
  status ENUM('Agendada', 'Realizada', 'Cancelada') DEFAULT 'Agendada',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (associado_id) REFERENCES associados(id) ON DELETE CASCADE,
  FOREIGN KEY (propriedade_id) REFERENCES propriedades(id) ON DELETE CASCADE,
  FOREIGN KEY (roteiro_id) REFERENCES roteiros_inspecao(id)
);
```

##### Tabela: `nao_conformidades`
```sql
CREATE TABLE nao_conformidades (
  id INT PRIMARY KEY AUTO_INCREMENT,
  visita_tecnica_id INT NOT NULL,
  descricao TEXT NOT NULL,
  gravidade ENUM('Baixa', 'Média', 'Alta', 'Crítica') NOT NULL,
  item_checklist VARCHAR(200),
  acao_corretiva TEXT,
  prazo_correcao DATE,
  status ENUM('Aberta', 'Em Correção', 'Resolvida', 'Não Resolvida') DEFAULT 'Aberta',
  data_resolucao DATE,
  evidencia_correcao_url VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (visita_tecnica_id) REFERENCES visitas_tecnicas(id) ON DELETE CASCADE
);
```

##### Tabela: `insumos_organicos_aprovados` (Referência IN 46/2011)
```sql
CREATE TABLE insumos_organicos_aprovados (
  id INT PRIMARY KEY AUTO_INCREMENT,
  nome VARCHAR(200) NOT NULL,
  categoria ENUM('Fertilizante', 'Defensivo', 'Adjuvante', 'Sementes', 'Outros') NOT NULL,
  tipo VARCHAR(100), -- Ex: Biofertilizante, Óleo mineral, etc
  permitido BOOLEAN DEFAULT TRUE,
  restricoes TEXT,
  normativa VARCHAR(100) DEFAULT 'IN 46/2011',
  observacoes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 1.2 Melhorias na Tabela `insumos` (Existente)
```sql
ALTER TABLE insumos 
ADD COLUMN tipo_insumo ENUM('Orgânico', 'Em Conversão', 'Convencional', 'Não Permitido') DEFAULT 'Orgânico',
ADD COLUMN aprovado_organico BOOLEAN DEFAULT FALSE,
ADD COLUMN certificacao_insumo VARCHAR(200),
ADD COLUMN insumo_aprovado_id INT,
ADD FOREIGN KEY (insumo_aprovado_id) REFERENCES insumos_organicos_aprovados(id);
```

---

### FASE 2: Novos Módulos (Backend + Frontend)

#### 2.1 Módulo de Certificação Participativa
- **Location:** `modulos/certificacao/`
- **Arquivos:**
  - [listar.php](file:///c:/xampp/htdocs/app/listar.php) - Listar certificações dos associados
  - [cadastrar.php](file:///c:/xampp/htdocs/app/cadastrar.php) - Registrar nova certificação
  - `editar.php` - Editar certificação
  - `renovar.php` - Processo de renovação
  - `historico.php` - Histórico de certificações por associado

#### 2.2 Módulo de Plano de Manejo
- **Location:** `modulos/plano_manejo/`
- **Arquivos:**
  - [listar.php](file:///c:/xampp/htdocs/app/listar.php) - Listar planos de manejo
  - [cadastrar.php](file:///c:/xampp/htdocs/app/cadastrar.php) - Criar novo plano (wizard multi-etapas)
  - `editar.php` - Editar plano existente
  - `visualizar.php` - Visualizar plano completo
  - `aprovar.php` - Aprovar/reprovar plano (apenas técnicos)
  - `croqui_upload.php` - Upload de croqui da propriedade

#### 2.3 Módulo de Roteiros de Inspeção
- **Location:** `modulos/roteiros_inspecao/`
- **Arquivos:**
  - `templates/listar.php` - Templates de roteiros
  - `templates/cadastrar.php` - Criar novo roteiro
  - `visitas/listar.php` - Listar visitas técnicas
  - `visitas/agendar.php` - Agendar nova visita
  - `visitas/realizar.php` - Interface para realizar visita (checklist)
  - `visitas/relatorio.php` - Relatório da visita
  - `nao_conformidades/listar.php` - Listar NCs
  - `nao_conformidades/acompanhar.php` - Acompanhar correções

---

### FASE 3: Melhorias de UX (Mobile-First)

#### 3.1 Interface Simplificada para Agricultores
- **Tela Inicial Simplificada** (toggle entre modo Agricultor/Gestor)
  - Cards grandes com ícones claros
  - Linguagem simples e direta
  - Acesso rápido às funções mais usadas:
    - ✅ Registrar Atividade Diária
    - ✅ Consultar Calendário de Atividades
    - ✅ Ver Meu Plano de Manejo
    - ✅ Próxima Visita Técnica

#### 3.2 Formulários Mobile-Friendly
- **Melhorias nos formulários:**
  - Inputs grandes (mínimo 44x44px para touch)
  - Teclado apropriado (numérico para quantidades, data para datas)
  - Validação em tempo real
  - Salvar rascunho automaticamente
  - Upload de fotos direto da câmera

#### 3.3 PWA (Progressive Web App) - OPCIONAL
> [!WARNING]
> Requer confirmação do usuário devido à complexidade adicional

- **Funcionalidades offline:**
  - Registrar atividades sem internet
  - Sincronizar quando conectar
  - Cache de dados essenciais
  - Notificações push (lembretes de atividades)

---

### FASE 4: Dashboard e Rastreabilidade

#### 4.1 Dashboard para Agricultores
- **Indicadores principais:**
  - 📅 Atividades registradas esta semana
  - 🌱 Cultivos ativos
  - ✅ Status de conformidade orgânica
  - 📋 Próximas tarefas/visitas
  - ⚠️ Não-conformidades pendentes

#### 4.2 Rastreabilidade Aprimorada
- **QR Code para lotes de produção:**
  - Gerar QR code automaticamente
  - Consumidor escaneia e vê:
    - Origem (propriedade, agricultor)
    - Data de colheita
    - Insumos utilizados
    - Certificação orgânica
    - Foto da propriedade

---

## 🎨 Design System para Agricultura Familiar

### Princípios de Design
1. **Simplicidade:** Interface limpa, sem distrações
2. **Clareza:** Linguagem acessível, evitar jargões técnicos
3. **Mobile-First:** Otimizado para celular (70% dos agricultores usam mobile)
4. **Visual:** Ícones e cores para identificação rápida
5. **Acessibilidade:** Contraste adequado, fonte legível (mínimo 16px)

### Paleta de Cores Proposta
- **Verde Orgânico:** `#4CAF50` (principal)
- **Terra:** `#8D6E63` (secundário)
- **Amarelo Colheita:** `#FFC107` (alertas)
- **Vermelho Não-Conformidade:** `#F44336` (erros/críticos)
- **Azul Informação:** `#2196F3` (informativo)

---

## ✅ Plano de Verificação

### Testes Automatizados
```bash
# Executar após implementação do schema
php tests/DatabaseSchemaTest.php

# Validar APIs REST
php tests/Api/CertificacaoTest.php
php tests/Api/PlanoManejoTest.php
php tests/Api/RoteirosInspecaoTest.php
```

### Testes Manuais (Com Usuários Reais)

#### 1. Teste de Usabilidade com Agricultores
**Objetivo:** Verificar se a interface é compreensível para agricultores com baixa alfabetização digital.

**Passos:**
1. Recrutar 3 agricultores de diferentes idades (20-30, 40-50, 60+)
2. Pedir para realizar tarefas sem ajuda:
   - [ ] Registrar uma atividade diária no celular
   - [ ] Consultar o plano de manejo
   - [ ] Visualizar próxima visita técnica
3. Coletar feedback:
   - Dificuldades encontradas
   - Tempo para completar cada tarefa
   - Sugestões de melhoria

**Critério de Sucesso:** Pelo menos 2 dos 3 agricultores completam tarefas em menos de 3 minutos cada.

#### 2. Teste de Conformidade Orgânica
**Objetivo:** Garantir que o sistema atende requisitos de certificação.

**Passos:**
1. Criar associado fictício
2. Criar propriedade
3. Preencher plano de manejo completo
4. Agendar visita técnica
5. Realizar visita com checklist
6. Registrar não-conformidade
7. Corrigir não-conformidade
8. Gerar relatório completo

**Critério de Sucesso:** Relatório contém TODOS os campos obrigatórios da certificação.

#### 3. Teste de Rastreabilidade
**Objetivo:** Validar QR code e informações de lote.

**Passos:**
1. Criar lote de produção
2. Gerar QR code
3. Escanear com celular
4. Verificar dados exibidos:
   - [ ] Nome do produto
   - [ ] Agricultor responsável
   - [ ] Propriedade de origem
   - [ ] Data de colheita
   - [ ] Insumos utilizados
   - [ ] Status de certificação orgânica

**Critério de Sucesso:** Todas informações estão corretas e página carrega em menos de 2 segundos.

### Testes de Performance
```bash
# Lighthouse audit (após deploy)
lighthouse https://sinporg.local --view --preset=desktop

# Testar com conexão lenta (simular área rural)
# Chrome DevTools -> Network -> Slow 3G
```

**Critérios:**
- [ ] Performance Score > 80
- [ ] Acessibilidade Score > 90
- [ ] Tempo de carregamento < 3s em 3G

---

## 📁 Estrutura de Arquivos Proposta

```
app/
├── modulos/
│   ├── certificacao/
│   │   ├── listar.php
│   │   ├── cadastrar.php
│   │   ├── editar.php
│   │   ├── renovar.php
│   │   └── historico.php
│   ├── plano_manejo/
│   │   ├── listar.php
│   │   ├── cadastrar.php (wizard)
│   │   ├── editar.php
│   │   ├── visualizar.php
│   │   ├── aprovar.php
│   │   └── croqui_upload.php
│   ├── roteiros_inspecao/
│   │   ├── templates/
│   │   │   ├── listar.php
│   │   │   └── cadastrar.php
│   │   ├── visitas/
│   │   │   ├── listar.php
│   │   │   ├── agendar.php
│   │   │   ├── realizar.php
│   │   │   └── relatorio.php
│   │   └── nao_conformidades/
│   │       ├── listar.php
│   │       └── acompanhar.php
│   └── caderno_campo/ (melhorias)
│       └── validar_insumo.php (nova)
├── api/
│   ├── certificacao.php
│   ├── plano_manejo.php
│   ├── roteiros_inspecao.php
│   └── insumos_aprovados.php
├── assets/
│   ├── css/
│   │   └── agricultura-familiar.css (nova)
│   └── js/
│       ├── offline-sync.js (PWA - opcional)
│       └── qrcode-generator.js
└── sql/
    ├── schema_certificacao.sql
    ├── schema_plano_manejo.sql
    ├── schema_roteiros.sql
    └── seed_insumos_aprovados.sql (IN 46/2011)
```

---

## 🚀 Ordem de Implementação Recomendada

### Sprint 1 (Fundação - 1 semana)
1. ✅ Criar schemas do banco de dados
2. ✅ Popular tabela `insumos_organicos_aprovados` com IN 46/2011
3. ✅ Atualizar tabela `insumos` com novos campos
4. ✅ Criar API básica para novos módulos

### Sprint 2 (Certificação SPG - 1 semana)
1. ✅ Módulo de Certificação Participativa (foco SPG)
2. ✅ Interface de cadastro e listagem
3. ✅ Relatórios de certificações
4. ✅ Feature flag para habilitar OCS no futuro

### Sprint 3 (Plano de Manejo - 2 semanas)
1. ✅ Wizard multi-etapas para criar plano
2. ✅ Upload de croqui
3. ✅ Fluxo de aprovação por técnicos
4. ✅ Interface mobile-friendly (UX intermediário)

### Sprint 4 (Roteiros de Inspeção - 2 semanas)
1. ✅ CRUD de templates de roteiros
2. ✅ Interface de realização de visitas (checklist mobile)
3. ✅ Gestão de não-conformidades
4. ✅ Upload de fotos de evidências

### Sprint 5 (UX Mobile e Dashboard - 1 semana)
1. ✅ Redesign mobile-first (UX intermediário)
2. ✅ Tooltips contextuais
3. ✅ Dashboard para agricultores
4. ✅ Tutorial de primeiro acesso

### Sprint 6 (PWA e Offline - 2 semanas) **NOVO**
1. ✅ Configurar Service Worker
2. ✅ Implementar cache offline
3. ✅ Sincronização em background
4. ✅ Indicadores de status (online/offline)
5. ✅ Testes de sincronização

### Sprint 7 (Rastreabilidade e Testes - 1 semana)
1. ✅ Geração de QR code
2. ✅ Página pública de rastreabilidade
3. ✅ Testes finais de integração
4. ✅ Testes de usabilidade com agricultores

---

## 🎯 Métricas de Sucesso

| Métrica | Objetivo | Como Medir |
|---------|----------|------------|
| **Taxa de Adoção** | 80% dos agricultores registrando atividades semanalmente | Analytics + DB queries |
| **Tempo de Registro** | <3 minutos para registrar atividade diária | Teste de usabilidade |
| **Conformidade Orgânica** | 100% dos associados com plano de manejo aprovado | Dashboard admin |
| **Satisfação do Usuário** | NPS > 8 | Pesquisa trimestral |
| **Mobile vs Desktop** | 70% dos acessos via mobile | Google Analytics |

---

## ✅ Decisões Confirmadas pelo Usuário

| Aspecto | Decisão | Impacto |
|---------|---------|---------|
| **Certificação** | SPG prioridade + OCS futuro | Schema flexível, interface inicial SPG |
| **Modo Offline** | SIM (PWA) | +2 semanas, Sprint 6 dedicada |
| **Nível UX** | INTERMEDIÁRIO | Tooltips, mobile-friendly, sem ultra-simplificação |

**Cronograma Total:** 9 semanas (7 Sprints)

---

## 📝 Observações Finais

### Dependências Externas
- **PHPSpreadsheet:** Para exportação de relatórios (já instalado via Composer)
- **TCPDF/FPDF:** Para relatórios PDF (verificar instalação)
- **QR Code Library:** phpqrcode (instalar via Composer)

### Compatibilidade
- **PHP:** >= 7.4 (confirmar versão atual)
- **MySQL:** >= 5.7
- **Navegadores:** Chrome 90+, Firefox 88+, Safari 14+

### Próximos Passos (Após Aprovação)
1. ✅ Criar branches no Git para cada Sprint
2. ✅ Configurar ambiente de desenvolvimento/homologação
3. ✅ Recrutar agricultores para testes de usabilidade
4. ✅ Iniciar Sprint 1 (Database Schema)

---

**Este plano está pronto para revisão. Aguardando aprovação do usuário para prosseguir com a implementação.**
