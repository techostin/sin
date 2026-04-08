# 📘 Documentação - Sprint 1: Database Schema

## Visão Geral

Esta Sprint implementa a **fundação do banco de dados** para suportar produção orgânica no SINPORG v2.0, incluindo:

- ✅ Certificação Participativa (SPG/OCS)
- ✅ Plano de Manejo Orgânico
- ✅ Roteiros de Inspeção
- ✅ Visitas Técnicas
- ✅ Não Conformidades
- ✅ Insumos Orgânicos Aprovados (**Portaria 52/2021**)

**Base Legal:** Portaria MAPA nº 52/2021 (substitui IN 46/2011 - revogada)
**Atualizações:** Portarias 404/2022, 811/2023, 685/2024, 1.201/2024

---

## 📂 Arquivos Criados

| Arquivo | Descrição | Linhas |
|---------|-----------|--------|
| `schema_organico_sprint1.sql` | Criação de 7 novas tabelas | ~300 |
| `alter_insumos_organico.sql` | Alteração da tabela `insumos` | ~40 |
| `seed_insumos_aprovados.sql` | 63 insumos da IN 46/2011 | ~200 |
| `install_sprint1.sql` | Script master de instalação | ~80 |
| `README_SPRINT1.md` | Esta documentação | - |

---

## 🗄️ Estrutura do Banco de Dados

### 1. `certificacao_participativa`
Armazena certificações SPG, OCS e Rede Ecovida dos agricultores.

**Campos principais:**
- `tipo_certificacao` - SPG (padrão), OCS, Rede Ecovida
- `numero_certificado` - Número da certificação
- `data_emissao` / `data_validade`
- `status` - Ativa, Vencida, Suspensa, Em Conversão
- `dados_adicionais` (JSON) - Campos flexíveis por tipo

**Relacionamentos:**
- `associado_id` → `associados.id`

---

### 2. `plano_manejo`
Planos de manejo orgânico das propriedades.

**Campos principais:**
- `ano_safra` - Ex: 2025/2026
- `area_total_ha`, `area_organica_ha`, `area_conversao_ha`
- Práticas: `manejo_pragas`, `adubacao_verde`, `rotacao_culturas`
- `croqui_propriedade_url` - Link para mapa da propriedade
- `status` - Rascunho, Em Análise, Aprovado, Reprovado

**Relacionamentos:**
- `propriedade_id` → `propriedades.id`
- `associado_id` → `associados.id`

---

### 3. `plano_manejo_cultivos`
Cultivos planejados dentro do plano de manejo.

**Campos principais:**
- `plano_manejo_id` → `plano_manejo.id`
- `cultura`, `variedade`, `area_cultivada_ha`
- `data_plantio_prevista`, `data_colheita_prevista`
- `producao_estimada_kg`
- **`tipo_semente`** - Orgânica Certificada, Convencional Não Tratada, Crioula, Própria (NOVO - Portaria 52/2021)
- **`justificativa_semente`** - Justificativa se não usar orgânica certificada (NOVO)

> ⚠️ **Importante:** A Portaria 52/2021 estabelece prazo de 5 anos (até março/2026) para uso obrigatório de sementes orgânicas certificadas.

---

### 4. `roteiros_inspecao`
Templates de roteiros de inspeção (checklists).

**Campos principais:**
- `tipo` - Visita Inicial, Regular, Renovação, Surpresa
- `checklist` (JSON) - Array de itens a verificar
- `pontuacao_minima` - % mínima para aprovação
- `itens_criticos` (JSON) - Itens que reprovam automaticamente

**Estrutura do JSON checklist:**
```json
[
  {
    "id": 1,
    "categoria": "Documentação",
    "item": "Caderno de campo atualizado?",
    "obrigatorio": true,
    "peso": 10
  },
  ...
]
```

---

### 5. `visitas_tecnicas`
Registro de visitas técnicas realizadas.

**Campos principais:**
- `associado_id`, `propriedade_id`, `roteiro_id`
- `data_visita`, `hora_inicio`, `hora_fim`
- `tecnico_responsavel_id` - Quem realizou a visita
- `resultado` (JSON) - Respostas do checklist
- `pontuacao` - Calculada automaticamente
- `conformidade` - Conforme, Não Conforme, Parcialmente
- `evidencias_fotos` - URLs separadas por vírgula

**Estrutura do JSON resultado:**
```json
{
  "1": {
    "conforme": true,
    "observacao": "Caderno atualizado até ontem",
    "evidencia_url": "uploads/visitas/foto1.jpg"
  },
  ...
}
```

---

### 6. `nao_conformidades`
Não-conformidades identificadas nas visitas.

**Campos principais:**
- `visita_tecnica_id` → `visitas_tecnicas.id`
- `descricao`, `gravidade` (Baixa, Média, Alta, Crítica)
- `acao_corretiva`, `prazo_correcao`
- `status` - Aberta, Em Correção, Resolvida
- `evidencia_correcao_url` - Comprovação da correção

---

### 7. `insumos_organicos_aprovados`
Lista oficial de insumos permitidos (**Portaria 52/2021**).

**Campos principais:**
- `nome`, `categoria` (Fertilizante, Defensivo, Adjuvante, Sementes)
- `tipo` - Orgânico, Biológico, Mineral, etc
- `permitido` - TRUE/FALSE
- `restricoes` - Condições de uso
- `normativa` - Portaria 52/2021 (atualizada)

**Dados inseridos:** 53 insumos (47 permitidos, 6 não-permitidos como referência)

> 📌 **Nota:** Esta lista contém os principais insumos. Para lista completa e atualizada, consulte:
> https://www.gov.br/agricultura/pt-br/assuntos/sustentabilidade/organicos

---

### 8. Alterações em `insumos` (tabela existente)

**Novos campos adicionados:**
- `tipo_insumo` - Orgânico, Em Conversão, Convencional, Não Permitido
- `aprovado_organico` - Se está na lista oficial
- `insumo_aprovado_id` → `insumos_organicos_aprovados.id`
- `restricoes`, `composicao`, `fabricante`, `lote`, `data_validade`

---

## 🚀 Como Instalar

### Opção 1: Script Master (Recomendado)

```bash
# No terminal do MySQL
mysql -u root -p sisagro < sql/install_sprint1.sql
```

### Opção 2: phpMyAdmin

1. Acesse phpMyAdmin
2. Selecione o banco `sisagro`
3. Vá em "Importar"
4. Selecione `install_sprint1.sql`
5. Clique em "Executar"

### Opção 3: Passo a Passo Manual

```sql
-- 1. Criar tabelas
SOURCE sql/schema_organico_sprint1.sql;

-- 2. Alterar tabela insumos
SOURCE sql/alter_insumos_organico.sql;

-- 3. Popular insumos aprovados
SOURCE sql/seed_insumos_aprovados.sql;
```

---

## ✅ Verificação Pós-Instalação

Execute estes comandos para verificar:

```sql
-- Verificar tabelas criadas
SHOW TABLES LIKE '%certificacao%';
SHOW TABLES LIKE '%plano_manejo%';
SHOW TABLES LIKE '%roteiros%';

-- Verificar insumos cadastrados
SELECT COUNT(*) FROM insumos_organicos_aprovados;
-- Esperado: 63

-- Verificar alterações na tabela insumos
DESCRIBE insumos;
-- Deve ter: tipo_insumo, aprovado_organico, insumo_aprovado_id
```

---

## 📊 Estatísticas

- **Tabelas criadas:** 7
- **Tabela alterada:** 1 (`insumos`)
- **Foreign keys:** 12
- **Índices:** 28
- **Registros seed:** 63 (insumos)

---

## 🔗 Relacionamentos

```
associados
├── certificacao_participativa
├── plano_manejo
└── visitas_tecnicas (como produtor ou técnico)

propriedades
├── plano_manejo
└── visitas_tecnicas

plano_manejo
└── plano_manejo_cultivos

roteiros_inspecao
└── visitas_tecnicas

visitas_tecnicas
└── nao_conformidades

insumos_organicos_aprovados
└── insumos (via insumo_aprovado_id)
```

---

## 🎯 Próximos Passos (Sprint 2+)

1. **API REST** - Criar endpoints para CRUD dos novos módulos
2. **Frontend** - Interfaces de cadastro e listagem
3. **Validações** - Regras de negócio (ex: não permitir insumo não-aprovado)
4. **Relatórios** - Gerar relatórios de conformidade
5. **PWA** - Suporte offline (Sprint 6)

---

## 🐛 Troubleshooting

### Erro: "Table already exists"
**Solução:** As tabelas usam `IF NOT EXISTS`. Se houver conflito, faça DROP manual:
```sql
DROP TABLE IF EXISTS certificacao_participativa;
-- Repita para outras tabelas
```

### Erro: "Foreign key constraint fails"
**Solução:** Verifique se as tabelas `associados` e `propriedades` existem:
```sql
SHOW TABLES LIKE 'associados';
SHOW TABLES LIKE 'propriedades';
```

### Erro: "Cannot add foreign key" na tabela insumos
**Solução:** Execute `schema_organico_sprint1.sql` ANTES de `alter_insumos_organico.sql`.

---

## 📞 Suporte

Em caso de dúvidas ou problemas:
1. Verifique os logs do MySQL
2. Confira se o charset é `utf8mb4`
3. Verifique permissões do usuário MySQL

---

**Desenvolvido para SINPORG v2.0 - Sprint 1**
*Data: 2026-02-17*
