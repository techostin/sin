# TODO - Implementação de Assinatura Digital para Certificados

## Etapas Concluídas

- [x] 1. Migração SQL - Adicionar colunas de assinatura digital
- [x] 2. Criar diretório uploads/certificados/ com .htaccess
- [x] 3. Atualizar API Controller - Endpoint de upload de assinatura
- [x] 4. Atualizar renovar.php - Campo de upload de PDF assinado
- [x] 5. Atualizar listar.php - Lógica de exibição condicional
- [x] 6. Remover arquivos temporários (check_cert_table.php, migrate_assinatura.php)

## Status Final ✅

Todas as etapas foram concluídas. O sistema agora permite:

1. **Renovar certificação com upload de PDF assinado** - Na página de renovação, o gestor faz upload do certificado PDF assinado junto com a atualização dos dados
2. **Assinatura digital registrada** - O sistema armazena quem assinou, quando assinou e o arquivo PDF
3. **Download condicional** - O botão de download do certificado só aparece se o PDF assinado foi enviado
4. **Histórico de assinatura** - O modal de visualização detalhada exibe informações da assinatura (data, assinante)
5. **Validação de arquivo** - Apenas PDF é aceito, com validação de extensão e tamanho máximo de 10MB

