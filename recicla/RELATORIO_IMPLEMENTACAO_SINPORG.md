# Relatório de Conformidade com implementation_sinporg.md

## 1. Visão Geral

O sistema já implementa a maioria dos módulos principais previstos em `implementation_sinporg.md`. Há cobertura real para:

- Certificação Participativa
- Plano de Manejo Orgânico
- Roteiros de Inspeção
- Visitas Técnicas
- Não conformidades
- Insumos Orgânicos Aprovados

No entanto, existem lacunas importantes de integração, UX móvel, e suporte offline que ainda precisam de implementação ou melhoria.

---

## 2. O que já está implementado

### 2.1 Módulos existentes

- `modulos/certificacao/`
  - `listar.php`
  - `cadastrar.php`
  - `editar.php`
  - `renovar.php`
  - `historico.php`
- `modulos/plano_manejo/`
  - `cadastrar.php`
  - `visualizar.php`
  - `aprovar.php`
  - `upload_croqui.php`
- `modulos/roteiros_inspecao/`
  - `templates/cadastrar.php`
  - `visitas/realizar.php`
  - `nao_conformidades/acompanhar.php`
- `modulos/certificacao/insumos.php`
- `modulos/caderno_campo/validar_insumo.php`

### 2.2 APIs existentes

- `api/certificacao/controller.php`
- `api/plano_manejo.php`
- `api/roteiros_inspecao.php`
- `api/insumos_aprovados.php`
- `api/nao_conformidades.php`

### 2.3 Schemas de banco

- `sql/schema_organico_sprint1.sql`
- `sql/alter_insumos_organico.sql`

### 2.4 PWA / Offline

- `manifest.json`
- `sw.js`
- `includes/header.php` registra service worker

### 2.5 UX / Mobile

- `assets/css/agricultura-familiar.css`
- uso de Bootstrap responsivo em vários formulários e páginas

---

## 3. Lacunas e melhorias necessárias

### 3.1 PWA e uso offline

- `sw.js` é muito básico e só faz cache de assets estáticos.
- Não há `IndexedDB` ou qualquer camada de armazenamento local.
- Não há fila de sincronização em background ou tratamento de conflitos.
- Não há indicador visual de status online/offline dentro do aplicativo.

### 3.2 Integração de validação de insumos

- O endpoint e script de validação existem, mas não há evidência de integração direta com o fluxo de cadastro ou uso de insumos no caderno de campo.
- Há risco de a validação não ser usada na prática se não estiver vinculada aos formulários de insumos.

### 3.3 UX mobile e experiência de agricultor

- Há responsividade básica, mas não há um modo dedicado para agricultores com interface simplificada.
- Não há dashboard específico de indicadores simples e operacionais para o usuário final.
- Menus e telas ainda seguem o padrão administrativo do Bootstrap sem a camada de simplificação prevista no documento.

### 3.4 Registro de ocorrências

- O plano de manejo tem campos de manejo e pragas, mas não existe um módulo específico de registro de ocorrências de pragas/doenças.
- Não há um fluxo único para capturar histórico de conformidade diário no caderno de campo.

### 3.5 Suporte específico para OCS / Rede Ecovida

- O campo `tipo_certificacao` aceita `OCS` e `Rede Ecovida`, mas não há regras de negócio ou formulário especializado para esses modelos.
- O suporte a esses sistemas parece limitado a etiquetas de tipo, sem workflows diferenciados.

### 3.6 Cobertura de testes

- A documentação sugere testes automatizados de API e schema.
- Não há evidência de arquivos de teste existentes para os módulos orgânicos.

---

## 4. Pontos críticos identificados

1. **PWA/Offline** deve ser ampliado de esqueleto para funcionalidade real.
2. **Validação de insumos** precisa ser conectada ao cadastro/uso de insumos no caderno de campo.
3. **UX móvel** requer tela inicial e navegação simplificada para agricultores.
4. **Registro de ocorrências** deve ser formalizado como recurso ou módulo próprio.
5. **Testes automatizados** devem ser adicionados para proteger o fluxo orgânico.

---

## 5. Recomendações de ação

### 5.1 Prioridade alta

- Implementar sincronização offline com armazenamento local e status de conexão.
- Integrar a validação de insumos ao fluxo de cadastro e uso de insumos.
- Criar dashboard simplificado para agricultores com indicadores de conformidade, próximas visitas e tarefas.

### 5.2 Prioridade média

- Formalizar módulo de registro de ocorrências/pragas/doenças.
- Validar se `insumos_organicos_aprovados` está populado e acessível ao usuário.
- Ajustar `sw.js` para cache dinâmico e fallback offline.

### 5.3 Prioridade baixa

- Adicionar regras específicas para OCS e Rede Ecovida.
- Criar testes de API para `certificacao`, `plano_manejo` e `roteiros_inspecao`.

---

## 6. Conclusão

O sistema já está bem alinhado com a proposta do `implementation_sinporg.md`, com os principais módulos e tabelas implementados. A etapa restante é consolidar esses módulos em fluxos integrados, melhorar a experiência móvel para agricultores e garantir suporte offline real.

> O trabalho maior agora é de refinamento e integração, não de entrega de novos módulos básicos.
