# TODO: Correção do Login e Permissões do Gestor

## Etapas

- [ ] 1. `login.php`: Gestor só informa órgão/cargo/setor no primeiro acesso (já preenchidos => login direto). Garantir `user_tipo='gestor'`.
- [ ] 2. `login.php` (form) + `pre_cadastro.php`: Adicionar campo "Tipo de Usuário" (Gestor/Usuário) no primeiro acesso e gravar nível/tipo corretamente.
- [ ] 3. `index.php`: Corrigir redirecionamento para usar `user_nivel` (não `user_tipo`).
- [ ] 4. Banco de dados: Corrigir registro do gestor `kajiani@gmail.com` para `tipo='gestor'` (e conferir demais gestores).
- [ ] 5. Testar fluxos (primeiro acesso com tipo, gestor já preenchido faz login direto, gestor com permissões).
