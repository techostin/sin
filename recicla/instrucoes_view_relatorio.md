# Instruções para Implementação da View de Relatório

## 1. Executar a View no Banco de Dados

### Opção A: Via phpMyAdmin
1. Acesse o phpMyAdmin
2. Selecione o banco de dados `sisagro`
3. Vá na aba "SQL"
4. Cole o conteúdo do arquivo `view_relatorio_caderno_campo.sql`
5. Clique em "Executar"

### Opção B: Via Linha de Comando MySQL
```bash
mysql -u root -p sisagro < view_relatorio_caderno_campo.sql
```

### Opção C: Via PHP (se preferir)
Crie um arquivo `criar_view.php` na raiz do projeto:

```php
<?php
require_once 'config/config.php';
require_once 'classes/Database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Ler o arquivo SQL
    $sql = file_get_contents('view_relatorio_caderno_campo.sql');
    
    // Executar a view
    $db->exec($sql);
    
    echo "View criada com sucesso!";
} catch (PDOException $e) {
    echo "Erro ao criar view: " . $e->getMessage();
}
?>
```

## 2. Verificar se a View foi Criada

Execute no banco de dados:
```sql
SHOW TABLES LIKE 'view_relatorio_caderno_campo';
```

Ou teste uma consulta:
```sql
SELECT COUNT(*) as total_registros FROM view_relatorio_caderno_campo;
```

## 3. Estrutura da View

A view `view_relatorio_caderno_campo` consolida dados de:

- **Atividades Diárias**: Todas as atividades registradas
- **Associados/Usuários**: Dados dos produtores
- **Propriedades**: Informações das propriedades rurais (incluindo latitude/longitude)
- **Lavouras**: Dados das lavouras
- **Categorias de Atividades**: Classificação das atividades
- **Insumos**: Controle de insumos utilizados
- **Fornecedores**: Dados dos fornecedores de insumos
- **Produtos**: Produtos agrícolas
- **Lotes de Produção**: Rastreabilidade completa

## 4. Campos Principais da View

### Identificação
- `atividade_id`, `associado_id`, `propriedade_id`, `lavoura_id`
- `codigo_rastreabilidade_lote`, `codigo_rastreabilidade_atividade`

### Dados Geográficos
- `propriedade_latitude`, `propriedade_longitude`
- `coordenadas_propriedade`
- `endereco_completo_propriedade`

### Estatísticas Calculadas
- `duracao_minutos`, `duracao_horas`
- `dias_desde_atividade`, `dias_desde_plantio`
- `status_atividade_formatado`, `tipo_atividade_formatado`

## 5. Usar o Relatório

Após criar a view, o relatório geral já estará funcionando com dados reais:

1. Acesse: `modulos/caderno_campo/relatorios.php`
2. Clique em "Gerar PDF" no card "Relatório Geral"
3. O PDF será gerado com dados reais do banco

## 6. Personalizações Possíveis

### Filtrar por Período
Modifique a consulta na view ou no relatório:
```sql
WHERE ad.data_atividade BETWEEN '2024-01-01' AND '2024-12-31'
```

### Filtrar por Associado
```sql
WHERE ad.associado_id = 13
```

### Filtrar por Propriedade
```sql
WHERE ad.propriedade_id = 1
```

## 7. Troubleshooting

### Erro: "Table doesn't exist"
- Verifique se todas as tabelas existem no banco
- Execute `SHOW TABLES;` para listar todas as tabelas

### Erro: "Column doesn't exist"
- Verifique se os nomes das colunas estão corretos
- Use `DESCRIBE nome_tabela;` para ver a estrutura

### View vazia
- Verifique se há dados nas tabelas relacionadas
- Teste consultas individuais em cada tabela

## 8. Performance

Para melhorar a performance da view:

1. **Índices**: Certifique-se de que existem índices nas colunas de JOIN
2. **Filtros**: Use WHERE para limitar os dados quando possível
3. **Paginação**: Para relatórios grandes, implemente paginação

## 9. Backup

Antes de executar, faça backup do banco:
```bash
mysqldump -u root -p sisagro > backup_antes_view.sql
```

## 10. Próximos Passos

Após implementar a view, você pode:

1. Criar relatórios específicos por período
2. Implementar relatórios por propriedade
3. Adicionar gráficos e estatísticas
4. Implementar exportação para Excel
5. Criar dashboards interativos 