# Instruções para Corrigir o Erro do Relatório de Produção

## Problema Identificado
O erro "Column not found: 1054 Unknown column 'l.associado_id' in 'on clause'" ocorreu porque:

1. A tabela `produtos` não existe no banco de dados
2. A tabela `lotes_producao` não existe no banco de dados
3. O relatório estava tentando fazer JOIN com tabelas inexistentes

## Solução

### Passo 1: Executar o Script SQL
Execute o arquivo `criar_tabelas_faltantes.sql` no seu banco de dados MySQL para criar as tabelas que estão faltando:

1. Abra o phpMyAdmin ou seu cliente MySQL preferido
2. Selecione o banco de dados `sisagro`
3. Execute o script `criar_tabelas_faltantes.sql`

### Passo 2: Verificar as Tabelas Criadas
O script criará as seguintes tabelas:

#### Tabela `produtos`
- `id` - Chave primária
- `nome` - Nome do produto
- `tipo` - Tipo do produto (Grão, Hortaliça, etc.)
- `unidade_medida` - Unidade de medida (kg, un, etc.)
- `quantidade_produzida` - Quantidade produzida
- `data_colheita` - Data da colheita
- `lavoura_id` - Referência à lavoura
- `observacoes` - Observações
- `criado_em` - Data de criação

#### Tabela `lotes_producao`
- `id` - Chave primária
- `nome_lote` - Nome do lote
- `produto_id` - Referência ao produto
- `data_producao` - Data de produção
- `quantidade` - Quantidade do lote
- `unidade_medida` - Unidade de medida
- `observacoes` - Observações
- `criado_em` - Data de criação

### Passo 3: Dados de Exemplo
O script também inserirá dados de exemplo para testar o relatório:
- 4 produtos diferentes (Milho, Feijão, Tomate, Alface)
- 4 lotes de produção correspondentes

### Passo 4: Testar o Relatório
Após executar o script, acesse:
```
http://localhost/app2/caderno-campo/relatorios/producao
```

O relatório agora deve funcionar corretamente e mostrar:
- Estatísticas de produção
- Lista de produtos com seus lotes
- Códigos de rastreabilidade
- Informações completas de lavoura, propriedade e associado

## Estrutura Corrigida do Relatório

O relatório de produção agora usa a estrutura correta:

```sql
SELECT 
    p.*,                    -- Dados do produto
    l.nome as lavoura_nome, -- Nome da lavoura
    l.cultura,              -- Cultura da lavoura
    prop.propriedade,       -- Nome da propriedade
    a.nome as associado_nome, -- Nome do associado
    lp.nome_lote,           -- Nome do lote
    lp.data_producao        -- Data de produção do lote
FROM produtos p
LEFT JOIN lavouras l ON p.lavoura_id = l.id
LEFT JOIN propriedades prop ON l.propriedade_id = prop.id
LEFT JOIN associados a ON prop.associado_id = a.id
LEFT JOIN lotes_producao lp ON p.id = lp.produto_id
```

## Relacionamentos Corretos
- `produtos.lavoura_id` → `lavouras.id`
- `lavouras.propriedade_id` → `propriedades.id`
- `propriedades.associado_id` → `associados.id`
- `lotes_producao.produto_id` → `produtos.id`

## Próximos Passos
1. Execute o script SQL
2. Teste o relatório de produção
3. Se necessário, adicione mais produtos e lotes através do sistema
4. Verifique se outros relatórios também funcionam corretamente 