# TODO - Menu Produção Orgânica para Admin/Gestor

## Etapas Concluídas

- [x] 1. Analisar includes/menu.php - estrutura do menu
- [x] 2. Mover bloco "Produção Orgânica" para imediatamente após "Associações" no menu lateral
- [x] 3. Alterar condição de NIVEL_ADMIN para NIVEL_GESTOR nos submenus (Roteiros, Novo Plano, Insumos Aprovados)
- [x] 4. Corrigir index.php (roteador) - rota logout e completar-perfil
- [x] 5. Adicionar guards de backend NIVEL_GESTOR nos 5 arquivos de backend (validacao.php, insumos.php, listar.php, realizar.php, cadastrar.php)

## Etapas Pendentes

- [ ] 6. Testar login como admin em https://localhost/app
- [ ] 7. Verificar se "Produção Orgânica" aparece logo abaixo de "Associações"
- [ ] 8. Verificar se todos os submenus estão disponíveis para admin e gestor

